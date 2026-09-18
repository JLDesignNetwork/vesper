<?php

namespace App\Mail;

use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class NewMessageNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Room $room,
        public Message $message,
        public User $recipient
    ) {}

    public function getRendered(): array
    {
        $locale = $this->recipient->preferred_locale ?: app()->getLocale();
        $channelName = $this->room->title ?: $this->room->code;

        return app(EmailTemplateService::class)->render('new_message', [
            'channel_name' => $channelName,
            'channel_code' => $this->room->code,
            'sender_name' => $this->message->sender_name,
            'message_preview' => Str::limit($this->message->content, 200),
            'channel_url' => route('rooms.show', ['room' => $this->room->code]),
            'recipient_name' => $this->recipient->name,
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

    public function attachments(): array
    {
        return [];
    }
}
