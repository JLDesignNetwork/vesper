<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'room_id',
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
            'is_admin' => 'boolean',
            'is_burn_read' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'attachment_size' => 'integer',
        ];
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
     * Get the public attachment URL.
     */
    protected function attachmentUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if (! $this->attachment_path) {
                    return null;
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

