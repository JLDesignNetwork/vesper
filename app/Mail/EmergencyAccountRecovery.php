<?php

namespace App\Mail;

use App\Models\User;
use App\Services\EmailTemplateService;
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

    public function getRendered(): array
    {
        $locale = $this->user->preferred_locale ?: app()->getLocale();

        return app(EmailTemplateService::class)->render('emergency_recovery', [
            'member_name' => $this->user->name,
            'operative_name' => $this->user->name,
            'email' => $this->user->email,
            'reset_url' => $this->resetUrl,
            'ip_address' => $this->ipAddress,
            'expires_in' => '60 minutes',
        ], $locale);
    }

    public function envelope(): Envelope
    {
        $rendered = $this->getRendered();

        return new Envelope(
            subject: $rendered['subject'] ?? __('Vesper — Account Recovery Link'),
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
