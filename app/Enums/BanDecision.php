<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a closed ban case was decided (owner decision Q6, audit finding AU-7).
 */
enum BanDecision: string
{
    /** A strict majority of the ballots cast. */
    case Majority = 'majority';

    /** The ballots were tied and Admin I's ballot decided. */
    case TieBreak = 'tie_break';

    /** The ballots were tied and Admin I did not vote, so the subject was not banned. */
    case TiedWithoutTieBreak = 'tied_without_tie_break';

    /** No Admin II existed, so Admin I's ballot decided alone. */
    case AdminOneAlone = 'admin_one_alone';

    /** Nobody voted before the case closed, so the subject was not banned. */
    case NoBallots = 'no_ballots';

    /** Admin I banned an Admin II directly, without a vote. */
    case Direct = 'direct';

    /**
     * The decision as shown on the review page.
     */
    public function label(): string
    {
        return match ($this) {
            self::Majority => __('Majority vote'),
            self::TieBreak => __('Tie broken by Admin I'),
            self::TiedWithoutTieBreak => __('Tied, no tie-break'),
            self::AdminOneAlone => __('Decided by Admin I'),
            self::NoBallots => __('No votes cast'),
            self::Direct => __('Banned directly by Admin I'),
        };
    }
}
