<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyUsage extends Model
{
    protected $fillable = [
        'user_id',
        'usage_date',
        'free_matches_used',
        'paid_credits_balance',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'free_matches_used' => 'integer',
            'paid_credits_balance' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'user_id');
    }
}
