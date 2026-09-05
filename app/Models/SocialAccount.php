<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
    use HasFactory;

    protected $table = 'social_accounts';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'email',
        'avatar',
    ];

    /**
     * The operative user linked to this external social identity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
