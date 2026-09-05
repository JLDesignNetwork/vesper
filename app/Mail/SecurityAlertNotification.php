<?php

namespace App\Mail;

use App\Models\User;
use App\Services\EmailTemplateService;
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

    public function getRendered(): array
    {
        $locale = $this->user->preferred_locale ?: app()->getLocale();

        return app(EmailTemplateService::class)->render('security_alert', [
            'operative_name' => $this->user->name,
            'event_description' => $this->eventDescription,
            'ip_address' => $this->ipAddress,
            'timestamp' => now()->toIso8601String(),
            'recovery_url' => route('recovery.request'),
        ], $locale);
    }

    public function envelope(): Envelope
    {
        $rendered = $this->getRendered();

        return new Envelope(
            subject: $rendered['subject'] ?? __('Vesper // Critical Security Alert'),
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
