<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Rank;
use App\Enums\ReportOrigin;
use App\Enums\ReportReason;
use App\Models\Room;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Member-to-member blocking.
 *
 * A block hides both members from each other: neither sees the other's messages,
 * reactions, presence, search results, member card or highlighted mentions, and
 * neither can reply to, react to or download the other's messages. Filtering is done
 * on the server, so the message feed never sends blocked content.
 *
 * Admin I and II cannot be blocked. An Admin III can be, except inside a channel they
 * created: a block against them does not apply there, and blocking them from inside
 * that channel files a report instead. Refused blocks answer exactly like a real block,
 * so a member's admin rank is never revealed (owner decision Q3).
 */
class BlockService
{
    public const int CACHE_MINUTES = 60;

    public function __construct(
        protected ModerationService $moderation
    ) {}

    /**
     * Block the target, or report them when they cannot be blocked here.
     * Returns true when a block was stored, false when a report was filed instead.
     */
    public function block(User $blocker, User $target, ?Room $room = null): bool
    {
        if ($this->isProtectedFromBlocking($target, $room)) {
            $this->moderation->fileReport($blocker, $target, ReportOrigin::BlockAttempt, ReportReason::Other, room: $room);

            return false;
        }

        UserBlock::firstOrCreate(['blocker_id' => $blocker->id, 'blocked_id' => $target->id]);
        $this->forget($blocker, $target);

        return true;
    }

    /**
     * Whether a block against the target would be refused (and reported instead):
     * always for Admin I and II, and for an Admin III inside a channel they created.
     */
    public function isProtectedFromBlocking(User $target, ?Room $room = null): bool
    {
        return $target->displaysAsAdmin() || ($target->hasRank(Rank::AdminThree) && $room?->isCreatedBy($target));
    }

    /**
     * Remove the blocker's block on the target. A block the target holds stays in place.
     */
    public function unblock(User $blocker, User $target): void
    {
        UserBlock::where('blocker_id', $blocker->id)->where('blocked_id', $target->id)->delete();
        $this->forget($blocker, $target);
    }

    /**
     * IDs of every member hidden from this member: those they blocked and those who blocked
     * them. Inside a channel created by an Admin III, blocks against that Admin III are ignored.
     *
     * @return list<int>
     */
    public function hiddenUserIdsFor(?User $user, ?Room $room = null): array
    {
        if ($user === null) {
            return [];
        }

        $relations = $this->relationsFor($user->id);
        $hidden = array_unique(array_merge($relations['blocked'], $relations['blocked_by']));

        return array_values(array_diff($hidden, $this->exemptInRoom($user, $relations, $room)));
    }

    /**
     * Whether either member has blocked the other (as it applies inside $room, if given).
     */
    public function isBlockedBetween(?User $user, User|int|null $other, ?Room $room = null): bool
    {
        if ($user === null || $other === null) {
            return false;
        }

        $otherId = $other instanceof User ? $other->id : $other;

        return in_array($otherId, $this->hiddenUserIdsFor($user, $room), true);
    }

    /**
     * The members this member has blocked, newest first, for their block list.
     *
     * @return Collection<int, User>
     */
    public function blockedBy(User $blocker): Collection
    {
        return User::query()
            ->join('user_blocks', 'user_blocks.blocked_id', '=', 'users.id')
            ->where('user_blocks.blocker_id', $blocker->id)
            ->orderByDesc('user_blocks.created_at')
            ->get(['users.id', 'users.name', 'users.tag', 'users.avatar_path']);
    }

    /**
     * The member's blocks in both directions, cached until either side changes a block.
     *
     * @return array{blocked: list<int>, blocked_by: list<int>}
     */
    protected function relationsFor(int $userId): array
    {
        return Cache::remember($this->cacheKey($userId), now()->addMinutes(self::CACHE_MINUTES), function () use ($userId): array {
            $blocks = UserBlock::query()
                ->where('blocker_id', $userId)
                ->orWhere('blocked_id', $userId)
                ->get(['blocker_id', 'blocked_id']);

            return [
                'blocked' => $blocks->where('blocker_id', $userId)->map(fn (UserBlock $block): int => (int) $block->blocked_id)->values()->all(),
                'blocked_by' => $blocks->where('blocked_id', $userId)->map(fn (UserBlock $block): int => (int) $block->blocker_id)->values()->all(),
            ];
        });
    }

    /**
     * Members whose block does not apply inside $room because it targets the room's
     * Admin III creator. A block the Admin III placed on someone still applies.
     *
     * @param  array{blocked: list<int>, blocked_by: list<int>}  $relations
     * @return list<int>
     */
    protected function exemptInRoom(User $user, array $relations, ?Room $room): array
    {
        if ($room === null || ! $room->creator?->hasRank(Rank::AdminThree)) {
            return [];
        }

        $creatorId = (int) $room->created_by_user_id;

        if ($user->id === $creatorId) {
            return array_values(array_diff($relations['blocked_by'], $relations['blocked']));
        }

        return in_array($creatorId, $relations['blocked'], true) && ! in_array($creatorId, $relations['blocked_by'], true)
            ? [$creatorId]
            : [];
    }

    /**
     * Clear both members' cached block lists after a change.
     */
    protected function forget(User $first, User $second): void
    {
        Cache::forget($this->cacheKey($first->id));
        Cache::forget($this->cacheKey($second->id));
    }

    protected function cacheKey(int $userId): string
    {
        return "user_blocks.relations.{$userId}";
    }
}
