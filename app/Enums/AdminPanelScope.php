<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What an admin rank sees in the admin panel (owner decision Q9).
 *
 * - Full (Admin I): every channel and member, with geolocation, IP addresses and access-log locations.
 * - Global (Admin II): every channel and member, without geolocation, IPs or locations.
 * - OwnChannels (Admin III): only the channels they created and those channels' members,
 *   without geolocation, IPs, locations or email addresses.
 */
enum AdminPanelScope: string
{
    case Full = 'full';
    case Global = 'global';
    case OwnChannels = 'own_channels';

    /**
     * The scope for a rank, or null when the rank has no admin panel.
     */
    public static function forRank(Rank $rank): ?self
    {
        return match ($rank) {
            Rank::AdminOne => self::Full,
            Rank::AdminTwo => self::Global,
            Rank::AdminThree => self::OwnChannels,
            default => null,
        };
    }

    /**
     * Whether every channel and member is shown, not only the viewer's own channels.
     */
    public function seesAllChannels(): bool
    {
        return $this !== self::OwnChannels;
    }

    /**
     * Whether geolocation, IP addresses and access-log locations are shown.
     */
    public function showsGeolocation(): bool
    {
        return $this === self::Full;
    }

    /**
     * Whether members' email addresses are shown.
     */
    public function showsEmails(): bool
    {
        return $this !== self::OwnChannels;
    }

    /**
     * The panel's name as shown in its header.
     */
    public function label(): string
    {
        return match ($this) {
            self::Full => __('Admin I console'),
            self::Global => __('Admin II console'),
            self::OwnChannels => __('My channels'),
        };
    }
}
