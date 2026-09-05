<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Services\EmailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class AdminEmailController extends Controller
{
    public function __construct(
        protected EmailTemplateService $templateService
    ) {}

    /**
     * Display the email template editor interface.
     */
    public function index(Request $request): View
    {
        $definitions = $this->templateService->getDefinitions();
        $activeKey = $request->query('template', 'registration_welcome');

        if (! array_key_exists($activeKey, $definitions)) {
            $activeKey = 'registration_welcome';
        }

        $activeLocale = $request->query('locale', 'en');
        if (! in_array($activeLocale, ['en', 'it', 'fr', 'ru'], true)) {
            $activeLocale = 'en';
        }

        $activeTemplate = $this->templateService->get($activeKey, $activeLocale);

        // Fetch customized matrix across all templates
        $customizedTemplates = EmailTemplate::select('key', 'locale', 'updated_at')->get();
        $customizedMatrix = [];
        foreach ($customizedTemplates as $ct) {
            $customizedMatrix[$ct->key][$ct->locale] = $ct->updated_at?->diffForHumans() ?? true;
        }

        return view('admin.emails.index', [
            'definitions' => $definitions,
            'activeKey' => $activeKey,
            'activeLocale' => $activeLocale,
            'activeTemplate' => $activeTemplate,
            'customizedMatrix' => $customizedMatrix,
            'adminUser' => Auth::user(),
        ]);
    }

    /**
     * Render an interactive HTML preview for an email template (powers live iframe).
     */
    public function preview(Request $request, string $key): Response
    {
        $definitions = $this->templateService->getDefinitions();
        $def = $definitions[$key] ?? null;

        if (! $def) {
            abort(404, 'Template not found');
        }

        $locale = $request->input('locale', $request->query('locale', 'en'));
        if (! in_array($locale, ['en', 'it', 'fr', 'ru'], true)) {
            $locale = 'en';
        }

        $variables = $def['sample_data'];

        // If POSTing live draft edits, preview the draft directly
        if ($request->has('body_markdown')) {
            $subject = $this->templateService->interpolate((string) $request->input('subject', $def['subject']), $variables);
            $preheader = $request->filled('preheader') ? $this->templateService->interpolate((string) $request->input('preheader'), $variables) : null;
            $bodyMarkdown = $this->templateService->interpolate((string) $request->input('body_markdown', $def['body_markdown']), $variables);
            $buttonText = $request->filled('button_text') ? $this->templateService->interpolate((string) $request->input('button_text'), $variables) : null;
            $buttonColor = (string) $request->input('button_color', $def['button_color']);
            $footerText = $request->filled('footer_text') ? $this->templateService->interpolate((string) $request->input('footer_text'), $variables) : null;

            $bodyHtml = \Illuminate\Support\Str::markdown($bodyMarkdown);
            $html = $this->templateService->renderFullEmailHtml(
                subject: $subject,
                preheader: $preheader,
                bodyHtml: $bodyHtml,
                buttonText: $buttonText,
                buttonUrl: $variables['action_url'] ?? ($variables['reset_url'] ?? ($variables['verification_url'] ?? ($variables['channel_url'] ?? ($variables['login_url'] ?? ($variables['admin_url'] ?? '#'))))),
                buttonColor: $buttonColor,
                footerText: $footerText
            );

            return response($html)->header('Content-Type', 'text/html');
        }

        // Otherwise render the saved or preset template
        $rendered = $this->templateService->render($key, $variables, $locale);

        return response($rendered['rendered_html'])->header('Content-Type', 'text/html');
    }

    /**
     * Save updates to an email template for a specific language.
     */
    public function update(Request $request, string $key)
    {
        $definitions = $this->templateService->getDefinitions();
        if (! array_key_exists($key, $definitions)) {
            abort(404);
        }

        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:en,it,fr,ru'],
            'subject' => ['required', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'body_markdown' => ['required', 'string'],
            'button_text' => ['nullable', 'string', 'max:100'],
            'button_color' => ['required', 'string', 'in:success,cyan,error,amber'],
            'footer_text' => ['nullable', 'string'],
        ]);

        $def = $definitions[$key];

        EmailTemplate::updateOrCreate(
            ['key' => $key, 'locale' => $validated['locale']],
            [
                'name' => $def['name'],
                'category' => $def['category'],
                'subject' => $validated['subject'],
                'preheader' => $validated['preheader'],
                'body_markdown' => $validated['body_markdown'],
                'button_text' => $validated['button_text'],
                'button_color' => $validated['button_color'],
                'footer_text' => $validated['footer_text'],
                'updated_by_user_id' => Auth::id(),
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('Email template saved successfully!'),
            ]);
        }

        return redirect()
            ->route('admin.emails.index', ['template' => $key, 'locale' => $validated['locale']])
            ->with('status', __('Email template saved successfully!'));
    }

    /**
     * Automatically translate the English template into IT, FR, and RU.
     */
    public function autoTranslate(Request $request, string $key): JsonResponse
    {
        $definitions = $this->templateService->getDefinitions();
        if (! array_key_exists($key, $definitions)) {
            return response()->json(['success' => false, 'message' => 'Invalid template key.'], 404);
        }

        $fromLocale = $request->input('from_locale', 'en');
        $targetLocales = $request->input('target_locales', ['it', 'fr', 'ru']);

        $result = $this->templateService->autoTranslate($key, $fromLocale, $targetLocales, Auth::id());

        return response()->json([
            'success' => $result['success'],
            'translated_locales' => $result['translated_locales'],
            'errors' => $result['errors'],
            'message' => $result['success']
                ? __('Successfully auto-translated to: :locales', ['locales' => strtoupper(implode(', ', $result['translated_locales']))])
                : __('Translation failed. Please verify network connectivity.'),
        ]);
    }

    /**
     * Send a live test email of the template to the currently logged-in administrator.
     */
    public function sendTest(Request $request, string $key): JsonResponse
    {
        $definitions = $this->templateService->getDefinitions();
        if (! array_key_exists($key, $definitions)) {
            return response()->json(['success' => false, 'message' => 'Invalid template key.'], 404);
        }

        $locale = $request->input('locale', 'en');
        $def = $definitions[$key];
        $user = Auth::user();

        if (! $user || ! $user->email) {
            return response()->json(['success' => false, 'message' => __('No authenticated email found.')], 400);
        }

        $variables = array_merge($def['sample_data'], [
            'operative_name' => $user->name,
            'recipient_name' => $user->name,
            'email' => $user->email,
        ]);

        $rendered = $this->templateService->render($key, $variables, $locale);

        try {
            Mail::to($user->email)->send(
                new \App\Mail\TemplatePreviewMail(
                    subjectLine: '[TEST] '.$rendered['subject'],
                    htmlBody: $rendered['rendered_html']
                )
            );

            return response()->json([
                'success' => true,
                'message' => __('Test email dispatched to :email', ['email' => $user->email]),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('Dispatch error: :msg', ['msg' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * Reset a template to its default factory copy.
     */
    public function reset(Request $request, string $key)
    {
        $locale = $request->input('locale', 'en');

        EmailTemplate::where('key', $key)->where('locale', $locale)->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('Template reset to factory default.'),
            ]);
        }

        return redirect()
            ->route('admin.emails.index', ['template' => $key, 'locale' => $locale])
            ->with('status', __('Template reset to factory default.'));
    }
}
