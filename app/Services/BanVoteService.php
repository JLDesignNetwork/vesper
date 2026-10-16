<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\BanCaseKind;
use App\Enums\BanCaseStatus;
use App\Enums\BanDecision;
use App\Enums\Rank;
use App\Enums\ReportStatus;
use App\Mail\BanCaseOpenedNotification;
use App\Mail\BanLiftedNotification;
use App\Models\AdminAuditLog;
use App\Models\BanCase;
use App\Models\BanVote;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * The ban vote system (owner decision Q6, audit findings AU-6 and AU-7).
 *
 * - Admin I or II opens a case against a member ranked Admin III or below. The subject
 *   is restricted (read-only) at once, and every other voter is emailed.
 * - Voters are Admin I and every Admin II. The opener's case is not a ballot; they vote
 *   separately. Ballots can be changed until the case closes.
 * - A case closes 72 hours after opening. A strict majority of the ballots cast decides.
 *   On a tie, Admin I's ballot decides; without one the subject is not banned. No ballots
 *   means not banned. While no Admin II exists, Admin I's ballot decides at once.
 * - An Admin II is never voted on: only Admin I may ban them, directly. Admin I cannot be banned.
 * - A ban revokes the member's sessions and passkeys and blocks sign-in.
 * - Lifting a ban is also a vote (owner decision Q13), with the same voters, window and
 *   tie rules. Without a decision (no ballots, or a tie without Admin I) the ban stays.
 *   Lifting clears the ban only: rank is unchanged and passkeys and sessions stay revoked.
 * - Every step is written to the audit log with moderation visibility.
 */
class BanVoteService
{
    public const int VOTING_HOURS = 72;

    /**
     * Open a ban case and restrict its subject.
     *
     * @throws ValidationException when the subject cannot be voted on
     */
    public function open(User $opener, User $subject, string $reason, ?Report $report = null): BanCase
    {
        if ($subject->displaysAsAdmin()) {
            throw ValidationException::withMessages(['tag' => __('Admin I and II cannot be put to a ban vote.')]);
        }

        if ($subject->isBanned()) {
            throw ValidationException::withMessages(['tag' => __('This member is already banned.')]);
        }

        $case = DB::transaction(function () use ($opener, $subject, $reason, $report): BanCase {
            // Lock the subject so two admins opening a vote at once cannot create two open cases.
            User::whereKey($subject->id)->lockForUpdate()->first();

            if ($subject->isRestricted()) {
                throw ValidationException::withMessages(['tag' => __('A ban vote about this member is already open.')]);
            }

            $case = BanCase::create([
                'subject_user_id' => $subject->id,
                'opened_by_user_id' => $opener->id,
                'report_id' => $report?->id,
                'reason' => $reason,
                'closes_at' => now()->addHours(self::VOTING_HOURS),
            ]);

            $report?->forceFill([
                'status' => ReportStatus::Reviewed,
                'reviewed_by_user_id' => $opener->id,
                'reviewed_at' => now(),
            ])->save();

            AdminAuditLog::record(AuditAction::BanCaseOpened, actor: $opener, subject: $subject, details: [
                'ban_case_id' => $case->id,
                'report_id' => $report?->id,
            ]);

            return $case;
        });

        $this->notifyVoters($case, $opener);

        return $case;
    }

