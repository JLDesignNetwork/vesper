<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The outcome of a member asking to open a channel.
 */
enum ChannelEntry: string
{
    /** The member may open the channel now. */
    case Granted = 'granted';

    /** The member may open the channel after entering its PIN. */
    case PinRequired = 'pin_required';

    /** The channel opens only with a biometric passkey, which the member has not enrolled. */
    case PasskeyRequired = 'passkey_required';

    /** The member may not open the channel. Callers must not reveal whether it exists. */
    case Denied = 'denied';
}
