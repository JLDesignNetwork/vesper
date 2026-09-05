<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'birthday', 'gender', 'location', 'latitude', 'longitude', 'city', 'country', 'country_code', 'location_synced_at', 'hide_age', 'hide_birthday', 'hide_location', 'hide_bio', 'bio', 'email_notifications', 'avatar_path', 'preferred_locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'latest_ip',
        'effective_locale',
        'location_locale',
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
            'birthday' => 'date',
            'latitude' => 'float',
            'longitude' => 'float',
            'location_synced_at' => 'datetime',
            'hide_age' => 'boolean',
            'hide_birthday' => 'boolean',
            'hide_location' => 'boolean',
            'hide_bio' => 'boolean',
            'email_notifications' => 'boolean',
        ];
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
     * Determine if this user is the platform administrator.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Determine if this user is a regular chat member.
     */
    public function isMember(): bool
    {
        return $this->role === 'member';
    }

    /**
     * Calculate age from birthday if set.
     */
    public function age(): ?int
    {
        return $this->birthday ? (int) $this->birthday->diffInYears(now()) : null;
    }

    /**
     * Get the birthday formatted according to privacy visibility rules.
     * If viewer is admin or self, returns full 'Y-m-d'.
     * If birthday is hidden, returns null.
     * If age is hidden, returns only Month & Day ('F j', e.g. "June 20").
     * Otherwise, returns full 'Y-m-d'.
     */
    public function birthdayForViewer(?User $viewer): ?string
    {
        if (! $this->birthday) {
            return null;
        }

        $canViewPrivate = ($viewer && $viewer->isAdmin()) || ($viewer && $viewer->id === $this->id);

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
        $canViewPrivate = ($viewer && $viewer->isAdmin()) || ($viewer && $viewer->id === $this->id);

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
        $canViewPrivate = ($viewer && $viewer->isAdmin()) || ($viewer && $viewer->id === $this->id);

        return ($canViewPrivate || ! $this->hide_location) ? $this->location : null;
    }

    /**
     * Get the bio for a viewer respecting privacy settings.
     */
    public function bioForViewer(?User $viewer): ?string
    {
        $canViewPrivate = ($viewer && $viewer->isAdmin()) || ($viewer && $viewer->id === $this->id);

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
        return app(\App\Services\GeoLocationService::class)->resolveLanguageFromLocation(
            $this->country_code,
            $this->country,
            $this->location
        );
    }

    /**
     * Get the effective platform language for this user.
     * If user explicitly set preferred_locale ('en', 'ru', 'fr', 'it'), this overrides the location language.
     * Otherwise, defaults to the common language of the registered location.
     */
    public function effectiveLocale(): string
    {
        $supported = ['en', 'ru', 'fr', 'it'];

        if (! empty($this->preferred_locale) && in_array($this->preferred_locale, $supported, true)) {
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
}

