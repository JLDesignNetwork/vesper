<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SecurityAlertNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $eventDescription,
        public string $ipAddress
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Vesper // Critical Security Alert'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.security_alert',
        );
    }
}
