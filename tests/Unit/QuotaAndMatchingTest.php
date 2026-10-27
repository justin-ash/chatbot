<?php

namespace Tests\Unit;

use App\Models\BlockedUser;
use App\Models\TelegramUser;
use App\Services\Chat\MatchingService;
use App\Services\Usage\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class QuotaAndMatchingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);
    }

    public function test_quota_boundaries_and_credit_ledger_reconciliation(): void
    {
        config(['services.telegram.free_matches_per_day' => 10]);

        $quotaService = app(QuotaService::class);

        $user = TelegramUser::create([
            'telegram_id' => 100001,
            'language_code' => 'en',
            'accepted_terms_at' => now(),
            'status' => 'active',
        ]);

        // Spend all 10 free daily matches
        for ($i = 1; $i <= 10; $i++) {
            $this->assertTrue($quotaService->canStartSearch($user)->allowed);
            $source = $quotaService->consumeForSuccessfulMatch($user);
            $this->assertSame('free', $source);
        }

        // 11th search is blocked when paid credits = 0
        $decision = $quotaService->canStartSearch($user);
        $this->assertFalse($decision->allowed);
        $this->assertSame('exhausted', $decision->source);

        // Grant 50 paid credits and verify ledger reconciliation
        $quotaService->grantPaidCredits($user, 50, 'purchase_grant');
        $this->assertTrue($quotaService->canStartSearch($user)->allowed);
        $this->assertSame('paid', $quotaService->canStartSearch($user)->source);

        // Spend 1 paid credit
        $source = $quotaService->consumeForSuccessfulMatch($user);
        $this->assertSame('paid', $source);

        $balance = $quotaService->currentBalance($user);
        $this->assertSame(0, $balance['free_remaining']);
        $this->assertSame(49, $balance['paid_remaining']);

        $reconciliation = $quotaService->reconcileUserLedger($user->fresh());
        $this->assertTrue($reconciliation['is_consistent']);
        $this->assertSame(49, $reconciliation['expected_balance']);
    }

    public function test_exact_language_matching_and_blocked_pair_exclusion(): void
    {
        $matchingService = app(MatchingService::class);

        $userEn1 = TelegramUser::create([
            'telegram_id' => 200001,
            'language_code' => 'en',
            'accepted_terms_at' => now(),
            'status' => 'active',
        ]);

        $userEs = TelegramUser::create([
            'telegram_id' => 200002,
            'language_code' => 'es',
            'accepted_terms_at' => now(),
            'status' => 'active',
        ]);

        $userEn2 = TelegramUser::create([
            'telegram_id' => 200003,
            'language_code' => 'en',
            'accepted_terms_at' => now(),
            'status' => 'active',
        ]);

        // Enqueue English user 1 and Spanish user
        $res1 = $matchingService->findOrEnqueue($userEn1);
        $this->assertSame('queued', $res1['status']);

        $resEs = $matchingService->findOrEnqueue($userEs);
        $this->assertSame('queued', $resEs['status']); // Different language, must not match

        // Block between En1 and En2 prevents matching
        BlockedUser::create([
            'blocker_user_id' => $userEn1->id,
            'blocked_user_id' => $userEn2->id,
        ]);

        $resBlocked = $matchingService->findOrEnqueue($userEn2);
        $this->assertSame('queued', $resBlocked['status']);

        // Remove block and retry matching En2 -> matches with En1
        BlockedUser::query()->delete();
        $matchingService->leaveQueue($userEn2);

        $resMatched = $matchingService->findOrEnqueue($userEn2);
        $this->assertSame('matched', $resMatched['status']);
        $this->assertSame($userEn1->id, $resMatched['partner']->id);
    }

    public function test_user_cannot_block_self(): void
    {
        $user = TelegramUser::create([
            'telegram_id' => 300001,
            'language_code' => 'en',
            'accepted_terms_at' => now(),
            'status' => 'active',
        ]);

        $this->expectException(InvalidArgumentException::class);

        BlockedUser::create([
            'blocker_user_id' => $user->id,
            'blocked_user_id' => $user->id,
        ]);
    }
}
