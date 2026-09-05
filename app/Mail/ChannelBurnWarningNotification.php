<?php

namespace App\Mail;

use App\Models\Room;
use App\Models\User;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChannelBurnWarningNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
        public Room $room,
        public string $hoursRemaining = '24'
    ) {}

    public function getRendered(): array
    {
        $locale = $this->recipient->preferred_locale ?: app()->getLocale();
        $channelName = $this->room->title ?: $this->room->code;

        return app(EmailTemplateService::class)->render('channel_burn_warning', [
            'operative_name' => $this->recipient->name,
            'channel_name' => $channelName,
            'channel_code' => $this->room->code,
            'burn_countdown' => "{$this->hoursRemaining} Hours",
            'channel_url' => route('rooms.show', ['room' => $this->room->code]),
        ], $locale);
    }

    public function envelope(): Envelope
    {
        $rendered = $this->getRendered();

        return new Envelope(
            subject: $rendered['subject'],
        );
    }

    public function content(): Content
    {
        $rendered = $this->getRendered();

        return new Content(
            htmlString: $rendered['rendered_html'],
        );
    }
}
