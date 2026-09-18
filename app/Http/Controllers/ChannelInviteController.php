<?php

namespace App\Http\Controllers;

use App\Models\ChannelInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChannelInviteController extends Controller
{
    /**
     * Display the secure invitation landing screen for an invite link.
     */
    public function showInvite(string $token): View|RedirectResponse
    {
        $invitation = ChannelInvitation::with(['room', 'creator:id,name'])->where('token', $token)->first();

        if (! $invitation || ! $invitation->isValid()) {
            return redirect()->route('login')->withErrors([
                'login' => __('This channel invitation link is invalid or has expired.'),
            ]);
        }

        // If user is already authenticated and an active member, route them in
        if (Auth::check() && $invitation->room->isMember(Auth::user())) {
            return redirect()->route('profile.show')->with(
                'status',
                __('You are already a member of :title.', ['title' => $invitation->room->title ?: $invitation->room->code])
            );
        }

        return view('invites.show', [
            'invitation' => $invitation,
            'room' => $invitation->room,
            'creator' => $invitation->creator,
        ]);
    }

    /**
     * Accept and claim an invitation link as the authenticated member.
     */
    public function acceptInviteLink(string $token, Request $request): RedirectResponse
    {
        $invitation = ChannelInvitation::with('room')->where('token', $token)->first();

        if (! $invitation || ! $invitation->isValid()) {
            return redirect()->route('profile.show')->withErrors([
                'channel' => __('This invitation link is invalid or has expired.'),
            ]);
        }

        $user = $request->user();
        $invitation->consume($user);

        return redirect()->route('profile.show')->with(
            'status',
            __('Invitation claimed! Clearance granted to channel :title.', ['title' => $invitation->room->title ?: $invitation->room->code])
        );
    }
}
