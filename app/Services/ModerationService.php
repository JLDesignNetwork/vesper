<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\ReportOrigin;
use App\Enums\ReportReason;
use App\Models\AdminAuditLog;
use App\Models\Message;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reports and channel kicks.
 *
 * Only a channel's creator may kick, and only active members. Admin I and II cannot be
 * kicked: the attempt files a report and the creator sees the same confirmation as a
 * real kick (owner decision Q3). An Admin III is kicked like any member (they can never
 * be kicked from their own channel, since only its creator kicks). A creator may let a
 * kicked member back. Every kick, lifted kick and report is written to the audit log
 * with moderation visibility, so Admin I and II can review it.
 */
class ModerationService
{
    public function __construct(
        protected RankService $ranks
    ) {}

    /**
     * File a report about a member and record it in the audit log.
     */
    public function fileReport(
        User $reporter,
        User $subject,
        ReportOrigin $origin,
        ReportReason $reason,
        ?string $details = null,
        ?Room $room = null,
        ?Message $message = null,
    ): Report {
        $report = Report::create([
            'reporter_id' => $reporter->id,
            'subject_user_id' => $subject->id,
            'room_id' => $room?->id,
            'message_id' => $message?->id,
            'origin' => $origin,
            'reason' => $reason,
            'details' => $details,
        ]);

        AdminAuditLog::record(AuditAction::ReportFiled, actor: $reporter, subject: $subject, room: $room, details: [
            'report_id' => $report->id,
            'origin' => $origin->value,
            'reason' => $reason->value,
        ]);

        return $report;
    }

    /**
     * Kick an active member from the creator's channel, or report them when they are
     * Admin I or II. Returns true when the member was kicked, false when a report was filed.
     */
    public function kick(User $creator, Room $room, User $target): bool
    {
        if ($target->displaysAsAdmin()) {
            $this->fileReport($creator, $target, ReportOrigin::KickAttempt, ReportReason::Other, room: $room);

            return false;
        }

        DB::table('room_user')
            ->where('room_id', $room->id)
            ->where('user_id', $target->id)
            ->update([
                'status' => 'kicked',
                'kicked_by_user_id' => $creator->id,
                'kicked_at' => now(),
                'updated_at' => now(),
            ]);

        AdminAuditLog::record(AuditAction::MemberKicked, actor: $creator, subject: $target, room: $room);
        $this->ranks->recomputeCreatorOf($room);

        return true;
    }

    /**
     * Let a kicked member back. Their membership becomes 'declined', so they can rejoin
     * through an invitation, or by opening the channel if it is public.
     */
    public function allowBack(User $creator, Room $room, User $target): void
    {
        DB::table('room_user')
            ->where('room_id', $room->id)
            ->where('user_id', $target->id)
            ->where('status', 'kicked')
            ->update([
                'status' => 'declined',
                'kicked_by_user_id' => null,
                'kicked_at' => null,
                'updated_at' => now(),
            ]);

        AdminAuditLog::record(AuditAction::KickLifted, actor: $creator, subject: $target, room: $room);
    }
}
