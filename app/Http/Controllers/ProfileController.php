<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Services\GeoLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            'hide_age' => ['nullable', 'boolean'],
            'hide_birthday' => ['nullable', 'boolean'],
            'hide_location' => ['nullable', 'boolean'],
            'hide_bio' => ['nullable', 'boolean'],
            'preferred_locale' => ['nullable', 'string', 'in:auto,en,ru,fr,it'],
            'room_id' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        $oldLocation = $user->location;

        $user->name = trim($validated['name']);
        $user->email = strtolower(trim($validated['email']));
        $user->birthday = ! empty($validated['birthday']) ? $validated['birthday'] : null;
        $user->gender = ! empty($validated['gender']) ? trim($validated['gender']) : null;
        $user->location = ! empty($validated['location']) ? trim($validated['location']) : null;
        $user->bio = ! empty($validated['bio']) ? trim($validated['bio']) : null;
        $user->email_notifications = $request->boolean('email_notifications');
        $user->hide_age = $request->boolean('hide_age');
        $user->hide_birthday = $request->boolean('hide_birthday');
        $user->hide_location = $request->boolean('hide_location');
        $user->hide_bio = $request->boolean('hide_bio');

        if ($request->has('preferred_locale')) {
            $pref = $request->input('preferred_locale');
            if (empty($pref) || $pref === 'auto') {
                $user->preferred_locale = null;
                $request->session()->put('locale', $user->resolveLocationLocale());
            } elseif (in_array($pref, ['en', 'ru', 'fr', 'it'], true)) {
                $user->preferred_locale = $pref;
                $request->session()->put('locale', $pref);
            }
        } elseif (empty($user->preferred_locale) && $oldLocation !== $user->location) {
            $request->session()->put('locale', $user->resolveLocationLocale());
        }

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
                    'hide_age' => $user->hide_age,
                    'hide_birthday' => $user->hide_birthday,
                    'hide_location' => $user->hide_location,
                    'hide_bio' => $user->hide_bio,
                    'email_notifications' => $user->email_notifications,
                    'preferred_locale' => $user->preferred_locale,
                    'effective_locale' => $user->effectiveLocale(),
                    'avatar_url' => $user->avatarUrl(),
                ],
            ]);
        }

        return back()->with('status', __('Profile details updated successfully.'));
    }

    /**
     * Synchronize authenticated user's high-precision GPS coordinates.
     */
    public function updateGps(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401, 'Unauthorized.');
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $lat = (float) $validated['latitude'];
        $lon = (float) $validated['longitude'];

        $geo = app(GeoLocationService::class)->reverseGeocode($lat, $lon);

        $user->latitude = $lat;
        $user->longitude = $lon;
        $user->city = $geo['city'];
        $user->country = $geo['country'];
        $user->country_code = $geo['country_code'];
        $user->location_synced_at = now();

        if (empty($user->location) || $user->location === 'Classified' || $user->location === '—') {
            $user->location = trim(($geo['city'] ?? '').', '.($geo['country'] ?? ''), ', ');
        }
        $user->save();

        AccessLog::where('user_id', $user->id)->update([
            'latitude' => $lat,
            'longitude' => $lon,
            'city' => $geo['city'],
            'region' => $geo['region'],
            'country' => $geo['country'],
            'country_code' => $geo['country_code'],
            'last_seen_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'latitude' => $lat,
            'longitude' => $lon,
            'city' => $geo['city'],
            'country' => $geo['country'],
            'country_code' => $geo['country_code'],
            'flag' => $geo['flag'],
            'location' => $user->location,
            'message' => __('Profile GPS synchronized successfully.'),
        ]);
    }
}
