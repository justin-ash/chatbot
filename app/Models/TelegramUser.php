<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TelegramUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_id',
        'username',
        'first_name',
        'last_name',
        'is_premium',
        'language_code',
        'accepted_terms_at',
        'status',
        'muted_until',
        'paid_credits_balance',
        'pending_report_conversation_id',
        'pending_feedback_id',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'telegram_id' => 'integer',
            'is_premium' => 'boolean',
            'accepted_terms_at' => 'datetime',
            'muted_until' => 'datetime',
            'paid_credits_balance' => 'integer',
            'pending_report_conversation_id' => 'integer',
            'pending_feedback_id' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    public function displayName(): string
    {
        $full = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        if ($full !== '') {
            return $full;
        }
        if ($this->username) {
            return '@' . $this->username;
        }
        return 'TG #' . $this->telegram_id;
    }

    public function hasAcceptedTerms(): bool
    {
        return $this->accepted_terms_at !== null;
    }

    public function isRestricted(): bool
    {
        if (in_array($this->status, ['banned', 'suspended'], true)) {
            return true;
        }

        if ($this->status === 'muted') {
            if ($this->muted_until === null || $this->muted_until->isFuture()) {
                return true;
            }
        }

        return false;
    }

    public function dailyUsages(): HasMany
    {
        return $this->hasMany(DailyUsage::class, 'user_id');
    }

    public function queueEntry(): HasOne
    {
        return $this->hasOne(MatchQueue::class, 'user_id');
    }

    public function participantRecords(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class, 'user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'user_id');
    }

    public function creditLedgers(): HasMany
    {
        return $this->hasMany(CreditLedger::class, 'user_id');
    }

    public function reportsMade(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_user_id');
    }

    public function reportsReceived(): HasMany
    {
        return $this->hasMany(Report::class, 'reported_user_id');
    }

    public function feedbackSubmitted(): HasMany
    {
        return $this->hasMany(ChatFeedback::class, 'reporter_user_id');
    }

    public function feedbackReceived(): HasMany
    {
        return $this->hasMany(ChatFeedback::class, 'target_user_id');
    }

    public function bans(): HasMany
    {
        return $this->hasMany(Ban::class, 'user_id');
    }

    public function blockedUsers(): HasMany
    {
        return $this->hasMany(BlockedUser::class, 'blocker_user_id');
    }

    public function blockedByUsers(): HasMany
    {
        return $this->hasMany(BlockedUser::class, 'blocked_user_id');
    }

    public function activeConversation(): ?Conversation
    {
        $participant = $this->participantRecords()
            ->whereNull('left_at')
            ->whereHas('conversation', fn ($q) => $q->where('status', 'active'))
            ->with('conversation.participants.user')
            ->latest('joined_at')
            ->first();

        return $participant?->conversation;
    }
}
