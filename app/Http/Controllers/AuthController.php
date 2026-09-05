<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
        ]);
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
            ]);

            $user = User::create([
                'name' => trim($validated['name']),
                'email' => strtolower(trim($validated['email'])),
                'password' => Hash::make($validated['password']),
            ]);

            Auth::login($user, true);
            $request->session()->regenerate();

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