    /**
     * Open a vote on lifting a member's ban. Nothing changes until the vote is decided.
     *
     * @throws ValidationException when the member is not banned or a vote is already open
     */
    public function openLift(User $opener, User $subject, string $reason): BanCase
    {
        if (! $subject->isBanned()) {
            throw ValidationException::withMessages(['tag' => __('This member is not banned.')]);
        }

        $case = DB::transaction(function () use ($opener, $subject, $reason): BanCase {
            // Lock the subject so two admins opening a vote at once cannot create two open cases.
            User::whereKey($subject->id)->lockForUpdate()->first();

            if ($subject->banCases()->open()->exists()) {
                throw ValidationException::withMessages(['tag' => __('A vote to lift this member\'s ban is already open.')]);
            }

            $case = BanCase::create([
                'kind' => BanCaseKind::Lift,
                'subject_user_id' => $subject->id,
                'opened_by_user_id' => $opener->id,
                'reason' => $reason,
                'closes_at' => now()->addHours(self::VOTING_HOURS),
            ]);

            AdminAuditLog::record(AuditAction::BanCaseOpened, actor: $opener, subject: $subject, details: [
                'ban_case_id' => $case->id,
                'kind' => BanCaseKind::Lift->value,
            ]);

            return $case;
        });

        $this->notifyVoters($case, $opener);

        return $case;
    }

    /**
     * Cast or change a ballot. `$ban` is true for "the member should be banned": in a ban
     * vote that means ban, in a lift vote it means keep the ban. While no Admin II exists, Admin I's ballot closes the case.
     *
     * @throws ValidationException when the case is no longer open
     */
    public function vote(User $voter, BanCase $case, bool $ban): BanVote
    {
        if (! $case->isOpen() || $case->closes_at->isPast()) {
            throw ValidationException::withMessages(['vote' => __('Voting on this case has closed.')]);
        }

        $ballot = BanVote::updateOrCreate(
            ['ban_case_id' => $case->id, 'voter_id' => $voter->id],
            ['ban' => $ban],
        );

        AdminAuditLog::record(AuditAction::BanVoteCast, actor: $voter, subject: $case->subject, details: [
            'ban_case_id' => $case->id,
            'kind' => $case->kind->value,
            'ban' => $ban,
        ]);

        if ($voter->hasRank(Rank::AdminOne) && ! $this->adminTwoExists()) {
            $this->close($case);
        }

        return $ballot;
    }

    /**
     * Close every open case whose voting window has ended. Returns how many were closed.
     */
    public function closeDue(): int
    {
        $due = BanCase::open()->where('closes_at', '<=', now())->get();
        $due->each(fn (BanCase $case) => $this->close($case));

        return $due->count();
    }

    /**
     * Decide and close a case: a ban vote bans the subject when the decision is to ban,
     * a lift vote lifts the ban when the decision is to lift it.
     */
    public function close(BanCase $case): BanCase
    {
        [$ban, $decision] = $this->decide($case);
        $lifted = $case->isLiftVote() && ! $ban;

        DB::transaction(function () use ($case, $ban, $decision, $lifted): void {
            $case->update([
                'status' => match (true) {
                    $case->isLiftVote() => $ban ? BanCaseStatus::Upheld : BanCaseStatus::Lifted,
                    default => $ban ? BanCaseStatus::Banned : BanCaseStatus::Cleared,
                },
                'decision' => $decision,
                'closed_at' => now(),
            ]);

            AdminAuditLog::record(AuditAction::BanCaseClosed, subject: $case->subject, details: [
                'ban_case_id' => $case->id,
                'kind' => $case->kind->value,
                'banned' => $ban,
                'decision' => $decision->value,
            ]);

            if ($lifted) {
                $this->liftBan($case->subject);
            } elseif ($ban && ! $case->isLiftVote()) {
                $this->applyBan($case->subject, null);
            }
        });

        if ($lifted) {
            $this->notifyBanLifted($case->subject);
        }

        return $case;
    }

    /**
     * Admin I bans an Admin II directly, without a vote. The case is kept for the record.
     *
     * @throws ValidationException when the subject is not an Admin II
     */
    public function banAdminTwo(User $adminOne, User $subject, string $reason): BanCase
    {
        if (! $subject->hasRank(Rank::AdminTwo)) {
            throw ValidationException::withMessages(['tag' => __('Only Admin II members are banned directly. Open a ban vote for anyone else.')]);
        }

        return DB::transaction(function () use ($adminOne, $subject, $reason): BanCase {
            $case = BanCase::create([
                'subject_user_id' => $subject->id,
                'opened_by_user_id' => $adminOne->id,
                'reason' => $reason,
                'status' => BanCaseStatus::Banned,
                'decision' => BanDecision::Direct,
                'closes_at' => now(),
                'closed_at' => now(),
            ]);

            $this->applyBan($subject, $adminOne);

            return $case;
        });
    }

