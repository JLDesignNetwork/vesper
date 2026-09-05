<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmergencyAccountRecovery extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $resetUrl,
        public string $ipAddress
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Vesper // Emergency Account Recovery Link'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.emergency_recovery',
        );
    }
}
