<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'invoice_payload',
        'currency',
        'total_amount',
        'credits_granted',
        'telegram_payment_charge_id',
        'provider_payment_charge_id',
        'status',
        'credited_at',
        'refunded_at',
        'raw_update_hash',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'total_amount' => 'integer',
            'credits_granted' => 'integer',
            'credited_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'user_id');
    }
}
