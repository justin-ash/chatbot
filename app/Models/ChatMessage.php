<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_user_id',
        'telegram_message_id',
        'message_type',
        'char_length',
        'moderation_state',
    ];

    protected function casts(): array
    {
        return [
            'conversation_id' => 'integer',
            'sender_user_id' => 'integer',
            'telegram_message_id' => 'integer',
            'char_length' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'sender_user_id');
    }
}
