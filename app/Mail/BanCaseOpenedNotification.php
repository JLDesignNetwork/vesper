<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\BanCase;
use App\Models\User;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells an Admin I or II voter that a ban case is waiting for their ballot.
 */
class BanCaseOpenedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public BanCase $banCase,
        public User $recipient
    ) {}

    /**
     * Render the ban_case_opened template in the voter's language.
     *
     * @return array<string, mixed>
     */
    public function getRendered(): array
    {
        $locale = $this->recipient->preferred_locale ?: app()->getLocale();

        return app(EmailTemplateService::class)->render('ban_case_opened', [
            'voter_name' => $this->recipient->name,
            'subject_name' => $this->banCase->subject?->name ?? '',
            'subject_tag' => $this->banCase->subject?->tag ?? '',
            'opened_by' => $this->banCase->openedBy?->name ?? '',
            'reason' => $this->banCase->reason,
            'closes_at' => $this->banCase->closes_at->toDayDateTimeString().' UTC',
            'action_url' => route('moderation.bans.index'),
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
