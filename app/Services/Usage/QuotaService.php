<?php

namespace App\Services\Usage;

use App\Models\BotSetting;
use App\Models\Conversation;
use App\Models\CreditLedger;
use App\Models\DailyUsage;
use App\Models\Payment;
use App\Models\TelegramUser;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class QuotaService
{
    public function usageTimezone(): string
    {
        return (string) (config('app.usage_timezone') ?: config('services.telegram.usage_timezone', 'UTC'));
    }

    public function todayDateString(): string
    {
        return now($this->usageTimezone())->toDateString();
    }

    public function dailyFreeLimit(): int
    {
        return (int) BotSetting::getValue(
            'free_matches_per_day',
            (int) config('services.telegram.free_matches_per_day', 10)
        );
    }

    public function getOrCreateDailyUsage(TelegramUser $user, bool $lock = false): DailyUsage
    {
        $today = $this->todayDateString();

        $query = DailyUsage::query()
            ->where('user_id', $user->id)
            ->where('usage_date', $today);

        if ($lock) {
            $query->lockForUpdate();
        }

        $usage = $query->first();

        if ($usage) {
            return $usage;
        }

        try {
            DailyUsage::create([
                'user_id' => $user->id,
                'usage_date' => $today,
                'free_matches_used' => 0,
                'paid_credits_balance' => (int) $user->paid_credits_balance,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Concurrent insert handled safely
        }

        $retryQuery = DailyUsage::query()
            ->where('user_id', $user->id)
            ->where('usage_date', $today);

        if ($lock) {
            $retryQuery->lockForUpdate();
        }

        return $retryQuery->firstOrFail();
    }

    public function currentBalance(TelegramUser $user): array
    {
        $user->refresh();
        $usage = $this->getOrCreateDailyUsage($user, false);
        $limit = $this->dailyFreeLimit();
        $freeUsed = (int) $usage->free_matches_used;
        $freeRemaining = max(0, $limit - $freeUsed);
        $paidRemaining = max((int) $user->paid_credits_balance, (int) $usage->paid_credits_balance);

        if ((int) $usage->paid_credits_balance !== $paidRemaining) {
            $usage->update(['paid_credits_balance' => $paidRemaining]);
        }

        return [
            'usage_date' => $usage->usage_date,
            'free_limit' => $limit,
            'free_used' => $freeUsed,
            'free_remaining' => $freeRemaining,
            'paid_remaining' => $paidRemaining,
        ];
    }

    public function canStartSearch(TelegramUser $user): QuotaDecision
    {
        $balance = $this->currentBalance($user);

        if ($balance['free_remaining'] > 0) {
            return new QuotaDecision(
                allowed: true,
                source: 'free',
                freeRemaining: $balance['free_remaining'],
                paidRemaining: $balance['paid_remaining'],
            );
        }

        if ($balance['paid_remaining'] > 0) {
            return new QuotaDecision(
                allowed: true,
                source: 'paid',
                freeRemaining: 0,
                paidRemaining: $balance['paid_remaining'],
            );
        }

        return new QuotaDecision(
            allowed: false,
            source: 'exhausted',
            freeRemaining: 0,
            paidRemaining: 0,
            reason: 'Daily free matches exhausted and no paid credits remaining.',
        );
    }

    /**
     * Spend 1 free daily match if available, otherwise 1 paid credit.
     * Caller should run inside a DB transaction (or this method wraps in a transaction).
     */
    public function consumeForSuccessfulMatch(TelegramUser $user, ?Conversation $conversation = null): string
    {
        return DB::transaction(function () use ($user, $conversation) {
            $lockedUser = TelegramUser::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $usage = $this->getOrCreateDailyUsage($lockedUser, true);
            $limit = $this->dailyFreeLimit();

            $paidBalance = max((int) $lockedUser->paid_credits_balance, (int) $usage->paid_credits_balance);

            if ((int) $usage->free_matches_used < $limit) {
                $usage->free_matches_used = (int) $usage->free_matches_used + 1;
                $usage->paid_credits_balance = $paidBalance;
                $usage->save();
                return 'free';
            }

            if ($paidBalance <= 0) {
                throw new RuntimeException('Insufficient search quota or paid credits.');
            }

            $newPaidBalance = $paidBalance - 1;
            $lockedUser->paid_credits_balance = $newPaidBalance;
            $lockedUser->save();

            $usage->paid_credits_balance = $newPaidBalance;
            $usage->save();

            CreditLedger::create([
                'user_id' => $lockedUser->id,
                'direction' => 'debit',
                'amount' => 1,
                'balance_after' => $newPaidBalance,
                'reason' => 'match_spend',
                'conversation_id' => $conversation?->id,
                'metadata' => [
                    'usage_date' => $usage->usage_date,
                ],
            ]);

            return 'paid';
        });
    }

    public function grantPaidCredits(
        TelegramUser $user,
        int $credits,
        string $reason = 'purchase_grant',
        ?Payment $payment = null,
        ?int $adminId = null,
        array $metadata = []
    ): int {
        if ($credits <= 0) {
            throw new RuntimeException('Credits to grant must be positive.');
        }

        return DB::transaction(function () use ($user, $credits, $reason, $payment, $adminId, $metadata) {
            $lockedUser = TelegramUser::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $usage = $this->getOrCreateDailyUsage($lockedUser, true);

            $currentPaid = max((int) $lockedUser->paid_credits_balance, (int) $usage->paid_credits_balance);
            $newPaidBalance = $currentPaid + $credits;

            $lockedUser->paid_credits_balance = $newPaidBalance;
            $lockedUser->save();

            $usage->paid_credits_balance = $newPaidBalance;
            $usage->save();

            CreditLedger::create([
                'user_id' => $lockedUser->id,
                'direction' => 'credit',
                'amount' => $credits,
                'balance_after' => $newPaidBalance,
                'reason' => $reason,
                'payment_id' => $payment?->id,
                'actor_admin_id' => $adminId,
                'metadata' => $metadata,
            ]);

            return $newPaidBalance;
        });
    }

    public function deductPaidCredits(
        TelegramUser $user,
        int $creditsToDeduct,
        string $reason = 'refund_reversal',
        ?Payment $payment = null,
        ?int $adminId = null,
        array $metadata = []
    ): int {
        if ($creditsToDeduct <= 0) {
            return (int) $user->paid_credits_balance;
        }

        return DB::transaction(function () use ($user, $creditsToDeduct, $reason, $payment, $adminId, $metadata) {
            $lockedUser = TelegramUser::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $usage = $this->getOrCreateDailyUsage($lockedUser, true);

            $currentPaid = max((int) $lockedUser->paid_credits_balance, (int) $usage->paid_credits_balance);
            $actualDeduction = min($currentPaid, $creditsToDeduct);
            $newPaidBalance = max(0, $currentPaid - $actualDeduction);

            $lockedUser->paid_credits_balance = $newPaidBalance;
            $lockedUser->save();

            $usage->paid_credits_balance = $newPaidBalance;
            $usage->save();

            CreditLedger::create([
                'user_id' => $lockedUser->id,
                'direction' => 'debit',
                'amount' => $actualDeduction,
                'balance_after' => $newPaidBalance,
                'reason' => $reason,
                'payment_id' => $payment?->id,
                'actor_admin_id' => $adminId,
                'metadata' => array_merge($metadata, [
                    'requested_deduction' => $creditsToDeduct,
                    'actual_deduction' => $actualDeduction,
                ]),
            ]);

            return $newPaidBalance;
        });
    }

    public function reconcileUserLedger(TelegramUser $user): array
    {
        $credits = (int) CreditLedger::where('user_id', $user->id)
            ->where('direction', 'credit')
            ->sum('amount');

        $debits = (int) CreditLedger::where('user_id', $user->id)
            ->where('direction', 'debit')
            ->sum('amount');

        $expectedBalance = max(0, $credits - $debits);
        $actualBalance = (int) $user->paid_credits_balance;

        return [
            'credits_total' => $credits,
            'debits_total' => $debits,
            'expected_balance' => $expectedBalance,
            'actual_balance' => $actualBalance,
            'is_consistent' => $expectedBalance === $actualBalance,
        ];
    }
}
