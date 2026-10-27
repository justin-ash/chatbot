<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatFeedback extends Model
{
    protected $table = 'chat_feedback';

    public const RATING_TYPES = ['thumbs_up', 'thumbs_down'];
    public const REPORT_TYPES = ['spam', 'vulgar', 'underage', 'other_report'];
    public const ALL_TYPES = ['thumbs_up', 'thumbs_down', 'spam', 'vulgar', 'underage', 'other_report'];

    protected $fillable = [
        'match_id',
        'reporter_user_id',
        'target_user_id',
        'feedback_type',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'match_id' => 'integer',
            'reporter_user_id' => 'integer',
            'target_user_id' => 'integer',
        ];
    }

    public function isRating(): bool
    {
        return in_array($this->feedback_type, self::RATING_TYPES, true);
    }

    public function isSafetyReport(): bool
    {
        return in_array($this->feedback_type, self::REPORT_TYPES, true);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'match_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'reporter_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'target_user_id');
    }
}
