<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChannelEntry;
use App\Enums\Rank;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for who may see, open and keep using a channel.
 *
 * - Visibility: public channels are listed for everyone; private channels only for
 *   their members, creator and pending invitees; admin channels only for admitted ranks.
 * - Entry: guests never enter. Private channels need membership; public channels are
 *   joined on entry. Members kicked from a channel never enter it again. Secured
 *   channels need a PIN, except that members with confirmed 2FA enter without one (A1) and members who meet the biometric
 *   precondition may use a passkey check instead of the PIN, unless they turned
 *   pin-free entry off for that channel. `pra` opens only with a fresh passkey check,
 *   for every rank including Admin I (AU-10). Admin I may open any other channel
 *   without membership or PIN (audited by the caller).
 * - Clearance: once opened, a secured channel stays open for 15 minutes after the
 *   member's last action. Polling for messages does not count as an action.
 */
class ChannelAccessService
{
    public const int CLEARANCE_IDLE_MINUTES = 15;

    /**
     * Whether the channel may be shown to this viewer (directory, search, listings).
     */
    public function isVisibleTo(?User $viewer, Room $room): bool
    {
        if (! $this->isOpen($room)) {
            return false;
        }

        if ($room->isPublic()) {
            return true;
        }

        if ($viewer === null) {
            return false;
        }

        if ($viewer->hasRank(Rank::AdminOne)) {
            return true;
        }

        if ($room->type->isAdminChannel()) {
            return $room->type->admitsRank($viewer->rank);
        }

        $status = $this->membershipStatus($viewer, $room);

        return $room->isCreatedBy($viewer) || ($status !== null && $status !== 'kicked');
    }

    /**
     * Decide whether a signed-in member may open the channel, and what they must present.
     */
    public function entryFor(User $user, Room $room): ChannelEntry
    {
        if (! $this->isOpen($room)) {
            return ChannelEntry::Denied;
        }

        if ($room->type->isPasskeyOnly()) {
            if (! $user->hasRank(Rank::AdminOne) && ! $room->type->admitsRank($user->rank)) {
                return ChannelEntry::Denied;
            }

            return $user->hasBiometrics() ? ChannelEntry::PasskeyAssertionRequired : ChannelEntry::PasskeyRequired;
        }

        if ($user->hasRank(Rank::AdminOne)) {
            return ChannelEntry::Granted;
        }

        if (! $room->type->admitsRank($user->rank)) {
            return ChannelEntry::Denied;
        }

        $status = $this->membershipStatus($user, $room);

        if ($status === 'kicked') {
            return ChannelEntry::Denied;
        }

        $isMember = $status === 'active' || $room->isCreatedBy($user);

        if (! $room->isPublic() && ! $room->type->isAdminChannel() && ! $isMember) {
            return ChannelEntry::Denied;
        }

        if (! $room->isSecured()) {
            return ChannelEntry::Granted;
        }

        if ($user->hasTwoFactor() && $this->allowsPinlessEntry($user, $room)) {
            return ChannelEntry::Granted;
        }

        return ChannelEntry::PinRequired;
    }

    /**
     * Whether the member may open the channel now with a passkey check: always for
     * `pra` once a passkey is enrolled, and for other PIN-secured channels when the
     * member meets the biometric precondition and has pin-free entry on for the channel.
     */
    public function allowsPasskeyEntry(User $user, Room $room): bool
    {
        return match ($this->entryFor($user, $room)) {
            ChannelEntry::PasskeyAssertionRequired => true,
            ChannelEntry::PinRequired => $user->hasBiometrics()
                && $user->meetsBiometricPrecondition()
                && $this->allowsPinlessEntry($user, $room),
            default => false,
        };
    }

    /**
     * Turn pin-free entry (2FA or passkey instead of the PIN) on or off for one of the
     * member's channels. `pra` is always passkey-only and is not affected.
     */
    public function setPinlessEntry(User $user, Room $room, bool $enabled): void
    {
        DB::table('room_user')
            ->where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->update(['biometric_entry' => $enabled, 'updated_at' => now()]);
    }

    /**
     * Whether the member may keep using a channel they already opened. Re-checked on
     * every channel request so a demotion, kick, lost membership or closed channel takes
     * effect immediately. In private member channels a membership that is no longer
     * active ends access.
     */
    public function mayContinue(User $user, Room $room): bool
    {
        if (! $this->isOpen($room)) {
            return false;
        }

        if ($user->hasRank(Rank::AdminOne)) {
            return true;
        }

        if (! $room->type->admitsRank($user->rank)) {
            return false;
        }

        $status = $this->membershipStatus($user, $room);

        if ($status === 'kicked') {
            return false;
        }

        // A private-channel membership that is no longer active (declined, or allowed
        // back after a kick) ends access even if the session still holds clearance.
        if (! $room->isPublic() && ! $room->type->isAdminChannel() && $status !== null && $status !== 'active') {
            return $room->isCreatedBy($user);
        }

        return true;
    }

    /**
     * Record that the member opened the channel.
     */
    public function grantClearance(Request $request, Room $room, string $alias): void
    {
        $request->session()->put("room_clearance_{$room->id}", true);
        $request->session()->put("room_clearance_at_{$room->id}", now()->getTimestamp());
        $request->session()->put("room_alias_{$room->id}", $alias);
    }

    /**
     * Whether the session still holds clearance for the channel. Secured channels lose
     * clearance after the idle limit; the clearance is revoked when that happens.
     */
    public function hasClearance(Request $request, Room $room): bool
    {
        if (! $request->session()->get("room_clearance_{$room->id}", false)) {
            return false;
        }

        if (! $room->isSecured()) {
            return true;
        }

        $lastActionAt = $request->session()->get("room_clearance_at_{$room->id}");

        if ($lastActionAt === null) {
            $this->touchClearance($request, $room);

            return true;
        }

        if (now()->getTimestamp() - (int) $lastActionAt > self::CLEARANCE_IDLE_MINUTES * 60) {
            $this->revokeClearance($request, $room);

            return false;
        }

        return true;
    }

    /**
     * Extend clearance after an action the member took (sending, reacting, typing).
     */
    public function touchClearance(Request $request, Room $room): void
    {
        $request->session()->put("room_clearance_at_{$room->id}", now()->getTimestamp());
    }

    /**
     * Close the channel for this session.
     */
    public function revokeClearance(Request $request, Room $room): void
    {
        $request->session()->forget([
            "room_clearance_{$room->id}",
            "room_clearance_at_{$room->id}",
            "room_alias_{$room->id}",
        ]);
    }

    /**
     * Whether the channel is active and unexpired.
     */
    protected function isOpen(Room $room): bool
    {
        return $room->status === 'active' && ! $room->isExpired();
    }

    /**
     * The member's status in the channel ('active', 'invited', 'declined', 'kicked') or null.
     */
    protected function membershipStatus(User $user, Room $room): ?string
    {
        return DB::table('room_user')
            ->where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->value('status');
    }

    /**
     * Whether the member has left pin-free (2FA / passkey) entry switched on for this channel.
     */
    public function allowsPinlessEntry(User $user, Room $room): bool
    {
        $setting = DB::table('room_user')
            ->where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->value('biometric_entry');

        return $setting === null || (bool) $setting;
    }
}
