<?php

namespace App\Http\Controllers;

use App\Mail\EmergencyAccountRecovery;
use App\Mail\RecoveryEmailVerification;
use App\Mail\SecurityAlertNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AccountRecoveryController extends Controller
{
    /**
     * Save a secondary recovery email and dispatch a signed confirmation link.
     */
    public function updateEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recovery_email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $user = $request->user();
        $targetEmail = strtolower(trim($validated['recovery_email']));

        if ($targetEmail === strtolower($user->email)) {
            return response()->json([
                'success' => false,
                'message' => __('Recovery email cannot be identical to your primary account email.'),
            ], 422);
        }

        $user->forceFill([
            'recovery_email' => $targetEmail,
            'recovery_email_verified_at' => null,
        ])->save();

        // Generate temporary signed URL valid for 24 hours
        $verificationUrl = URL::temporarySignedRoute(
            'recovery.email.verify',
            now()->addHours(24),
            ['id' => $user->id, 'hash' => sha1($targetEmail)]
        );

        try {
            Mail::to($targetEmail)->send(new RecoveryEmailVerification($user, $verificationUrl));
        } catch (\Throwable $e) {
            // Log mail exception without crashing request
        }

        return response()->json([
            'success' => true,
            'message' => __('Verification link dispatched to your recovery email. Please confirm it.'),
            'recovery_email' => $targetEmail,
            'verified' => false,
        ]);
    }

    /**
     * Verify the signed recovery email link.
     */
    public function verifyEmail(Request $request, int $id, string $hash): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('login')->withErrors(['login' => __('The recovery email verification link is invalid or has expired.')]);
        }

        $user = User::findOrFail($id);

        if (! hash_equals($hash, sha1($user->recovery_email))) {
            return redirect()->route('login')->withErrors(['login' => __('Invalid recovery email verification token.')]);
        }

        $user->forceFill([
            'recovery_email_verified_at' => now(),
        ])->save();

        if (Auth::check()) {
            return redirect()->route('admin.dashboard')->with('status', __('Secondary recovery email verified successfully.'));
        }

        return redirect()->route('login')->with('status', __('Recovery email verified successfully. You may now sign in.'));
    }

    /**
     * Show the emergency account recovery request form.
     */
    public function showRequestForm(): View
    {
        return view('auth.recovery_request');
    }

    /**
     * Process emergency recovery request and email single-use token to verified recovery address.
     */
    public function sendRecoveryLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string'],
        ]);

        $input = trim($validated['identifier']);
        $user = User::where('email', $input)->orWhere('name', $input)->first();

        if ($user && $user->hasVerifiedRecoveryEmail()) {
            $token = Str::random(64);
            $user->forceFill([
                'recovery_token' => hash('sha256', $token),
                'recovery_token_expires_at' => now()->addMinutes(15),
            ])->save();

            $resetUrl = route('recovery.reset.form', ['token' => $token]);
            $clientIp = $request->ip() ?: '0.0.0.0';

            try {
                // Dispatch reset link to recovery email
                Mail::to($user->recovery_email)->send(new EmergencyAccountRecovery($user, $resetUrl, $clientIp));

                // Dispatch security alert to primary email
                Mail::to($user->email)->send(new SecurityAlertNotification(
                    $user,
                    __('Emergency account recovery initiated via secondary recovery channel.'),
                    $clientIp
                ));
            } catch (\Throwable $e) {
                // Log and continue
            }
        }

        // Generic response to prevent user/email enumeration
        return back()->with('status', __('If a verified recovery email is associated with that account, emergency access instructions have been dispatched.'));
    }

    /**
     * Show the emergency password reset form.
     */
    public function showResetForm(string $token): View|RedirectResponse
    {
        $hashedToken = hash('sha256', $token);
        $user = User::where('recovery_token', $hashedToken)
            ->where('recovery_token_expires_at', '>', now())
            ->first();

        if (! $user) {
            return redirect()->route('login')->withErrors(['login' => __('Emergency recovery link is invalid or has expired.')]);
        }

        return view('auth.recovery_reset', [
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Execute emergency password reset and clear recovery token.
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $hashedToken = hash('sha256', $validated['token']);
        $user = User::where('recovery_token', $hashedToken)
            ->where('recovery_token_expires_at', '>', now())
            ->first();

        if (! $user) {
            return redirect()->route('login')->withErrors(['login' => __('Emergency recovery link has expired.')]);
        }

        $clientIp = $request->ip() ?: '0.0.0.0';

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'recovery_token' => null,
            'recovery_token_expires_at' => null,
            // Reset 2FA so locked out operative can re-enroll
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        try {
            // Dual security alert
            $alert = new SecurityAlertNotification($user, __('Password reset and 2FA credentials purged via emergency recovery channel.'), $clientIp);
            Mail::to($user->email)->send($alert);
            if ($user->recovery_email) {
                Mail::to($user->recovery_email)->send($alert);
            }
        } catch (\Throwable $e) {
            // Log and continue
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->to($user->homeRoute())->with('status', __('Account recovered successfully. Please re-configure your two-factor credentials and biometrics.'));
    }
}
