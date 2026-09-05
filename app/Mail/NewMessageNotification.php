<?php

namespace App\Mail;

use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewMessageNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Room $room,
        public Message $message,
        public User $recipient
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $channelName = $this->room->title ?: $this->room->code;

        return new Envelope(
            subject: "[{$channelName}] New transmission from {$this->message->sender_name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.new_message',
            with: [
                'room' => $this->room,
                'message' => $this->message,
                'recipient' => $this->recipient,
                'channelUrl' => route('rooms.show', ['room' => $this->room->code]),
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
