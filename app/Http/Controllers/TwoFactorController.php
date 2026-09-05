<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorService $twoFactorService
    ) {}

    /**
     * Generate 2FA secret and QR code for profile setup.
     */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = $this->twoFactorService->generateSecretKey();
        session(['2fa_setup_secret' => $secret]);

        $otpAuthUrl = $this->twoFactorService->getOtpAuthUrl($user->email, $secret, config('app.name', 'Vesper'));
        $qrCodeSvg = $this->twoFactorService->getInlineSvgQrCode($otpAuthUrl, 200);

        return response()->json([
            'success' => true,
            'secret' => $secret,
            'qr_code_svg' => $qrCodeSvg,
            'otpauth_url' => $otpAuthUrl,
        ]);
    }

    /**
     * Confirm 2FA setup by verifying a code, saving the secret, and generating backup codes.
     */
    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $secret = session('2fa_setup_secret');
        if (empty($secret)) {
            return response()->json([
                'success' => false,
                'message' => __('Two-factor setup expired. Please try again.'),
            ], 422);
        }

        if (! $this->twoFactorService->verify($secret, $validated['code'])) {
            return response()->json([
                'success' => false,
                'message' => __('Invalid confirmation code. Please check your authenticator app and system clock.'),
            ], 422);
        }

        $user = $request->user();
        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ])->save();

        session()->forget('2fa_setup_secret');

        $recoveryCodes = $user->generateTwoFactorRecoveryCodes();

        return response()->json([
            'success' => true,
            'message' => __('Two-factor authentication enabled successfully.'),
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable 2FA with password confirmation.
     */
    public function disable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => __('Incorrect password.'),
            ], 422);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => __('Two-factor authentication disabled.'),
        ]);
    }

    /**
     * Regenerate emergency backup recovery codes.
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasTwoFactor()) {
            return response()->json(['success' => false, 'message' => __('2FA is not enabled.')], 400);
        }

        $codes = $user->generateTwoFactorRecoveryCodes();

        return response()->json([
            'success' => true,
            'recovery_codes' => $codes,
        ]);
    }

    /**
     * Show the 2FA login challenge screen.
     */
    public function showChallenge(Request $request): View|RedirectResponse
    {
        if (! session()->has('2fa_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two_factor_challenge');
    }

    /**
     * Verify the 2FA login challenge (code or emergency backup code).
     */
    public function verifyChallenge(Request $request): RedirectResponse
    {
        $userId = session('2fa_user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (! $user) {
            session()->forget(['2fa_user_id', '2fa_remember']);
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $remember = (bool) session('2fa_remember', false);

        // 1. Check TOTP 6-digit rolling code
        if (! empty($validated['code'])) {
            try {
                $secret = Crypt::decryptString($user->two_factor_secret);
                if ($this->twoFactorService->verify($secret, $validated['code'])) {
                    session()->forget(['2fa_user_id', '2fa_remember']);
                    Auth::login($user, $remember);
                    $request->session()->regenerate();

                    return redirect()->intended($user->homeRoute());
                }
            } catch (\Throwable $e) {
                // fall through to error
            }

            return back()->withErrors(['code' => __('Invalid 6-digit authentication code.')]);
        }

        // 2. Check Emergency Backup Recovery Code
        if (! empty($validated['recovery_code'])) {
            if ($user->consumeRecoveryCode($validated['recovery_code'])) {
                session()->forget(['2fa_user_id', '2fa_remember']);
                Auth::login($user, $remember);
                $request->session()->regenerate();

                return redirect()->intended($user->homeRoute())
                    ->with('status', __('Signed in using emergency backup code. Please review your active 2FA codes.'));
            }

            return back()->withErrors(['recovery_code' => __('Invalid or already consumed recovery code.')]);
        }

        return back()->withErrors(['code' => __('Please provide an authentication code or backup recovery code.')]);
    }
}
