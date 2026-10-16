<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a report is in moderator review. Reviewed and dismissed reports are kept.
 */
enum ReportStatus: string
{
    case Open = 'open';
    case Reviewed = 'reviewed';
    case Dismissed = 'dismissed';
}
