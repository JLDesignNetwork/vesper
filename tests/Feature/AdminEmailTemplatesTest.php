<?php

use App\Mail\EmergencyAccountRecovery;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailTemplateService;
use App\Services\LanguageService;
use App\Services\TranslationService;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'CommanderJeff',
        'email' => 'admin@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'admin',
        'preferred_locale' => 'en',
    ]);

    $this->operative = User::create([
        'name' => 'Agent007',
        'email' => 'agent@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'user',
        'preferred_locale' => 'en',
    ]);
});

test('guest cannot access email template manager', function () {
    $response = $this->get(route('admin.emails.index'));
    $response->assertRedirect(route('login'));
});

test('regular member without admin privileges is redirected to profile hub', function () {
    $response = $this->actingAs($this->operative)->get(route('admin.emails.index'));
    $response->assertRedirect(route('profile.show'));
});

test('admin can view the email templates manager and see library', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.emails.index'));

    $response->assertStatus(200);
    $response->assertSee('Email Templates');
    $response->assertSee('registration_welcome');
    $response->assertSee('emergency_recovery');
    $response->assertSee('new_message');
    $response->assertSee('channel_invitation');
    $response->assertSee('security_alert');
    $response->assertSee('two_factor_status');
    $response->assertSee('channel_burn_warning');
    $response->assertSee('admin_operations_digest');
});

test('admin can preview an email template via GET and draft POST', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.emails.preview', [
        'key' => 'registration_welcome',
        'locale' => 'en',
    ]));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    $response->assertSee('VESPER');
    $response->assertSee('ENTERPRISE');

    // Live POST draft preview
    $postDraft = $this->actingAs($this->admin)->post(route('admin.emails.preview', 'registration_welcome'), [
        'locale' => 'en',
        'subject' => 'LIVE DRAFT SUBJECT: {{operative_name}}',
        'preheader' => 'DRAFT PREHEADER',
        'body_markdown' => '## Live Draft Heading\n\nThis is a customized live transmission body.',
        'button_text' => 'Deploy Now',
        'button_color' => 'success',
        'footer_text' => 'Custom disclaimer 123',
    ]);

    $postDraft->assertStatus(200);
    $postDraft->assertSee('LIVE DRAFT SUBJECT');
    $postDraft->assertSee('Live Draft Heading');
    $postDraft->assertSee('Deploy Now');
});

test('admin can customize and update an email template', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.emails.update', 'emergency_recovery'), [
        'locale' => 'en',
        'subject' => 'Customized Emergency Recovery for {{operative_name}}',
        'preheader' => 'Immediate action required',
        'body_markdown' => 'Agent {{operative_name}}, your credentials need immediate verification.',
        'button_text' => 'Initiate Overwrite',
        'button_color' => 'error',
        'footer_text' => 'Automated clearance defense layer.',
    ]);

    $response->assertRedirect(route('admin.emails.index', ['template' => 'emergency_recovery', 'locale' => 'en']));

    $template = EmailTemplate::where('key', 'emergency_recovery')->where('locale', 'en')->first();
    expect($template)->not->toBeNull();
    expect($template->subject)->toBe('Customized Emergency Recovery for {{operative_name}}');
    expect($template->button_color)->toBe('error');
    expect($template->updated_by_user_id)->toBe($this->admin->id);
});

test('admin can reset a customized template back to factory default', function () {
    EmailTemplate::create([
        'key' => 'new_message',
        'locale' => 'en',
        'name' => 'New Transmission Notification',
        'category' => 'Operations',
        'subject' => 'Custom subject',
        'body_markdown' => 'Custom body',
        'button_color' => 'cyan',
        'updated_by_user_id' => $this->admin->id,
    ]);

    expect(EmailTemplate::where('key', 'new_message')->where('locale', 'en')->exists())->toBeTrue();

    $response = $this->actingAs($this->admin)->post(route('admin.emails.reset', 'new_message'), [
        'locale' => 'en',
    ]);

    $response->assertRedirect(route('admin.emails.index', ['template' => 'new_message', 'locale' => 'en']));
    expect(EmailTemplate::where('key', 'new_message')->where('locale', 'en')->exists())->toBeFalse();

    // Default factory preset is still returned by the service
    $resolved = app(EmailTemplateService::class)->get('new_message', 'en');
    expect($resolved['subject'])->toContain('{{channel_code}}');
});

