<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a report came to be filed.
 *
 * Block and kick attempts against admin ranks become reports (owner decision Q3),
 * so moderators can tell them apart from reports a member filed directly.
 */
enum ReportOrigin: string
{
    case Direct = 'direct';
    case BlockAttempt = 'block_attempt';
    case KickAttempt = 'kick_attempt';
}
