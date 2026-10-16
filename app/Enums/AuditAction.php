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
    case BanLifted = 'ban_lifted';

    /**
     * The default audience allowed to read entries of this action.
     */
    public function visibility(): AuditVisibility
    {
        return match ($this) {
            self::MemberKicked, self::KickLifted, self::ReportFiled,
            self::BanCaseOpened, self::BanVoteCast, self::BanCaseClosed, self::MemberBanned, self::BanLifted => AuditVisibility::Moderation,
            default => AuditVisibility::AdminOneOnly,
        };
    }

    /**
     * The action as shown in the admin panel's audit log.
     */
    public function label(): string
    {
        return match ($this) {
            self::RankChanged => __('Rank changed'),
            self::AdminTwoAppointed => __('Admin II appointed'),
            self::AdminTwoRevoked => __('Admin II revoked'),
            self::ChannelAccessOverride => __('Entered a channel as Admin I'),
            self::MemberKicked => __('Member removed from channel'),
            self::KickLifted => __('Member allowed back'),
            self::ReportFiled => __('Report filed'),
            self::BanCaseOpened => __('Ban vote opened'),
            self::BanVoteCast => __('Ban vote cast'),
            self::BanCaseClosed => __('Ban vote closed'),
            self::MemberBanned => __('Member banned'),
            self::BanLifted => __('Ban lifted'),
        };
    }
}
