<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'room_id',
        'session_id',
        'alias',
        'ip_address',
        'city',
        'region',
        'country',
        'country_code',
        'latitude',
        'longitude',
        'isp',
        'user_agent',
        'last_seen_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Get the room associated with this access log.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
