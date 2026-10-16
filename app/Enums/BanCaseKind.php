<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a ban case votes on: banning a member, or lifting their ban (owner decision Q13).
 */
enum BanCaseKind: string
{
    case Ban = 'ban';
    case Lift = 'lift';

    /**
     * Whether the member stays banned when the vote reaches no decision
     * (no ballots, or a tie without Admin I's ballot). Nothing changes without a decision.
     */
    public function bannedWithoutDecision(): bool
    {
        return $this === self::Lift;
    }
}
