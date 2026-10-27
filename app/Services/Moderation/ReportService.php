<?php

namespace App\Services\Moderation;

use App\Models\AuditLog;
use App\Models\Ban;
use App\Models\BlockedUser;
use App\Models\ChatFeedback;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Report;
use App\Models\TelegramUser;
use App\Services\Telegram\TelegramApi;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class ReportService
{
    public function __construct(
        private readonly TelegramApi $telegram,
    ) {}

    public function buildFeedbackMessageText(): string
    {
        return "How was your chat?\n"
            . "Your feedback helps keep ChatMingle safe. Choose any applicable option.";
    }

    public function buildFeedbackKeyboard(int $matchId): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '👍 Thumbs up', 'callback_data' => "fb:{$matchId}:thumbs_up"],
                    ['text' => '👎 Thumbs down', 'callback_data' => "fb:{$matchId}:thumbs_down"],
                ],
                [
                    ['text' => '🚫 Spam', 'callback_data' => "fb:{$matchId}:spam"],
                    ['text' => '🤬 Vulgar partner', 'callback_data' => "fb:{$matchId}:vulgar"],
                ],
                [
                    ['text' => '🛡️ Underage user', 'callback_data' => "fb:{$matchId}:underage"],
                    ['text' => '🚩 Report', 'callback_data' => "fb:{$matchId}:other_report"],
                ],
            ],
        ];
    }

    /**
     * Record a rating (thumbs_up / thumbs_down) or a safety report (spam / vulgar / underage / other_report)
     * in chat_feedback, and queue safety reports in the admin moderation queue.
     *
     * @return array{status: string, feedback?: ChatFeedback, report?: Report, message: string}
     */
    public function submitChatFeedback(
        TelegramUser $reporter,
        int $matchId,
        string $feedbackType,
        ?string $description = null
    ): array {
        if (!in_array($feedbackType, ChatFeedback::ALL_TYPES, true)) {
            return [
                'status' => 'invalid_type',
                'message' => '⚠️ Invalid feedback option selected.',
            ];
        }

        $conversation = Conversation::with('participants.user')->find($matchId);
        if (!$conversation || !$conversation->participants->contains('user_id', $reporter->id)) {
            return [
                'status' => 'not_found',
                'message' => '⚠️ Conversation not found for feedback.',
            ];
        }

        $targetUser = $conversation->otherParticipant($reporter->id);
        if (!$targetUser || $targetUser->id === $reporter->id) {
            return [
                'status' => 'no_partner',
                'message' => '⚠️ Could not identify chat partner for feedback.',
            ];
        }

        $cleanDescription = $description !== null && trim($description) !== ''
            ? mb_substr(trim($description), 0, 1000)
            : null;

        // 1. Handle Rating (thumbs_up or thumbs_down): Only ONE rating per user per conversation allowed
        if (in_array($feedbackType, ChatFeedback::RATING_TYPES, true)) {
            $existingRating = ChatFeedback::query()
                ->where('match_id', $conversation->id)
                ->where('reporter_user_id', $reporter->id)
                ->whereIn('feedback_type', ChatFeedback::RATING_TYPES)
                ->first();

            if ($existingRating) {
                return [
                    'status' => 'duplicate_rating',
                    'feedback' => $existingRating,
                    'message' => 'ℹ️ You have already submitted a thumbs-up or thumbs-down rating for this chat.',
                ];
            }

            try {
                $feedback = ChatFeedback::create([
                    'match_id' => $conversation->id,
                    'reporter_user_id' => $reporter->id,
                    'target_user_id' => $targetUser->id,
                    'feedback_type' => $feedbackType,
                    'description' => null,
                    'status' => 'reviewed',
                ]);
            } catch (UniqueConstraintViolationException) {
                return [
                    'status' => 'duplicate_rating',
                    'message' => 'ℹ️ You have already submitted a rating for this chat.',
                ];
            }

            $emoji = $feedbackType === 'thumbs_up' ? '👍' : '👎';
            return [
                'status' => 'rating_saved',
                'feedback' => $feedback,
                'message' => "{$emoji} Thank you! Your chat rating has been saved.",
            ];
        }

        // 2. Handle Safety Report (spam, vulgar, underage, other_report)
        $existingReportFeedback = ChatFeedback::query()
            ->where('match_id', $conversation->id)
            ->where('reporter_user_id', $reporter->id)
            ->where('feedback_type', $feedbackType)
            ->first();

        if ($existingReportFeedback) {
            return [
                'status' => 'duplicate_report',
                'feedback' => $existingReportFeedback,
                'message' => 'ℹ️ You have already submitted this safety report for this conversation.',
            ];
        }

        return DB::transaction(function () use ($conversation, $reporter, $targetUser, $feedbackType, $cleanDescription) {
            try {
                $feedback = ChatFeedback::create([
                    'match_id' => $conversation->id,
                    'reporter_user_id' => $reporter->id,
                    'target_user_id' => $targetUser->id,
                    'feedback_type' => $feedbackType,
                    'description' => $cleanDescription,
                    'status' => 'pending',
                ]);
            } catch (UniqueConstraintViolationException) {
                return [
                    'status' => 'duplicate_report',
                    'message' => 'ℹ️ You have already submitted this safety report for this conversation.',
                ];
            }

            $reasonCodeMap = [
                'spam' => 'spam',
                'vulgar' => 'harassment',
                'underage' => 'underage_safety',
                'other_report' => 'other',
            ];

            $report = Report::create([
                'reporter_user_id' => $reporter->id,
                'reported_user_id' => $targetUser->id,
                'conversation_id' => $conversation->id,
                'reason_code' => $reasonCodeMap[$feedbackType] ?? $feedbackType,
                'details' => $cleanDescription,
                'status' => 'open',
            ]);

            if ($cleanDescription === null) {
                $reporter->update(['pending_feedback_id' => $feedback->id]);
            }

            return [
                'status' => 'report_saved',
                'feedback' => $feedback,
                'report' => $report,
                'message' => "🛡️ Thank you. Your safety report has been queued for moderator review (your identity remains strictly confidential).\n\n"
                    . "📝 Optional: Reply with a short message now to add description details to this report, or tap Skip below.",
            ];
        });
    }

    public function attachFeedbackDescription(TelegramUser $reporter, string $description): ?ChatFeedback
    {
        if (!$reporter->pending_feedback_id) {
            return null;
        }

        $feedback = ChatFeedback::query()
            ->whereKey($reporter->pending_feedback_id)
            ->where('reporter_user_id', $reporter->id)
            ->first();

        $reporter->update(['pending_feedback_id' => null]);

        if (!$feedback || !$feedback->isSafetyReport()) {
            return null;
        }

        $cleanDescription = mb_substr(trim($description), 0, 1000);
        $feedback->update(['description' => $cleanDescription]);

        // Also update the matching moderation queue Report row
        Report::query()
            ->where('conversation_id', $feedback->match_id)
            ->where('reporter_user_id', $reporter->id)
            ->where('reported_user_id', $feedback->target_user_id)
            ->latest('id')
            ->first()
            ?->update(['details' => $cleanDescription]);

        return $feedback->fresh();
    }

    public function clearPendingFeedbackDescription(TelegramUser $reporter): void
    {
        if ($reporter->pending_feedback_id !== null) {
            $reporter->update(['pending_feedback_id' => null]);
        }
    }

    public function resolveTargetConversation(TelegramUser $user): ?Conversation
    {
        $active = $user->activeConversation();
        if ($active) {
            return $active;
        }

        if ($user->pending_report_conversation_id) {
            $pending = Conversation::with('participants.user')
                ->find($user->pending_report_conversation_id);
            if ($pending && $pending->participants->contains('user_id', $user->id)) {
                return $pending;
            }
        }

        $recentParticipant = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->where('joined_at', '>=', now()->subHours(12))
            ->with('conversation.participants.user')
            ->latest('joined_at')
            ->first();

        return $recentParticipant?->conversation;
    }

    public function createReport(
        TelegramUser $reporter,
        string $reasonCode,
        ?string $details = null,
        ?Conversation $conversation = null
    ): ?Report {
        $conversation ??= $this->resolveTargetConversation($reporter);
        if (!$conversation) {
            return null;
        }

        $typeMap = [
            'spam' => 'spam',
            'harassment' => 'vulgar',
            'nsfw' => 'vulgar',
            'underage_safety' => 'underage',
            'scam' => 'other_report',
            'other' => 'other_report',
        ];
        $feedbackType = $typeMap[$reasonCode] ?? 'other_report';

        $res = $this->submitChatFeedback($reporter, $conversation->id, $feedbackType, $details);
        if (isset($res['report'])) {
            return $res['report'];
        }

        return Report::query()
            ->where('conversation_id', $conversation->id)
            ->where('reporter_user_id', $reporter->id)
            ->latest('id')
            ->first();
    }

    public function blockPartner(TelegramUser $blocker, ?Conversation $conversation = null): ?BlockedUser
    {
        $conversation ??= $this->resolveTargetConversation($blocker);
        if (!$conversation) {
            return null;
        }

        $partner = $conversation->otherParticipant($blocker->id);
        if (!$partner || $partner->id === $blocker->id) {
            return null;
        }

        return BlockedUser::firstOrCreate([
            'blocker_user_id' => $blocker->id,
            'blocked_user_id' => $partner->id,
        ]);
    }

    public function applyModerationAction(
        TelegramUser $targetUser,
        string $action, // warn, mute, suspend, ban, unban
        string $reason,
        ?int $durationMinutes = null,
        ?int $actorAdminId = null,
        ?int $actorTelegramId = null,
        ?Report $report = null
    ): ?Ban {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A moderation reason is required.');
        }

        return DB::transaction(function () use (
            $targetUser,
            $action,
            $reason,
            $durationMinutes,
            $actorAdminId,
            $actorTelegramId,
            $report
        ) {
            $lockedUser = TelegramUser::whereKey($targetUser->id)->lockForUpdate()->firstOrFail();
            $banRecord = null;

            if ($action === 'unban') {
                Ban::where('user_id', $lockedUser->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false, 'ends_at' => now()]);

                $lockedUser->update([
                    'status' => 'active',
                    'muted_until' => null,
                ]);
            } else {
                $endsAt = $durationMinutes && $durationMinutes > 0
                    ? now()->addMinutes($durationMinutes)
                    : null;

                $banRecord = Ban::create([
                    'user_id' => $lockedUser->id,
                    'action' => $action,
                    'starts_at' => now(),
                    'ends_at' => $endsAt,
                    'reason' => $reason,
                    'created_by_admin_id' => $actorAdminId,
                    'is_active' => $action !== 'warn',
                ]);

                if ($action === 'mute') {
                    $lockedUser->update([
                        'status' => 'muted',
                        'muted_until' => $endsAt ?? now()->addMinutes(60),
                    ]);
                } elseif ($action === 'suspend') {
                    $lockedUser->update([
                        'status' => 'suspended',
                        'muted_until' => $endsAt,
                    ]);
                } elseif ($action === 'ban') {
                    $lockedUser->update([
                        'status' => 'banned',
                        'muted_until' => null,
                    ]);
                }
            }

            if ($report) {
                $report->update([
                    'status' => 'resolved',
                    'assigned_admin_id' => $actorAdminId,
                    'resolution' => strtoupper($action) . ': ' . $reason,
                ]);

                if ($report->conversation_id) {
                    ChatFeedback::where('match_id', $report->conversation_id)
                        ->where('reporter_user_id', $report->reporter_user_id)
                        ->whereIn('feedback_type', ChatFeedback::REPORT_TYPES)
                        ->update(['status' => 'actioned']);
                }
            }

            AuditLog::record(
                action: "user.{$action}",
                targetType: 'telegram_user',
                targetId: $lockedUser->id,
                reason: $reason,
                metadata: [
                    'telegram_id' => $lockedUser->telegram_id,
                    'duration_minutes' => $durationMinutes,
                    'report_id' => $report?->id,
                ],
                actorAdminId: $actorAdminId,
                actorTelegramId: $actorTelegramId
            );

            $this->notifyTargetUser($lockedUser, $action, $reason, $durationMinutes);

            return $banRecord;
        });
    }

    public function dismissReport(Report $report, string $reason, ?int $actorAdminId = null, ?int $actorTelegramId = null): Report
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A dismissal reason is required.');
        }

        $report->update([
            'status' => 'dismissed',
            'assigned_admin_id' => $actorAdminId,
            'resolution' => 'DISMISSED: ' . $reason,
        ]);

        if ($report->conversation_id) {
            ChatFeedback::where('match_id', $report->conversation_id)
                ->where('reporter_user_id', $report->reporter_user_id)
                ->whereIn('feedback_type', ChatFeedback::REPORT_TYPES)
                ->update(['status' => 'dismissed']);
        }

        AuditLog::record(
            action: 'report.dismiss',
            targetType: 'report',
            targetId: $report->id,
            reason: $reason,
            metadata: [
                'reported_user_id' => $report->reported_user_id,
            ],
            actorAdminId: $actorAdminId,
            actorTelegramId: $actorTelegramId
        );

        return $report->fresh();
    }

    private function notifyTargetUser(TelegramUser $user, string $action, string $reason, ?int $durationMinutes = null): void
    {
        $text = match ($action) {
            'warn' => "⚠️ Moderation Warning: Please follow the ChatMingle safety rules. Reason: {$reason}",
            'mute' => "🔇 Your account has been temporarily muted" . ($durationMinutes ? " for {$durationMinutes} minutes" : "") . ". Reason: {$reason}",
            'suspend' => "⛔ Your account has been suspended. Reason: {$reason}",
            'ban' => "🚫 Your account has been banned from using ChatMingle. Reason: {$reason}",
            'unban' => "✅ Your account restrictions have been lifted. Please follow the ChatMingle community guidelines.",
            default => null,
        };

        if ($text !== null) {
            try {
                $this->telegram->sendMessage($user->telegram_id, $text);
            } catch (Throwable) {
                // Ignore delivery errors if user blocked the bot
            }
        }
    }
}
