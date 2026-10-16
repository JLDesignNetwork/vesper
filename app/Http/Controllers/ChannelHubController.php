<?php

namespace App\Http\Controllers;

use App\Enums\ChannelEntry;
use App\Models\ChannelInvitation;
use App\Models\Room;
use App\Services\ChannelAccessService;
use App\Services\RankService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ChannelHubController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected RankService $rankService,
        protected ChannelAccessService $channelAccess
    ) {}

    /**
     * Display the Operative Channels Hub for the authenticated member.
     * Strictly displays ONLY the channels this user belongs to (zero discovery).
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Fetch strictly the active channels where this user is an enrolled member
        $channels = $user->channels()
            ->where('rooms.status', 'active')
            ->withCount('messages')
            ->orderByDesc('room_user.last_accessed_at')
            ->get();

        // Fetch any pending direct channel invitations
        $pendingInvites = $user->pendingChannelInvitations()
            ->with('creator:id,name')
            ->get();

        $canUsePinlessEntry = $user->canUsePinlessEntry();

        return view('channels.index', [
            'user' => $user,
            'channels' => $channels,
            'pendingInvites' => $pendingInvites,
            'canUsePinlessEntry' => $canUsePinlessEntry,
        ]);
    }

    /**
     * Re-enter an enrolled channel from the hub.
     * If operative has 2FA/biometrics active, auto-grants clearance (pinless).
     * Otherwise, routes to PIN gate with 2FA prompt.
     */
    public function enter(Room $room, Request $request): RedirectResponse
    {
        $user = $request->user();
        $entry = $this->channelAccess->entryFor($user, $room);

        if ($entry === ChannelEntry::Denied) {
            return redirect()->route('profile.show')->withErrors([
                'channel' => __('This channel is unavailable, or you are not a member of it.'),
            ]);
        }

        if ($entry === ChannelEntry::Granted) {
            $this->channelAccess->grantClearance($request, $room, $user->name);
            $room->touchMemberAccess($user);

            return redirect()->route('rooms.show', ['room' => $room->code]);
        }

        return redirect()->route('rooms.show', ['room' => $room->code])->with(
            'status',
            __('Enter the channel PIN, or turn on Two-Factor Authentication or a passkey in Profile Settings to open secured channels without it.')
        );
    }

    /**
     * Accept a pending direct channel invitation.
     */
    public function acceptInvite(string $roomId, Request $request): RedirectResponse
    {
        $user = $request->user();

        $invitation = DB::table('room_user')
            ->where('room_id', $roomId)
            ->where('user_id', $user->id)
            ->where('status', 'invited')
            ->first();

        if (! $invitation) {
            return redirect()->route('profile.show')->withErrors([
                'channel' => __('Invitation not found or already processed.'),
            ]);
        }

        DB::table('room_user')
            ->where('room_id', $roomId)
            ->where('user_id', $user->id)
            ->update([
                'status' => 'active',
                'last_accessed_at' => now(),
                'updated_at' => now(),
            ]);

        $room = Room::find($roomId);
        if ($room) {
            $this->rankService->recomputeCreatorOf($room);
        }

        return redirect()->route('profile.show')->with('status', __('Channel clearance accepted. You are now an active member.'));
    }

    /**
     * Decline a pending direct channel invitation.
     */
    public function declineInvite(string $roomId, Request $request): RedirectResponse
    {
        $user = $request->user();

        DB::table('room_user')
            ->where('room_id', $roomId)
            ->where('user_id', $user->id)
            ->where('status', 'invited')
            ->update([
                'status' => 'declined',
                'updated_at' => now(),
            ]);

        return redirect()->route('profile.show')->with('status', __('Channel clearance declined.'));
    }

    /**
     * Redeem an alphanumeric channel invitation clearance code (e.g. INV-XXXX-XXXX).
     */
    public function redeemCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ]);

        $cleanCode = strtoupper(trim($validated['code']));
        $invitation = ChannelInvitation::where('code', $cleanCode)->first();

        if (! $invitation || ! $invitation->isValid()) {
            return back()->withErrors([
                'code' => __('Invalid or expired channel invitation code.'),
            ]);
        }

        $user = $request->user();

        if (! $invitation->consume($user)) {
            return back()->withErrors([
                'code' => __('This invitation cannot be used with your account.'),
            ]);
        }

        return redirect()->route('profile.show')->with(
            'status',
            __('Invitation code verified! You have been granted clearance to :title.', ['title' => $invitation->room->title ?: $invitation->room->code])
        );
    }
}
