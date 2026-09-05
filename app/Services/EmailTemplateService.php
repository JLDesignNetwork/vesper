<?php

namespace App\Services;

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EmailTemplateService
{
    public function __construct(
        protected TranslationService $translator
    ) {}

    /**
     * Master registry of all 9 tactical email templates.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDefinitions(): array
    {
        return [
            'registration_welcome' => [
                'name' => 'Operative Welcome & Enrollment',
                'category' => 'Authentication',
                'description' => 'Dispatched when an operative registers or is onboarded into Vesper.',
                'subject' => 'Welcome to Vesper — Identity Clearance & Next Steps',
                'preheader' => 'Your encrypted communications profile has been established.',
                'body_markdown' => "Hello **{{operative_name}}**,\n\nWelcome to the **Vesper** private communications network. Your operative clearance has been initialized under identity **{{email}}** with clearance level **{{clearance_level}}**.\n\n### Critical Next Steps:\n1. Verify your operational profile credentials.\n2. Configure hardware biometrics (Touch ID / Face ID / Passkeys) or an Authenticator App (2FA) for Level 5 defense.\n3. Keep your communications strictly compartmentalized across assigned frequency rooms.",
                'button_text' => 'Access Command Center',
                'button_color' => 'success',
                'footer_text' => 'Vesper Private Communications • Ghostwire Protocol • Confidential',
                'variables' => [
                    'operative_name' => 'Full name or callsign of the operative',
                    'email' => 'Registered primary email address',
                    'clearance_level' => 'Assigned security clearance level (e.g. Level 5)',
                    'login_url' => 'Secure access portal URL',
                ],
                'sample_data' => [
                    'operative_name' => 'Agent Sterling',
                    'email' => 'sterling@vesper.network',
                    'clearance_level' => 'Level 5 Clearance',
                    'login_url' => config('app.url', 'https://vesper.jldn').'/login',
                ],
            ],

            'emergency_recovery' => [
                'name' => 'Emergency Account Recovery',
                'category' => 'Authentication',
                'description' => 'Dispatched to verified secondary recovery email during emergency access.',
                'subject' => 'Emergency Account Recovery Request — Single-Use Entry Token',
                'preheader' => 'An emergency recovery request was initiated for your Vesper identity.',
                'body_markdown' => "Hello **{{operative_name}}**,\n\nAn emergency account recovery request was initiated for your **Vesper** identity from IP node `{{ip_address}}`.\n\nClicking the authorization button below will allow you to reset your access credentials and restore operational clearance. This single-use emergency link will expire in **{{expiry_minutes}} minutes**.\n\n> **Security Warning:** If you did not initiate this recovery request, your primary identity may be compromised. Lock down your account immediately.",
                'button_text' => 'Reset Credentials & Access Account',
                'button_color' => 'error',
                'footer_text' => 'Vesper Security Operations • Emergency Access Terminal',
                'variables' => [
                    'operative_name' => 'Name or callsign of the operative',
                    'ip_address' => 'IP node origin of the request',
                    'expiry_minutes' => 'Token validity lifespan in minutes',
                    'reset_url' => 'Single-use cryptographic recovery URL',
                ],
                'sample_data' => [
                    'operative_name' => 'Agent Sterling',
                    'ip_address' => '192.0.2.148',
                    'expiry_minutes' => '15',
                    'reset_url' => config('app.url', 'https://vesper.jldn').'/recovery/reset/sample-token-abc123xyz',
                ],
            ],

            'recovery_verification' => [
                'name' => 'Secondary Recovery Email Verification',
                'category' => 'Authentication',
                'description' => 'Dispatched to confirm and lock in a secondary emergency backup email address.',
                'subject' => 'Confirm Secondary Recovery Email Verification',
                'preheader' => 'Authorize this backup recovery channel for your Vesper account.',
                'body_markdown' => "Hello **{{operative_name}}**,\n\nYou recently designated this address (**{{recovery_email}}**) as the official secondary emergency recovery email for your **Vesper** identity.\n\nBefore emergency password resets or high-priority security override tokens can be dispatched to this mailbox, you must verify ownership by confirming the authorization link below.\n\nThis verification link expires in **{{expiry_time}}**.",
                'button_text' => 'Verify Secondary Recovery Channel',
                'button_color' => 'cyan',
                'footer_text' => 'Vesper Cryptographic Defense • Account Recovery Pipeline',
                'variables' => [
                    'operative_name' => 'Name or callsign of the operative',
                    'recovery_email' => 'Target secondary recovery address',
                    'expiry_time' => 'Validity window (e.g. 60 minutes)',
                    'verification_url' => 'Cryptographic signed verification URL',
                ],
                'sample_data' => [
                    'operative_name' => 'Agent Sterling',
                    'recovery_email' => 'sterling.backup@protonmail.com',
                    'expiry_time' => '60 minutes',
                    'verification_url' => config('app.url', 'https://vesper.jldn').'/recovery/email/verify/1/sample-hash',
                ],
            ],

            'new_message' => [
                'name' => 'New Transmission Notification',
                'category' => 'Operations',
                'description' => 'Dispatched when new traffic or attachments are transmitted in an active channel.',
                'subject' => 'New Encrypted Transmission in [{{channel_code}}] {{channel_title}}',
                'preheader' => 'A new transmission has been posted by {{sender_alias}}.',
                'body_markdown' => "Hello **{{recipient_name}}**,\n\nA new encrypted transmission was dispatched to channel **{{channel_title}}** (`{{channel_code}}`) by operative **{{sender_alias}}**.\n\n### Transmission Payload Preview:\n\"{{message_preview}}\"\n\n{{attachment_info}}",
                'button_text' => 'Open Secure Channel',
                'button_color' => 'success',
                'footer_text' => 'Vesper Private Communications • End-to-End Frequency Dispatch',
                'variables' => [
                    'recipient_name' => 'Name of the recipient operative',
                    'sender_alias' => 'Callsign/Alias of the sender',
                    'channel_title' => 'Title of the frequency room',
                    'channel_code' => 'Unique uppercase room code (e.g. VIP-99)',
                    'message_preview' => 'Text snippet of the transmission',
                    'attachment_info' => 'Attachment metadata (if present)',
                    'channel_url' => 'Direct link to enter channel',
                ],
                'sample_data' => [
                    'recipient_name' => 'Agent Sterling',
                    'sender_alias' => 'Ghostwire-01',
                    'channel_title' => 'Executive Strategic Room',
                    'channel_code' => 'ALPHA-09',
                    'message_preview' => 'Coordinates confirmed. Satellite telemetry indicates green across all designated checkpoints.',
                    'attachment_info' => '📎 Attachment: reconnaissance_briefing.pdf (1.4 MB)',
                    'channel_url' => config('app.url', 'https://vesper.jldn').'/c/ALPHA-09',
                ],
            ],

            'channel_invitation' => [
                'name' => 'Channel Clearance & Direct Invitation',
                'category' => 'Channels',
                'description' => 'Dispatched when an operative is granted clearance to an exclusive frequency channel.',
                'subject' => 'Channel Clearance Granted — Frequency [{{channel_code}}]',
                'preheader' => 'You have been granted access clearance to {{channel_title}}.',
                'body_markdown' => "Hello **{{operative_name}}**,\n\nOperative **{{inviter_name}}** has granted you security clearance to join private frequency room **{{channel_title}}** (`{{channel_code}}`).\n\n### Clearance Parameters:\n- **Channel Code:** `{{channel_code}}`\n- **Authorization PIN:** `{{pin_code}}`\n- **Access Expiration:** {{expires_in}}\n\nEnter the frequency immediately using the direct clearance token below.",
                'button_text' => 'Enter Encrypted Frequency',
                'button_color' => 'cyan',
                'footer_text' => 'Vesper Frequency Directory • Discreet Communications Network',
                'variables' => [
                    'operative_name' => 'Invited operative name',
                    'inviter_name' => 'Callsign of operative issuing invitation',
                    'channel_title' => 'Channel title',
                    'channel_code' => 'Channel code',
                    'pin_code' => 'Zero-discovery PIN code',
                    'expires_in' => 'Clearance token duration',
                    'access_url' => 'Direct authenticated access link',
                ],
                'sample_data' => [
                    'operative_name' => 'Agent Sterling',
                    'inviter_name' => 'Commander Grant',
                    'channel_title' => 'Tactical Operations Grid',
                    'channel_code' => 'TAC-44',
                    'pin_code' => '784920',
                    'expires_in' => '24 Hours',
                    'access_url' => config('app.url', 'https://vesper.jldn').'/c/TAC-44?pin=784920',
                ],
            ],

            'security_alert' => [
                'name' => 'Security Alert & Credential Mutation',
                'category' => 'Security',
                'description' => 'Dispatched when high-security events occur on an operative account.',
                'subject' => 'Security Alert — {{event_title}} on Your Vesper Account',
                'preheader' => 'Critical security notice regarding your authentication profile.',
                'body_markdown' => "Hello **{{operative_name}}**,\n\nA high-priority security mutation was executed on your **Vesper** account profile.\n\n### Incident Details:\n- **Event Type:** {{event_title}}\n- **Origin IP:** `{{ip_address}}`\n- **Client Device:** {{user_agent}}\n- **Timestamp:** {{timestamp}}\n\nIf you authorized this action, no further steps are required. If you did **NOT** execute this change, lock down your profile immediately using the emergency security protocol below.",
                'button_text' => 'Inspect Security Credentials',
                'button_color' => 'error',
                'footer_text' => 'Vesper Automated Security Monitor • Incident Dispatch Protocol',
                'variables' => [
                    'operative_name' => 'Name or callsign of operative',
                    'event_title' => 'Type of security event (e.g. Password Changed, 2FA Deactivated)',
                    'ip_address' => 'IP node origin',
                    'user_agent' => 'Browser and operating system string',
                    'timestamp' => 'Time of occurrence',
                    'lockdown_url' => 'Emergency account lockdown link',
                ],
                'sample_data' => [
                    'operative_name' => 'Agent Sterling',
                    'event_title' => 'Two-Factor Authentication Disabled',
                    'ip_address' => '198.51.100.22',
                    'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6)',
                    'timestamp' => now()->toFormattedDateString().' '.now()->toTimeString().' UTC',
                    'lockdown_url' => config('app.url', 'https://vesper.jldn').'/login',
                ],
            ],

            'two_factor_status' => [
                'name' => '2FA & Biometric Defense Update',
                'category' => 'Security',
                'description' => 'Dispatched when multi-factor authentication or hardware passkeys are updated.',
                'subject' => 'Defense Status Update: {{auth_method}} {{status_action}}',
                'preheader' => 'Cryptographic defense status has been updated for your account.',
                'body_markdown' => "Hello **{{operative_name}}**,\n\nYour account defense state has been updated. **{{auth_method}}** has been **{{status_action}}** on device **{{device_name}}**.\n\n### Defense Posture Recommendations:\n- Store your single-use emergency backup recovery codes in an encrypted offline safe.\n- Periodically audit enrolled biometric passkeys to prune decommissioned hardware.",
                'button_text' => 'View Defense Posture',
                'button_color' => 'success',
                'footer_text' => 'Vesper Cryptographic Armor • Hardware WebAuthn & TOTP Engine',
                'variables' => [
                    'operative_name' => 'Name or callsign of operative',
                    'auth_method' => 'Authentication protocol (e.g. Touch ID Biometrics, TOTP 2FA)',
                    'status_action' => 'Enrolled / Activated / Revoked',
                    'device_name' => 'Device descriptor label',
                    'timestamp' => 'Timestamp of change',
                    'profile_url' => 'Account profile security settings URL',
                ],
                'sample_data' => [
                    'operative_name' => 'Agent Sterling',
                    'auth_method' => 'Hardware Passkey (Touch ID)',
                    'status_action' => 'Enrolled & Verified',
                    'device_name' => 'MacBook Pro M3 Max',
                    'timestamp' => now()->toFormattedDateString().' UTC',
                    'profile_url' => config('app.url', 'https://vesper.jldn').'/admin',
                ],
            ],

            'channel_burn_warning' => [
                'name' => 'Imminent Channel Self-Destruct Warning',
                'category' => 'Channels',
                'description' => 'Dispatched before an ephemeral frequency room expires and purges its data.',
                'subject' => 'Notice: Channel [{{channel_code}}] Expires in {{time_remaining}}',
                'preheader' => 'Frequency {{channel_code}} will self-destruct shortly.',
                'body_markdown' => "Hello **{{operative_name}}**,\n\nThe ephemeral frequency room **{{channel_title}}** (`{{channel_code}}`) has entered its final operational window.\n\n### Expiration Countdown:\n- **Time Remaining:** {{time_remaining}}\n- **Lifespan Status:** Purge Imminent\n\nOnce the countdown expires, all transmissions and shared intelligence payloads in this frequency will be permanently erased. Review or export necessary notes before the channel is archived.",
                'button_text' => 'Enter Frequency Room',
                'button_color' => 'amber',
                'footer_text' => 'Vesper Ghostwire Protocol • Ephemeral Frequency Management',
                'variables' => [
                    'operative_name' => 'Name or callsign of operative',
                    'channel_title' => 'Channel title',
                    'channel_code' => 'Room code',
                    'time_remaining' => 'Remaining duration (e.g. 60 minutes)',
                    'channel_url' => 'Direct channel link',
                ],
                'sample_data' => [
                    'operative_name' => 'Agent Sterling',
                    'channel_title' => 'Temporary Tactical Briefing',
                    'channel_code' => 'BURN-07',
                    'time_remaining' => '60 minutes',
                    'channel_url' => config('app.url', 'https://vesper.jldn').'/c/BURN-07',
                ],
            ],

            'admin_operations_digest' => [
                'name' => 'Executive Intelligence Digest',
                'category' => 'System',
                'description' => 'Dispatched periodically to platform administrators summarizing telemetry.',
                'subject' => 'Executive Intelligence Digest — Vesper Network Telemetry',
                'preheader' => 'Weekly operational telemetry and network traffic report.',
                'body_markdown' => "Commander **{{admin_name}}**,\n\nHere is your operational network briefing covering recent Vesper telemetry:\n\n### Network Metrics:\n- **Active Frequency Channels:** {{active_channels}}\n- **Registered Operatives:** {{total_operatives}}\n- **Dispatched Transmissions:** {{messages_count}}\n- **Ingress Visitors Tracked:** {{visitors_count}}\n- **Security Alerts Raised:** {{security_alerts_count}}\n\nAll encrypted Ghostwire relays are functioning within normal operational parameters.",
                'button_text' => 'Access Command Console',
                'button_color' => 'success',
                'footer_text' => 'Vesper Command Console • Internal Telemetry Dispatch',
                'variables' => [
                    'admin_name' => 'Name of administrator',
                    'active_channels' => 'Count of active channels',
                    'total_operatives' => 'Total registered operatives',
                    'messages_count' => 'Total transmissions sent',
                    'visitors_count' => 'Tracked visitor ingress count',
                    'security_alerts_count' => 'Number of security incidents',
                    'admin_url' => 'Direct link to Admin Dashboard',
                ],
                'sample_data' => [
                    'admin_name' => 'Commander Grant',
                    'active_channels' => '12',
                    'total_operatives' => '48',
                    'messages_count' => '1,420',
                    'visitors_count' => '392',
                    'security_alerts_count' => '0',
                    'admin_url' => config('app.url', 'https://vesper.jldn').'/admin',
                ],
            ],
        ];
    }

    /**
     * Retrieve an email template for a key and locale, with graceful fallback.
     */
    public function get(string $key, string $locale = 'en'): array
    {
        $definitions = $this->getDefinitions();
        $def = $definitions[$key] ?? null;

        if (! $def) {
            throw new \InvalidArgumentException("Unknown email template key: {$key}");
        }

        // 1. Try to find customized template in database for specified locale
        $custom = EmailTemplate::where('key', $key)->where('locale', $locale)->first();

        // 2. If not found and locale isn't English, fallback to English customization
        if (! $custom && $locale !== 'en') {
            $custom = EmailTemplate::where('key', $key)->where('locale', 'en')->first();
        }

        if ($custom) {
            return array_merge($def, [
                'id' => $custom->id,
                'key' => $custom->key,
                'locale' => $custom->locale,
                'name' => $custom->name ?: $def['name'],
                'category' => $custom->category ?: $def['category'],
                'subject' => $custom->subject ?: $def['subject'],
                'preheader' => $custom->preheader ?? $def['preheader'],
                'body_markdown' => $custom->body_markdown ?: $def['body_markdown'],
                'button_text' => $custom->button_text ?? $def['button_text'],
                'button_color' => $custom->button_color ?: $def['button_color'],
                'footer_text' => $custom->footer_text ?? $def['footer_text'],
                'is_custom' => true,
                'updated_at' => $custom->updated_at,
            ]);
        }

        // 3. Fallback to hardcoded factory preset
        return array_merge($def, [
            'id' => null,
            'key' => $key,
            'locale' => $locale,
            'is_custom' => false,
            'updated_at' => null,
        ]);
    }

    /**
     * Replace dynamic variables and compile Markdown body to HTML.
     *
     * @param  array<string, string>  $variables
     * @return array{
     *     subject: string,
     *     preheader: string|null,
     *     body_html: string,
     *     button_text: string|null,
     *     button_color: string,
     *     footer_text: string|null,
     *     rendered_html: string
     * }
     */
    public function render(string $key, array $variables, string $locale = 'en', ?string $buttonUrl = null): array
    {
        $template = $this->get($key, $locale);

        $subject = $this->interpolate($template['subject'], $variables);
        $preheader = ! empty($template['preheader']) ? $this->interpolate($template['preheader'], $variables) : null;
        $bodyMarkdown = $this->interpolate($template['body_markdown'], $variables);
        $buttonText = ! empty($template['button_text']) ? $this->interpolate($template['button_text'], $variables) : null;
        $footerText = ! empty($template['footer_text']) ? $this->interpolate($template['footer_text'], $variables) : null;
        $buttonColor = $template['button_color'] ?? 'success';

        // Convert markdown to clean HTML
        $bodyHtml = Str::markdown($bodyMarkdown);

        // Render full luxury Vesper email layout
        $renderedHtml = $this->renderFullEmailHtml(
            subject: $subject,
            preheader: $preheader,
            bodyHtml: $bodyHtml,
            buttonText: $buttonText,
            buttonUrl: $buttonUrl ?? ($variables['action_url'] ?? ($variables['reset_url'] ?? ($variables['verification_url'] ?? ($variables['channel_url'] ?? ($variables['login_url'] ?? ($variables['admin_url'] ?? '#')))))),
            buttonColor: $buttonColor,
            footerText: $footerText
        );

        return [
            'subject' => $subject,
            'preheader' => $preheader,
            'body_html' => $bodyHtml,
            'button_text' => $buttonText,
            'button_color' => $buttonColor,
            'footer_text' => $footerText,
            'rendered_html' => $renderedHtml,
        ];
    }

    /**
     * Interpolate {{variable}} tokens into a string.
     *
     * @param  array<string, string>  $variables
     */
    public function interpolate(string $text, array $variables): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($matches) use ($variables) {
            $key = $matches[1];

            return array_key_exists($key, $variables) ? (string) $variables[$key] : $matches[0];
        }, $text);
    }

    /**
     * Auto-translate an English template into Italian, French, and Russian while protecting dynamic variable placeholders.
     *
     * @param  array<string>  $targetLocales
     * @return array{
     *     success: bool,
     *     translated_locales: array<string>,
     *     errors: array<string>
     * }
     */
    public function autoTranslate(string $key, string $fromLocale = 'en', array $targetLocales = ['it', 'fr', 'ru'], ?int $userId = null): array
    {
        $source = $this->get($key, $fromLocale);
        $translatedLocales = [];
        $errors = [];

        foreach ($targetLocales as $targetLocale) {
            if ($targetLocale === $fromLocale) {
                continue;
            }

            try {
                $translatedSubject = $this->translateWithProtectedVariables($source['subject'], $targetLocale);
                $translatedPreheader = ! empty($source['preheader']) ? $this->translateWithProtectedVariables($source['preheader'], $targetLocale) : null;
                $translatedBody = $this->translateWithProtectedVariables($source['body_markdown'], $targetLocale);
                $translatedButton = ! empty($source['button_text']) ? $this->translateWithProtectedVariables($source['button_text'], $targetLocale) : null;
                $translatedFooter = ! empty($source['footer_text']) ? $this->translateWithProtectedVariables($source['footer_text'], $targetLocale) : null;

                EmailTemplate::updateOrCreate(
                    ['key' => $key, 'locale' => $targetLocale],
                    [
                        'name' => $source['name'],
                        'category' => $source['category'],
                        'subject' => $translatedSubject,
                        'preheader' => $translatedPreheader,
                        'body_markdown' => $translatedBody,
                        'button_text' => $translatedButton,
                        'button_color' => $source['button_color'] ?? 'success',
                        'footer_text' => $translatedFooter,
                        'updated_by_user_id' => $userId ?: Auth::id(),
                    ]
                );

                $translatedLocales[] = $targetLocale;
            } catch (\Throwable $e) {
                $errors[] = "Failed translating to {$targetLocale}: {$e->getMessage()}";
            }
        }

        return [
            'success' => count($translatedLocales) > 0,
            'translated_locales' => $translatedLocales,
            'errors' => $errors,
        ];
    }

    /**
     * Protect dynamic placeholders like {{operative_name}} with safe tokens during translation, then restore them.
     */
    public function translateWithProtectedVariables(string $text, string $targetLocale): string
    {
        if (trim($text) === '') {
            return '';
        }

        $tokens = [];
        $index = 0;

        // Replace {{variable}} with safe tokens: __VAR_0__, __VAR_1__, etc.
        $protectedText = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($matches) use (&$tokens, &$index) {
            $tokenPlaceholder = "__VAR_{$index}__";
            $tokens[$tokenPlaceholder] = $matches[0];
            $index++;

            return $tokenPlaceholder;
        }, $text);

        // Translate the masked string
        $translationResult = $this->translator->translate($protectedText, $targetLocale);
        $translatedText = $translationResult['translated_text'] ?? $protectedText;

        // Restore original {{variable}} tags
        foreach ($tokens as $tokenPlaceholder => $originalTag) {
            // Match with possible whitespace injected by translation API
            $translatedText = preg_replace('/__\s*VAR_'.explode('_', $tokenPlaceholder)[2].'\s*__/', $originalTag, $translatedText);
            // Also direct replace if clean
            $translatedText = str_replace($tokenPlaceholder, $originalTag, $translatedText);
        }

        return $translatedText;
    }

    /**
     * Render the complete HTML email body with Vesper's dark/luxury aesthetic.
     */
    public function renderFullEmailHtml(
        string $subject,
        ?string $preheader,
        string $bodyHtml,
        ?string $buttonText,
        string $buttonUrl,
        string $buttonColor = 'success',
        ?string $footerText = null
    ): string {
        $btnBg = match ($buttonColor) {
            'cyan' => '#0891b2',
            'error', 'rose' => '#e11d48',
            'amber' => '#d97706',
            default => '#059669',
        };

        $btnHover = match ($buttonColor) {
            'cyan' => '#06b6d4',
            'error', 'rose' => '#f43f5e',
            'amber' => '#f59e0b',
            default => '#10b981',
        };

        $preheaderHtml = $preheader ? "<span style=\"display:none;font-size:1px;color:#0b0f17;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;\">{$preheader}</span>" : '';

        $buttonHtml = '';
        if ($buttonText && $buttonUrl) {
            $buttonHtml = <<<HTML
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin: 28px 0 20px 0;">
                <tr>
                    <td align="center">
                        <a href="{$buttonUrl}" target="_blank" style="display: inline-block; padding: 12px 28px; background-color: {$btnBg}; color: #ffffff; text-decoration: none; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 14px; font-weight: 600; letter-spacing: 0.025em; border-radius: 12px; box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4);">
                            {$buttonText}
                        </a>
                    </td>
                </tr>
            </table>
HTML;
        }

        $footerContent = $footerText ?: 'Vesper Private Communications • Ghostwire Protocol • Encrypted Intelligence';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #0b0f17; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #cbd5e1; }
        a { color: #34d399; }
        blockquote { border-left: 3px solid #10b981; margin: 16px 0; padding: 8px 16px; background: #0f172a; border-radius: 0 8px 8px 0; color: #94a3b8; }
        code { background: #0f172a; padding: 2px 6px; border-radius: 4px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 13px; color: #38bdf8; }
        h1, h2, h3 { color: #ffffff; margin-top: 0; font-weight: 600; }
        p { line-height: 1.6; margin: 12px 0; font-size: 14px; }
        ul, ol { padding-left: 20px; line-height: 1.6; font-size: 14px; }
    </style>
</head>
<body style="margin: 0; padding: 24px 12px; background-color: #0b0f17;">
    {$preheaderHtml}
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 580px; background-color: #0f172a; border: 1px solid #1e293b; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.6);">
                    <!-- Brand Banner -->
                    <tr>
                        <td style="padding: 24px 32px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(6, 182, 212, 0.08)); border-bottom: 1px solid #1e293b;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td>
                                        <div style="font-size: 18px; font-weight: 700; color: #ffffff; letter-spacing: -0.025em; display: inline-flex; align-items: center; gap: 8px;">
                                            <span>VESPER</span>
                                            <span style="font-size: 10px; font-family: ui-monospace, monospace; padding: 2px 6px; border-radius: 4px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; text-transform: uppercase; margin-left: 6px;">GHOSTWIRE</span>
                                        </div>
                                        <div style="font-size: 11px; font-family: ui-monospace, monospace; color: #64748b; margin-top: 4px;">
                                            ENCRYPTED COMMUNICATIONS PROTOCOL
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Main Body -->
                    <tr>
                        <td style="padding: 32px 32px 24px 32px;">
                            <h2 style="font-size: 20px; color: #ffffff; margin-bottom: 18px; font-weight: 600; letter-spacing: -0.015em;">{$subject}</h2>
                            <div style="color: #cbd5e1; font-size: 14px; line-height: 1.65;">
                                {$bodyHtml}
                            </div>
                            {$buttonHtml}
                        </td>
                    </tr>
                    <!-- Security Footer -->
                    <tr>
                        <td style="padding: 20px 32px; background-color: #090d16; border-top: 1px solid #1e293b; text-align: center;">
                            <p style="margin: 0; font-size: 11px; color: #64748b; font-family: ui-monospace, monospace; line-height: 1.5;">
                                {$footerContent}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
