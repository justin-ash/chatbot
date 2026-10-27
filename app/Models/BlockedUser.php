<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class BlockedUser extends Model
{
    protected $fillable = [
        'blocker_user_id',
        'blocked_user_id',
    ];

    protected static function booted(): void
    {
        static::saving(function (BlockedUser $block) {
            if ((int) $block->blocker_user_id === (int) $block->blocked_user_id) {
                throw new InvalidArgumentException('A user cannot block themselves.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'blocker_user_id' => 'integer',
            'blocked_user_id' => 'integer',
        ];
    }

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'blocker_user_id');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'blocked_user_id');
    }
}
