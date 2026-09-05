<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'locale',
        'name',
        'category',
        'subject',
        'preheader',
        'body_markdown',
        'button_text',
        'button_color',
        'footer_text',
        'updated_by_user_id',
    ];

    /**
     * The admin user who last updated this template.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
