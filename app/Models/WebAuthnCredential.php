<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebAuthnCredential extends Model
{
    use HasFactory;

    protected $table = 'webauthn_credentials';

    protected $fillable = [
        'user_id',
        'credential_id',
        'public_key',
        'counter',
        'device_name',
        'transports',
        'last_used_at',
    ];

    protected $casts = [
        'counter' => 'integer',
        'transports' => 'array',
        'last_used_at' => 'datetime',
    ];

    /**
     * The operative that owns this biometric credential.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
