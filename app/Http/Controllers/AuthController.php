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
            return redirect()->route('admin.dashboard');
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

        $remember = (bool) ($validated['remember'] ?? false);

        if (Auth::attempt([$field => $loginInput, 'password' => $validated['password']], $remember)) {
            $request->session()->regenerate();

            return redirect()->intended(route('admin.dashboard'));
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
