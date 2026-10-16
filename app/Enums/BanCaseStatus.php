<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a ban case stands. An open case keeps its subject restricted (read-only).
 */
enum BanCaseStatus: string
{
    case Open = 'open';
    case Banned = 'banned';
    case Cleared = 'cleared';
}
