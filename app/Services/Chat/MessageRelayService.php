<?php

namespace App\Services\Chat;

use App\Models\BotSetting;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\MatchQueue;
use App\Models\TelegramUser;
use App\Services\Billing\StarsPaymentService;
use App\Services\Moderation\ReportService;
use App\Services\Telegram\TelegramApi;
use App\Services\Usage\QuotaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class MessageRelayService
{
    public function __construct(
        private readonly TelegramApi $telegram,
        private readonly QuotaService $quotaService,
        private readonly StarsPaymentService $paymentService,
        private readonly ReportService $reportService,
    ) {}

    /**
     * Relay a plain-text message from sender to their anonymous partner.
     *
     * @return array{relayed: bool, reason?: string}
     */
    public function relayTextMessage(TelegramUser $sender, string $rawText, ?int $telegramMessageId = null): array
    {
        // Resolve active conversation strictly from sender's internal user record
        $conversation = $sender->activeConversation();

        if (!$conversation) {
            return [
                'relayed' => false,
                'reason' => 'no_active_conversation',
            ];
        }

        if ($sender->isRestricted()) {
            return [
                'relayed' => false,
                'reason' => 'restricted',
            ];
        }

        $partner = $conversation->otherParticipant($sender->id);
        if (!$partner) {
            $this->endActiveConversation($sender, 'missing_partner', false);
            return [
                'relayed' => false,
                'reason' => 'missing_partner',
            ];
        }

        // Sanitize & validate text
        $cleanText = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $rawText) ?? '');
        if ($cleanText === '') {
            return [
                'relayed' => false,
                'reason' => 'empty_message',
            ];
        }

        // Never relay bot commands through chat
        if (str_starts_with($cleanText, '/')) {
            return [
                'relayed' => false,
                'reason' => 'command_blocked',
            ];
        }

        $maxLength = (int) BotSetting::getValue(
            'message_max_length',
            (int) config('services.telegram.message_max_length', 2000)
        );

        if (mb_strlen($cleanText) > $maxLength) {
            $this->telegram->sendMessage(
                $sender->telegram_id,
                "⚠️ Your message is too long (maximum {$maxLength} characters). Please shorten it."
            );
            return [
                'relayed' => false,
                'reason' => 'message_too_long',
            ];
        }

        // Per-user flood / rate limit check
        if (!$this->checkAndConsumeRateLimit($sender)) {
            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_user_id' => $sender->id,
                'telegram_message_id' => $telegramMessageId,
                'message_type' => 'text',
                'char_length' => mb_strlen($cleanText),
                'moderation_state' => 'rate_limited',
            ]);

            $this->telegram->sendMessage(
                $sender->telegram_id,
                "⏳ You are sending messages too quickly. Please slow down."
            );

            return [
                'relayed' => false,
                'reason' => 'rate_limited',
            ];
        }

        // Record metadata only (no message body persisted per privacy design)
        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_user_id' => $sender->id,
            'telegram_message_id' => $telegramMessageId,
            'message_type' => 'text',
            'char_length' => mb_strlen($cleanText),
            'moderation_state' => 'allowed',
        ]);

        // Send a brand-new message to the partner (never forwardMessage/copyMessage)
        $this->telegram->sendMessage(
            $partner->telegram_id,
            "💬 Partner: " . $cleanText
        );

        return [
            'relayed' => true,
        ];
    }

    public function endActiveConversation(
        TelegramUser $actor,
        string $reason = 'user_stopped',
        bool $notifyActor = true
    ): ?Conversation {
        $conversation = $actor->activeConversation();
        if (!$conversation) {
            return null;
        }

        $partner = $conversation->otherParticipant($actor->id);

        DB::transaction(function () use ($conversation, $actor, $partner, $reason) {
            $lockedConv = Conversation::whereKey($conversation->id)->lockForUpdate()->first();
            if (!$lockedConv || $lockedConv->status === 'ended') {
                return;
            }

            $now = now();
            $lockedConv->update([
                'status' => 'ended',
                'ended_at' => $now,
                'ended_by_user_id' => $actor->id,
                'end_reason' => $reason,
            ]);

            $lockedConv->participants()
                ->whereNull('left_at')
                ->update(['left_at' => $now]);

            // Remove any stale queue entries for both participants
            $userIds = array_filter([$actor->id, $partner?->id]);
            MatchQueue::whereIn('user_id', $userIds)->delete();

            // Remember this conversation briefly so /report and /block work after ending
            TelegramUser::whereIn('id', $userIds)
                ->update(['pending_report_conversation_id' => $lockedConv->id]);
        });

        // Notify both participants and send the ChatMingle post-chat feedback menu to both
        if ($notifyActor) {
            $this->sendConversationEndedAndFeedbackMenu(
                $actor,
                $conversation->id,
                "🛑 You have stopped this chat.\nSend /search to find a new partner.",
                checkQuotaPrompt: true
            );
        } else {
            // Even when actor used /next (so actor immediately starts searching),
            // still send actor the feedback menu for the ended conversation!
            $this->sendFeedbackMenuOnly($actor, $conversation->id);
        }

        if ($partner) {
            $this->sendConversationEndedAndFeedbackMenu(
                $partner,
                $conversation->id,
                "🔌 Your partner has left the chat.\nSend /search or /next to find a new partner.",
                checkQuotaPrompt: true
            );
        }

        return $conversation->fresh();
    }

    public function sendFeedbackMenuOnly(TelegramUser $participant, int $matchId): void
    {
        try {
            $this->telegram->sendMessage(
                $participant->telegram_id,
                $this->reportService->buildFeedbackMessageText(),
                ['reply_markup' => $this->reportService->buildFeedbackKeyboard($matchId)]
            );
        } catch (Throwable) {
            // Ignore delivery failure
        }
    }

    public function sendQuotaExhaustedWithPaymentLink(TelegramUser $user, ?string $heading = null): void
    {
        $balance = $this->quotaService->currentBalance($user);
        $packCredits = $this->paymentService->packCreditsCount();
        $packStars = $this->paymentService->packStarsAmount();
        $paymentInfo = $this->paymentService->createPaymentLinkForUser($user);

        $title = $heading ?? "🛑 You have completed all {$balance['free_limit']} free random user searches for today!";

        $text = "{$title}\n\n"
            . "📊 Daily Free Searches Used: {$balance['free_used']} / {$balance['free_limit']}\n"
            . "⭐ Paid Credits Remaining: {$balance['paid_remaining']}\n\n"
            . "🔓 Unlock +{$packCredits} more random user searches for {$packStars} Telegram Stars (~$2 USD):\n"
            . "👉 Payment Link: {$paymentInfo['web_link']}";

        $buttons = [
            [['text' => "💳 Pay Now & Unlock +{$packCredits} Searches", 'url' => $paymentInfo['primary_link']]],
            [['text' => "⭐ Send Telegram Stars Invoice ({$packStars} XTR)", 'callback_data' => 'buy:pack50']],
        ];

        try {
            $this->telegram->sendMessage($user->telegram_id, $text, [
                'reply_markup' => [
                    'inline_keyboard' => $buttons,
                ],
            ]);
        } catch (Throwable) {
            // Ignore delivery failure
        }
    }

    private function sendConversationEndedAndFeedbackMenu(
        TelegramUser $participant,
        int $matchId,
        string $baseMessage,
        bool $checkQuotaPrompt = true
    ): void {
        try {
            $this->telegram->sendMessage($participant->telegram_id, $baseMessage);
        } catch (Throwable) {
            // Ignore
        }

        // Send the ChatMingle Feedback Menu to the participant
        $this->sendFeedbackMenuOnly($participant, $matchId);

        if ($checkQuotaPrompt) {
            $balance = $this->quotaService->currentBalance($participant);
            if ($balance['free_remaining'] <= 0 && $balance['paid_remaining'] <= 0) {
                $this->sendQuotaExhaustedWithPaymentLink(
                    $participant,
                    "⚡ You have now completed your {$balance['free_limit']} free random user searches for today!"
                );
            }
        }
    }

    private function checkAndConsumeRateLimit(TelegramUser $user): bool
    {
        $maxPerMinute = (int) BotSetting::getValue(
            'message_rate_limit_per_minute',
            (int) config('services.telegram.message_rate_limit_per_minute', 25)
        );

        $bucketKey = "rate_limit:msg:{$user->id}:" . now()->format('YmdHi');
        $count = (int) Cache::get($bucketKey, 0) + 1;
        Cache::put($bucketKey, $count, 70);

        if ($count > $maxPerMinute) {
            $violationKey = "rate_limit:violations:{$user->id}";
            $violations = (int) Cache::get($violationKey, 0) + 1;
            Cache::put($violationKey, $violations, 600);

            // Escalating temporary mute on severe flooding
            if ($violations >= 5) {
                $user->update([
                    'status' => 'muted',
                    'muted_until' => now()->addMinutes(15),
                ]);
            }

            return false;
        }

        return true;
    }
}
