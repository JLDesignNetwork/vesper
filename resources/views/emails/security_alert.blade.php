<x-mail::message>
# ⚠️ {{ __('Critical Security Alert') }}

Hello **{{ $user->name }}**,

A critical security modification occurred on your **Vesper** account:

<x-mail::panel>
**{{ __('Event') }}:** {{ $eventDescription }}<br>
**{{ __('IP Origin') }}:** `{{ $ipAddress }}`<br>
**{{ __('Timestamp') }}:** {{ now()->toRfc2822String() }}
</x-mail::panel>

{{ __('This notification was dispatched simultaneously to your primary account address and your verified secondary recovery channel.') }}

*{{ __('Vesper Private Communications • Ghostwire Protocol') }}*
</x-mail::message>
