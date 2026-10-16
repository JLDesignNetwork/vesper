<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rank;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the viewer may see fields the subject has chosen to hide
     * (age, birthday, location, bio, preferred language). Only the subject and Admin I may.
     */
    public function viewHiddenFields(User $viewer, User $subject): bool
    {
        return $viewer->is($subject) || $viewer->hasRank(Rank::AdminOne);
    }

    /**
     * Determine whether the viewer may appoint or revoke the subject's Admin II rank.
     * Only Admin I may, and never on themself.
     */
    public function manageAdminTwo(User $viewer, User $subject): bool
    {
        return $viewer->hasRank(Rank::AdminOne) && ! $viewer->is($subject);
    }
}
