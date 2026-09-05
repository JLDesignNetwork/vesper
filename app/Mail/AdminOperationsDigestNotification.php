<?php

namespace App\Mail;

use App\Models\User;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminOperationsDigestNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $admin,
        public array $digestStats = []
    ) {}

    public function getRendered(): array
    {
        $locale = $this->admin->preferred_locale ?: app()->getLocale();

        return app(EmailTemplateService::class)->render('admin_operations_digest', [
            'operative_name' => $this->admin->name,
            'active_channels_count' => (string) ($this->digestStats['active_channels_count'] ?? '0'),
            'total_operatives_count' => (string) ($this->digestStats['total_operatives_count'] ?? '0'),
            'total_messages_count' => (string) ($this->digestStats['total_messages_count'] ?? '0'),
            'security_incidents_count' => (string) ($this->digestStats['security_incidents_count'] ?? '0'),
            'admin_dashboard_url' => route('admin.dashboard'),
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
