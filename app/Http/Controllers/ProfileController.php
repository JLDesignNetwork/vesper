<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    /**
     * Update the authenticated user's profile details.
     */
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401, 'Unauthorized.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'birthday' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:30'],
            'location' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:500'],
            'password' => ['nullable', 'string', 'min:6'],
            'email_notifications' => ['nullable', 'boolean'],
            'room_id' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        $user->name = trim($validated['name']);
        $user->email = strtolower(trim($validated['email']));
        $user->birthday = ! empty($validated['birthday']) ? $validated['birthday'] : null;
        $user->gender = ! empty($validated['gender']) ? trim($validated['gender']) : null;
        $user->location = ! empty($validated['location']) ? trim($validated['location']) : null;
        $user->bio = ! empty($validated['bio']) ? trim($validated['bio']) : null;
        $user->email_notifications = $request->boolean('email_notifications');

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        // Handle avatar removal
        if ($request->boolean('remove_avatar')) {
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = null;
        }

        // Handle avatar upload
        if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $file = $request->file('avatar');
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
            $filename = "avatar_{$user->id}_".Str::random(16).".{$extension}";
            $path = $file->storeAs('avatars', $filename, 'public');

            $user->avatar_path = $path;
        }

        $user->save();

        // Synchronize room alias if currently inside a room
        if (! empty($validated['room_id'])) {
            $request->session()->put("room_alias_{$validated['room_id']}", $user->name);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('Profile details updated successfully.'),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'birthday' => $user->birthday?->format('Y-m-d'),
                    'age' => $user->age(),
                    'gender' => $user->gender,
                    'location' => $user->location,
                    'bio' => $user->bio,
                    'role' => $user->role,
                    'email_notifications' => $user->email_notifications,
                    'avatar_url' => $user->avatarUrl(),
                ],
            ]);
        }

        return back()->with('status', __('Profile details updated successfully.'));
    }
}
