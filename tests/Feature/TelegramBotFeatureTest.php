<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\TelegramUser;
use App\Models\User;
use App\Services\Billing\StarsPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class TelegramBotFeatureTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test_webhook_secret_token_12345';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.token' => '123456:TEST_BOT_TOKEN',
            'services.telegram.webhook_secret' => $this->secret,
            'services.telegram.admin_ids' => [999999],
            'services.telegram.free_matches_per_day' => 10,
            'services.telegram.pack_credits' => 50,
            'services.telegram.pack_stars' => 100,
            'services.telegram.search_cooldown_seconds' => 0,
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 10]], 200),
        ]);
    }

    public function test_webhook_rejects_invalid_secret_and_deduplicates_update_id(): void
    {
        // Forged secret on /api/telegram/webhook -> 403
        $this->postJson('/api/telegram/webhook', ['update_id' => 1001], [
            'X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret',
        ])->assertStatus(403);

        // Valid secret on /api/telegram/webhook -> 200
        $payload = [
            'update_id' => 1001,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 50001, 'language_code' => 'en'],
                'chat' => ['id' => 50001, 'type' => 'private'],
                'text' => '/start',
            ],
        ];

        $this->postJson('/api/telegram/webhook', $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => $this->secret,
        ])->assertOk()->assertJson(['ok' => true]);

        // Duplicate update_id -> idempotently ignored
        $this->postJson('/api/telegram/webhook', $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => $this->secret,
        ])->assertOk()->assertJson(['ok' => true, 'duplicate' => true]);
    }

    public function test_full_user_journey_onboarding_matching_anonymous_relay_report_and_block(): void
    {
        // 1. User A and User B send /start and accept terms
        foreach ([60001, 60002] as $idx => $tgId) {
            $this->sendWebhook([
                'update_id' => 2000 + $idx,
                'callback_query' => [
                    'id' => 'cb_' . $tgId,
                    'from' => ['id' => $tgId, 'language_code' => 'en', 'username' => "secret_user_{$tgId}"],
                    'data' => 'terms:accept',
                ],
            ]);
        }

        $userA = TelegramUser::where('telegram_id', 60001)->firstOrFail();
        $userB = TelegramUser::where('telegram_id', 60002)->firstOrFail();
        $this->assertNotNull($userA->accepted_terms_at);
        $this->assertNotNull($userB->accepted_terms_at);

        // 2. Both tap /find and get matched
        $this->sendWebhook([
            'update_id' => 2010,
            'message' => [
                'message_id' => 10,
                'from' => ['id' => 60001, 'language_code' => 'en'],
                'chat' => ['id' => 60001, 'type' => 'private'],
                'text' => '/find',
            ],
        ]);

        $this->sendWebhook([
            'update_id' => 2011,
            'message' => [
                'message_id' => 11,
                'from' => ['id' => 60002, 'language_code' => 'en'],
                'chat' => ['id' => 60002, 'type' => 'private'],
                'text' => '/find',
            ],
        ]);

        $conv = $userA->activeConversation();
        $this->assertNotNull($conv);
        $this->assertSame($conv->id, $userB->activeConversation()?->id);

        // 3. User A sends a text message; verify it is relayed to User B without exposing User A's identity
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 99]], 200),
        ]);

        $this->sendWebhook([
            'update_id' => 2012,
            'message' => [
                'message_id' => 12,
                'from' => ['id' => 60001, 'first_name' => 'AliceSecret', 'username' => 'alice_hidden'],
                'chat' => ['id' => 60001, 'type' => 'private'],
                'text' => 'Hello anonymous friend!',
            ],
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return str_contains($request->url(), '/sendMessage')
                && (int) ($body['chat_id'] ?? 0) === 60002
                && str_contains((string) ($body['text'] ?? ''), 'Hello anonymous friend!')
                && !str_contains((string) ($body['text'] ?? ''), 'AliceSecret')
                && !str_contains((string) ($body['text'] ?? ''), 'alice_hidden')
                && !str_contains((string) ($body['text'] ?? ''), '60001')
                && ($body['protect_content'] ?? false) === true;
        });

        // 4. User B reports and blocks User A
        $this->sendWebhook([
            'update_id' => 2013,
            'callback_query' => [
                'id' => 'cb_rep',
                'from' => ['id' => 60002],
                'data' => 'report:reason:spam',
            ],
        ]);

        $this->assertDatabaseHas('reports', [
            'reporter_user_id' => $userB->id,
            'reported_user_id' => $userA->id,
            'reason_code' => 'spam',
            'status' => 'open',
        ]);

        $this->sendWebhook([
            'update_id' => 2014,
            'message' => [
                'message_id' => 14,
                'from' => ['id' => 60002],
                'chat' => ['id' => 60002, 'type' => 'private'],
                'text' => '/block',
            ],
        ]);

        $this->assertDatabaseHas('blocked_users', [
            'blocker_user_id' => $userB->id,
            'blocked_user_id' => $userA->id,
        ]);
    }

    public function test_stars_payment_verification_idempotency_and_tamper_rejection(): void
    {
        $user = TelegramUser::create([
            'telegram_id' => 70001,
            'language_code' => 'en',
            'accepted_terms_at' => now(),
            'status' => 'active',
        ]);

        $otherUser = TelegramUser::create([
            'telegram_id' => 70002,
            'language_code' => 'en',
            'accepted_terms_at' => now(),
            'status' => 'active',
        ]);

        $paymentService = app(StarsPaymentService::class);
        $payment = $paymentService->createPackInvoice($user);

        $this->assertSame('XTR', $payment->currency);
        $this->assertSame(100, $payment->total_amount);
        $this->assertSame('pending', $payment->status);

        // Pre-checkout with wrong amount -> rejected, credits remain 0
        $preCheckoutWrong = $paymentService->handlePreCheckoutQuery([
            'id' => 'pcq_1',
            'from' => ['id' => 70001],
            'currency' => 'XTR',
            'total_amount' => 1,
            'invoice_payload' => $payment->invoice_payload,
        ]);
        $this->assertFalse($preCheckoutWrong);
        $this->assertSame(0, $user->fresh()->paid_credits_balance);

        // Pre-checkout with valid details -> approved, still 0 credits until successful_payment
        $preCheckoutValid = $paymentService->handlePreCheckoutQuery([
            'id' => 'pcq_2',
            'from' => ['id' => 70001],
            'currency' => 'XTR',
            'total_amount' => 100,
            'invoice_payload' => $payment->invoice_payload,
        ]);
        $this->assertTrue($preCheckoutValid);
        $this->assertSame(0, $user->fresh()->paid_credits_balance);

        // Wrong user attempting fulfillment -> throws exception
        try {
            $paymentService->handleSuccessfulPayment($otherUser, [
                'currency' => 'XTR',
                'total_amount' => 100,
                'invoice_payload' => $payment->invoice_payload,
                'telegram_payment_charge_id' => 'charge_wrong_user',
            ]);
            $this->fail('Expected exception for wrong user payment fulfillment.');
        } catch (RuntimeException) {
            $this->assertSame(0, $otherUser->fresh()->paid_credits_balance);
        }

        // Valid successful_payment -> credits exactly 50 searches
        $fulfilled = $paymentService->handleSuccessfulPayment($user, [
            'currency' => 'XTR',
            'total_amount' => 100,
            'invoice_payload' => $payment->invoice_payload,
            'telegram_payment_charge_id' => 'charge_valid_001',
        ]);

        $this->assertSame('paid', $fulfilled->status);
        $this->assertSame(50, $user->fresh()->paid_credits_balance);

        // Duplicate successful_payment delivery -> idempotent, never double-credits
        $duplicate = $paymentService->handleSuccessfulPayment($user, [
            'currency' => 'XTR',
            'total_amount' => 100,
            'invoice_payload' => $payment->invoice_payload,
            'telegram_payment_charge_id' => 'charge_valid_001',
        ]);

        $this->assertSame('paid', $duplicate->status);
        $this->assertSame(50, $user->fresh()->paid_credits_balance);
    }

    public function test_admin_authorization_and_audit_logging(): void
    {
        $normalUser = TelegramUser::create([
            'telegram_id' => 80001,
            'language_code' => 'en',
            'accepted_terms_at' => now(),
            'status' => 'active',
        ]);

        // Non-admin user tries /ban -> denied
        $this->sendWebhook([
            'update_id' => 3001,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 80001],
                'chat' => ['id' => 80001, 'type' => 'private'],
                'text' => '/ban 80001 self-ban-attempt',
            ],
        ]);
        $this->assertSame('active', $normalUser->fresh()->status);

        // Authorized Telegram admin (999999) executes /ban -> succeeds and logs audit event
        $this->sendWebhook([
            'update_id' => 3002,
            'message' => [
                'message_id' => 2,
                'from' => ['id' => 999999],
                'chat' => ['id' => 999999, 'type' => 'private'],
                'text' => '/ban 80001 Spamming links in chat',
            ],
        ]);

        $this->assertSame('banned', $normalUser->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'user.ban')->where('target_id', $normalUser->id)->exists());

        // Unauthenticated web request to /admin is redirected to /admin/login
        $this->get('/admin')->assertRedirect('/admin/login');

        // Authenticated web admin can access dashboard
        $webAdmin = User::create([
            'name' => 'Admin Tester',
            'email' => 'tester@example.com',
            'password' => 'secret123',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($webAdmin)->get('/admin')->assertOk();
    }

    public function test_search_next_stop_help_and_10th_search_end_payment_link(): void
    {
        // Pre-seed User 1 with 9 free matches already used today (1 remaining before hitting 10)
        $user1 = TelegramUser::create([
            'telegram_id' => 91001,
            'language_code' => 'en',
            'accepted_terms_at' => now(),
            'status' => 'active',
        ]);

        \App\Models\DailyUsage::create([
            'user_id' => $user1->id,
            'usage_date' => now('UTC')->toDateString(),
            'free_matches_used' => 9,
            'paid_credits_balance' => 0,
        ]);

        // User 1 sends /search (10th search)
        $this->sendWebhook([
            'update_id' => 4001,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 91001, 'language_code' => 'en'],
                'chat' => ['id' => 91001, 'type' => 'private'],
                'text' => '/search',
            ],
        ]);

        // Other person (User 2) joins directly with /search (without prior /start) and gets matched immediately!
        $this->sendWebhook([
            'update_id' => 4002,
            'message' => [
                'message_id' => 2,
                'from' => ['id' => 91002, 'language_code' => 'en'],
                'chat' => ['id' => 91002, 'type' => 'private'],
                'text' => '/search',
            ],
        ]);

        $this->assertNotNull($user1->fresh()->activeConversation());

        // Now User 1 sends /stop to end their 10th conversation -> End message + Payment Link MUST come!
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => 'https://t.me/$test_stars_link'], 200),
        ]);

        $this->sendWebhook([
            'update_id' => 4003,
            'message' => [
                'message_id' => 3,
                'from' => ['id' => 91001, 'language_code' => 'en'],
                'chat' => ['id' => 91001, 'type' => 'private'],
                'text' => '/stop',
            ],
        ]);

        $payment = Payment::where('user_id', $user1->id)->where('status', 'pending')->first();
        $this->assertNotNull($payment, 'Pending payment record for payment link should be created after 10th search ends.');

        Http::assertSent(function ($request) {
            $body = $request->data();
            return str_contains($request->url(), '/sendMessage')
                && (int) ($body['chat_id'] ?? 0) === 91001
                && str_contains((string) ($body['text'] ?? ''), 'Payment Link:')
                && str_contains((string) ($body['text'] ?? ''), '10 / 10');
        });

        // Test clicking & completing the payment link (/pay/{payload})
        $this->get("/pay/{$payment->invoice_payload}")->assertOk();
        $this->post("/pay/{$payment->invoice_payload}/complete")->assertRedirect("/pay/{$payment->invoice_payload}");

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame(50, $user1->fresh()->paid_credits_balance);

        // Test /help command
        $this->sendWebhook([
            'update_id' => 4004,
            'message' => [
                'message_id' => 4,
                'from' => ['id' => 91001, 'language_code' => 'en'],
                'chat' => ['id' => 91001, 'type' => 'private'],
                'text' => '/help',
            ],
        ]);
    }

    public function test_chatmingle_search_messages_post_chat_feedback_ratings_and_safety_reports_with_description(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 50]], 200),
        ]);

        // 1. User 1 sends /search -> receives "🔎 Searching for people..."
        $this->sendWebhook([
            'update_id' => 5001,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 95001, 'language_code' => 'en'],
                'chat' => ['id' => 95001, 'type' => 'private'],
                'text' => '/search',
            ],
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return str_contains($request->url(), '/sendMessage')
                && (int) ($body['chat_id'] ?? 0) === 95001
                && str_contains((string) ($body['text'] ?? ''), '🔎 Searching for people...');
        });

        // 2. User 2 sends /search -> both receive "🎉 You are in a chat right now! :)"
        $this->sendWebhook([
            'update_id' => 5002,
            'message' => [
                'message_id' => 2,
                'from' => ['id' => 95002, 'language_code' => 'en'],
                'chat' => ['id' => 95002, 'type' => 'private'],
                'text' => '/search',
            ],
        ]);

        $user1 = TelegramUser::where('telegram_id', 95001)->firstOrFail();
        $user2 = TelegramUser::where('telegram_id', 95002)->firstOrFail();
        $conv = $user1->activeConversation();
        $this->assertNotNull($conv);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return str_contains($request->url(), '/sendMessage')
                && (int) ($body['chat_id'] ?? 0) === 95001
                && str_contains((string) ($body['text'] ?? ''), '🎉 You are in a chat right now! :)');
        });

        // 3. User 1 sends /stop -> both users receive "How was your chat?" feedback menu
        $this->sendWebhook([
            'update_id' => 5003,
            'message' => [
                'message_id' => 3,
                'from' => ['id' => 95001, 'language_code' => 'en'],
                'chat' => ['id' => 95001, 'type' => 'private'],
                'text' => '/stop',
            ],
        ]);

        foreach ([95001, 95002] as $tgId) {
            Http::assertSent(function ($request) use ($tgId) {
                $body = $request->data();
                return str_contains($request->url(), '/sendMessage')
                    && (int) ($body['chat_id'] ?? 0) === $tgId
                    && str_contains((string) ($body['text'] ?? ''), 'How was your chat?');
            });
        }

        // 4. User 1 submits 👍 Thumbs up rating -> saved in chat_feedback
        $this->sendWebhook([
            'update_id' => 5004,
            'callback_query' => [
                'id' => 'cb_fb_1',
                'from' => ['id' => 95001],
                'data' => "fb:{$conv->id}:thumbs_up",
            ],
        ]);

        $this->assertDatabaseHas('chat_feedback', [
            'match_id' => $conv->id,
            'reporter_user_id' => $user1->id,
            'target_user_id' => $user2->id,
            'feedback_type' => 'thumbs_up',
            'status' => 'reviewed',
        ]);

        // Duplicate rating (thumbs_down after thumbs_up on same conversation) is blocked
        $this->sendWebhook([
            'update_id' => 5005,
            'callback_query' => [
                'id' => 'cb_fb_2',
                'from' => ['id' => 95001],
                'data' => "fb:{$conv->id}:thumbs_down",
            ],
        ]);

        $this->assertDatabaseMissing('chat_feedback', [
            'match_id' => $conv->id,
            'reporter_user_id' => $user1->id,
            'feedback_type' => 'thumbs_down',
        ]);

        // 5. User 2 submits a safety report (🤬 Vulgar partner) + optional description
        $this->sendWebhook([
            'update_id' => 5006,
            'callback_query' => [
                'id' => 'cb_fb_3',
                'from' => ['id' => 95002],
                'data' => "fb:{$conv->id}:vulgar",
            ],
        ]);

        $this->assertDatabaseHas('chat_feedback', [
            'match_id' => $conv->id,
            'reporter_user_id' => $user2->id,
            'target_user_id' => $user1->id,
            'feedback_type' => 'vulgar',
            'status' => 'pending',
        ]);

        // User 2 sends optional description text
        $this->sendWebhook([
            'update_id' => 5007,
            'message' => [
                'message_id' => 7,
                'from' => ['id' => 95002],
                'chat' => ['id' => 95002, 'type' => 'private'],
                'text' => 'Used explicit abusive language immediately after connecting.',
            ],
        ]);

        $this->assertDatabaseHas('chat_feedback', [
            'match_id' => $conv->id,
            'reporter_user_id' => $user2->id,
            'feedback_type' => 'vulgar',
            'description' => 'Used explicit abusive language immediately after connecting.',
        ]);

        $this->assertDatabaseHas('reports', [
            'conversation_id' => $conv->id,
            'reporter_user_id' => $user2->id,
            'reported_user_id' => $user1->id,
            'details' => 'Used explicit abusive language immediately after connecting.',
            'status' => 'open',
        ]);
    }

    private function sendWebhook(array $payload): void
    {
        $this->postJson('/telegram/webhook', $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => $this->secret,
        ])->assertOk();
    }
}
