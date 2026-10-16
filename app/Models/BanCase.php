<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BanCaseStatus;
use App\Enums\BanDecision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A vote by Admin I and II on whether to ban a member. See BanVoteService.
 */
class BanCase extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'subject_user_id',
        'opened_by_user_id',
        'report_id',
        'reason',
        'status',
        'decision',
        'closes_at',
        'closed_at',
    ];

    /**
     * Default attribute values, mirrored from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BanCaseStatus::class,
            'decision' => BanDecision::class,
            'closes_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Limit to cases still being voted on.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', BanCaseStatus::Open);
    }

    /**
     * Whether ballots may still be cast.
     */
    public function isOpen(): bool
    {
        return $this->status === BanCaseStatus::Open;
    }

    /**
     * The member being voted on.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    /**
     * The admin who opened the case.
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    /**
     * The report the case was opened from, if any.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Ballots cast on this case.
     */
    public function votes(): HasMany
    {
        return $this->hasMany(BanVote::class);
    }
}
