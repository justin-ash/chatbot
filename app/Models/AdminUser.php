<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminUser extends Model
{
    protected $fillable = [
        'user_id',
        'web_user_id',
        'telegram_id',
        'role',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'web_user_id' => 'integer',
            'telegram_id' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'user_id');
    }

    public function webUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'web_user_id');
    }
}
