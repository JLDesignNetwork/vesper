<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One voter's ballot on a ban case: ban (true) or don't ban (false).
 */
class BanVote extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'ban_case_id',
        'voter_id',
        'ban',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ban' => 'boolean',
        ];
    }

    /**
     * The case this ballot belongs to.
     */
    public function banCase(): BelongsTo
    {
        return $this->belongsTo(BanCase::class);
    }

    /**
     * The admin who cast the ballot.
     */
    public function voter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voter_id');
    }
}
