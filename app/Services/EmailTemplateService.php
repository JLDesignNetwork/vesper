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
                'name' => 'Member Welcome & Setup',
                'category' => 'Authentication',
                'description' => 'Dispatched when a new member registers or is onboarded into Vesper.',
                'subject' => 'Welcome to Vesper — Getting Started with Secure Communications',
                'preheader' => 'Your secure private communications account is ready.',
                'body_markdown' => "Hello **{{member_name}}**,\n\nWelcome to **Vesper**, your private communications platform. Your member account has been created under **{{email}}** with role **{{role}}**.\n\n### Next Steps:\n1. Complete your account profile.\n2. Configure Two-Factor Authentication (Touch ID / Face ID / Authenticator App) for enhanced account security.\n3. Join or create private encrypted channels with your team.",
                'button_text' => 'Access Dashboard',
                'button_color' => 'success',
                'footer_text' => 'Vesper Private Communications • Confidential',
                'variables' => [
                    'member_name' => 'Full name or display name of the member',
                    'email' => 'Registered primary email address',
                    'role' => 'Assigned role (e.g. Member or Administrator)',
                    'login_url' => 'Secure access portal URL',
                ],
                'sample_data' => [
                    'member_name' => 'Alex Sterling',
                    'email' => 'alex@vesper.network',
                    'role' => 'Member',
                    'login_url' => config('app.url', 'https://vesper.jldn').'/login',
                ],
            ],

            'emergency_recovery' => [
                'name' => 'Emergency Account Recovery',
                'category' => 'Authentication',
                'description' => 'Dispatched to verified secondary recovery email during emergency access.',
                'subject' => 'Account Recovery Request — Single-Use Link',
                'preheader' => 'An account recovery request was initiated for your Vesper account.',
                'body_markdown' => "Hello **{{member_name}}**,\n\nAn account recovery request was initiated for your **Vesper** account from IP address `{{ip_address}}`.\n\nClicking the authorization button below will allow you to reset your access credentials and restore account access. This single-use link will expire in **{{expiry_minutes}} minutes**.\n\n> **Security Notice:** If you did not initiate this recovery request, your account credentials may be compromised. Please secure your account immediately.",
                'button_text' => 'Reset Password & Access Account',
                'button_color' => 'error',
                'footer_text' => 'Vesper Security Operations • Account Recovery',
                'variables' => [
                    'member_name' => 'Name or display name of the member',
                    'ip_address' => 'IP address origin of the request',
                    'expiry_minutes' => 'Link validity lifespan in minutes',
                    'reset_url' => 'Single-use cryptographic recovery URL',
                ],
                'sample_data' => [
                    'member_name' => 'Alex Sterling',
                    'ip_address' => '192.0.2.148',
                    'expiry_minutes' => '15',
                    'reset_url' => config('app.url', 'https://vesper.jldn').'/recovery/reset/sample-token-abc123xyz',
                ],
            ],

            'recovery_verification' => [
                'name' => 'Secondary Recovery Email Verification',
                'category' => 'Authentication',
                'description' => 'Dispatched to confirm a secondary emergency backup email address.',
                'subject' => 'Confirm Secondary Recovery Email Verification',
                'preheader' => 'Verify backup recovery address for your Vesper account.',
                'body_markdown' => "Hello **{{member_name}}**,\n\nYou recently designated this address (**{{recovery_email}}**) as the backup recovery email for your **Vesper** account.\n\nBefore emergency password resets or security notifications can be sent to this mailbox, please verify ownership by confirming the authorization link below.\n\nThis verification link expires in **{{expiry_time}}**.",
                'button_text' => 'Verify Backup Recovery Email',
                'button_color' => 'cyan',
                'footer_text' => 'Vesper Security • Account Recovery Pipeline',
                'variables' => [
                    'member_name' => 'Name or display name of the member',
                    'recovery_email' => 'Target secondary recovery address',
                    'expiry_time' => 'Validity window (e.g. 60 minutes)',
                    'verification_url' => 'Cryptographic signed verification URL',
                ],
                'sample_data' => [
                    'member_name' => 'Alex Sterling',
                    'recovery_email' => 'alex.backup@example.com',
                    'expiry_time' => '60 minutes',
                    'verification_url' => config('app.url', 'https://vesper.jldn').'/recovery/email/verify/1/sample-hash',
                ],
            ],

            'new_message' => [
                'name' => 'New Message Notification',
                'category' => 'Operations',
                'description' => 'Dispatched when new messages or attachments are transmitted in an active channel.',
                'subject' => 'New Message in [{{channel_code}}] {{channel_title}}',
                'preheader' => 'A new message has been posted by {{sender_alias}}.',
                'body_markdown' => "Hello **{{recipient_name}}**,\n\nA new message was posted to channel **{{channel_title}}** (`{{channel_code}}`) by **{{sender_alias}}**.\n\n### Message Preview:\n\"{{message_preview}}\"\n\n{{attachment_info}}",
                'button_text' => 'Open Secure Channel',
                'button_color' => 'success',
                'footer_text' => 'Vesper Private Communications • Secure Notifications',
                'variables' => [
                    'recipient_name' => 'Name of the recipient member',
                    'sender_alias' => 'Display name or alias of the sender',
                    'channel_title' => 'Title of the channel',
                    'channel_code' => 'Unique uppercase room code (e.g. VIP-99)',
                    'message_preview' => 'Text snippet of the message',
                    'attachment_info' => 'Attachment metadata (if present)',
                    'channel_url' => 'Direct link to enter channel',
                ],
                'sample_data' => [
                    'recipient_name' => 'Alex Sterling',
                    'sender_alias' => 'Alex Sterling',
                    'channel_title' => 'Executive Strategy Room',
                    'channel_code' => 'ALPHA-09',
                    'message_preview' => 'The updated quarterly metrics and strategic roadmap have been uploaded for review.',
                    'attachment_info' => '📎 Attachment: executive_summary.pdf (1.4 MB)',
                    'channel_url' => config('app.url', 'https://vesper.jldn').'/c/ALPHA-09',
                ],
            ],

            'channel_invitation' => [
                'name' => 'Channel Invitation',
                'category' => 'Channels',
                'description' => 'Dispatched when a member is invited to join a private channel.',
                'subject' => 'Channel Invitation — [{{channel_code}}] {{channel_title}}',
                'preheader' => 'You have been invited to join {{channel_title}}.',
                'body_markdown' => "Hello **{{member_name}}**,\n\n**{{inviter_name}}** has invited you to join the private channel **{{channel_title}}** (`{{channel_code}}`).\n\n### Channel Details:\n- **Channel Code:** `{{channel_code}}`\n- **Access PIN:** `{{pin_code}}`\n- **Invitation Expiration:** {{expires_in}}\n\nEnter the channel using the link below.",
                'button_text' => 'Enter Secure Channel',
                'button_color' => 'cyan',
                'footer_text' => 'Vesper Private Communications • Channel Management',
                'variables' => [
                    'member_name' => 'Invited member name',
                    'inviter_name' => 'Name of member issuing invitation',
                    'channel_title' => 'Channel title',
                    'channel_code' => 'Channel code',
                    'pin_code' => 'Access PIN code',
                    'expires_in' => 'Invitation token duration',
                    'access_url' => 'Direct authenticated access link',
                ],
                'sample_data' => [
                    'member_name' => 'Alex Sterling',
                    'inviter_name' => 'Grant Morgan',
                    'channel_title' => 'Corporate Strategy',
                    'channel_code' => 'STRAT-44',
                    'pin_code' => '784920',
                    'expires_in' => '24 Hours',
                    'access_url' => config('app.url', 'https://vesper.jldn').'/c/STRAT-44?pin=784920',
                ],
            ],

            'security_alert' => [
                'name' => 'Security Alert',
                'category' => 'Security',
                'description' => 'Dispatched when sensitive security changes occur on a member account.',
                'subject' => 'Security Alert — {{event_title}} on Your Vesper Account',
                'preheader' => 'Important security notice regarding your account.',
                'body_markdown' => "Hello **{{member_name}}**,\n\nA security update was executed on your **Vesper** account profile.\n\n### Incident Details:\n- **Event Type:** {{event_title}}\n- **Origin IP:** `{{ip_address}}`\n- **Client Device:** {{user_agent}}\n- **Timestamp:** {{timestamp}}\n\nIf you authorized this action, no further steps are required. If you did **NOT** execute this change, please sign in immediately and update your credentials.",
                'button_text' => 'Review Security Settings',
                'button_color' => 'error',
                'footer_text' => 'Vesper Security Operations • Automated Alert',
                'variables' => [
                    'member_name' => 'Name or display name of member',
                    'event_title' => 'Type of security event (e.g. Password Changed, 2FA Deactivated)',
                    'ip_address' => 'IP address origin',
                    'user_agent' => 'Browser and operating system string',
                    'timestamp' => 'Time of occurrence',
                    'lockdown_url' => 'Account security link',
                ],
                'sample_data' => [
                    'member_name' => 'Alex Sterling',
                    'event_title' => 'Two-Factor Authentication Disabled',
                    'ip_address' => '198.51.100.22',
                    'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6)',
                    'timestamp' => now()->toFormattedDateString().' '.now()->toTimeString().' UTC',
                    'lockdown_url' => config('app.url', 'https://vesper.jldn').'/login',
                ],
            ],

            'two_factor_status' => [
                'name' => 'Two-Factor Authentication Update',
                'category' => 'Security',
                'description' => 'Dispatched when multi-factor authentication or hardware passkeys are updated.',
                'subject' => 'Security Update: {{auth_method}} {{status_action}}',
                'preheader' => 'Two-Factor authentication status has been updated for your account.',
                'body_markdown' => "Hello **{{member_name}}**,\n\nYour account security settings have been updated. **{{auth_method}}** has been **{{status_action}}** on device **{{device_name}}**.\n\n### Security Recommendations:\n- Store your backup recovery codes in a secure password manager.\n- Periodically review active devices and security credentials.",
                'button_text' => 'View Security Settings',
                'button_color' => 'success',
                'footer_text' => 'Vesper Security • Multi-Factor Authentication',
                'variables' => [
                    'member_name' => 'Name or display name of member',
                    'auth_method' => 'Authentication protocol (e.g. Touch ID Biometrics, TOTP 2FA)',
                    'status_action' => 'Enrolled / Activated / Revoked',
                    'device_name' => 'Device descriptor label',
                    'timestamp' => 'Timestamp of change',
                    'profile_url' => 'Account profile security settings URL',
                ],
                'sample_data' => [
                    'member_name' => 'Alex Sterling',
                    'auth_method' => 'Hardware Passkey (Touch ID)',
                    'status_action' => 'Enrolled & Verified',
                    'device_name' => 'MacBook Pro M3 Max',
                    'timestamp' => now()->toFormattedDateString().' UTC',
                    'profile_url' => config('app.url', 'https://vesper.jldn').'/admin',
                ],
            ],

            'channel_burn_warning' => [
                'name' => 'Channel Expiration Warning',
                'category' => 'Channels',
                'description' => 'Dispatched before an ephemeral channel expires and deletes its data.',
                'subject' => 'Notice: Channel [{{channel_code}}] Expires in {{time_remaining}}',
                'preheader' => 'Channel {{channel_code}} will expire shortly.',
                'body_markdown' => "Hello **{{member_name}}**,\n\nThe temporary channel **{{channel_title}}** (`{{channel_code}}`) has entered its final operational window.\n\n### Expiration Countdown:\n- **Time Remaining:** {{time_remaining}}\n- **Status:** Auto-Deletion Pending\n\nOnce the countdown expires, all messages and shared attachments in this channel will be permanently deleted according to the channel retention policy. Review or export necessary notes before the channel is closed.",
                'button_text' => 'Enter Secure Channel',
                'button_color' => 'amber',
                'footer_text' => 'Vesper Private Communications • Channel Retention Policy',
                'variables' => [
                    'member_name' => 'Name or display name of member',
                    'channel_title' => 'Channel title',
                    'channel_code' => 'Room code',
                    'time_remaining' => 'Remaining duration (e.g. 60 minutes)',
                    'channel_url' => 'Direct channel link',
                ],
                'sample_data' => [
                    'member_name' => 'Alex Sterling',
                    'channel_title' => 'Temporary Project Briefing',
                    'channel_code' => 'BURN-07',
                    'time_remaining' => '60 minutes',
                    'channel_url' => config('app.url', 'https://vesper.jldn').'/c/BURN-07',
                ],
            ],

            'admin_operations_digest' => [
                'name' => 'Administrative Daily Digest',
                'category' => 'System',
                'description' => 'Dispatched periodically to platform administrators summarizing system activity.',
                'subject' => 'Administrative Digest — Vesper System Overview',
                'preheader' => 'Daily system activity and channel summary report.',
                'body_markdown' => "Hello **{{admin_name}}**,\n\nHere is your daily administrative summary covering recent Vesper activity:\n\n### Platform Overview:\n- **Active Channels:** {{active_channels}}\n- **Registered Members:** {{total_members}}\n- **Messages Exchanged:** {{messages_count}}\n- **Access Log Entries:** {{visitors_count}}\n- **Security Notices:** {{security_alerts_count}}\n\nAll services and communication channels are operating normally.",
                'button_text' => 'Open Admin Dashboard',
                'button_color' => 'success',
                'footer_text' => 'Vesper Administration • System Report',
                'variables' => [
                    'admin_name' => 'Name of administrator',
                    'active_channels' => 'Count of active channels',
                    'total_members' => 'Total registered members',
                    'messages_count' => 'Total messages sent',
                    'visitors_count' => 'Tracked visitor access count',
                    'security_alerts_count' => 'Number of security incidents',
                    'admin_url' => 'Direct link to Admin Dashboard',
                ],
                'sample_data' => [
                    'admin_name' => 'Administrator',
                    'active_channels' => '12',
                    'total_members' => '48',
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
            footerText: $footerText,
            locale: $locale
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
        // Provide seamless alias support between legacy and professional variable names
        if (isset($variables['member_name']) && ! isset($variables['operative_name'])) {
            $variables['operative_name'] = $variables['member_name'];
        } elseif (isset($variables['operative_name']) && ! isset($variables['member_name'])) {
            $variables['member_name'] = $variables['operative_name'];
        }

        if (isset($variables['total_members']) && ! isset($variables['total_operatives'])) {
            $variables['total_operatives'] = $variables['total_members'];
        } elseif (isset($variables['total_operatives']) && ! isset($variables['total_members'])) {
            $variables['total_members'] = $variables['total_operatives'];
        }

        if (isset($variables['total_members_count']) && ! isset($variables['total_operatives_count'])) {
            $variables['total_operatives_count'] = $variables['total_members_count'];
        } elseif (isset($variables['total_operatives_count']) && ! isset($variables['total_members_count'])) {
            $variables['total_members_count'] = $variables['total_operatives_count'];
        }

        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($matches) use ($variables) {
            $key = $matches[1];

            return array_key_exists($key, $variables) ? (string) $variables[$key] : $matches[0];
        }, $text);
    }

    /**
     * Auto-translate an English template into target languages while protecting dynamic variable placeholders.
     *
     * @param  array<string>|null  $targetLocales
     * @return array{
     *     success: bool,
     *     translated_locales: array<string>,
     *     errors: array<string>
     * }
     */
    public function autoTranslate(string $key, string $fromLocale = 'en', ?array $targetLocales = null, ?int $userId = null): array
    {
        if ($targetLocales === null || empty($targetLocales)) {
            $targetLocales = array_values(array_diff(LanguageService::codes(), [$fromLocale]));
        }

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
        ?string $footerText = null,
        string $locale = 'en'
    ): string {
        $direction = LanguageService::getDirection($locale);
        $textAlign = ($direction === 'rtl') ? 'right' : 'left';

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

        $footerContent = $footerText ?: 'Vesper Private Communications • Encrypted Messaging Network • Confidential';

        return <<<HTML
<!DOCTYPE html>
<html lang="{$locale}" dir="{$direction}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #0b0f17; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #cbd5e1; direction: {$direction}; text-align: {$textAlign}; }
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
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" dir="{$direction}" style="max-width: 580px; background-color: #0f172a; border: 1px solid #1e293b; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.6); text-align: {$textAlign};">
                    <!-- Brand Banner -->
                    <tr>
                        <td style="padding: 24px 32px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(6, 182, 212, 0.08)); border-bottom: 1px solid #1e293b;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td>
                                        <div style="font-size: 18px; font-weight: 700; color: #ffffff; letter-spacing: -0.025em; display: inline-flex; align-items: center; gap: 8px;">
                                             <span>VESPER</span>
                                            <span style="font-size: 10px; font-family: ui-monospace, monospace; padding: 2px 6px; border-radius: 4px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; text-transform: uppercase; margin-left: 6px;">ENTERPRISE</span>
                                        </div>
                                        <div style="font-size: 11px; font-family: ui-monospace, monospace; color: #64748b; margin-top: 4px;">
                                            SECURE COMMUNICATIONS PLATFORM
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Main Body -->
                    <tr>
                        <td style="padding: 32px 32px 24px 32px; text-align: {$textAlign};">
                            <h2 style="font-size: 20px; color: #ffffff; margin-bottom: 18px; font-weight: 600; letter-spacing: -0.015em; text-align: {$textAlign};">{$subject}</h2>
                            <div style="color: #cbd5e1; font-size: 14px; line-height: 1.65; text-align: {$textAlign};">
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
