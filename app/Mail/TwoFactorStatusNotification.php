<?php

namespace App\Mail;

use App\Models\User;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TwoFactorStatusNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $actionType, // 'enabled' or 'disabled'
        public ?string $ipAddress = null
    ) {}

    public function getRendered(): array
    {
        $locale = $this->user->preferred_locale ?: app()->getLocale();

        return app(EmailTemplateService::class)->render('two_factor_status', [
            'operative_name' => $this->user->name,
            'action_type' => $this->actionType,
            'ip_address' => $this->ipAddress ?: request()->ip() ?: 'Unknown IP',
            'timestamp' => now()->toIso8601String(),
            'profile_url' => route('channels.index'),
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
