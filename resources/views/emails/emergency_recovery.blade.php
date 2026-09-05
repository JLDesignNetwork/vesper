<x-mail::message>
# {{ __('Emergency Account Recovery Request') }}

Hello **{{ $user->name }}**,

An emergency account recovery request was initiated for your **Vesper** identity from IP node `{{ $ipAddress }}`.

<x-mail::panel>
{{ __('Clicking the link below will allow you to reset your password and gain immediate entry. This link is single-use and will expire in 15 minutes.') }}
</x-mail::panel>

<x-mail::button :url="$resetUrl" color="error">
{{ __('Reset Password & Access Account') }}
</x-mail::button>

**{{ __('Security Notice') }}:** {{ __('If you did NOT initiate this recovery request, your account may be under reconnaissance. Please log in immediately and inspect your active security credentials.') }}

*{{ __('Vesper Private Communications • Ghostwire Protocol') }}*
</x-mail::message>
