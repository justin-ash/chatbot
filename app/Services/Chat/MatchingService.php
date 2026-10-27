<?php

namespace App\Services\Chat;

use App\Models\BlockedUser;
use App\Models\BotSetting;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\MatchQueue;
use App\Models\TelegramUser;
use App\Services\Telegram\TelegramApi;
use App\Services\Usage\QuotaService;
use Illuminate\Support\Facades\DB;
use Throwable;

class MatchingService
{
    public function __construct(
        private readonly QuotaService $quotaService,
        private readonly TelegramApi $telegram,
    ) {}

    /**
     * Attempt to match the user with an eligible waiting partner of the same language,
     * or place the user in the FIFO queue.
     *
     * @return array{status: string, conversation?: Conversation, partner?: TelegramUser, message?: string}
     */
    public function findOrEnqueue(TelegramUser $user): array
    {
        $user->refresh();

        if (BotSetting::getValue('maintenance_mode', false)) {
            return [
                'status' => 'maintenance',
                'message' => '🔧 The matching service is temporarily in maintenance mode. Please try again shortly.',
            ];
        }

        if (!$user->hasAcceptedTerms()) {
            return [
                'status' => 'terms_required',
                'message' => 'Please accept the Terms of Service and Privacy Policy first using /start.',
            ];
        }

        // Lift expired temporary mute automatically
        if ($user->status === 'muted' && $user->muted_until !== null && $user->muted_until->isPast()) {
            $user->update(['status' => 'active', 'muted_until' => null]);
        }

        if ($user->isRestricted()) {
            return [
                'status' => 'restricted',
                'message' => '🚫 Your account is currently restricted from starting new chats.',
            ];
        }

        $activeConversation = $user->activeConversation();
        if ($activeConversation) {
            return [
                'status' => 'already_in_chat',
                'conversation' => $activeConversation,
                'message' => '💬 You are already in an active conversation. Use /next to find someone else or /stop to end it.',
            ];
        }

        $quotaDecision = $this->quotaService->canStartSearch($user);
        if (!$quotaDecision->allowed) {
            $this->leaveQueue($user);
            return [
                'status' => 'quota_exhausted',
                'message' => '⚡ You have used all your free daily matches and have 0 paid credits remaining. Use /buy to get 50 additional matches!',
            ];
        }

        $result = DB::transaction(function () use ($user) {
            $language = $user->language_code ?: 'en';

            // Look for oldest eligible candidate in FIFO order
            $candidates = MatchQueue::query()
                ->where('language_code', $language)
                ->where('status', 'waiting')
                ->where('user_id', '!=', $user->id)
                ->whereNotExists(function ($query) use ($user) {
                    $query->select(DB::raw(1))
                        ->from('blocked_users')
                        ->where(function ($q) use ($user) {
                            $q->whereColumn('blocked_users.blocker_user_id', 'match_queue.user_id')
                                ->where('blocked_users.blocked_user_id', $user->id);
                        })
                        ->orWhere(function ($q) use ($user) {
                            $q->where('blocked_users.blocker_user_id', $user->id)
                                ->whereColumn('blocked_users.blocked_user_id', 'match_queue.user_id');
                        });
                })
                ->orderBy('queued_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($candidates as $candidateQueue) {
                $candidateId = (int) $candidateQueue->user_id;

                // Deterministic lock ordering by user ID to prevent deadlocks
                $firstId = min((int) $user->id, $candidateId);
                $secondId = max((int) $user->id, $candidateId);

                $lockedUsers = TelegramUser::query()
                    ->whereIn('id', [$firstId, $secondId])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                /** @var TelegramUser|null $lockedSeeker */
                $lockedSeeker = $lockedUsers->get($user->id);
                /** @var TelegramUser|null $lockedCandidate */
                $lockedCandidate = $lockedUsers->get($candidateId);

                if (!$lockedSeeker || !$lockedCandidate) {
                    $candidateQueue->delete();
                    continue;
                }

                // Double-check candidate eligibility
                if (
                    $lockedCandidate->isRestricted() ||
                    !$lockedCandidate->hasAcceptedTerms() ||
                    $lockedCandidate->language_code !== $language ||
                    $lockedCandidate->activeConversation() !== null ||
                    $this->isBlockedPair($lockedSeeker->id, $lockedCandidate->id) ||
                    !$this->quotaService->canStartSearch($lockedCandidate)->allowed
                ) {
                    $candidateQueue->delete();
                    continue;
                }

                // Double-check seeker quota inside lock
                if (!$this->quotaService->canStartSearch($lockedSeeker)->allowed) {
                    MatchQueue::where('user_id', $lockedSeeker->id)->delete();
                    return [
                        'status' => 'quota_exhausted',
                    ];
                }

                // Remove both from queue
                MatchQueue::whereIn('user_id', [$lockedSeeker->id, $lockedCandidate->id])->delete();

                $now = now();
                $conversation = Conversation::create([
                    'status' => 'active',
                    'language_code' => $language,
                    'started_at' => $now,
                ]);

                ConversationParticipant::insert([
                    [
                        'conversation_id' => $conversation->id,
                        'user_id' => $lockedSeeker->id,
                        'joined_at' => $now,
                        'left_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    [
                        'conversation_id' => $conversation->id,
                        'user_id' => $lockedCandidate->id,
                        'joined_at' => $now,
                        'left_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ]);

                // Spend quota in deterministic order
                if ($lockedSeeker->id < $lockedCandidate->id) {
                    $this->quotaService->consumeForSuccessfulMatch($lockedSeeker, $conversation);
                    $this->quotaService->consumeForSuccessfulMatch($lockedCandidate, $conversation);
                } else {
                    $this->quotaService->consumeForSuccessfulMatch($lockedCandidate, $conversation);
                    $this->quotaService->consumeForSuccessfulMatch($lockedSeeker, $conversation);
                }

                return [
                    'status' => 'matched',
                    'conversation' => $conversation,
                    'partner' => $lockedCandidate,
                    'seeker' => $lockedSeeker,
                ];
            }

            // No compatible candidate available; place or keep user in queue
            MatchQueue::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'language_code' => $language,
                    'queued_at' => now(),
                    'status' => 'waiting',
                ]
            );

            return [
                'status' => 'queued',
            ];
        });

        if ($result['status'] === 'matched') {
            $this->notifyMatchedPair($result['seeker'], $result['partner']);
        }

        return $result;
    }

    public function leaveQueue(TelegramUser $user): bool
    {
        return MatchQueue::where('user_id', $user->id)->delete() > 0;
    }

    public function isBlockedPair(int $userAId, int $userBId): bool
    {
        return BlockedUser::query()
            ->where(function ($q) use ($userAId, $userBId) {
                $q->where('blocker_user_id', $userAId)
                    ->where('blocked_user_id', $userBId);
            })
            ->orWhere(function ($q) use ($userAId, $userBId) {
                $q->where('blocker_user_id', $userBId)
                    ->where('blocked_user_id', $userAId);
            })
            ->exists();
    }

    public function cleanupStaleQueue(?int $timeoutMinutes = null): int
    {
        $timeoutMinutes ??= (int) BotSetting::getValue(
            'queue_timeout_minutes',
            (int) config('services.telegram.queue_timeout_minutes', 30)
        );

        $cutoff = now()->subMinutes(max(1, $timeoutMinutes));

        $staleEntries = MatchQueue::with('user')
            ->where('status', 'waiting')
            ->where('queued_at', '<', $cutoff)
            ->get();

        $count = 0;
        foreach ($staleEntries as $entry) {
            $user = $entry->user;
            $entry->delete();
            $count++;

            if ($user) {
                try {
                    $this->telegram->sendMessage(
                        $user->telegram_id,
                        "⏳ No available partner was found for your language within {$timeoutMinutes} minutes. You have been removed from the queue and no search credit was used.\n\nTap /find whenever you want to search again!"
                    );
                } catch (Throwable) {
                    // Ignore notification failure
                }
            }
        }

        return $count;
    }

    private function notifyMatchedPair(TelegramUser $userA, TelegramUser $userB): void
    {
        $text = "🎉 You are in a chat right now! :)\n\n"
            . "Say hello and enjoy your anonymous conversation.\n\n"
            . "/next — Find a new partner\n\n"
            . "/stop — Stop this chat\n\n"
            . "Stay respectful. Never share sensitive personal information.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '⏭️ Next Partner (/next)', 'callback_data' => 'chat:next'],
                    ['text' => '🛑 Stop Chat (/stop)', 'callback_data' => 'chat:stop'],
                ],
            ],
        ];

        foreach ([$userA, $userB] as $participant) {
            try {
                $this->telegram->sendMessage($participant->telegram_id, $text, [
                    'reply_markup' => $keyboard,
                ]);
            } catch (Throwable) {
                // Continue even if one notification fails
            }
        }
    }
}
