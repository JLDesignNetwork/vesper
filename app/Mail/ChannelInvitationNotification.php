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

class ChannelInvitationNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
        public Room $room,
        public string $invitationUrl,
        public string $invitationCode,
        public ?string $inviterName = null
    ) {}

    public function getRendered(): array
    {
        $locale = $this->recipient->preferred_locale ?: app()->getLocale();
        $channelName = $this->room->title ?: $this->room->code;

        return app(EmailTemplateService::class)->render('channel_invitation', [
            'operative_name' => $this->recipient->name,
            'channel_name' => $channelName,
            'channel_code' => $this->room->code,
            'invitation_url' => $this->invitationUrl,
            'invitation_code' => $this->invitationCode,
            'inviter_name' => $this->inviterName ?: 'Command Control',
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
