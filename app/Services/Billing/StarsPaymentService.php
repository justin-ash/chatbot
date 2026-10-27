<?php

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\BotSetting;
use App\Models\Payment;
use App\Models\TelegramUser;
use App\Services\Telegram\TelegramApi;
use App\Services\Usage\QuotaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StarsPaymentService
{
    public function __construct(
        private readonly TelegramApi $telegram,
        private readonly QuotaService $quotaService,
    ) {}

    public function packStarsAmount(): int
    {
        return max(1, (int) BotSetting::getValue(
            'pack_stars',
            (int) config('services.telegram.pack_stars', 100)
        ));
    }

    public function packCreditsCount(): int
    {
        return max(1, (int) BotSetting::getValue(
            'pack_credits',
            (int) config('services.telegram.pack_credits', 50)
        ));
    }

    public function createPackInvoice(TelegramUser $user): Payment
    {
        $starsAmount = $this->packStarsAmount();
        $creditsCount = $this->packCreditsCount();
        $payload = 'pack50_' . Str::random(48);

        $payment = Payment::create([
            'user_id' => $user->id,
            'invoice_payload' => $payload,
            'currency' => 'XTR',
            'total_amount' => $starsAmount,
            'credits_granted' => $creditsCount,
            'status' => 'pending',
        ]);

        $this->telegram->sendInvoice($user->telegram_id, [
            'title' => "{$creditsCount} Additional Search Credits",
            'description' => "Adds {$creditsCount} random user search credits to your account. Price: {$starsAmount} Telegram Stars (XTR).",
            'payload' => $payload,
            'currency' => 'XTR',
            'prices' => [[
                'label' => "{$creditsCount} search credits",
                'amount' => $starsAmount,
            ]],
        ]);

        return $payment;
    }

    /**
     * Create a pending payment record and generate both a Telegram Stars invoice link (if available)
     * and a direct web payment link so the user always gets a working payment URL and button.
     *
     * @return array{payment: Payment, stars_link: ?string, web_link: string, primary_link: string}
     */
    public function createPaymentLinkForUser(TelegramUser $user): array
    {
        $starsAmount = $this->packStarsAmount();
        $creditsCount = $this->packCreditsCount();

        // Reuse a recent pending order (< 30 mins old) or create a fresh one
        $payment = Payment::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('total_amount', $starsAmount)
            ->where('credits_granted', $creditsCount)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->latest('id')
            ->first();

        if (!$payment) {
            $payment = Payment::create([
                'user_id' => $user->id,
                'invoice_payload' => 'pack50_' . Str::random(48),
                'currency' => 'XTR',
                'total_amount' => $starsAmount,
                'credits_granted' => $creditsCount,
                'status' => 'pending',
            ]);
        }

        $starsLink = $this->telegram->createInvoiceLink([
            'title' => "{$creditsCount} Additional Search Credits",
            'description' => "Unlock {$creditsCount} more random user searches ({$starsAmount} Telegram Stars).",
            'payload' => $payment->invoice_payload,
            'currency' => 'XTR',
            'prices' => [[
                'label' => "{$creditsCount} search credits",
                'amount' => $starsAmount,
            ]],
        ]);

        $configuredExternalUrl = trim((string) config('services.telegram.custom_payment_url', ''));
        $webLink = $configuredExternalUrl !== ''
            ? $configuredExternalUrl . (str_contains($configuredExternalUrl, '?') ? '&' : '?') . 'payload=' . urlencode($payment->invoice_payload)
            : url('/pay/' . $payment->invoice_payload);

        return [
            'payment' => $payment,
            'stars_link' => $starsLink,
            'web_link' => $webLink,
            'primary_link' => $starsLink ?: $webLink,
        ];
    }

    public function handlePreCheckoutQuery(array $preCheckoutQuery): bool
    {
        $queryId = (string) ($preCheckoutQuery['id'] ?? '');
        $payload = (string) ($preCheckoutQuery['invoice_payload'] ?? '');
        $currency = (string) ($preCheckoutQuery['currency'] ?? '');
        $totalAmount = (int) ($preCheckoutQuery['total_amount'] ?? 0);
        $fromTelegramId = (int) ($preCheckoutQuery['from']['id'] ?? 0);

        if ($queryId === '' || $payload === '' || $fromTelegramId <= 0) {
            if ($queryId !== '') {
                $this->telegram->answerPreCheckoutQuery($queryId, false, 'Invalid checkout request.');
            }
            return false;
        }

        $payment = Payment::with('user')->where('invoice_payload', $payload)->first();

        if (!$payment) {
            $this->telegram->answerPreCheckoutQuery($queryId, false, 'Order not found or expired.');
            return false;
        }

        if ($payment->status !== 'pending') {
            $this->telegram->answerPreCheckoutQuery($queryId, false, 'This order has already been processed.');
            return false;
        }

        if ($payment->created_at && $payment->created_at->lt(now()->subHours(24))) {
            $payment->update(['status' => 'failed']);
            $this->telegram->answerPreCheckoutQuery($queryId, false, 'This invoice has expired. Please use /buy to create a new one.');
            return false;
        }

        if (
            !$payment->user ||
            (int) $payment->user->telegram_id !== $fromTelegramId ||
            $currency !== 'XTR' ||
            $totalAmount !== (int) $payment->total_amount
        ) {
            $this->telegram->answerPreCheckoutQuery($queryId, false, 'Payment verification details did not match the order.');
            return false;
        }

        $this->telegram->answerPreCheckoutQuery($queryId, true);
        return true;
    }

    public function handleSuccessfulPayment(TelegramUser $telegramUser, array $paymentData, ?string $rawUpdateHash = null): Payment
    {
        $payload = (string) ($paymentData['invoice_payload'] ?? '');
        $currency = (string) ($paymentData['currency'] ?? '');
        $totalAmount = (int) ($paymentData['total_amount'] ?? 0);
        $telegramChargeId = trim((string) ($paymentData['telegram_payment_charge_id'] ?? ''));
        $providerChargeId = isset($paymentData['provider_payment_charge_id'])
            ? (string) $paymentData['provider_payment_charge_id']
            : null;

        if ($payload === '' || $telegramChargeId === '') {
            throw new RuntimeException('Payment verification failed: missing payload or charge ID.');
        }

        return DB::transaction(function () use (
            $payload,
            $currency,
            $totalAmount,
            $telegramChargeId,
            $providerChargeId,
            $telegramUser,
            $rawUpdateHash
        ) {
            $payment = Payment::where('invoice_payload', $payload)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                throw new RuntimeException('Payment verification failed: unknown invoice payload.');
            }

            if ($payment->status === 'paid') {
                return $payment; // Idempotent duplicate delivery
            }

            if ($payment->status !== 'pending') {
                throw new RuntimeException("Payment cannot be fulfilled from state [{$payment->status}].");
            }

            if (
                (int) $payment->user_id !== (int) $telegramUser->id ||
                $currency !== 'XTR' ||
                $totalAmount !== (int) $payment->total_amount
            ) {
                throw new RuntimeException('Payment verification failed: user, currency, or amount mismatch.');
            }

            $duplicateCharge = Payment::where('telegram_payment_charge_id', $telegramChargeId)
                ->where('id', '!=', $payment->id)
                ->lockForUpdate()
                ->exists();

            if ($duplicateCharge) {
                throw new RuntimeException('Payment verification failed: duplicate telegram_payment_charge_id.');
            }

            $creditsToGrant = (int) ($payment->credits_granted ?: $this->packCreditsCount());

            $payment->update([
                'telegram_payment_charge_id' => $telegramChargeId,
                'provider_payment_charge_id' => $providerChargeId,
                'status' => 'paid',
                'credited_at' => now(),
                'raw_update_hash' => $rawUpdateHash,
            ]);

            $this->quotaService->grantPaidCredits(
                user: $telegramUser,
                credits: $creditsToGrant,
                reason: 'purchase_grant',
                payment: $payment,
                metadata: [
                    'invoice_payload' => $payload,
                    'telegram_payment_charge_id' => $telegramChargeId,
                    'stars_amount' => $totalAmount,
                ]
            );

            return $payment->fresh();
        });
    }

    public function refundPayment(Payment $payment, int $adminId, string $reason): Payment
    {
        if ($payment->status !== 'paid') {
            throw new RuntimeException('Only paid orders can be refunded.');
        }

        if (!$payment->telegram_payment_charge_id) {
            throw new RuntimeException('Payment does not have a Telegram charge ID for refund.');
        }

        $payment->loadMissing('user');

        // Call Telegram refundStarPayment API
        $this->telegram->refundStarPayment(
            (int) $payment->user->telegram_id,
            $payment->telegram_payment_charge_id
        );

        return DB::transaction(function () use ($payment, $adminId, $reason) {
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status === 'refunded') {
                return $lockedPayment;
            }

            $lockedPayment->update([
                'status' => 'refunded',
                'refunded_at' => now(),
            ]);

            $creditsToReverse = (int) ($lockedPayment->credits_granted ?: $this->packCreditsCount());

            $this->quotaService->deductPaidCredits(
                user: $lockedPayment->user,
                creditsToDeduct: $creditsToReverse,
                reason: 'refund_reversal',
                payment: $lockedPayment,
                adminId: $adminId,
                metadata: [
                    'reason' => $reason,
                    'telegram_payment_charge_id' => $lockedPayment->telegram_payment_charge_id,
                ]
            );

            AuditLog::record(
                action: 'payment.refund',
                targetType: 'payment',
                targetId: $lockedPayment->id,
                reason: $reason,
                metadata: [
                    'user_id' => $lockedPayment->user_id,
                    'stars_amount' => $lockedPayment->total_amount,
                    'charge_id' => $lockedPayment->telegram_payment_charge_id,
                ],
                actorAdminId: $adminId
            );

            try {
                $this->telegram->sendMessage(
                    $lockedPayment->user->telegram_id,
                    "ℹ️ Your purchase of {$lockedPayment->total_amount} Telegram Stars has been refunded and unused pack credits have been reversed."
                );
            } catch (Throwable) {
                // Non-fatal notification failure
            }

            return $lockedPayment->fresh();
        });
    }
}
