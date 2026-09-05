<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChannelInvitation extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'room_id',
        'created_by_user_id',
        'token',
        'code',
        'max_uses',
        'uses_count',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the room this invitation admits to.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the user who created this invitation.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Determine if this invitation is still valid.
     */
    public function isValid(): bool
    {
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses > 0 && $this->uses_count >= $this->max_uses) {
            return false;
        }

        return $this->room && $this->room->status === 'active';
    }

    /**
     * Redeem this invitation for a specific user.
     */
    public function consume(User $user): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        $this->room->addMember($user, 'member', $this->created_by_user_id);
        $this->increment('uses_count');

        return true;
    }

    /**
     * Generate a new invitation for a room.
     */
    public static function createForRoom(
        Room $room,
        User $creator,
        ?int $maxUses = null,
        \DateTimeInterface|int|null $expiresAt = null,
        ?int $expiresInDays = null
    ): self {
        $code = 'INV-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        $token = Str::random(64);

        $expiration = null;
        if ($expiresAt instanceof \DateTimeInterface) {
            $expiration = $expiresAt;
        } elseif (is_int($expiresAt)) {
            $expiration = now()->addDays($expiresAt);
        } elseif ($expiresInDays) {
            $expiration = now()->addDays($expiresInDays);
        }

        return self::create([
            'room_id' => $room->id,
            'created_by_user_id' => $creator->id,
            'token' => $token,
            'code' => $code,
            'max_uses' => $maxUses,
            'uses_count' => 0,
            'expires_at' => $expiration,
        ]);
    }
}
