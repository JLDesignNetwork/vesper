<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Platform rank hierarchy.
 *
 * AdminOne is the platform owner. AdminTwo is appointed by AdminOne and requires
 * biometrics. AdminThree, UserOne and UserTwo are computed from the channels a
 * member has created (see RankService).
 */
enum Rank: string
{
    case AdminOne = 'admin_one';
    case AdminTwo = 'admin_two';
    case AdminThree = 'admin_three';
    case UserOne = 'user_one';
    case UserTwo = 'user_two';

    /**
     * Whether this rank is derived from channel activity and recomputed automatically.
     */
    public function isComputed(): bool
    {
        return in_array($this, [self::AdminThree, self::UserOne, self::UserTwo], true);
    }

    /**
     * Whether this rank holds any admin standing (Admin I, II or III).
     */
    public function isAdminRank(): bool
    {
        return in_array($this, [self::AdminOne, self::AdminTwo, self::AdminThree], true);
    }

    /**
     * Whether this rank is publicly displayed as "Admin". Admin III displays as a member.
     */
    public function displaysAsAdmin(): bool
    {
        return in_array($this, [self::AdminOne, self::AdminTwo], true);
    }

    /**
     * The rank label shown to other members: either "Admin" or "Member".
     */
    public function publicLabel(): string
    {
        return $this->displaysAsAdmin() ? 'Admin' : 'Member';
    }

    /**
     * The full rank name, shown only to the rank holder and to Admin I.
     */
    public function label(): string
    {
        return match ($this) {
            self::AdminOne => 'Admin I',
            self::AdminTwo => 'Admin II',
            self::AdminThree => 'Admin III',
            self::UserOne => 'User I',
            self::UserTwo => 'User II',
        };
    }
}
