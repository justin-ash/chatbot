<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = [
        'reporter_user_id',
        'reported_user_id',
        'conversation_id',
        'reason_code',
        'details',
        'status',
        'assigned_admin_id',
        'resolution',
    ];

    protected function casts(): array
    {
        return [
            'reporter_user_id' => 'integer',
            'reported_user_id' => 'integer',
            'conversation_id' => 'integer',
            'assigned_admin_id' => 'integer',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'reporter_user_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'reported_user_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
