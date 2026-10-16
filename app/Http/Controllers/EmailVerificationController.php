<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RankService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailVerificationController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected RankService $rankService
    ) {}

    /**
     * Confirm a primary email address from its signed link. Works without an active
     * session so the link can be opened on any device.
     */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $destination = Auth::check() ? 'profile.show' : 'login';

        if (! $request->hasValidSignature()) {
            return redirect()->route($destination)->withErrors([
                'email' => __('This email verification link is invalid or has expired. Request a new one from your profile.'),
            ]);
        }

        $user = User::find($id);

        if (! $user || ! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return redirect()->route($destination)->withErrors([
                'email' => __('This email verification link does not match your current email address. Request a new one from your profile.'),
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            $this->rankService->recomputeCreatorsOfChannelsJoinedBy($user);
        }

        return redirect()->route($destination)->with('status', __('Your email address is verified.'));
    }

    /**
     * Send a fresh verification link to the signed-in user's primary email.
     */
    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('profile.show')->with('status', __('Your email address is already verified.'));
        }

        $user->sendEmailVerificationNotification();

        return redirect()->route('profile.show')->with('status', __('A new verification link was sent to :email.', ['email' => $user->email]));
    }
}
