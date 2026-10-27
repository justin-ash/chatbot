<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ban extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'starts_at',
        'ends_at',
        'reason',
        'created_by_admin_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'created_by_admin_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'user_id');
    }
}
