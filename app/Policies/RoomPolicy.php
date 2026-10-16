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
     * Only the channel creator and Admin I may.
     */
    public function destroy(User $user, Room $room): bool
    {
        return $user->hasRank(Rank::AdminOne) || (int) $room->created_by_user_id === $user->id;
    }

    /**
     * Determine whether the user may pin or unpin messages in the channel.
     */
    public function pinMessages(User $user, Room $room): bool
    {
        return $user->hasRank(Rank::AdminOne) || (int) $room->created_by_user_id === $user->id;
    }
}