    /**
     * The outcome of a case from its ballots: [banned?, how it was decided].
     * Without a decision nothing changes: a ban vote does not ban, a lift vote keeps the ban.
     *
     * @return array{0: bool, 1: BanDecision}
     */
    public function decide(BanCase $case): array
    {
        $ballots = $case->votes()->with('voter:id,rank')->get();
        $bannedWithoutDecision = $case->kind->bannedWithoutDecision();

        if ($ballots->isEmpty()) {
            return [$bannedWithoutDecision, BanDecision::NoBallots];
        }

        $adminOneBallot = $ballots->first(fn (BanVote $ballot): bool => $ballot->voter?->hasRank(Rank::AdminOne) ?? false);

        if ($adminOneBallot && ! $this->adminTwoExists()) {
            return [$adminOneBallot->ban, BanDecision::AdminOneAlone];
        }

        $forBan = $ballots->where('ban', true)->count();
        $against = $ballots->count() - $forBan;

        if ($forBan !== $against) {
            return [$forBan > $against, BanDecision::Majority];
        }

        return $adminOneBallot
            ? [$adminOneBallot->ban, BanDecision::TieBreak]
            : [$bannedWithoutDecision, BanDecision::TiedWithoutTieBreak];
    }

    /**
     * Admin I and every Admin II who may vote, excluding banned members.
     *
     * @return Collection<int, User>
     */
    public function voters(): Collection
    {
        return User::whereIn('rank', [Rank::AdminOne, Rank::AdminTwo])->whereNull('banned_at')->get();
    }

    /**
     * Ban the member: block sign-in and revoke their sessions, remember token and passkeys.
     */
    protected function applyBan(User $subject, ?User $actor): void
    {
        $subject->forceFill([
            'banned_at' => now(),
            'remember_token' => null,
        ])->save();

        $subject->webauthnCredentials()->delete();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $subject->id)->delete();
        }

        AdminAuditLog::record(AuditAction::MemberBanned, actor: $actor, subject: $subject);
    }

    /**
     * Lift the member's ban. Their rank is unchanged; revoked passkeys and sessions stay revoked.
     */
    protected function liftBan(User $subject): void
    {
        $subject->forceFill(['banned_at' => null])->save();

        AdminAuditLog::record(AuditAction::BanLifted, subject: $subject);
    }

    protected function adminTwoExists(): bool
    {
        return User::where('rank', Rank::AdminTwo)->whereNull('banned_at')->exists();
    }

    /**
     * Email every voter except the opener that a ban or lift vote is waiting for their ballot.
     */
    protected function notifyVoters(BanCase $case, User $opener): void
    {
        $this->voters()
            ->reject(fn (User $voter): bool => $voter->is($opener) || empty($voter->email))
            ->each(function (User $voter) use ($case): void {
                try {
                    Mail::to($voter->email)->queue(new BanCaseOpenedNotification($case, $voter));
                } catch (\Throwable $e) {
                    Log::warning("Failed to queue ban case notification: {$e->getMessage()}");
                }
            });
    }

    /**
     * Email the member that their ban has been lifted.
     */
    protected function notifyBanLifted(User $member): void
    {
        if (empty($member->email)) {
            return;
        }

        try {
            Mail::to($member->email)->queue(new BanLiftedNotification($member));
        } catch (\Throwable $e) {
            Log::warning("Failed to queue ban lifted notification: {$e->getMessage()}");
        }
    }
}
