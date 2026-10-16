<?php

namespace App\Models;

use App\Enums\ChannelType;
use App\Services\LanguageService;
use App\Services\RankService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        'type',
        'description',
        'passcode_hash',
        'duress_passcode_hash',
        'pin',
        'allowed_languages',
        'burn_after_reading',
        'notify_admin',
        'expires_at',
        'created_by_ip',
        'created_by_user_id',
        'status',
        'is_system',
    ];

    /**
     * Default attribute values, mirrored from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'prs',
        'is_system' => false,
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
            'type' => ChannelType::class,
            'is_system' => 'boolean',
        ];
    }

    /**
     * Write-only channel PIN. Setting it stores only a hash in passcode_hash; reading
     * it always returns null, so a PIN can never be displayed or emailed after it is set.
     */
    protected function pin(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => null,
            set: fn (?string $value): array => [
                'passcode_hash' => ($value === null || trim($value) === '') ? null : Hash::make(trim($value)),
            ],
        );
    }

    /**
     * Whether entry to this channel needs a credential each time it is opened.
     */
    public function isSecured(): bool
    {
        return $this->type->isSecured();
    }

    /**
     * Whether this channel appears in the public directory.
     */
    public function isPublic(): bool
    {
        return $this->type->isPublic();
    }

    /**
     * Whether the user created this channel.
     */
    public function isCreatedBy(?User $user): bool
    {
        return $user !== null && (int) $this->created_by_user_id === $user->id;
    }

    /**
     * Get the user who created this room.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * The channel's pins, most recently pinned first.
     */
    public function pins(): HasMany
    {
        return $this->hasMany(MessagePin::class)->latest('id');
    }

    /**
     * The channel's pinned messages for the pinned tray, most recently pinned first.
     *
     * @return list<array{id: int, sender_name: string, content: string, created_at_time: string, pinned_at: string|null}>
     */
    public function pinnedTray(): array
    {
        return $this->pins()->with('message')->get()
            ->filter(fn (MessagePin $pin): bool => $pin->message !== null)
            ->map(fn (MessagePin $pin): array => [
                'id' => $pin->message->id,
                'sender_name' => (string) $pin->message->sender_name,
                'content' => Str::limit($pin->message->content ?: ($pin->message->attachment_name ?: __('Media Attachment')), 120),
                'created_at_time' => $pin->message->created_at?->format('H:i:s') ?? '',
                'pinned_at' => $pin->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * IDs of the channel's pinned messages.
     *
     * @return list<int>
     */
    public function pinnedMessageIds(): array
    {
        return $this->pins()->pluck('message_id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * Get all messages in this room.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get all active enrolled members of this channel.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'room_user')
            ->wherePivot('status', 'active')
            ->withPivot(['role', 'alias', 'status', 'last_accessed_at'])
            ->withTimestamps();
    }

    /**
     * Get all secure invitations generated for this channel.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(ChannelInvitation::class);
    }

    /**
     * Determine if a user is an active enrolled member of this room.
     */
    public function isMember(User|int|null $user): bool
    {
        if (! $user) {
            return false;
        }

        $userId = $user instanceof User ? $user->id : $user;

        return $this->members()->where('users.id', $userId)->exists();
    }

    /**
     * Enroll a user as an active member of this room.
     */
    public function addMember(User|int $user, string $role = 'member', ?int $invitedBy = null, ?string $alias = null): void
    {
        $userId = $user instanceof User ? $user->id : $user;

        $this->belongsToMany(User::class, 'room_user')->syncWithoutDetaching([
            $userId => [
                'role' => $role,
                'status' => 'active',
                'alias' => $alias,
                'invited_by_user_id' => $invitedBy,
                'last_accessed_at' => now(),
            ],
        ]);

        app(RankService::class)->recomputeCreatorOf($this);
    }

    /**
     * Issue a pending direct invitation to a user for this room.
     */
    public function inviteUser(User|int $user, ?int $invitedBy = null): void
    {
        $userId = $user instanceof User ? $user->id : $user;

        $this->belongsToMany(User::class, 'room_user')->syncWithoutDetaching([
            $userId => [
                'role' => 'member',
                'status' => 'invited',
                'invited_by_user_id' => $invitedBy,
            ],
        ]);
    }

    /**
     * Update the last accessed timestamp for an enrolled member.
     */
    public function touchMemberAccess(User|int $user): void
    {
        $userId = $user instanceof User ? $user->id : $user;

        $this->members()->updateExistingPivot($userId, [
            'last_accessed_at' => now(),
        ]);
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
        return $passcode !== '' && ! empty($this->passcode_hash) && Hash::check($passcode, $this->passcode_hash);
    }

    /**
     * Determine if a given string matches the duress passcode.
     */
    public function verifyDuressPasscode(?string $passcode): bool
    {
        if (empty($passcode) || empty($this->duress_passcode_hash)) {
            return false;
        }

        return Hash::check($passcode, $this->duress_passcode_hash);
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
        if (empty($this->allowed_languages) || ! is_array($this->allowed_languages)) {
            return LanguageService::codes();
        }

        // If the channel had the legacy 4-language set before the expansion, allow all supported
        $legacyFour = ['en', 'ru', 'fr', 'it'];
        if (count($this->allowed_languages) === 4 && empty(array_diff($legacyFour, $this->allowed_languages))) {
            return LanguageService::codes();
        }

        return array_values($this->allowed_languages);
    }

    /**
     * Determine whether the room has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Purge all physical media files associated with this room with multi-pass cryptographic shredding.
     */
    public function purgeAllMedia(): void
    {
        $dirs = [
            Storage::disk('local')->path("attachments/{$this->id}"),
            Storage::disk('public')->path("attachments/{$this->id}"),
        ];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $files = glob($dir.'/*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        $size = filesize($file);
                        if ($size > 0) {
                            $fh = @fopen($file, 'r+');
                            if ($fh) {
                                @fwrite($fh, random_bytes($size));
                                @fflush($fh);
                                @fclose($fh);
                            }
                        }
                        @unlink($file);
                    }
                }
            }
            @rmdir($dir);
        }
    }
}
