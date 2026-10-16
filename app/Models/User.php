<?php

namespace App\Models;

use App\Enums\AdminPanelScope;
use App\Enums\Rank;
use App\Mail\PrimaryEmailVerification;
use App\Services\GeoLocationService;
use App\Services\LanguageService;
use App\Services\UserTagService;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

#[Fillable(['name', 'tag', 'email', 'password', 'birthday', 'gender', 'location', 'latitude', 'longitude', 'city', 'country', 'country_code', 'location_synced_at', 'hide_age', 'hide_birthday', 'hide_location', 'hide_bio', 'bio', 'email_notifications', 'avatar_path', 'preferred_locale', 'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes', 'recovery_email', 'recovery_email_verified_at', 'recovery_token', 'recovery_token_expires_at'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'recovery_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Default attribute values, mirrored from the database so a freshly created
     * model has a rank before it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'rank' => 'user_two',
    ];

    /**
     * Give every new member a unique @tag generated from their name.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->tag)) {
                $user->tag = app(UserTagService::class)->generateFor((string) $user->name);
            }
        });
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'latest_ip',
        'effective_locale',
        'location_locale',
        'has_two_factor',
        'has_biometrics',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rank' => Rank::class,
            'rank_computed_at' => 'datetime',
            'banned_at' => 'datetime',
            'birthday' => 'date',
            'latitude' => 'float',
            'longitude' => 'float',
            'location_synced_at' => 'datetime',
            'hide_age' => 'boolean',
            'hide_birthday' => 'boolean',
            'hide_location' => 'boolean',
            'hide_bio' => 'boolean',
            'email_notifications' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'array',
            'recovery_email_verified_at' => 'datetime',
            'recovery_token_expires_at' => 'datetime',
        ];
    }

    /**
     * Get all channels this user is an active enrolled member of.
     */
    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'room_user')
            ->wherePivot('status', 'active')
            ->withPivot(['role', 'alias', 'status', 'last_accessed_at', 'biometric_entry'])
            ->withTimestamps();
    }

    /**
     * Ban cases in which this member is the subject.
     */
    public function banCases(): HasMany
    {
        return $this->hasMany(BanCase::class, 'subject_user_id');
    }

    /**
     * What this member sees in the admin panel, or null when they have no admin panel.
     */
    public function adminPanelScope(): ?AdminPanelScope
    {
        return $this->isBanned() ? null : AdminPanelScope::forRank($this->rank);
    }

    /**
     * The member as shown in the admin panel's profile dossier to the given viewer.
     * Hidden profile fields appear only for viewers allowed to see them, and IP addresses
     * and email addresses only for panel scopes that show them.
     *
     * @return array<string, mixed>
     */
    public function adminDossierFor(User $viewer): array
    {
        $scope = $viewer->adminPanelScope();
        $seesHidden = $this->hiddenFieldsVisibleTo($viewer);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'tag' => $this->tag,
            'email' => $scope?->showsEmails() ? $this->email : null,
            'avatar_url' => $this->avatarUrl(),
            'avatar_path' => $this->avatar_path,
            'rank' => $this->rank->value,
            'birthday' => ($seesHidden || ! $this->hide_birthday) && ($seesHidden || ! $this->hide_age) ? $this->birthday?->format('Y-m-d') : null,
            'gender' => $this->gender,
            'location' => ($seesHidden || ! $this->hide_location) ? $this->location : null,
            'bio' => ($seesHidden || ! $this->hide_bio) ? $this->bio : null,
            'hide_age' => (bool) $this->hide_age,
            'hide_birthday' => (bool) $this->hide_birthday,
            'hide_location' => (bool) $this->hide_location,
            'hide_bio' => (bool) $this->hide_bio,
            'email_notifications' => (bool) $this->email_notifications,
            'preferred_locale' => $seesHidden ? $this->preferred_locale : null,
            'location_locale' => $this->resolveLocationLocale(),
            'latest_ip' => $scope?->showsGeolocation() ? $this->latestIp() : null,
            'has_two_factor' => $this->hasTwoFactor(),
            'has_biometrics' => $this->hasBiometrics(),
            'created_at' => $this->created_at?->toIso8601String(),
            'hidden_fields_visible' => $seesHidden,
        ];
    }

    /**
     * Whether the member has been banned from Vesper.
     */
    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    /**
     * Whether the member is restricted (read-only) while a ban case about them is open.
     */
    public function isRestricted(): bool
    {
        return $this->banCases()->open()->exists();
    }

    /**
     * Get all pending channel invitations for this user.
     */
    public function pendingChannelInvitations(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'room_user')
            ->wherePivot('status', 'invited')
            ->withPivot(['role', 'alias', 'status', 'invited_by_user_id'])
            ->withTimestamps();
    }

    /**
     * Get all rooms created by this user.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'created_by_user_id');
    }

    /**
     * Get all access logs associated with this user.
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class);
    }

    /**
     * Get the most recent access log entry for this user.
     */
    public function latestAccessLog(): HasOne
    {
        return $this->hasOne(AccessLog::class)->latestOfMany('last_seen_at');
    }

    /**
     * Resolve the latest recorded client IP address for this user.
     */
    public function latestIp(): ?string
    {
        return $this->latestAccessLog?->ip_address
            ?: $this->accessLogs()->latest('last_seen_at')->value('ip_address');
    }

    /**
     * Get the latest_ip attribute for serialization.
     */
    public function getLatestIpAttribute(): ?string
    {
        return $this->latestIp();
    }

    /**
     * Determine if this user has synchronized valid GPS coordinates.
     */
    public function hasGps(): bool
    {
        return ! is_null($this->latitude) && ! is_null($this->longitude);
    }

    /**
     * Determine if this user holds the given rank.
     */
    public function hasRank(Rank $rank): bool
    {
        return $this->rank === $rank;
    }

    /**
     * Determine if this user is publicly shown as an Admin (Admin I or Admin II).
     */
    public function displaysAsAdmin(): bool
    {
        return $this->rank->displaysAsAdmin();
    }

    /**
     * Determine the user's primary post-login destination based on rank.
     */
    public function homeRoute(): string
    {
        return $this->displaysAsAdmin() && $this->can('accessAdminPanel') ? route('admin.dashboard') : route('profile.show');
    }

    /**
     * Determine whether the user meets the contact precondition for biometric setup:
     * a verified primary email plus either a verified recovery email or confirmed 2FA.
     */
    public function meetsBiometricPrecondition(): bool
    {
        return $this->hasVerifiedEmail()
            && ($this->hasVerifiedRecoveryEmail() || $this->hasTwoFactor());
    }

    /**
     * Send a signed link that confirms ownership of the primary email address.
     */
    public function sendEmailVerificationNotification(): void
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())]
        );

        try {
            Mail::to($this->email)->send(new PrimaryEmailVerification($this, $verificationUrl));
        } catch (\Throwable $e) {
            Log::warning("Failed to dispatch primary email verification to user {$this->id}: {$e->getMessage()}");
        }
    }

    /**
     * Determine if this user may open PIN-secured channels without the PIN: with confirmed
     * 2FA, or with an enrolled passkey once they meet the biometric precondition.
     */
    public function canUsePinlessEntry(): bool
    {
        return $this->hasTwoFactor() || ($this->hasBiometrics() && $this->meetsBiometricPrecondition());
    }

    /**
     * Calculate age from birthday if set.
     */
    public function age(): ?int
    {
        return $this->birthday ? (int) $this->birthday->diffInYears(now()) : null;
    }

    /**
     * Determine whether a viewer may see the fields this user has chosen to hide.
     * Only the user themself and Admin I may (see UserPolicy::viewHiddenFields).
     */
    public function hiddenFieldsVisibleTo(?User $viewer): bool
    {
        return $viewer !== null && $viewer->can('viewHiddenFields', $this);
    }

    /**
     * Get the birthday formatted according to privacy visibility rules.
     * If viewer is Admin I or self, returns full 'Y-m-d'.
     * If birthday is hidden, returns null.
     * If age is hidden, returns only Month & Day ('F j', e.g. "June 20").
     * Otherwise, returns full 'Y-m-d'.
     */
    public function birthdayForViewer(?User $viewer): ?string
    {
        if (! $this->birthday) {
            return null;
        }

        $canViewPrivate = $this->hiddenFieldsVisibleTo($viewer);

        if ($canViewPrivate) {
            return $this->birthday->format('Y-m-d');
        }

        if ($this->hide_birthday) {
            return null;
        }

        if ($this->hide_age) {
            return $this->birthday->format('F j');
        }

        return $this->birthday->format('Y-m-d');
    }

    /**
     * Get the age for a viewer respecting privacy settings.
     */
    public function ageForViewer(?User $viewer): ?int
    {
        $canViewPrivate = $this->hiddenFieldsVisibleTo($viewer);

        if ($canViewPrivate || ! $this->hide_age) {
            return $this->age();
        }

        return null;
    }

    /**
     * Get the location for a viewer respecting privacy settings.
     */
    public function locationForViewer(?User $viewer): ?string
    {
        $canViewPrivate = $this->hiddenFieldsVisibleTo($viewer);

        return ($canViewPrivate || ! $this->hide_location) ? $this->location : null;
    }

    /**
     * Get the bio for a viewer respecting privacy settings.
     */
    public function bioForViewer(?User $viewer): ?string
    {
        $canViewPrivate = $this->hiddenFieldsVisibleTo($viewer);

        return ($canViewPrivate || ! $this->hide_bio) ? $this->bio : null;
    }

    /**
     * Get the absolute public URL to the user's custom avatar, if uploaded.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? asset('storage/'.$this->avatar_path) : null;
    }

    /**
     * Resolve the common language of this user's registered location.
     */
    public function resolveLocationLocale(): string
    {
        return app(GeoLocationService::class)->resolveLanguageFromLocation(
            $this->country_code,
            $this->country,
            $this->location
        );
    }

    /**
     * Get the effective platform language for this user.
     * If user explicitly set preferred_locale, this overrides the location language.
     * Otherwise, defaults to the common language of the registered location.
     */
    public function effectiveLocale(): string
    {
        if (! empty($this->preferred_locale) && LanguageService::isValid($this->preferred_locale)) {
            return $this->preferred_locale;
        }

        return $this->resolveLocationLocale();
    }

    /**
     * Get the effective_locale attribute for JSON serialization.
     */
    public function getEffectiveLocaleAttribute(): string
    {
        return $this->effectiveLocale();
    }

    /**
     * Get the location_locale attribute for JSON serialization.
     */
    public function getLocationLocaleAttribute(): string
    {
        return $this->resolveLocationLocale();
    }

    /**
     * Registered hardware biometric authenticators (Touch ID, Face ID, Passkeys).
     */
    public function webauthnCredentials(): HasMany
    {
        return $this->hasMany(WebAuthnCredential::class);
    }

    /**
     * Linked external social/OAuth identities (Google, Apple).
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Whether this operative has confirmed two-factor authentication enabled.
     */
    public function hasTwoFactor(): bool
    {
        return ! empty($this->two_factor_secret) && ! empty($this->two_factor_confirmed_at);
    }

    /**
     * Accessor for has_two_factor.
     */
    public function getHasTwoFactorAttribute(): bool
    {
        return $this->hasTwoFactor();
    }

    /**
     * Whether this operative has at least one enrolled hardware biometric passkey.
     */
    public function hasBiometrics(): bool
    {
        return $this->webauthnCredentials()->exists();
    }

    /**
     * Accessor for has_biometrics.
     */
    public function getHasBiometricsAttribute(): bool
    {
        return $this->hasBiometrics();
    }

    /**
     * Whether this operative has a verified recovery email address.
     */
    public function hasVerifiedRecoveryEmail(): bool
    {
        return ! empty($this->recovery_email) && ! empty($this->recovery_email_verified_at);
    }

    /**
     * Check if user is linked to a specific social provider.
     */
    public function hasSocial(string $provider): bool
    {
        return $this->socialAccounts()->where('provider', $provider)->exists();
    }

    /**
     * Generate 8 cryptographically secure single-use recovery codes.
     * Returns raw codes array for display to user; stores bcrypt hashes in DB.
     *
     * @return array<int, string>
     */
    public function generateTwoFactorRecoveryCodes(): array
    {
        $rawCodes = [];
        $hashedCodes = [];

        for ($i = 0; $i < 8; $i++) {
            $code = strtoupper(bin2hex(random_bytes(4))); // 8 hex characters e.g. 7F4A2B9C
            $rawCodes[] = substr($code, 0, 4).'-'.substr($code, 4, 4);
            $hashedCodes[] = password_hash(str_replace('-', '', $code), PASSWORD_DEFAULT);
        }

        $this->update([
            'two_factor_recovery_codes' => $hashedCodes,
        ]);

        return $rawCodes;
    }

    /**
     * Verify and consume a single-use emergency recovery code.
     */
    public function consumeRecoveryCode(string $code): bool
    {
        $storedHashes = $this->two_factor_recovery_codes;
        if (empty($storedHashes) || ! is_array($storedHashes)) {
            return false;
        }

        $cleanCode = strtoupper(str_replace(['-', ' '], '', trim($code)));

        foreach ($storedHashes as $index => $hash) {
            if (password_verify($cleanCode, $hash)) {
                // Consume code (remove from array)
                unset($storedHashes[$index]);
                $this->update([
                    'two_factor_recovery_codes' => array_values($storedHashes),
                ]);

                return true;
            }
        }

        return false;
    }
}
