<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminPayoutAccount extends Model
{
    protected $fillable = [
        'admin_user_id',
        'method_type',
        'account_label',
        'account_holder_name',
        'gpay_upi_id',
        'gpay_phone',
        'bank_name',
        'account_number',
        'ifsc_swift_code',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'admin_user_id' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class, 'payout_account_id');
    }

    public function formattedDestination(): string
    {
        if ($this->method_type === 'gpay_upi') {
            $upi = $this->gpay_upi_id ?: $this->gpay_phone;
            return "GPay / UPI: {$upi} ({$this->account_holder_name})";
        }

        $maskedAcc = $this->account_number
            ? '****' . substr((string) $this->account_number, -4)
            : 'N/A';

        return "Bank: {$this->bank_name} {$maskedAcc} | IFSC: {$this->ifsc_swift_code} ({$this->account_holder_name})";
    }
}
