<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    protected $fillable = [
        'admin_user_id',
        'payout_account_id',
        'reference_id',
        'payout_method',
        'stars_deducted',
        'usd_amount',
        'inr_amount',
        'payout_currency',
        'payout_amount',
        'destination_summary',
        'provider',
        'provider_payout_id',
        'status',
        'notes',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'admin_user_id' => 'integer',
            'payout_account_id' => 'integer',
            'stars_deducted' => 'integer',
            'usd_amount' => 'float',
            'inr_amount' => 'float',
            'payout_amount' => 'float',
            'processed_at' => 'datetime',
        ];
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(AdminPayoutAccount::class, 'payout_account_id');
    }
}
