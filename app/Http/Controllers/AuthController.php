<?php

namespace App\Http\Controllers;

use App\Mail\MemberWelcomeNotification;
use App\Models\User;
use App\Services\LanguageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Display the login / admin setup portal.
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->to(Auth::user()->homeRoute());
        }

        $needsSetup = User::count() === 0;

        return view('auth.login', [
            'needsSetup' => $needsSetup,
            'isRegister' => false,
        ]);
    }

    /**
     * Display the registration portal for new members.
     */
    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->to(Auth::user()->homeRoute());
        }

        return view('auth.login', [
            'needsSetup' => false,
            'isRegister' => true,
        ]);
    }

    /**
     * Register a new member account with immediate preferred language selection.
     */
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'preferred_locale' => ['nullable', 'string', Rule::in(LanguageService::codes())],
            'location' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:500'],
        ]);

        $preferredLocale = $validated['preferred_locale'] ?? session('locale', config('app.locale', 'en'));

        $user = User::create([
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'password' => Hash::make($validated['password']),
            'role' => 'member',
            'preferred_locale' => $preferredLocale,
            'location' => ! empty($validated['location']) ? trim($validated['location']) : null,
            'bio' => ! empty($validated['bio']) ? trim($validated['bio']) : null,
            'email_notifications' => true,
        ]);

        try {
            Mail::to($user->email)->send(new MemberWelcomeNotification($user));
        } catch (\Throwable $e) {
            Log::warning("Failed to dispatch welcome notification to new member: {$e->getMessage()}");
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put('locale', $preferredLocale);
        app()->setLocale($preferredLocale);

        return redirect()->route('profile.show')->with('status', __('Welcome to Vesper! Your account is active.'));
    }

    /**
     * Authenticate an existing admin or complete initial setup.
     */
    public function login(Request $request): RedirectResponse
    {
        $needsSetup = User::count() === 0;

        if ($needsSetup) {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:50'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:6'],
                'preferred_locale' => ['nullable', 'string', Rule::in(LanguageService::codes())],
            ]);

            $locale = $validated['preferred_locale'] ?? session('locale', config('app.locale', 'en'));

            $user = User::create([
                'name' => trim($validated['name']),
                'email' => strtolower(trim($validated['email'])),
                'password' => Hash::make($validated['password']),
                'role' => 'admin',
                'preferred_locale' => $locale,
            ]);

            try {
                Mail::to($user->email)->send(new MemberWelcomeNotification($user));
            } catch (\Throwable $e) {
                Log::warning("Failed to dispatch welcome notification to initial admin: {$e->getMessage()}");
            }

            Auth::login($user, true);
            $request->session()->regenerate();
            $request->session()->put('locale', $locale);
            app()->setLocale($locale);

            return redirect()->route('admin.dashboard')->with('status', 'Admin account configured successfully.');
        }

        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $loginInput = trim($validated['login']);
        $field = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

        $user = User::where($field, $loginInput)->first();

        if ($user && Hash::check($validated['password'], $user->password)) {
            $remember = $request->boolean('remember');

            // Check if two-factor authentication is active
            if ($user->hasTwoFactor()) {
                session([
                    '2fa_user_id' => $user->id,
                    '2fa_remember' => $remember,
                ]);

                return redirect()->route('2fa.challenge');
            }

            Auth::login($user, $remember);
            $request->session()->regenerate();

            return redirect()->intended($user->homeRoute());
        }

        return back()->withInput($request->only('login'))->withErrors([
            'login' => __('Invalid credentials provided.'),
        ]);
    }

    /**
     * Log out the authenticated administrator.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out.');
    }
}
