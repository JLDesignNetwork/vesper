<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who may read an admin audit log entry.
 *
 * AdminOneOnly entries (rank changes, private-channel overrides) are hidden from
 * Admin II. Moderation entries (kicks, reports, ban votes) are visible to Admin I and II.
 */
enum AuditVisibility: string
{
    case AdminOneOnly = 'admin_one_only';
    case Moderation = 'moderation';
}
