<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\Rank;
use App\Models\AdminAuditLog;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Computes and assigns platform ranks.
 *
 * Admin III, User I and User II are derived from qualifying channels (owner decision Q2):
 * a channel qualifies when it was created by the user, is active and unexpired, has a
 * message in the last 10 days, and has at least 10 active members with a verified email,
 * not counting the creator. Admin I and Admin II are never changed by a recompute.
 */
class RankService
{
    public const int ADMIN_THREE_MIN_CHANNELS = 5;

    public const int USER_ONE_MIN_CHANNELS = 3;

    public const int MIN_VERIFIED_MEMBERS = 10;

    public const int ACTIVITY_WINDOW_DAYS = 10;

    /**
     * Count the channels created by the user that currently qualify toward a rank.
     */
    public function qualifyingChannelCount(User $user): int
    {
        return Room::query()
            ->where('created_by_user_id', $user->id)
            ->where('status', 'active')
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('messages', fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(self::ACTIVITY_WINDOW_DAYS)))
            ->whereHas(
                'members',
                fn (Builder $query) => $query->whereNotNull('users.email_verified_at')->whereNull('users.banned_at')->where('users.id', '!=', $user->id),
                '>=',
                self::MIN_VERIFIED_MEMBERS,
            )
            ->count();
    }

    /**
     * Determine the computed rank a user's channels currently earn.
     */
    public function computedRankFor(User $user): Rank
    {
        $qualifyingChannels = $this->qualifyingChannelCount($user);

        return match (true) {
            $qualifyingChannels >= self::ADMIN_THREE_MIN_CHANNELS => Rank::AdminThree,
            $qualifyingChannels >= self::USER_ONE_MIN_CHANNELS => Rank::UserOne,
            default => Rank::UserTwo,
        };
    }

    /**
     * Recompute and save a user's rank. Appointed ranks (Admin I, Admin II) are left unchanged.
     * Promotions and demotions take effect immediately and are audited.
     */
    public function recompute(User $user): Rank
    {
        if (! $user->rank->isComputed()) {
            return $user->rank;
        }

        $previousRank = $user->rank;
        $newRank = $this->computedRankFor($user);

        $user->forceFill([
            'rank' => $newRank,
            'rank_computed_at' => now(),
        ])->save();

        if ($newRank !== $previousRank) {
            AdminAuditLog::record(AuditAction::RankChanged, subject: $user, details: [
                'from' => $previousRank->value,
                'to' => $newRank->value,
                'reason' => 'recompute',
            ]);
        }

        return $newRank;
    }

    /**
     * Recompute every user holding a computed rank. Returns the number whose rank changed.
     */
    public function recomputeAll(): int
    {
        $changed = 0;

        User::query()
            ->whereIn('rank', [Rank::AdminThree, Rank::UserOne, Rank::UserTwo])
            ->chunkById(200, function (Collection $users) use (&$changed): void {
                foreach ($users as $user) {
                    $previousRank = $user->rank;
                    if ($this->recompute($user) !== $previousRank) {
                        $changed++;
                    }
                }
            });

        return $changed;
    }

    /**
     * Recompute the creator of a channel after its membership or status changed.
     */
    public function recomputeCreatorOf(Room $room): void
    {
        if ($room->created_by_user_id === null) {
            return;
        }

        $creator = User::find($room->created_by_user_id);

        if ($creator) {
            $this->recompute($creator);
        }
    }

    /**
     * Recompute the creators of every channel a member belongs to, for example after
     * the member verifies their email or is removed from the platform.
     */
    public function recomputeCreatorsOfChannelsJoinedBy(User $member): void
    {
        $creatorIds = $member->channels()
            ->whereNotNull('created_by_user_id')
            ->where('created_by_user_id', '!=', $member->id)
            ->pluck('created_by_user_id')
            ->unique();

        User::whereIn('id', $creatorIds)->get()->each(fn (User $creator) => $this->recompute($creator));
    }

    /**
     * List the prerequisites a user is missing before they can be appointed Admin II.
     *
     * @return list<string>
     */
    public function missingAdminTwoPrerequisites(User $candidate): array
    {
        $missing = [];

        if (! $candidate->hasVerifiedEmail()) {
            $missing[] = __('Verified primary email');
        }

        if (! $candidate->hasVerifiedRecoveryEmail() && ! $candidate->hasTwoFactor()) {
            $missing[] = __('Verified recovery email or Two-Factor Authentication');
        }

        if (! $candidate->hasBiometrics()) {
            $missing[] = __('Enrolled biometric passkey');
        }

        return $missing;
    }

    /**
     * Appoint a user as Admin II. Returns the missing prerequisites; an empty list means
     * the appointment was made.
     *
     * @return list<string>
     */
    public function appointAdminTwo(User $appointedBy, User $candidate): array
    {
        $missing = $this->missingAdminTwoPrerequisites($candidate);

        if ($missing !== []) {
            return $missing;
        }

        $previousRank = $candidate->rank;

        $candidate->forceFill(['rank' => Rank::AdminTwo])->save();

        AdminAuditLog::record(AuditAction::AdminTwoAppointed, actor: $appointedBy, subject: $candidate, details: [
            'from' => $previousRank->value,
        ]);

        return [];
    }

    /**
     * Revoke a user's Admin II rank and return them to the rank their channels earn.
     */
    public function revokeAdminTwo(User $revokedBy, User $adminTwo): Rank
    {
        $newRank = $this->computedRankFor($adminTwo);

        $adminTwo->forceFill([
            'rank' => $newRank,
            'rank_computed_at' => now(),
        ])->save();

        AdminAuditLog::record(AuditAction::AdminTwoRevoked, actor: $revokedBy, subject: $adminTwo, details: [
            'to' => $newRank->value,
        ]);

        return $newRank;
    }
}
