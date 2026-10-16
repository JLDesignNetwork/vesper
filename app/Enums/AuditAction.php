<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Actions recorded in the admin audit log.
 */
enum AuditAction: string
{
    case RankChanged = 'rank_changed';
    case AdminTwoAppointed = 'admin_two_appointed';
    case AdminTwoRevoked = 'admin_two_revoked';
    case ChannelAccessOverride = 'channel_access_override';
    case MemberKicked = 'member_kicked';
    case KickLifted = 'kick_lifted';
    case ReportFiled = 'report_filed';
    case BanCaseOpened = 'ban_case_opened';
    case BanVoteCast = 'ban_vote_cast';
    case BanCaseClosed = 'ban_case_closed';
    case MemberBanned = 'member_banned';

    /**
     * The default audience allowed to read entries of this action.
     */
    public function visibility(): AuditVisibility
    {
        return match ($this) {
            self::MemberKicked, self::KickLifted, self::ReportFiled,
            self::BanCaseOpened, self::BanVoteCast, self::BanCaseClosed, self::MemberBanned => AuditVisibility::Moderation,
            default => AuditVisibility::AdminOneOnly,
        };
    }
}
