<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Channel visibility and entry types.
 *
 * Private channels are hidden from everyone who is not a member (or invited).
 * Public channels appear in the directory. Secured channels need a PIN or, when the
 * member has it, 2FA or a passkey. The two admin channels are restricted by rank.
 */
enum ChannelType: string
{
    case PrivateSecured = 'prs';
    case PrivateFreeEntry = 'prf';
    case PublicSecured = 'pus';
    case PublicFreeEntry = 'puf';
    case PrivateAdmin = 'pra';
    case PrivateAdminTwo = 'pra-2';

    /**
     * Whether the channel is listed in the public directory.
     */
    public function isPublic(): bool
    {
        return in_array($this, [self::PublicSecured, self::PublicFreeEntry], true);
    }

    /**
     * Whether entry needs a credential (PIN, 2FA or passkey) each time the channel is opened.
     */
    public function isSecured(): bool
    {
        return $this !== self::PrivateFreeEntry && $this !== self::PublicFreeEntry;
    }

    /**
     * Whether this is one of the rank-restricted admin channels.
     */
    public function isAdminChannel(): bool
    {
        return $this === self::PrivateAdmin || $this === self::PrivateAdminTwo;
    }

    /**
     * Whether only a biometric passkey may open the channel (no PIN, no 2FA).
     */
    public function isPasskeyOnly(): bool
    {
        return $this === self::PrivateAdmin;
    }

    /**
     * Whether a member holding the given rank may use this channel at all.
     */
    public function admitsRank(Rank $rank): bool
    {
        return match ($this) {
            self::PrivateAdmin => in_array($rank, [Rank::AdminOne, Rank::AdminTwo], true),
            self::PrivateAdminTwo => $rank->isAdminRank(),
            default => true,
        };
    }

    /**
     * The channel types a member may choose when creating or editing their own channel.
     *
     * @return list<self>
     */
    public static function memberCreatable(): array
    {
        return [self::PrivateSecured, self::PrivateFreeEntry, self::PublicSecured, self::PublicFreeEntry];
    }

    /**
     * Human-readable name for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::PrivateSecured => 'Private · PIN secured',
            self::PrivateFreeEntry => 'Private · free entry',
            self::PublicSecured => 'Public · PIN secured',
            self::PublicFreeEntry => 'Public · free entry',
            self::PrivateAdmin => 'Private admin (Admin I & II)',
            self::PrivateAdminTwo => 'Private admin (all admins)',
        };
    }
}