test('auto-translation preserves dynamic variable tokens across target locales', function () {
    // Mock translation service response so network test is deterministic and protects tokens
    $mockTranslationService = Mockery::mock(TranslationService::class);
    $mockTranslationService->shouldReceive('translate')
        ->andReturnUsing(function ($text, $targetLang) {
            return [
                'success' => true,
                'original_text' => $text,
                'translated_text' => "[{$targetLang}] ".$text,
                'target_lang' => $targetLang,
            ];
        });

    $service = new EmailTemplateService($mockTranslationService);
    $result = $service->autoTranslate('registration_welcome', 'en', ['it', 'fr', 'ru'], $this->admin->id);

    expect($result['success'])->toBeTrue();
    expect($result['translated_locales'])->toBe(['it', 'fr', 'ru']);

    foreach (['it', 'fr', 'ru'] as $loc) {
        $saved = EmailTemplate::where('key', 'registration_welcome')->where('locale', $loc)->first();
        expect($saved)->not->toBeNull();
        // Dynamic variables must remain intact in translated markdown
        expect($saved->body_markdown)->toContain('{{member_name}}');
        expect($saved->body_markdown)->toContain('{{email}}');
    }
});

test('admin can dispatch a test email to their own address', function () {
    Mail::fake();

    $response = $this->actingAs($this->admin)->postJson(route('admin.emails.test', 'registration_welcome'), [
        'locale' => 'en',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);

    Mail::assertSent(function (Mailable $mail) {
        return $mail->hasTo('admin@vesper.test');
    });
});

test('outgoing mailables render using customized email templates or factory fallback', function () {
    // 1. Test factory default rendering
    $emergencyMail = new EmergencyAccountRecovery(
        user: $this->operative,
        resetUrl: 'https://vesper.test/recovery/reset/12345',
        ipAddress: '127.0.0.1'
    );

    $content = $emergencyMail->content();
    expect($content->htmlString)->toContain('Agent007');
    expect($content->htmlString)->toContain('https://vesper.test/recovery/reset/12345');

    // 2. Test customized template rendering
    EmailTemplate::create([
        'key' => 'emergency_recovery',
        'locale' => 'en',
        'name' => 'Emergency Recovery',
        'category' => 'Authentication',
        'subject' => 'CUSTOM TITLE FOR {{operative_name}}',
        'body_markdown' => 'CLASSIFIED INTRUSION DETECTED for {{operative_name}}.',
        'button_text' => 'LOCKDOWN LINK',
        'button_color' => 'error',
        'updated_by_user_id' => $this->admin->id,
    ]);

    $customEmergencyMail = new EmergencyAccountRecovery(
        user: $this->operative,
        resetUrl: 'https://vesper.test/recovery/reset/99999',
        ipAddress: '10.0.0.1'
    );

    $envelope = $customEmergencyMail->envelope();
    expect($envelope->subject)->toBe('CUSTOM TITLE FOR Agent007');

    $customContent = $customEmergencyMail->content();
    expect($customContent->htmlString)->toContain('CLASSIFIED INTRUSION DETECTED for Agent007');
    expect($customContent->htmlString)->toContain('LOCKDOWN LINK');
});

test('admin email templates interface supports all 15 dynamic languages', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.emails.index'));
    $response->assertStatus(200);

    // Verify all 15 language codes are present in the UI
    foreach (LanguageService::codes() as $code) {
        $response->assertSee('locale='.$code, false);
    }

    // Verify saving a template in a non-legacy language like Spanish (es) or Japanese (ja)
    $updateResponse = $this->actingAs($this->admin)->put(route('admin.emails.update', 'registration_welcome'), [
        'locale' => 'ja',
        'subject' => 'Vesperへようこそ: {{member_name}}',
        'preheader' => 'アカウントの準備が整いました',
        'body_markdown' => 'こんにちは **{{member_name}}** さん、Vesperへようこそ。',
        'button_text' => 'ダッシュボードへ',
        'button_color' => 'success',
        'footer_text' => 'Vesper 機密通信',
    ]);

    $updateResponse->assertRedirect(route('admin.emails.index', ['template' => 'registration_welcome', 'locale' => 'ja']));

    $jaTemplate = EmailTemplate::where('key', 'registration_welcome')->where('locale', 'ja')->first();
    expect($jaTemplate)->not->toBeNull();
    expect($jaTemplate->subject)->toBe('Vesperへようこそ: {{member_name}}');
});
