<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'status',
        'language_code',
        'started_at',
        'ended_at',
        'ended_by_user_id',
        'end_reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'ended_by_user_id' => 'integer',
        ];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class, 'conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'ended_by_user_id');
    }

    public function otherParticipant(int $userId): ?TelegramUser
    {
        $participant = $this->participants()
            ->where('user_id', '!=', $userId)
            ->with('user')
            ->first();

        return $participant?->user;
    }
}
