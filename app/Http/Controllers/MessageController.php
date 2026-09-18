<?php

namespace App\Http\Controllers;

use App\Mail\NewMessageNotification;
use App\Models\AccessLog;
use App\Models\Message;
use App\Models\MessageUserView;
use App\Models\Reaction;
use App\Models\Room;
use App\Models\User;
use App\Services\GeoLocationService;
use App\Services\LanguageService;
use App\Services\MediaStorageService;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
     * Translate message content into target language.
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
            'text' => ['nullable', 'string', 'max:10000'],
            'message_id' => ['nullable', 'integer'],
            'target' => ['nullable', 'string', Rule::in(LanguageService::codes())],
            'target_lang' => ['nullable', 'string', Rule::in(LanguageService::codes())],
        ]);

        $text = $validated['text'] ?? null;
        if (empty($text) && ! empty($validated['message_id'])) {
            $msg = Message::where('room_id', $room->id)->find($validated['message_id']);
            $text = $msg?->content;
        }

        if (empty($text)) {
            return response()->json(['error' => __('Cannot translate an empty message.')], 422);
        }

        $targetLang = $request->input('target_lang') ?: ($validated['target'] ?? 'en');

        if (! $room->supportsLanguage($targetLang)) {
            return response()->json([
                'error' => __('Translation to this language is not available in this channel.'),
            ], 422);
        }

        $result = $this->translationService->translate($text, $targetLang);

        return response()->json($result);
    }

    /**
     * Batch translate multiple message strings or message IDs into target language.
     */
    public function translateBatch(Request $request, string $code): JsonResponse
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
            'messages' => ['required', 'array', 'max:50'],
            'messages.*.id' => ['nullable'],
            'messages.*.text' => ['nullable', 'string', 'max:5000'],
            'target_lang' => ['nullable', 'string', Rule::in(LanguageService::codes())],
        ]);

        $targetLang = $request->input('target_lang') ?: 'en';

        if (! $room->supportsLanguage($targetLang)) {
            return response()->json([
                'error' => __('Translation to this language is not available in this channel.'),
            ], 422);
        }

        $texts = [];
        foreach ($validated['messages'] as $idx => $item) {
            $msgId = (string) ($item['id'] ?? $idx);
            $text = $item['text'] ?? '';
            if (empty($text) && ! empty($item['id'])) {
                $dbMsg = Message::where('room_id', $room->id)->find($item['id']);
                $text = $dbMsg?->content ?? '';
            }
            $texts[$msgId] = (string) $text;
        }

        $result = $this->translationService->translateBatch($texts, $targetLang);

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

        // Decoy / Duress Mode: conceal all actual messages
        if ($request->session()->get("room_is_duress_{$room->id}", false)) {
            return response()->json([
                'status' => 'active',
                'messages' => [],
                'active_count' => 1,
                'active_operatives' => [],
            ]);
        }

        $sessionId = $request->session()->getId();
        $afterId = (int) $request->query('after_id', 0);

        // Throttle last_seen_at updates to once every 30 seconds to minimize DB write locks on high polling
        AccessLog::where('room_id', $room->id)
            ->where('session_id', $sessionId)
            ->where(function ($q) {
                $q->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', now()->subSeconds(30));
            })
            ->update(['last_seen_at' => now()]);

        $query = $room->messages()
            ->with(['user', 'reactions', 'replyTo', 'views'])
            ->where('id', '>', $afterId)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('id', 'asc');

        if ($afterId === 0) {
            $query->limit(100);
        }

        $viewer = Auth::user();
        $targetLang = $request->query('target_lang') ?: ($viewer?->preferred_locale ?: session('locale', config('app.locale', 'en')));
        if (! LanguageService::isValid($targetLang)) {
            $targetLang = 'en';
        }

        $rawMessages = $query->get();

        $resolveView = function (Message $m) use ($viewer, $sessionId): ?MessageUserView {
            return $m->views->first(function (MessageUserView $v) use ($viewer, $sessionId): bool {
                if ($viewer && $v->user_id && $v->user_id === $viewer->id) {
                    return true;
                }

                return $v->session_id === $sessionId;
            });
        };

        // For non-sender messages with ttl_seconds, initialize their personal view countdown upon first viewing
        foreach ($rawMessages as $m) {
            $isSender = ($m->sender_session_id === $sessionId) || ($viewer && $m->user_id === $viewer->id);
            if (! $isSender && ! empty($m->ttl_seconds)) {
                $userView = $resolveView($m);
                if (! $userView) {
                    $newView = MessageUserView::create([
                        'message_id' => $m->id,
                        'user_id' => $viewer?->id,
                        'session_id' => $sessionId,
                        'viewed_at' => now(),
                        'expires_at' => now()->addSeconds((int) $m->ttl_seconds),
                    ]);
                    $m->views->push($newView);
                }
            }
        }

        $resolveSenderExpiresAt = function (Message $message) use ($room): ?\Illuminate\Support\Carbon {
            if (empty($message->ttl_seconds)) {
                return $message->expires_at;
            }

            $recipientViews = $message->views->filter(function (MessageUserView $v) use ($message): bool {
                if ($message->user_id && $v->user_id && $v->user_id === $message->user_id) {
                    return false;
                }

                return $v->session_id !== $message->sender_session_id;
            });

            if ($recipientViews->isEmpty()) {
                return null;
            }

            $allViewed = false;

            // Check enrolled members first if any exist
            $otherMemberIds = $room->members()
                ->when($message->user_id, fn ($q) => $q->where('users.id', '!=', $message->user_id))
                ->pluck('users.id');

            if ($otherMemberIds->isNotEmpty()) {
                $viewedUserIds = $recipientViews->pluck('user_id')->filter()->all();
                $allViewed = $otherMemberIds->every(fn ($id) => in_array($id, $viewedUserIds));
            } else {
                // Passcode / open channel without formal membership:
                // Check distinct other operatives recorded in access logs in last 24h
                $otherOperatives = AccessLog::where('room_id', $room->id)
                    ->where('session_id', '!=', $message->sender_session_id)
                    ->where('last_seen_at', '>=', now()->subHours(24))
                    ->when($message->user_id, fn ($q) => $q->where(function ($sq) use ($message) {
                        $sq->whereNull('user_id')->orWhere('user_id', '!=', $message->user_id);
                    }))
                    ->get();

                if ($otherOperatives->isNotEmpty()) {
                    $allViewed = $otherOperatives->every(function ($op) use ($recipientViews) {
                        if ($op->user_id) {
                            return $recipientViews->contains('user_id', $op->user_id);
                        }

                        return $recipientViews->contains('session_id', $op->session_id);
                    });
                } else {
                    $allViewed = true;
                }
            }

            if (! $allViewed) {
                return null;
            }

            /** @var Carbon|null $maxExpiresAt */
            $maxExpiresAt = $recipientViews->max('expires_at');

            return $maxExpiresAt;
        };

        // Filter out any messages whose per-user countdown has already expired for this viewer
        $rawMessages = $rawMessages->filter(function (Message $m) use ($sessionId, $viewer, $resolveView, $resolveSenderExpiresAt): bool {
            $isSender = ($m->sender_session_id === $sessionId) || ($viewer && $m->user_id === $viewer->id);
            if ($isSender) {
                $senderExpiresAt = $resolveSenderExpiresAt($m);
                if ($senderExpiresAt && $senderExpiresAt <= now()) {
                    if ($m->expires_at === null) {
                        $m->update(['expires_at' => now()->subSecond()]);
                    }

                    return false;
                }

                return true;
            }

            $userView = $resolveView($m);
            if ($userView && $userView->expires_at && $userView->expires_at <= now()) {
                return false;
            }

            return true;
        })->values();

        // Batch-resolve any unlinked authors in one query to prevent N+1
        $unlinkedAuthors = $rawMessages->whereNull('user')->pluck('sender_name')->unique()->filter()->values();
        $usersByName = $unlinkedAuthors->isNotEmpty()
            ? User::whereIn('name', $unlinkedAuthors)->get()->keyBy('name')
            : collect();

        $messages = $rawMessages->map(function (Message $message) use ($sessionId, $viewer, $usersByName, $targetLang, $resolveView, $resolveSenderExpiresAt): array {
            $flag = $message->country_code
                ? $this->geoLocationService->countryCodeToFlag($message->country_code)
                : '🌐';

            $author = $message->user ?: ($usersByName[$message->sender_name] ?? null);

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

            $autoTranslatedText = null;
            if (! empty($message->content)) {
                $trimmed = trim($message->content);
                $cacheKey = 'trans_'.md5($trimmed.'_'.$targetLang);
                $cached = Cache::get($cacheKey);
                if (is_array($cached) && ! empty($cached['success'])) {
                    $autoTranslatedText = $cached['translated_text'];
                }
            }

            $replyToData = null;
            if ($message->replyTo) {
                $replyToData = [
                    'id' => $message->replyTo->id,
                    'sender_name' => $message->replyTo->sender_name,
                    'snippet' => Str::limit($message->replyTo->content ?: ($message->replyTo->attachment_name ?: __('Media Attachment')), 60),
                ];
            }

            $isSender = ($message->sender_session_id === $sessionId) || ($viewer && $message->user_id === $viewer->id);
            $effectiveExpiresAt = $isSender
                ? $resolveSenderExpiresAt($message)
                : ($resolveView($message)?->expires_at ?? $message->expires_at);

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
                'auto_translated_text' => $autoTranslatedText,
                'auto_translated_lang' => $targetLang,
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
                'reply_to' => $replyToData,
                'reactions' => $message->reactionsSummary($sessionId, $viewer?->id),
                'ttl_seconds' => $message->ttl_seconds,
                'expires_at' => $effectiveExpiresAt?->toIso8601String(),
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

        $pinnedMsg = $room->pinnedMessage;

        return response()->json([
            'status' => 'active',
            'messages' => $messages,
            'pinned_message' => $pinnedMsg ? [
                'id' => $pinnedMsg->id,
                'sender_name' => $pinnedMsg->sender_name,
                'content' => Str::limit($pinnedMsg->content ?: ($pinnedMsg->attachment_name ?: __('Media Attachment')), 120),
                'created_at_time' => $pinnedMsg->created_at?->format('H:i:s') ?? '',
            ] : null,
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
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
            'ttl_seconds' => ['nullable', 'integer', 'min:5', 'max:604800'],
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

        $rawLat = ($user && $user->hasGps()) ? (float) $user->latitude : (float) $geo['latitude'];
        $rawLon = ($user && $user->hasGps()) ? (float) $user->longitude : (float) $geo['longitude'];
        $jitteredGps = $this->geoLocationService->jitterCoordinates($rawLat, $rawLon, 3.0);
        $anonymizedIp = $this->geoLocationService->anonymizeIp($clientIp);

        $ttlSeconds = ! empty($validated['ttl_seconds'])
            ? (int) $validated['ttl_seconds']
            : null;

        $message = Message::create([
            'room_id' => $room->id,
            'reply_to_id' => $validated['reply_to_id'] ?? null,
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
            'ip_address' => $anonymizedIp,
            'country' => $user?->country ?: $geo['country'],
            'country_code' => $user?->country_code ?: $geo['country_code'],
            'city' => $user?->city ?: $geo['city'],
            'latitude' => $jitteredGps['lat'],
            'longitude' => $jitteredGps['lon'],
            'ttl_seconds' => $ttlSeconds,
            'expires_at' => null,
            'is_burn_read' => false,
        ]);

        if (! empty($message->content)) {
            try {
                foreach (['en', 'ru', 'fr', 'it'] as $loc) {
                    $this->translationService->translate($message->content, $loc);
                }
            } catch (\Throwable $e) {
                // Pre-warm failure does not block message transmission
            }
        }

        $flag = $message->country_code
            ? $this->geoLocationService->countryCodeToFlag($message->country_code)
            : '🌐';

        // Dispatch email notification to opted-in subscribers
        $this->notifySubscribers($room, $message, $user?->id);

        $replyToData = null;
        if ($message->replyTo) {
            $replyToData = [
                'id' => $message->replyTo->id,
                'sender_name' => $message->replyTo->sender_name,
                'snippet' => Str::limit($message->replyTo->content ?: ($message->replyTo->attachment_name ?: __('Media Attachment')), 60),
            ];
        }

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
                'reply_to' => $replyToData,
                'reactions' => [],
                'ttl_seconds' => $message->ttl_seconds,
                'expires_at' => $message->expires_at?->toIso8601String(),
                'created_at_human' => 'Just now',
                'created_at_time' => $message->created_at->format('H:i:s'),
            ],
        ]);
    }

    /**
     * Toggle an emoji reaction on a message.
     */
    public function toggleReaction(Request $request, string $code, int|string $messageId): JsonResponse
    {
        $code = strtoupper(trim($code));
        $room = Room::where('code', $code)->first();

        if (! $room || $room->status !== 'active') {
            return response()->json(['error' => 'Channel terminated'], 410);
        }

        if (! $request->session()->get("room_clearance_{$room->id}", false)) {
            return response()->json(['error' => 'Clearance required'], 403);
        }

        $message = Message::where('room_id', $room->id)->find($messageId);
        if (! $message) {
            return response()->json(['error' => 'Message not found'], 404);
        }

        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:16'],
        ]);

        $emoji = trim($validated['emoji']);
        $sessionId = $request->session()->getId();
        $userId = Auth::id();

        $existing = Reaction::where('message_id', $message->id)
            ->where(function ($q) use ($sessionId, $userId) {
                if ($userId) {
                    $q->where('user_id', $userId);
                } else {
                    $q->where('session_id', $sessionId);
                }
            })
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();
            $action = 'removed';
        } else {
            Reaction::create([
                'room_id' => $room->id,
                'message_id' => $message->id,
                'user_id' => $userId,
                'session_id' => $sessionId,
                'emoji' => $emoji,
            ]);
            $action = 'added';
        }

        $message->load('reactions');

        return response()->json([
            'success' => true,
            'action' => $action,
            'reactions' => $message->reactionsSummary($sessionId, $userId),
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

    /**
     * Stream an attachment securely after verifying room clearance.
     */
    public function streamAttachment(Request $request, string $code, int|string $messageId)
    {
        $code = strtoupper(trim($code));
        $room = Room::where('code', $code)->first();

        if (! $room || $room->status !== 'active') {
            abort(404, 'Channel not found');
        }

        if (! $request->session()->get("room_clearance_{$room->id}", false)) {
            abort(403, 'Room clearance required to access media');
        }

        $message = Message::where('room_id', $room->id)->find($messageId);
        if (! $message || ! $message->attachment_path) {
            abort(404, 'Attachment not found');
        }

        $disk = 'local';
        if (! Storage::disk('local')->exists($message->attachment_path)) {
            if (Storage::disk('public')->exists($message->attachment_path)) {
                $disk = 'public';
            } else {
                abort(404, 'File not found');
            }
        }

        $fullPath = Storage::disk($disk)->path($message->attachment_path);
        $mime = $message->attachment_mime ?: mime_content_type($fullPath) ?: 'application/octet-stream';
        $fileName = $message->attachment_name ?: basename($fullPath);
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.addslashes($fileName).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
