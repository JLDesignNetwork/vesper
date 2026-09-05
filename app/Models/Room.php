<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class Room extends Model
{
    use HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'title',
        'passcode_hash',
        'pin',
        'allowed_languages',
        'burn_after_reading',
        'notify_admin',
        'expires_at',
        'created_by_ip',
        'created_by_user_id',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'burn_after_reading' => 'boolean',
            'notify_admin' => 'boolean',
            'expires_at' => 'datetime',
            'allowed_languages' => 'array',
        ];
    }

    /**
     * Get the user who created this room.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get all messages in this room.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get all access logs for this room.
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class);
    }

    /**
     * Verify the given passcode against the room's stored PIN or hash.
     */
    public function verifyPasscode(string $passcode): bool
    {
        if (! empty($this->pin) && $this->pin === $passcode) {
            return true;
        }

        return ! empty($this->passcode_hash) && Hash::check($passcode, $this->passcode_hash);
    }

    /**
     * Determine if a target language code is permitted in this channel.
     */
    public function supportsLanguage(string $lang): bool
    {
        $allowed = $this->effectiveAllowedLanguages();

        return in_array(strtolower($lang), array_map('strtolower', $allowed), true);
    }

    /**
     * Return list of enabled translation languages for this channel.
     *
     * @return list<string>
     */
    public function effectiveAllowedLanguages(): array
    {
        return ! empty($this->allowed_languages) && is_array($this->allowed_languages)
            ? array_values($this->allowed_languages)
            : ['en', 'ru', 'fr', 'it'];
    }

    /**
     * Determine whether the room has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Purge all physical media files associated with this room.
     */
    public function purgeAllMedia(): void
    {
        Storage::disk('public')->deleteDirectory("attachments/{$this->id}");
    }
}

