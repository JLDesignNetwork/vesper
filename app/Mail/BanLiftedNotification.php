<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a member that a vote lifted their ban and how to sign in again.
 */
class BanLiftedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient
    ) {}

    /**
     * Render the ban_lifted template in the member's language.
     *
     * @return array<string, mixed>
     */
    public function getRendered(): array
    {
        $locale = $this->recipient->preferred_locale ?: app()->getLocale();

        return app(EmailTemplateService::class)->render('ban_lifted', [
            'member_name' => $this->recipient->name,
            'login_url' => route('login'),
        ], $locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->getRendered()['subject'],
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->getRendered()['rendered_html'],
        );
    }
}
