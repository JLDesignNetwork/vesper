<?php

namespace App\Http\Controllers;

use App\Models\ChannelInvitation;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ChannelHubController extends Controller
{
    /**
     * Display the Operative Channels Hub for the authenticated member.
     * Strictly displays ONLY the channels this user belongs to (zero discovery).
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Fetch strictly the active channels where this user is an enrolled member
        $channels = $user->channels()
            ->where('rooms.status', 'active')
            ->withCount('messages')
            ->orderByDesc('room_user.last_accessed_at')
            ->get();

        // Fetch any pending direct channel invitations
        $pendingInvites = $user->pendingChannelInvitations()
            ->with('creator:id,name')
            ->get();

        $canUsePinlessEntry = $user->canUsePinlessEntry();

        return view('channels.index', [
            'user' => $user,
            'channels' => $channels,
            'pendingInvites' => $pendingInvites,
            'canUsePinlessEntry' => $canUsePinlessEntry,
        ]);
    }

    /**
     * Re-enter an enrolled channel from the hub.
     * If operative has 2FA/biometrics active, auto-grants clearance (pinless).
     * Otherwise, routes to PIN gate with 2FA prompt.
     */
    public function enter(Room $room, Request $request): RedirectResponse
    {
        $user = $request->user();

        // Verify membership
        if (! $room->isMember($user)) {
            return redirect()->route('channels.index')->withErrors([
                'channel' => __('Access restricted. You are not an enrolled member of this channel.'),
            ]);
        }

        if ($room->status !== 'active') {
            return redirect()->route('channels.index')->withErrors([
                'channel' => __('This channel is currently archived or inactive.'),
            ]);
        }

        // Check if operative qualifies for 2FA-guarded pinless entry
        if ($user->canUsePinlessEntry()) {
            $request->session()->put("room_clearance_{$room->id}", true);
            $request->session()->put("room_alias_{$room->id}", $user->name);
            $request->session()->put("room_is_admin_{$room->id}", $user->isAdmin());

            // Update last accessed timestamp
            DB::table('room_user')
                ->where('room_id', $room->id)
                ->where('user_id', $user->id)
                ->update([
                    'last_accessed_at' => now(),
                    'updated_at' => now(),
                ]);

            return redirect()->route('rooms.show', ['room' => $room->code]);
        }

        // If user does not have 2FA, they must supply the channel PIN
        return redirect()->route('rooms.show', ['room' => $room->code])->with(
            'pin_notice',
            __('Pinless entry requires Two-Factor Authentication. Please enter your channel PIN, or activate 2FA in Profile Settings to unlock instant pinless access.')
        );
    }

    /**
     * Accept a pending direct channel invitation.
     */
    public function acceptInvite(int $roomId, Request $request): RedirectResponse
    {
        $user = $request->user();

        $invitation = DB::table('room_user')
            ->where('room_id', $roomId)
            ->where('user_id', $user->id)
            ->where('status', 'invited')
            ->first();

        if (! $invitation) {
            return redirect()->route('channels.index')->withErrors([
                'channel' => __('Invitation not found or already processed.'),
            ]);
        }

        DB::table('room_user')
            ->where('room_id', $roomId)
            ->where('user_id', $user->id)
            ->update([
                'status' => 'active',
                'last_accessed_at' => now(),
                'updated_at' => now(),
            ]);

        return redirect()->route('channels.index')->with('status', __('Channel clearance accepted. You are now an active member.'));
    }

    /**
     * Decline a pending direct channel invitation.
     */
    public function declineInvite(int $roomId, Request $request): RedirectResponse
    {
        $user = $request->user();

        DB::table('room_user')
            ->where('room_id', $roomId)
            ->where('user_id', $user->id)
            ->where('status', 'invited')
            ->update([
                'status' => 'declined',
                'updated_at' => now(),
            ]);

        return redirect()->route('channels.index')->with('status', __('Channel clearance declined.'));
    }

    /**
     * Redeem an alphanumeric channel invitation clearance code (e.g. INV-XXXX-XXXX).
     */
    public function redeemCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ]);

        $cleanCode = strtoupper(trim($validated['code']));
        $invitation = ChannelInvitation::where('code', $cleanCode)->first();

        if (! $invitation || ! $invitation->isValid()) {
            return back()->withErrors([
                'code' => __('Invalid or expired channel invitation code.'),
            ]);
        }

        $user = $request->user();
        $invitation->consume($user);

        return redirect()->route('channels.index')->with(
            'status',
            __('Invitation code verified! You have been granted clearance to :title.', ['title' => $invitation->room->title ?: $invitation->room->code])
        );
    }
}
