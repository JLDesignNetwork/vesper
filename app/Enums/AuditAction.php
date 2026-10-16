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

    /**
     * The default audience allowed to read entries of this action.
     */
    public function visibility(): AuditVisibility
    {
        return AuditVisibility::AdminOneOnly;
    }
}
