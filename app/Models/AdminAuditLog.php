<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\AuditVisibility;
use App\Enums\Rank;
use Database\Factories\AdminAuditLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    /** @use HasFactory<AdminAuditLogFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'actor_user_id',
        'subject_user_id',
        'room_id',
        'action',
        'visibility',
        'details',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'visibility' => AuditVisibility::class,
            'details' => 'array',
        ];
    }

    /**
     * Record an audit entry using the action's default visibility.
     *
     * @param  array<string, mixed>  $details
     */
    public static function record(
        AuditAction $action,
        ?User $actor = null,
        ?User $subject = null,
        ?Room $room = null,
        array $details = [],
    ): self {
        return self::create([
            'actor_user_id' => $actor?->id,
            'subject_user_id' => $subject?->id,
            'room_id' => $room?->id,
            'action' => $action,
            'visibility' => $action->visibility(),
            'details' => $details ?: null,
        ]);
    }

    /**
     * Limit entries to those the given viewer is allowed to read.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        return match ($viewer->rank) {
            Rank::AdminOne => $query,
            Rank::AdminTwo => $query->where('visibility', AuditVisibility::Moderation),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * The user who performed the action, if any (null for system actions).
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * The user the action was performed on.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    /**
     * The channel the action relates to.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
