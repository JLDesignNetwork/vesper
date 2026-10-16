<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rank;
use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    /**
     * Determine whether the user may enter a channel without membership or PIN.
     * Only Admin I holds this override, and every use is audited (owner decision Q1).
     */
    public function overrideAccess(User $user, Room $room): bool
    {
        return $user->hasRank(Rank::AdminOne);
    }

    /**
     * Determine whether the user may permanently destroy the channel and its media.
     * Only the channel creator and Admin I may. System channels (pra, pra-2) never can be.
     */
    public function destroy(User $user, Room $room): bool
    {
        if ($room->is_system) {
            return false;
        }

        return $user->hasRank(Rank::AdminOne) || $room->isCreatedBy($user);
    }

    /**
     * Determine whether the user may change the channel's settings.
     * The creator may; Admin I may change any channel.
     */
    public function update(User $user, Room $room): bool
    {
        return $user->hasRank(Rank::AdminOne) || $room->isCreatedBy($user);
    }

    /**
     * Determine whether the user may kick members from the channel. Only the channel's
     * creator may; Admin I has no kick override.
     */
    public function kickMembers(User $user, Room $room): bool
    {
        return $room->isCreatedBy($user);
    }

    /**
     * Determine whether the user may pin or unpin messages in the channel.
     */
    public function pinMessages(User $user, Room $room): bool
    {
        return $user->hasRank(Rank::AdminOne) || (int) $room->created_by_user_id === $user->id;
    }
}
