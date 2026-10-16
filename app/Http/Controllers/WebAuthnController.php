<?php

namespace App\Http\Controllers;

use App\Enums\Rank;
use App\Models\WebAuthnCredential;
use App\Services\WebAuthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebAuthnController extends Controller
{
    public function __construct(
        protected WebAuthnService $webAuthnService
    ) {}

    /**
     * Return challenge & registration options for enrolling a new biometric key.
     */
    public function optionsRegister(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($refusal = $this->refuseUnlessPreconditionMet($request)) {
            return $refusal;
        }

        $options = $this->webAuthnService->generateRegisterOptions($user);

        return response()->json($options);
    }

    /**
     * Store and verify newly created biometric credential.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'clientDataJSON' => ['nullable', 'string'],
            'attestationObject' => ['nullable', 'string'],
            'publicKey' => ['nullable', 'string'],
            'deviceName' => ['nullable', 'string', 'max:80'],
            'transports' => ['nullable', 'array'],
        ]);

        if ($refusal = $this->refuseUnlessPreconditionMet($request)) {
            return $refusal;
        }

        try {
            $credential = $this->webAuthnService->registerCredential($request->user(), $validated);

            return response()->json([
                'success' => true,
                'message' => __('Biometric key enrolled successfully.'),
                'credential' => [
                    'id' => $credential->id,
                    'device_name' => $credential->device_name,
                    'created_at' => $credential->created_at->diffForHumans(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Biometric sign-in requires a verified primary email plus a verified recovery email
     * or confirmed 2FA (owner decisions Q5 and A1). Refuse enrollment otherwise, listing
     * what is missing.
     */
    protected function refuseUnlessPreconditionMet(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if ($user->meetsBiometricPrecondition()) {
            return null;
        }

        $missing = array_values(array_filter([
            $user->hasVerifiedEmail() ? null : __('Verified primary email'),
            ($user->hasVerifiedRecoveryEmail() || $user->hasTwoFactor()) ? null : __('Verified recovery email or Two-Factor Authentication'),
        ]));

        return response()->json([
            'success' => false,
            'message' => __('Before enrolling a passkey you need: :missing.', ['missing' => implode('; ', $missing)]),
            'missing' => $missing,
        ], 422);
    }

    /**
     * Return challenge & options for authenticating via biometrics on login.
     */
    public function optionsLogin(): JsonResponse
    {
        $options = $this->webAuthnService->generateLoginOptions();

        return response()->json($options);
    }

    /**
     * Verify biometric assertion and log the user into the platform.
     */
    public function verifyLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'clientDataJSON' => ['nullable', 'string'],
            'authenticatorData' => ['nullable', 'string'],
            'signature' => ['nullable', 'string'],
        ]);

        try {
            $user = $this->webAuthnService->verifyLogin($validated);

            // If user has 2FA enabled, intercept and require 2FA
            if ($user->hasTwoFactor()) {
                session(['2fa_user_id' => $user->id, '2fa_remember' => true]);

                return response()->json([
                    'success' => true,
                    'requires_2fa' => true,
                    'redirect' => route('2fa.challenge'),
                ]);
            }

            Auth::login($user, true);
            $request->session()->regenerate();

            return response()->json([
                'success' => true,
                'requires_2fa' => false,
                'redirect' => $user->homeRoute(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Revoke and delete an enrolled biometric key.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $credential = WebAuthnCredential::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        if ($user->hasRank(Rank::AdminTwo) && $user->webauthnCredentials()->count() === 1) {
            return response()->json([
                'success' => false,
                'message' => __('Admin II requires an enrolled biometric key. Register another key before removing this one.'),
            ], 422);
        }

        $credential->delete();

        return response()->json([
            'success' => true,
            'message' => __('Biometric key revoked.'),
        ]);
    }
}
