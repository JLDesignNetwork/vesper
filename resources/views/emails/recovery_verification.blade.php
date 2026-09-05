<x-mail::message>
# {{ __('Verify Secondary Recovery Email') }}

Hello **{{ $user->name }}**,

A request was made to designate this email address as the emergency recovery channel for your **Vesper** account.

<x-mail::button :url="$verificationUrl" color="success">
{{ __('Confirm Recovery Email') }}
</x-mail::button>

{{ __('If you did not request this authorization, no action is required. This link will expire in 24 hours.') }}

*{{ __('Vesper Private Communications • Ghostwire Protocol') }}*
</x-mail::message>
