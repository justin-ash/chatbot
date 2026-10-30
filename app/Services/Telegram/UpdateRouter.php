<?php

namespace App\Services\Telegram;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\BotSetting;
use App\Models\Conversation;
use App\Models\MatchQueue;
use App\Models\Payment;
use App\Models\Report;
use App\Models\TelegramUser;
use App\Services\Billing\StarsPaymentService;
use App\Services\Chat\MatchingService;
use App\Services\Chat\MessageRelayService;
use App\Services\Moderation\ReportService;
use App\Services\Usage\QuotaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateRouter
{
    public function __construct(
        private readonly TelegramApi $telegram,
        private readonly MatchingService $matchingService,
        private readonly MessageRelayService $relayService,
        private readonly QuotaService $quotaService,
        private readonly StarsPaymentService $paymentService,
        private readonly ReportService $reportService,
    ) {}

    public function route(array $update): void
    {
        if (isset($update['pre_checkout_query']) && is_array($update['pre_checkout_query'])) {
            $this->paymentService->handlePreCheckoutQuery($update['pre_checkout_query']);
            return;
        }

        if (isset($update['callback_query']) && is_array($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
            return;
        }

        if (isset($update['message']) && is_array($update['message'])) {
            $this->handleMessage($update['message'], $update);
            return;
        }
    }

    private function handleMessage(array $message, array $rawUpdate): void
    {
        // Only accept private-chat updates for the user-facing bot workflow
        $chatType = (string) ($message['chat']['type'] ?? '');
        if ($chatType !== 'private') {
            return;
        }

        $fromId = (int) ($message['from']['id'] ?? 0);
        if ($fromId <= 0) {
            return;
        }

        $langHint = $this->normalizeLanguageCode((string) ($message['from']['language_code'] ?? 'en'));
        $user = $this->resolveOrCreateUser($fromId, $langHint, $message['from'] ?? []);

        // Handle Telegram Stars successful_payment update
        if (isset($message['successful_payment']) && is_array($message['successful_payment'])) {
            $rawHash = hash('sha256', json_encode($rawUpdate) ?: '');
            try {
                $payment = $this->paymentService->handleSuccessfulPayment(
                    $user,
                    $message['successful_payment'],
                    $rawHash
                );
                $balance = $this->quotaService->currentBalance($user);

                $this->telegram->sendMessage(
                    $user->telegram_id,
                    "✅ Payment verified! {$payment->credits_granted} paid match credits have been added to your ChatMingle account.\n\n"
                    . "📊 Current Balance:\n"
                    . "• Free searches left today: {$balance['free_remaining']}/{$balance['free_limit']}\n"
                    . "• Paid credits remaining: {$balance['paid_remaining']}\n\n"
                    . "Send /search to find a random user!",
                    [
                        'reply_markup' => [
                            'inline_keyboard' => [
                                [['text' => '🔍 Search Random User (/search)', 'callback_data' => 'chat:find']],
                            ],
                        ],
                    ]
                );
            } catch (Throwable $e) {
                Log::error('Stars payment fulfillment failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
            return;
        }

        // Block contacts, locations, venues, polls, dice, and media in text-only launch
        if (!isset($message['text'])) {
            if ($user->activeConversation() !== null) {
                $this->telegram->sendMessage(
                    $user->telegram_id,
                    "🔒 Only plain text messages are permitted in anonymous chats. Media, contacts, and locations are not relayed."
                );
            }
            return;
        }

        $text = trim((string) $message['text']);
        if ($text === '') {
            return;
        }

        if (str_starts_with($text, '/')) {
            $this->handleCommand($user, $text);
            return;
        }

        // If user is not in an active chat and has a pending safety report awaiting an optional description
        if ($user->activeConversation() === null && $user->pending_feedback_id !== null) {
            $updatedFeedback = $this->reportService->attachFeedbackDescription($user, $text);
            if ($updatedFeedback) {
                $this->telegram->sendMessage(
                    $user->telegram_id,
                    "✅ Thank you. Your optional description has been saved to your moderation report.\n\nSend /search whenever you are ready to find a new partner!",
                    [
                        'reply_markup' => [
                            'inline_keyboard' => [
                                [['text' => '🔍 Search Random User (/search)', 'callback_data' => 'chat:find']],
                            ],
                        ],
                    ]
                );
                return;
            }
        }

        // Ordinary text message: relay if in active conversation
        $messageId = isset($message['message_id']) ? (int) $message['message_id'] : null;
        $relayResult = $this->relayService->relayTextMessage($user, $text, $messageId);

        if (!$relayResult['relayed'] && ($relayResult['reason'] ?? '') === 'no_active_conversation') {
            $this->telegram->sendMessage(
                $user->telegram_id,
                "💡 You are not currently in an active conversation. Send /search to find a random user!",
                [
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => '🔍 Search Random User (/search)', 'callback_data' => 'chat:find'],
                                ['text' => '🌐 Language', 'callback_data' => 'menu:language'],
                            ],
                            [
                                ['text' => '💳 Balance / Payment Link', 'callback_data' => 'menu:balance'],
                            ],
                        ],
                    ],
                ]
            );
        }
    }

    private function handleCommand(TelegramUser $user, string $text): void
    {
        $parts = preg_split('/\s+/', $text, 2);
        $rawCmd = strtolower($parts[0] ?? '');
        $command = explode('@', $rawCmd)[0];
        $argsString = trim($parts[1] ?? '');

        match ($command) {
            '/start' => $this->cmdStart($user),
            '/search', '/find' => $this->cmdSearch($user),
            '/next' => $this->cmdNext($user),
            '/stop' => $this->cmdStop($user),
            '/language' => $this->cmdLanguage($user),
            '/gender' => $this->cmdGender($user),
            '/balance' => $this->cmdBalance($user),
            '/buy', '/pay' => $this->cmdBuy($user),
            '/report' => $this->cmdReport($user),
            '/block' => $this->cmdBlock($user),
            '/help' => $this->cmdHelp($user),
            '/privacy', '/terms' => $this->cmdPrivacy($user),
            '/support' => $this->cmdSupport($user),
            '/admin', '/stats', '/ban', '/unban', '/mute', '/grant', '/reports' => $this->handleAdminCommand($user, $command, $argsString),
            default => $this->telegram->sendMessage(
                $user->telegram_id,
                "❓ Unknown command. Use /help to see how to use ChatMingle."
            ),
        };
    }

    private function handleCallbackQuery(array $callbackQuery): void
    {
        $callbackId = (string) ($callbackQuery['id'] ?? '');
        $data = (string) ($callbackQuery['data'] ?? '');
        $fromId = (int) ($callbackQuery['from']['id'] ?? 0);

        if ($fromId <= 0) {
            return;
        }

        $langHint = $this->normalizeLanguageCode((string) ($callbackQuery['from']['language_code'] ?? 'en'));
        $user = $this->resolveOrCreateUser($fromId, $langHint, $callbackQuery['from'] ?? []);

        if ($callbackId !== '') {
            try {
                $this->telegram->answerCallbackQuery($callbackId);
            } catch (Throwable) {
                // Ignore expired callback query ID
            }
        }

        // Handle ChatMingle post-chat feedback buttons: fb:{match_id}:{feedback_type}
        if (str_starts_with($data, 'fb:')) {
            $parts = explode(':', $data, 3);
            $matchId = (int) ($parts[1] ?? 0);
            $feedbackType = (string) ($parts[2] ?? '');

            $res = $this->reportService->submitChatFeedback($user, $matchId, $feedbackType);

            if ($res['status'] === 'report_saved' && isset($res['feedback'])) {
                $this->telegram->sendMessage($user->telegram_id, $res['message'], [
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [['text' => '⏭️ Skip Optional Description', 'callback_data' => 'fb_skip:' . $res['feedback']->id]],
                        ],
                    ],
                ]);
                return;
            }

            $this->telegram->sendMessage($user->telegram_id, $res['message']);
            return;
        }

        if (str_starts_with($data, 'fb_skip:')) {
            $this->reportService->clearPendingFeedbackDescription($user);
            $this->telegram->sendMessage(
                $user->telegram_id,
                "✅ Report submitted without additional description. Send /search whenever you want to find a new partner!"
            );
            return;
        }

        if ($data === 'terms:accept') {
            if (!$user->hasAcceptedTerms()) {
                $user->update(['accepted_terms_at' => now()]);
            }
            $this->telegram->sendMessage(
                $user->telegram_id,
                "✅ Terms accepted! Choose your preferred language or tap Search Random User (/search):",
                ['reply_markup' => $this->buildLanguageKeyboard($user->language_code, includeFindButton: true)]
            );
            return;
        }

        if (str_starts_with($data, 'lang:set:')) {
            $code = $this->normalizeLanguageCode(substr($data, 9));
            $this->matchingService->leaveQueue($user);
            $user->update([
                'language_code' => $code,
                'accepted_terms_at' => $user->accepted_terms_at ?? now(),
            ]);

            $languages = $this->supportedLanguages();
            $label = $languages[$code] ?? strtoupper($code);

            $this->telegram->sendMessage(
                $user->telegram_id,
                "🌐 Language updated to {$label}.\nSend /search or tap below to find a random user speaking {$label}!",
                [
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [['text' => '🔍 Search Random User (/search)', 'callback_data' => 'chat:find']],
                            [['text' => '🌐 Change Language', 'callback_data' => 'menu:language']],
                        ],
                    ],
                ]
            );
            return;
        }

        if ($data === 'menu:language') {
            $this->cmdLanguage($user);
            return;
        }

        if ($data === 'menu:gender') {
            $this->cmdGender($user);
            return;
        }

        if (str_starts_with($data, 'gender:set:')) {
            $gender = strtolower(trim(substr($data, 11)));
            if (in_array($gender, ['male', 'female', 'other'], true)) {
                $user->update([
                    'gender' => $gender,
                    'accepted_terms_at' => $user->accepted_terms_at ?? now(),
                ]);
                $this->telegram->sendMessage(
                    $user->telegram_id,
                    "✅ Gender updated to {$user->genderLabel()}.\nSend /search or tap below to find a random person!",
                    [
                        'reply_markup' => [
                            'inline_keyboard' => [
                                [['text' => '🔍 Search Random Person (/search)', 'callback_data' => 'chat:find']],
                            ],
                        ],
                    ]
                );
            }
            return;
        }

        if ($data === 'menu:balance') {
            $this->cmdBalance($user);
            return;
        }

        if ($data === 'chat:find' || $data === 'chat:search') {
            $this->cmdSearch($user);
            return;
        }

        if ($data === 'chat:next') {
            $this->cmdNext($user);
            return;
        }

        if ($data === 'chat:stop') {
            $this->cmdStop($user);
            return;
        }

        if ($data === 'buy:pack50') {
            $this->cmdBuy($user);
            return;
        }

        if ($data === 'report:start') {
            $this->cmdReport($user);
            return;
        }

        if (str_starts_with($data, 'report:reason:')) {
            $reasonCode = substr($data, 14);
            $report = $this->reportService->createReport($user, $reasonCode);

            if (!$report) {
                $this->telegram->sendMessage(
                    $user->telegram_id,
                    "⚠️ Could not find an active or recent conversation to report."
                );
                return;
            }

            if ($user->activeConversation() !== null) {
                $this->relayService->endActiveConversation($user, 'reported_partner', false);
            }

            $this->telegram->sendMessage(
                $user->telegram_id,
                "🛡️ Thank you. Your report (#{$report->id}) has been submitted to our moderation queue. You can also tap /block to prevent future matching with that user."
            );
            return;
        }

        if ($data === 'block:partner') {
            $this->cmdBlock($user);
            return;
        }
    }

    private function cmdStart(TelegramUser $user): void
    {
        $minAge = (int) config('services.telegram.minimum_age', 18);
        $termsUrl = (string) config('services.telegram.terms_url');
        $privacyUrl = (string) config('services.telegram.privacy_url');
        $languages = $this->supportedLanguages();
        $currentLang = $languages[$user->language_code] ?? strtoupper($user->language_code);

        if (!$user->hasAcceptedTerms()) {
            $text = "👋 Welcome to ChatMingle!\n\n"
                . "🔒 Privacy & Safety Notice:\n"
                . "• Chat anonymously with random users speaking the same language.\n"
                . "• Your Telegram name, username, and ID are never shown to your chat partner.\n"
                . "• Core Commands:\n"
                . "  /search — Find a random person\n"
                . "  /next — Find a new partner\n"
                . "  /stop — Stop this chat\n"
                . "  /help — How to use the bot\n"
                . "• Minimum age requirement: {$minAge}+\n\n"
                . "📄 Terms: {$termsUrl}\n"
                . "🔐 Privacy Policy: {$privacyUrl}\n\n"
                . "Tap below to accept Terms or send /search to begin:";

            $this->telegram->sendMessage($user->telegram_id, $text, [
                'reply_markup' => [
                    'inline_keyboard' => [
                        [['text' => "✅ Accept Terms & Start (/search)", 'callback_data' => 'terms:accept']],
                    ],
                ],
            ]);
            return;
        }

        $balance = $this->quotaService->currentBalance($user);

        $text = "👋 Welcome back to ChatMingle!\n\n"
            . "🌐 Selected Language: {$currentLang}\n"
            . "🚻 Gender: {$user->genderLabel()}\n"
            . "🎯 Free Random Searches Left Today: {$balance['free_remaining']}/{$balance['free_limit']}\n"
            . "⭐ Paid Credits Balance: {$balance['paid_remaining']}\n\n"
            . "Commands:\n"
            . "• /search — Find a random person\n"
            . "• /next — Find a new partner\n"
            . "• /stop — Stop this chat\n"
            . "• /gender — Select your gender\n"
            . "• /help — How to use the bot";

        $this->telegram->sendMessage($user->telegram_id, $text, [
            'reply_markup' => [
                'inline_keyboard' => [
                    [['text' => '🔍 Search Random Person (/search)', 'callback_data' => 'chat:find']],
                    [
                        ['text' => '🌐 Language', 'callback_data' => 'menu:language'],
                        ['text' => '🚻 Gender', 'callback_data' => 'menu:gender'],
                    ],
                    [
                        ['text' => '💳 Balance / Payment Link', 'callback_data' => 'menu:balance'],
                    ],
                ],
            ],
        ]);
    }

    private function cmdSearch(TelegramUser $user): void
    {
        $this->reportService->clearPendingFeedbackDescription($user);

        if (!$user->hasAcceptedTerms()) {
            $user->update(['accepted_terms_at' => now()]);
        }

        // Enforce search cooldown to prevent queue spam
        $cooldownSeconds = (int) BotSetting::getValue(
            'search_cooldown_seconds',
            (int) config('services.telegram.search_cooldown_seconds', 3)
        );
        $cooldownKey = "cooldown:search:{$user->id}";

        if ($cooldownSeconds > 0 && Cache::has($cooldownKey)) {
            $this->telegram->sendMessage(
                $user->telegram_id,
                "⏳ Please wait a moment before starting another search."
            );
            return;
        }
        if ($cooldownSeconds > 0) {
            Cache::put($cooldownKey, 1, $cooldownSeconds);
        }

        // Check if already in chat or quota exhausted before sending Message 1
        if ($user->activeConversation() === null && $this->quotaService->canStartSearch($user)->allowed && !$user->isRestricted()) {
            // Message 1: While searching (sent immediately when user clicks /search)
            $this->telegram->sendMessage(
                $user->telegram_id,
                "🔎 Searching for people...\n\nPlease wait while we find someone who speaks your language.",
                [
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => '🛑 Stop Search (/stop)', 'callback_data' => 'chat:stop'],
                                ['text' => '🌐 Switch Language', 'callback_data' => 'menu:language'],
                            ],
                        ],
                    ],
                ]
            );
        }

        $result = $this->matchingService->findOrEnqueue($user);
        $status = $result['status'];

        if ($status === 'matched' || $status === 'queued') {
            return;
        }

        if ($status === 'quota_exhausted') {
            $this->relayService->sendQuotaExhaustedWithPaymentLink($user);
            return;
        }

        if (isset($result['message'])) {
            $this->telegram->sendMessage($user->telegram_id, $result['message']);
        }
    }

    private function cmdNext(TelegramUser $user): void
    {
        $this->reportService->clearPendingFeedbackDescription($user);

        if ($user->activeConversation() !== null) {
            // End current conversation for both participants and send feedback menu to both
            $this->relayService->endActiveConversation($user, 'user_skipped_next', notifyActor: false);
        } else {
            $this->matchingService->leaveQueue($user);
        }

        // /next then searches for a new partner for the requesting user
        $this->cmdSearch($user);
    }

    private function cmdStop(TelegramUser $user): void
    {
        $this->reportService->clearPendingFeedbackDescription($user);

        $endedConversation = $this->relayService->endActiveConversation($user, 'user_stopped', notifyActor: true);
        if ($endedConversation) {
            return;
        }

        if ($this->matchingService->leaveQueue($user)) {
            $this->telegram->sendMessage(
                $user->telegram_id,
                "🛑 You have stopped searching and left the waiting queue. No search credit was used.",
                [
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [['text' => '🔍 Search Random Person (/search)', 'callback_data' => 'chat:find']],
                        ],
                    ],
                ]
            );
            return;
        }

        $this->telegram->sendMessage(
            $user->telegram_id,
            "ℹ️ You have no active conversation or search right now. Send /search to find a random person!"
        );
    }

    private function cmdLanguage(TelegramUser $user): void
    {
        $wasQueued = $this->matchingService->leaveQueue($user);

        $prefix = $wasQueued ? "ℹ️ You have been removed from the waiting queue.\n\n" : "";

        $this->telegram->sendMessage(
            $user->telegram_id,
            $prefix . "🌐 Select your preferred language for ChatMingle matching:",
            ['reply_markup' => $this->buildLanguageKeyboard($user->language_code, includeFindButton: false)]
        );
    }

    private function cmdGender(TelegramUser $user): void
    {
        $current = $user->gender;

        $this->telegram->sendMessage(
            $user->telegram_id,
            "🚻 Select your gender (Current: {$user->genderLabel()}):",
            [
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => ($current === 'male' ? '✅ ' : '') . '👨 Male', 'callback_data' => 'gender:set:male'],
                            ['text' => ($current === 'female' ? '✅ ' : '') . '👩 Female', 'callback_data' => 'gender:set:female'],
                        ],
                        [
                            ['text' => ($current === 'other' ? '✅ ' : '') . '🧑 Other / Prefer not to say', 'callback_data' => 'gender:set:other'],
                        ],
                    ],
                ],
            ]
        );
    }

    private function cmdBalance(TelegramUser $user): void
    {
        $balance = $this->quotaService->currentBalance($user);
        $packCredits = $this->paymentService->packCreditsCount();
        $packStars = $this->paymentService->packStarsAmount();
        $paymentInfo = $this->paymentService->createPaymentLinkForUser($user);

        $text = "📊 Your ChatMingle Search Quota & Balance\n\n"
            . "📅 Date (UTC): {$balance['usage_date']}\n"
            . "🆓 Free Random Searches Used Today: {$balance['free_used']} / {$balance['free_limit']}\n"
            . "🟢 Free Random Searches Remaining Today: {$balance['free_remaining']}\n"
            . "⭐ Paid Credits Balance: {$balance['paid_remaining']}\n\n"
            . "💳 Unlock +{$packCredits} searches ({$packStars} Stars / ~$2):\n"
            . "👉 Payment Link: {$paymentInfo['web_link']}";

        $this->telegram->sendMessage($user->telegram_id, $text, [
            'reply_markup' => [
                'inline_keyboard' => [
                    [['text' => "💳 Open Payment Link (+{$packCredits} Searches)", 'url' => $paymentInfo['primary_link']]],
                    [['text' => "⭐ Send Stars Invoice ({$packStars} XTR)", 'callback_data' => 'buy:pack50']],
                    [['text' => '🔍 Search Random Person (/search)', 'callback_data' => 'chat:find']],
                ],
            ],
        ]);
    }

    private function cmdBuy(TelegramUser $user): void
    {
        if (!$user->hasAcceptedTerms()) {
            $user->update(['accepted_terms_at' => now()]);
        }

        $packCredits = $this->paymentService->packCreditsCount();
        $packStars = $this->paymentService->packStarsAmount();
        $paymentInfo = $this->paymentService->createPaymentLinkForUser($user);

        $this->telegram->sendMessage(
            $user->telegram_id,
            "⭐ Unlock +{$packCredits} Random User Searches\n\n"
            . "• Pack Size: +{$packCredits} random user search credits\n"
            . "• Price: {$packStars} Telegram Stars (XTR) (~$2 USD)\n"
            . "• Direct Payment Link: {$paymentInfo['web_link']}\n\n"
            . "Tap the payment link below or pay via the Telegram Stars invoice:",
            [
                'reply_markup' => [
                    'inline_keyboard' => [
                        [['text' => "💳 Pay Now (+{$packCredits} Searches)", 'url' => $paymentInfo['primary_link']]],
                    ],
                ],
            ]
        );

        $this->paymentService->createPackInvoice($user);
    }

    private function cmdReport(TelegramUser $user): void
    {
        $targetConv = $this->reportService->resolveTargetConversation($user);
        if (!$targetConv) {
            $this->telegram->sendMessage(
                $user->telegram_id,
                "⚠️ You do not have an active or recent conversation to report."
            );
            return;
        }

        $this->telegram->sendMessage(
            $user->telegram_id,
            $this->reportService->buildFeedbackMessageText(),
            ['reply_markup' => $this->reportService->buildFeedbackKeyboard($targetConv->id)]
        );
    }

    private function cmdBlock(TelegramUser $user): void
    {
        $targetConv = $this->reportService->resolveTargetConversation($user);
        if (!$targetConv) {
            $this->telegram->sendMessage(
                $user->telegram_id,
                "⚠️ You do not have an active or recent partner to block."
            );
            return;
        }

        $this->reportService->blockPartner($user, $targetConv);

        if ($user->activeConversation()?->id === $targetConv->id) {
            $this->relayService->endActiveConversation($user, 'blocked_partner', notifyActor: false);
        }

        $this->telegram->sendMessage(
            $user->telegram_id,
            "🚫 Partner blocked. Any active chat has been closed, and you will never be matched with this user again.",
            [
                'reply_markup' => [
                    'inline_keyboard' => [
                        [['text' => '🔍 Search Random Person (/search)', 'callback_data' => 'chat:find']],
                    ],
                ],
            ]
        );
    }

    private function cmdHelp(TelegramUser $user): void
    {
        $freeLimit = $this->quotaService->dailyFreeLimit();
        $packCredits = $this->paymentService->packCreditsCount();
        $packStars = $this->paymentService->packStarsAmount();

        $text = "📖 How to Use ChatMingle\n\n"
            . "🔹 /search — Find a random person speaking your language\n"
            . "🔹 /next — Find a new partner\n"
            . "🔹 /stop — Stop this chat\n"
            . "🔹 /help — Show this guide on how to use the bot\n"
            . "🔹 /language — Choose or change your matching language\n"
            . "🔹 /balance — Check your remaining daily free searches & paid credits\n"
            . "🔹 /buy — Get a payment link for +{$packCredits} additional searches ({$packStars} Stars)\n"
            . "🔹 /report — Open the feedback & safety report menu for your chat\n"
            . "🔹 /block — Permanently prevent future matches with the current partner\n\n"
            . "💡 How ChatMingle Works:\n"
            . "• You get {$freeLimit} free random user matches every day.\n"
            . "• After a chat ends via /stop or /next, both users receive a feedback menu (👍 Thumbs up, 👎 Thumbs down, 🚫 Spam, 🤬 Vulgar partner, 🛡️ Underage user, 🚩 Report).";

        $this->telegram->sendMessage($user->telegram_id, $text, [
            'reply_markup' => [
                'inline_keyboard' => [
                    [['text' => '🔍 Search Random Person (/search)', 'callback_data' => 'chat:find']],
                ],
            ],
        ]);
    }

    private function cmdPrivacy(TelegramUser $user): void
    {
        $termsUrl = (string) config('services.telegram.terms_url');
        $privacyUrl = (string) config('services.telegram.privacy_url');
        $retentionDays = (int) BotSetting::getValue(
            'retention_days_metadata',
            (int) config('services.telegram.retention_days_metadata', 30)
        );

        $text = "🔐 ChatMingle Privacy, Safety & Data Retention\n\n"
            . "• Chat Partner Anonymity: We never share your Telegram username, display name, profile photo, or numeric ID with your chat partner.\n"
            . "• Operator Metadata: To operate the service, prevent abuse, and process Telegram Stars payments, we store your Telegram ID, daily usage counters, payment records, and moderation logs.\n"
            . "• Message Privacy: By default, we do NOT store message text bodies in the database—only operational metadata (timestamps and message lengths) retained for {$retentionDays} days.\n"
            . "• Confidential Feedback & Reports: Reporter identities are never disclosed to the reported user.\n\n"
            . "📄 Full Terms: {$termsUrl}\n"
            . "🔒 Privacy Policy: {$privacyUrl}";

        $this->telegram->sendMessage($user->telegram_id, $text);
    }

    private function cmdSupport(TelegramUser $user): void
    {
        $support = (string) config('services.telegram.support_contact', '@Support');
        $this->telegram->sendMessage(
            $user->telegram_id,
            "🛟 Support & Appeals\n\n"
            . "If you need help with a payment or wish to appeal a moderation action, please contact: {$support}\n"
            . "Include your reference ID (#{$user->id}) when contacting support."
        );
    }

    private function handleAdminCommand(TelegramUser $actor, string $command, string $argsString): void
    {
        if (!$this->isTelegramAdmin($actor)) {
            $this->telegram->sendMessage(
                $actor->telegram_id,
                "⛔ Unauthorized. This command is restricted to authorized administrators."
            );
            return;
        }

        if ($command === '/admin') {
            $this->telegram->sendMessage(
                $actor->telegram_id,
                "🛡️ ChatMingle Admin Console\n\n"
                . "• /stats — Live platform metrics\n"
                . "• /reports — View open moderation reports\n"
                . "• /ban <telegram_id> <reason> — Ban user\n"
                . "• /unban <telegram_id> <reason> — Unban user\n"
                . "• /mute <telegram_id> <minutes> <reason> — Mute user\n"
                . "• /grant <telegram_id> <credits> <reason> — Grant paid credits"
            );
            return;
        }

        if ($command === '/stats') {
            $totalUsers = TelegramUser::count();
            $activeToday = TelegramUser::where('last_seen_at', '>=', now()->subDay())->count();
            $activeChats = Conversation::where('status', 'active')->count();
            $queueDepth = MatchQueue::where('status', 'waiting')->count();
            $openReports = Report::where('status', 'open')->count();
            $paidCount = Payment::where('status', 'paid')->count();
            $starsVolume = (int) Payment::where('status', 'paid')->sum('total_amount');

            $this->telegram->sendMessage(
                $actor->telegram_id,
                "📈 Live ChatMingle Metrics\n\n"
                . "👥 Total Users: {$totalUsers} (24h active: {$activeToday})\n"
                . "💬 Active Conversations: {$activeChats}\n"
                . "⏳ Waiting in Queue: {$queueDepth}\n"
                . "🚩 Open Reports: {$openReports}\n"
                . "⭐ Paid Orders: {$paidCount} ({$starsVolume} XTR Stars)"
            );
            return;
        }

        if ($command === '/reports') {
            $reports = Report::with(['reporter', 'reportedUser'])
                ->where('status', 'open')
                ->latest()
                ->limit(10)
                ->get();

            if ($reports->isEmpty()) {
                $this->telegram->sendMessage($actor->telegram_id, "✅ No open reports in the moderation queue.");
                return;
            }

            $lines = ["🚩 Open Moderation Reports:"];
            foreach ($reports as $r) {
                $reportedTg = $r->reportedUser?->telegram_id ?? 'unknown';
                $lines[] = "• #{$r->id} | Target TG: {$reportedTg} | Reason: {$r->reason_code} | {$r->created_at?->diffForHumans()}";
            }
            $this->telegram->sendMessage($actor->telegram_id, implode("\n", $lines));
            return;
        }

        if (in_array($command, ['/ban', '/unban'], true)) {
            $parts = preg_split('/\s+/', $argsString, 2);
            $targetTgId = (int) ($parts[0] ?? 0);
            $reason = trim($parts[1] ?? '');

            if ($targetTgId <= 0 || $reason === '') {
                $this->telegram->sendMessage($actor->telegram_id, "Usage: {$command} <telegram_id> <reason>");
                return;
            }

            $target = TelegramUser::where('telegram_id', $targetTgId)->first();
            if (!$target) {
                $this->telegram->sendMessage($actor->telegram_id, "User with Telegram ID {$targetTgId} not found.");
                return;
            }

            $action = $command === '/ban' ? 'ban' : 'unban';
            $this->reportService->applyModerationAction(
                targetUser: $target,
                action: $action,
                reason: $reason,
                actorTelegramId: $actor->telegram_id
            );

            $this->telegram->sendMessage($actor->telegram_id, "✅ Action [{$action}] applied to user {$targetTgId}.");
            return;
        }

        if ($command === '/mute') {
            $parts = preg_split('/\s+/', $argsString, 3);
            $targetTgId = (int) ($parts[0] ?? 0);
            $minutes = (int) ($parts[1] ?? 0);
            $reason = trim($parts[2] ?? '');

            if ($targetTgId <= 0 || $minutes <= 0 || $reason === '') {
                $this->telegram->sendMessage($actor->telegram_id, "Usage: /mute <telegram_id> <minutes> <reason>");
                return;
            }

            $target = TelegramUser::where('telegram_id', $targetTgId)->first();
            if (!$target) {
                $this->telegram->sendMessage($actor->telegram_id, "User with Telegram ID {$targetTgId} not found.");
                return;
            }

            $this->reportService->applyModerationAction(
                targetUser: $target,
                action: 'mute',
                reason: $reason,
                durationMinutes: $minutes,
                actorTelegramId: $actor->telegram_id
            );

            $this->telegram->sendMessage($actor->telegram_id, "✅ User {$targetTgId} muted for {$minutes} minutes.");
            return;
        }

        if ($command === '/grant') {
            $parts = preg_split('/\s+/', $argsString, 3);
            $targetTgId = (int) ($parts[0] ?? 0);
            $credits = (int) ($parts[1] ?? 0);
            $reason = trim($parts[2] ?? '');

            if ($targetTgId <= 0 || $credits <= 0 || $reason === '') {
                $this->telegram->sendMessage($actor->telegram_id, "Usage: /grant <telegram_id> <credits> <reason>");
                return;
            }

            $target = TelegramUser::where('telegram_id', $targetTgId)->first();
            if (!$target) {
                $this->telegram->sendMessage($actor->telegram_id, "User with Telegram ID {$targetTgId} not found.");
                return;
            }

            $newBalance = $this->quotaService->grantPaidCredits(
                user: $target,
                credits: $credits,
                reason: 'admin_adjustment',
                metadata: ['reason' => $reason, 'actor_telegram_id' => $actor->telegram_id]
            );

            AuditLog::record(
                action: 'user.grant_credits',
                targetType: 'telegram_user',
                targetId: $target->id,
                reason: $reason,
                metadata: ['credits' => $credits, 'balance_after' => $newBalance],
                actorTelegramId: $actor->telegram_id
            );

            $this->telegram->sendMessage($actor->telegram_id, "✅ Granted {$credits} credits to {$targetTgId}. New balance: {$newBalance}.");
        }
    }

    public function isTelegramAdmin(TelegramUser $user): bool
    {
        $allowlist = config('services.telegram.admin_ids', []);
        if (in_array((int) $user->telegram_id, $allowlist, true)) {
            return true;
        }

        return AdminUser::query()
            ->where('enabled', true)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('telegram_id', $user->telegram_id);
            })
            ->exists();
    }

    private function resolveOrCreateUser(int $telegramId, string $languageHint = 'en', array|string|null $fromData = null): TelegramUser
    {
        $from = is_array($fromData) ? $fromData : ['username' => $fromData];
        $username = isset($from['username']) && trim((string) $from['username']) !== ''
            ? ltrim(trim((string) $from['username']), '@')
            : null;
        $firstName = isset($from['first_name']) && trim((string) $from['first_name']) !== ''
            ? trim((string) $from['first_name'])
            : null;
        $lastName = isset($from['last_name']) && trim((string) $from['last_name']) !== ''
            ? trim((string) $from['last_name'])
            : null;
        $isPremium = (bool) ($from['is_premium'] ?? false);

        $user = TelegramUser::firstOrCreate(
            ['telegram_id' => $telegramId],
            [
                'username' => $username,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'is_premium' => $isPremium,
                'language_code' => $languageHint,
                'status' => 'active',
                'paid_credits_balance' => 0,
                'last_seen_at' => now(),
            ]
        );

        $updates = ['last_seen_at' => now()];
        if ($username !== null) {
            $updates['username'] = $username;
        }
        if ($firstName !== null) {
            $updates['first_name'] = $firstName;
        }
        if ($lastName !== null) {
            $updates['last_name'] = $lastName;
        }
        if (isset($from['is_premium'])) {
            $updates['is_premium'] = $isPremium;
        }
        $user->update($updates);

        if ($username !== null && $username !== '') {
            $cleanUsername = strtolower($username);
            $allowlist = config('services.telegram.admin_ids', []);
            if (in_array($cleanUsername, $allowlist, true)) {
                AdminUser::updateOrCreate(
                    ['telegram_id' => $telegramId],
                    [
                        'user_id' => $user->id,
                        'role' => 'super_admin',
                        'enabled' => true,
                    ]
                );
            }
        }

        return $user;
    }

    private function supportedLanguages(): array
    {
        $configured = BotSetting::getValue(
            'supported_languages',
            config('services.telegram.supported_languages', ['en' => '🇬🇧 English'])
        );

        return is_array($configured) && !empty($configured)
            ? $configured
            : ['en' => '🇬🇧 English'];
    }

    public function normalizeLanguageCode(string $rawCode): string
    {
        $normalized = strtolower(trim(explode('-', str_replace('_', '-', $rawCode))[0] ?? 'en'));
        $supported = array_keys($this->supportedLanguages());

        return in_array($normalized, $supported, true) ? $normalized : 'en';
    }

    private function buildLanguageKeyboard(string $selectedCode, bool $includeFindButton = false): array
    {
        $languages = $this->supportedLanguages();
        $rows = [];
        $currentRow = [];

        foreach ($languages as $code => $label) {
            $prefix = $code === $selectedCode ? '✅ ' : '';
            $currentRow[] = [
                'text' => $prefix . $label,
                'callback_data' => "lang:set:{$code}",
            ];

            if (count($currentRow) === 2) {
                $rows[] = $currentRow;
                $currentRow = [];
            }
        }

        if (!empty($currentRow)) {
            $rows[] = $currentRow;
        }

        if ($includeFindButton) {
            $rows[] = [
                ['text' => '🔍 Search Random Person (/search)', 'callback_data' => 'chat:find'],
            ];
        }

        return ['inline_keyboard' => $rows];
    }
}
