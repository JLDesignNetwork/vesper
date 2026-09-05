<x-mail::message>
# {{ __('New Channel Message') }}

Hello **{{ $recipient->name }}**,

A new message was posted in **{{ $room->title ?: $room->code }}** by **{{ $message->sender_name }}**.

<x-mail::panel>
@if($message->content)
"{{ \Illuminate\Support\Str::limit($message->content, 250) }}"
@endif
@if($message->attachment_name)
<br><small>📎 {{ __('Attachment') }}: {{ $message->attachment_name }} ({{ $message->formatted_size }})</small>
@endif
</x-mail::panel>

<x-mail::button :url="$channelUrl" color="success">
{{ __('Open Channel') }}
</x-mail::button>

*{{ __('You are receiving this notification because email notifications are enabled in your Vesper profile.') }}*

{{ __('Vesper Private Communications') }}
</x-mail::message>
