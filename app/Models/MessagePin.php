<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message pinned to the top of its channel. Pinned messages are exempt from every
 * self-destruct timer while they stay pinned. A channel holds at most 10 pins.
 */
class MessagePin extends Model
{
    public const int MAX_PER_CHANNEL = 10;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'room_id',
        'message_id',
        'pinned_by_user_id',
    ];

    /**
     * The pinned message.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * The channel the pin belongs to.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * The member who pinned the message.
     */
    public function pinnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by_user_id');
    }
}
