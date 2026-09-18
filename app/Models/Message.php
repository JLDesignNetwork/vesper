<?php

namespace App\Models;

use App\Casts\SafeEncryptedCast;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'room_id',
        'reply_to_id',
        'user_id',
        'sender_name',
        'sender_session_id',
        'is_admin',
        'content',
        'attachment_path',
        'attachment_name',
        'attachment_type',
        'attachment_mime',
        'attachment_size',
        'ip_address',
        'country',
        'country_code',
        'city',
        'latitude',
        'longitude',
        'is_burn_read',
        'ttl_seconds',
        'expires_at',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'attachment_url',
        'formatted_size',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => SafeEncryptedCast::class,
            'is_admin' => 'boolean',
            'is_burn_read' => 'boolean',
            'ttl_seconds' => 'integer',
            'expires_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'attachment_size' => 'integer',
        ];
    }

    /**
     * Get the parent message being replied to, if any.
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    /**
     * Get replies to this message.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }

    /**
     * Get all emoji reactions for this message.
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    /**
     * Get all individual operative views and timers for this message.
     */
    public function views(): HasMany
    {
        return $this->hasMany(MessageUserView::class);
    }

    /**
     * Build aggregated reaction pills summary for the active viewer.
     *
     * @return list<array{emoji: string, count: int, has_reacted: bool}>
     */
    public function reactionsSummary(?string $sessionId, ?int $userId = null): array
    {
        $grouped = [];
        foreach ($this->reactions as $reaction) {
            $emoji = $reaction->emoji;
            if (! isset($grouped[$emoji])) {
                $grouped[$emoji] = [
                    'emoji' => $emoji,
                    'count' => 0,
                    'has_reacted' => false,
                ];
            }
            $grouped[$emoji]['count']++;
            if (($sessionId && $reaction->session_id === $sessionId) || ($userId && $reaction->user_id === $userId)) {
                $grouped[$emoji]['has_reacted'] = true;
            }
        }

        return array_values($grouped);
    }

    /**
     * Get the user that authored the message, if authenticated.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the room that owns the message.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the authenticated streaming attachment URL.
     */
    protected function attachmentUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if (! $this->attachment_path) {
                    return null;
                }

                $roomCode = $this->room?->code;
                if ($roomCode) {
                    return route('messages.attachment', ['room' => $roomCode, 'message' => $this->id]);
                }

                return '/storage/'.ltrim($this->attachment_path, '/');
            },
        );
    }

    /**
     * Format the attachment size into human-readable format.
     */
    protected function formattedSize(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if (! $this->attachment_size) {
                    return null;
                }

                $bytes = $this->attachment_size;
                $units = ['B', 'KB', 'MB', 'GB'];

                for ($i = 0; $bytes >= 1024 && $i < 3; $i++) {
                    $bytes /= 1024;
                }

                return round($bytes, 1).' '.$units[$i];
            },
        );
    }
}
