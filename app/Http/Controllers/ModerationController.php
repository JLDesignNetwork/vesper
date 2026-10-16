<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ReportOrigin;
use App\Enums\ReportReason;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\BlockService;
use App\Services\ChannelAccessService;
use App\Services\ModerationService;
use App\Services\UserTagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Blocking, reporting and kicking, addressed by the member's @tag.
 *
 * Block and kick attempts that are not allowed (Admin I and II, or an Admin III inside
 * their own channel) file a report instead. The response is identical to a real block
 * or kick, so a member's admin rank is never revealed (Q3).
 */
class ModerationController extends Controller
{
    public function __construct(
        protected BlockService $blocks,
        protected ModerationService $moderation,
        protected UserTagService $userTags,
        protected ChannelAccessService $channelAccess
    ) {}

    /**
     * Block a member.
     */
    public function block(Request $request, string $tag): JsonResponse|RedirectResponse
    {
        $target = $this->memberByTag($tag);

        if ($target->is($request->user())) {
            return $this->respond($request, __('You cannot block yourself.'), 422);
        }

        $this->blocks->block($request->user(), $target, $this->roomFromRequest($request));

        return $this->respond($request, __('@:tag is blocked. You will no longer see each other in channels or search.', ['tag' => $target->tag]));
    }

    /**
     * Lift the viewer's block on a member.
     */
    public function unblock(Request $request, string $tag): JsonResponse|RedirectResponse
    {
        $this->blocks->unblock($request->user(), $this->memberByTag($tag));

        return $this->respond($request, __('@:tag is unblocked.', ['tag' => $this->userTags->normalize($tag)]));
    }

    /**
     * File a report about a member, optionally pointing at a channel and message.
     */
    public function report(Request $request, string $tag): JsonResponse|RedirectResponse
    {
        $target = $this->memberByTag($tag);

        if ($target->is($request->user())) {
            return $this->respond($request, __('You cannot report yourself.'), 422);
        }

        $room = $this->roomFromRequest($request);

        $validated = $request->validate([
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => ['nullable', 'string', 'max:1000'],
            'message_id' => ['nullable', 'integer', Rule::exists('messages', 'id')->where('room_id', $room?->id)->where('user_id', $target->id)],
        ]);

        $this->moderation->fileReport(
            $request->user(),
            $target,
            ReportOrigin::Direct,
            ReportReason::from($validated['reason']),
            details: $validated['details'] ?? null,
            room: $room,
            message: isset($validated['message_id']) ? Message::find($validated['message_id']) : null,
        );

        return $this->respond($request, __('Thanks. Your report about @:tag was sent to the moderators.', ['tag' => $target->tag]));
    }

    /**
     * Kick an active member from the channel. Only the channel's creator may.
     */
    public function kick(Request $request, string $code, string $tag): JsonResponse
    {
        $room = Room::where('code', strtoupper(trim($code)))->where('status', 'active')->firstOrFail();

        if (! $request->user()->can('kickMembers', $room)) {
            return response()->json(['error' => __('Only the channel creator can remove members.')], 403);
        }

        $target = $room->members()->where('users.tag', $this->userTags->normalize($tag))->first();

        if (! $target) {
            return response()->json(['error' => __('That member is not in this channel.')], 404);
        }

        if ($target->is($request->user())) {
            return response()->json(['error' => __('You cannot remove yourself from your own channel.')], 422);
        }

        $this->moderation->kick($request->user(), $room, $target);

        return response()->json([
            'success' => true,
            'message' => __('@:tag was removed from this channel and cannot rejoin.', ['tag' => $target->tag]),
        ]);
    }

    /**
     * Let a kicked member back into the channel. Only the channel's creator may.
     */
    public function allowBack(Request $request, string $code, string $tag): JsonResponse|RedirectResponse
    {
        $room = Room::where('code', strtoupper(trim($code)))->where('status', 'active')->firstOrFail();

        if (! $request->user()->can('kickMembers', $room)) {
            return $this->respond($request, __('Only the channel creator can let members back.'), 403);
        }

        $target = $room->kickedMembers()->where('users.tag', $this->userTags->normalize($tag))->first();

        if (! $target) {
            return $this->respond($request, __('That member was not removed from this channel.'), 404);
        }

        $this->moderation->allowBack($request->user(), $room, $target);

        return $this->respond($request, $room->isPublic()
            ? __('@:tag is allowed back and can rejoin by opening the channel.', ['tag' => $target->tag])
            : __('@:tag is allowed back and can rejoin with an invitation.', ['tag' => $target->tag]));
    }

    /**
     * Find a member by @tag or fail with 404.
     */
    protected function memberByTag(string $tag): User
    {
        return User::where('tag', $this->userTags->normalize($tag))->firstOrFail();
    }

    /**
     * The channel named by the optional `room` code, when the action was taken inside one.
     * Channels the member cannot see are ignored, so a report cannot probe private channels.
     */
    protected function roomFromRequest(Request $request): ?Room
    {
        $code = strtoupper(trim((string) $request->input('room', '')));
        $room = $code === '' ? null : Room::where('code', $code)->first();

        return $room && $this->channelAccess->isVisibleTo($request->user(), $room) ? $room : null;
    }

    /**
     * Answer an API call with JSON, or a form post with a redirect back.
     */
    protected function respond(Request $request, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return $status === 200
                ? response()->json(['success' => true, 'message' => $message])
                : response()->json(['error' => $message], $status);
        }

        return $status === 200
            ? back()->with('status', $message)
            : back()->withErrors(['moderation' => $message]);
    }
}
