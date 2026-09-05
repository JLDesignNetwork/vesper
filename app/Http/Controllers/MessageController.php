<?php

namespace App\Http\Controllers;

use App\Mail\NewMessageNotification;
use App\Models\AccessLog;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\GeoLocationService;
use App\Services\MediaStorageService;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MessageController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        public GeoLocationService $geoLocationService,
        public MediaStorageService $mediaStorageService,
        public TranslationService $translationService
    ) {}

    /**
     * Translate message content into Russian (or target language).
     */
    public function translate(Request $request, string $code): JsonResponse
    {
        $code = strtoupper(trim($code));
        $room = Room::where('code', $code)->first();

        if (! $room || $room->status !== 'active') {
            return response()->json(['error' => 'Channel terminated'], 410);
        }

        if (! $request->session()->get("room_clearance_{$room->id}", false)) {
            return response()->json(['error' => 'Clearance required'], 403);
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:10000'],
            'target' => ['nullable', 'string', 'in:ru,fr,it,en'],
            'target_lang' => ['nullable', 'string', 'in:ru,fr,it,en'],
        ]);

        $targetLang = $request->input('target_lang') ?: ($validated['target'] ?? 'ru');

        if (! $room->supportsLanguage($targetLang)) {
            return response()->json([
                'error' => __('Translation to this language is not available in this channel.'),
            ], 422);
        }

        $result = $this->translationService->translate($validated['text'], $targetLang);

        return response()->json($result);
    }

    /**
     * Fetch message stream for live updates.
     */
    public function index(Request $request, string $code): JsonResponse
    {
        $code = strtoupper(trim($code));
        $room = Room::where('code', $code)->first();

        if (! $room || $room->status !== 'active') {
            return response()->json([
                'error' => 'Channel terminated',
                'status' => 'destroyed',
                'messages' => [],
            ], 410);
        }

        if (! $request->session()->get("room_clearance_{$room->id}", false)) {
            return response()->json(['error' => 'Clearance required'], 403);
        }

        $sessionId = $request->session()->getId();
        $afterId = (int) $request->query('after_id', 0);

        AccessLog::where('room_id', $room->id)
            ->where('session_id', $sessionId)
            ->update(['last_seen_at' => now()]);

        $query = $room->messages()->with('user')->where('id', '>', $afterId)->orderBy('id', 'asc');

        if ($afterId === 0) {
            $query->limit(100);
        }

        $viewer = Auth::user();

        $messages = $query->get()->map(function (Message $message) use ($sessionId, $viewer): array {
            $flag = $message->country_code
                ? $this->geoLocationService->countryCodeToFlag($message->country_code)
                : '🌐';

            $author = $message->user ?: User::where('name', $message->sender_name)->first();

            $senderAge = $author ? $author->ageForViewer($viewer) : null;
            $senderGender = $author?->gender;
            $senderLocation = $author ? $author->locationForViewer($viewer) : null;
            $senderBio = $author ? $author->bioForViewer($viewer) : null;
            $senderBirthday = $author ? $author->birthdayForViewer($viewer) : null;
            $senderRole = $author?->role ?? ($message->is_admin ? 'admin' : 'guest');
            $senderAvatarUrl = $author?->avatarUrl();

            $isLocationHidden = (bool) ($author?->hide_location);
            $canViewPrivate = ($viewer && $viewer->isAdmin()) || ($viewer && $author && $viewer->id === $author->id);

            $msgCity = ($isLocationHidden && ! $canViewPrivate) ? null : $message->city;
            $msgCountry = ($isLocationHidden && ! $canViewPrivate) ? null : $message->country;
            $msgFlag = ($isLocationHidden && ! $canViewPrivate) ? '🔒' : $flag;
            $msgIp = ($canViewPrivate || ! $isLocationHidden) ? $message->ip_address : '***.***.***.***';

            return [
                'id' => $message->id,
                'sender_name' => $message->sender_name,
                'is_self' => $message->sender_session_id === $sessionId,
                'is_admin' => (bool) $message->is_admin,
                'sender_role' => $senderRole,
                'sender_age' => $senderAge,
                'sender_gender' => $senderGender,
                'sender_location' => $senderLocation,
                'sender_bio' => $senderBio,
                'sender_birthday' => $senderBirthday,
                'sender_avatar_url' => $senderAvatarUrl,
                'content' => $message->content,
                'attachment_url' => $message->attachment_url,
                'attachment_name' => $message->attachment_name,
                'attachment_type' => $message->attachment_type,
                'attachment_mime' => $message->attachment_mime,
                'formatted_size' => $message->formatted_size,
                'ip_address' => $msgIp,
                'country' => $msgCountry,
                'city' => $msgCity,
                'flag' => $msgFlag,
                'location_hidden' => $isLocationHidden,
                'created_at_human' => $message->created_at?->diffForHumans() ?? 'Just now',
                'created_at_time' => $message->created_at?->format('H:i:s') ?? '',
            ];
        });

        if ($room->burn_after_reading) {
            $otherMessages = $messages->filter(fn (array $m): bool => ! $m['is_self']);
            if ($otherMessages->isNotEmpty()) {
                Message::whereIn('id', $otherMessages->pluck('id'))->update(['is_burn_read' => true]);
            }
        }

        $activeThreshold = now()->subMinutes(5);
        $activeOperatives = AccessLog::where('room_id', $room->id)
            ->where('last_seen_at', '>=', $activeThreshold)
            ->orderBy('last_seen_at', 'desc')
            ->get()
            ->map(function ($log) {
                return [
                    'alias' => $log->alias,
                    'city' => $log->city,
                    'country' => $log->country,
                    'flag' => $log->country_code ? $this->geoLocationService->countryCodeToFlag($log->country_code) : '🌐',
                    'last_seen' => $log->last_seen_at?->diffForHumans() ?? 'Active',
                ];
            });

        return response()->json([
            'status' => 'active',
            'messages' => $messages,
            'active_count' => $activeOperatives->count(),
            'active_operatives' => $activeOperatives,
        ]);
    }

    /**
     * Store and broadcast a new message or media upload.
     */
    public function store(Request $request, string $code): JsonResponse
    {
        $code = strtoupper(trim($code));
        $room = Room::where('code', $code)->first();

        if (! $room || $room->status !== 'active') {
            return response()->json(['error' => 'Channel terminated'], 410);
        }

        if (! $request->session()->get("room_clearance_{$room->id}", false)) {
            return response()->json(['error' => 'Clearance required'], 403);
        }

        $validated = $request->validate([
            'content' => ['nullable', 'string', 'max:10000'],
            'attachment' => ['nullable', 'file', 'max:51200'],
        ]);

        if (empty($validated['content']) && ! $request->hasFile('attachment')) {
            return response()->json(['error' => 'Cannot transmit an empty message.'], 422);
        }

        $attachmentData = null;
        if ($request->hasFile('attachment')) {
            $attachmentData = $this->mediaStorageService->storeAttachment(
                $request->file('attachment'),
                $room->id
            );
        }

        $clientIp = $this->geoLocationService->getClientIp($request);
        $geo = $this->geoLocationService->locate($clientIp);
        $sessionId = $request->session()->getId();
        $user = Auth::user();
        $isAdmin = (bool) $request->session()->get("room_is_admin_{$room->id}", false) || ($user && $user->isAdmin());
        $alias = $request->session()->get("room_alias_{$room->id}");

        if ($user) {
            $alias = $user->name;
        } elseif (! $alias) {
            $alias = 'Guest';
        }

        $message = Message::create([
            'room_id' => $room->id,
            'user_id' => $user?->id,
            'sender_name' => $alias,
            'sender_session_id' => $sessionId,
            'is_admin' => $isAdmin,
            'content' => $validated['content'] ?? null,
            'attachment_path' => $attachmentData['path'] ?? null,
            'attachment_name' => $attachmentData['name'] ?? null,
            'attachment_type' => $attachmentData['type'] ?? null,
            'attachment_mime' => $attachmentData['mime'] ?? null,
            'attachment_size' => $attachmentData['size'] ?? null,
            'is_burn_read' => false,
        ]);

        $flag = $message->country_code
            ? $this->geoLocationService->countryCodeToFlag($message->country_code)
            : '🌐';

        // Dispatch email notification to opted-in subscribers
        $this->notifySubscribers($room, $message, $user?->id);

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'sender_name' => $message->sender_name,
                'is_self' => true,
                'is_admin' => (bool) $message->is_admin,
                'sender_role' => $user?->role ?? ($message->is_admin ? 'admin' : 'guest'),
                'sender_age' => $user?->age(),
                'sender_gender' => $user?->gender,
                'sender_location' => $user?->location,
                'sender_bio' => $user?->bio,
                'sender_birthday' => $user?->birthday?->format('Y-m-d'),
                'content' => $message->content,
                'attachment_url' => $message->attachment_url,
                'attachment_name' => $message->attachment_name,
                'attachment_type' => $message->attachment_type,
                'attachment_mime' => $message->attachment_mime,
                'formatted_size' => $message->formatted_size,
                'ip_address' => $message->ip_address,
                'country' => $message->country,
                'city' => $message->city,
                'flag' => $flag,
                'created_at_human' => 'Just now',
                'created_at_time' => $message->created_at->format('H:i:s'),
            ],
        ]);
    }

    /**
     * Send email notifications to users who opted in.
     * Admin notification setting is controlled per channel via $room->notify_admin.
     * Regular members opt in via their profile email_notifications toggle.
     */
    protected function notifySubscribers(Room $room, Message $message, ?string $senderUserId): void
    {
        try {
            $recipients = collect();

            // 1. Channel-level Admin notification
            if ($room->notify_admin) {
                $admins = User::where(function ($q) use ($room) {
                    $q->where('role', 'admin')
                        ->orWhere('id', $room->created_by_user_id);
                })
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->when($senderUserId, fn ($q) => $q->where('id', '!=', $senderUserId))
                    ->get();

                $recipients = $recipients->merge($admins);
            }

            // 2. Regular channel participants with email_notifications enabled in profile
            $members = User::where('role', '!=', 'admin')
                ->where('id', '!=', $room->created_by_user_id)
                ->where('email_notifications', true)
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->when($senderUserId, fn ($q) => $q->where('id', '!=', $senderUserId))
                ->whereIn('id', Message::where('room_id', $room->id)->select('user_id'))
                ->get();

            $recipients = $recipients->merge($members)->unique('id');

            foreach ($recipients as $recipient) {
                Mail::to($recipient->email)->send(new NewMessageNotification($room, $message, $recipient));
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to send message email notification: {$e->getMessage()}");
        }
    }
}

