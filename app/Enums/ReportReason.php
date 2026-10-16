<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a member reported someone.
 */
enum ReportReason: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case Impersonation = 'impersonation';
    case InappropriateContent = 'inappropriate_content';
    case Other = 'other';

    /**
     * The reason as shown to the reporter.
     */
    public function label(): string
    {
        return match ($this) {
            self::Spam => __('Spam'),
            self::Harassment => __('Harassment'),
            self::Impersonation => __('Impersonation'),
            self::InappropriateContent => __('Inappropriate content'),
            self::Other => __('Something else'),
        };
    }
}
