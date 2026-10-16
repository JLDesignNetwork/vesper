<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a ban case stands. An open ban vote keeps its subject restricted (read-only);
 * an open lift vote changes nothing until it is decided.
 */
enum BanCaseStatus: string
{
    case Open = 'open';

    /** A ban vote (or a direct Admin II ban) banned the member. */
    case Banned = 'banned';

    /** A ban vote decided not to ban. */
    case Cleared = 'cleared';

    /** A lift vote lifted the ban. */
    case Lifted = 'lifted';

    /** A lift vote kept the ban. */
    case Upheld = 'upheld';

    /**
     * The outcome as shown on the review page.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Banned => __('Banned'),
            self::Cleared => __('Not banned'),
            self::Lifted => __('Ban lifted'),
            self::Upheld => __('Ban kept'),
        };
    }

    /**
     * Whether the outcome left the member banned.
     */
    public function leftMemberBanned(): bool
    {
        return in_array($this, [self::Banned, self::Upheld], true);
    }
}
