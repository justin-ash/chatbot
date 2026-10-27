<?php

namespace App\Services\Billing;

use App\Models\AdminPayoutAccount;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WithdrawalService
{
    public function usdPerStarRate(): float
    {
        return (float) config('services.telegram.usd_per_star', 0.02); // 100 Stars = $2.00 USD
    }

    public function inrPerUsdRate(): float
    {
        return (float) config('services.telegram.inr_per_usd', 84.0); // $1.00 = ₹84.00 INR
    }

    /**
     * Calculate gross revenue, withdrawn totals, and available withdrawable balance.
     */
    public function getBalanceSummary(): array
    {
        $totalPaidStars = (int) Payment::where('status', 'paid')->sum('total_amount');

        $withdrawnOrPendingStars = (int) Withdrawal::whereIn('status', ['pending', 'processing', 'completed'])
            ->sum('stars_deducted');

        $availableStars = max(0, $totalPaidStars - $withdrawnOrPendingStars);

        $usdRate = $this->usdPerStarRate();
        $inrRate = $this->inrPerUsdRate();

        $grossUsd = round($totalPaidStars * $usdRate, 2);
        $grossInr = round($grossUsd * $inrRate, 2);

        $withdrawnUsd = round($withdrawnOrPendingStars * $usdRate, 2);
        $withdrawnInr = round($withdrawnUsd * $inrRate, 2);

        $availableUsd = round($availableStars * $usdRate, 2);
        $availableInr = round($availableUsd * $inrRate, 2);

        return [
            'total_paid_stars' => $totalPaidStars,
            'withdrawn_stars' => $withdrawnOrPendingStars,
            'available_stars' => $availableStars,
            'usd_per_star' => $usdRate,
            'inr_per_usd' => $inrRate,
            'gross_usd' => $grossUsd,
            'gross_inr' => $grossInr,
            'withdrawn_usd' => $withdrawnUsd,
            'withdrawn_inr' => $withdrawnInr,
            'available_usd' => $availableUsd,
            'available_inr' => $availableInr,
            'razorpayx_configured' => trim((string) config('services.razorpayx.key_id')) !== ''
                && trim((string) config('services.razorpayx.key_secret')) !== '',
        ];
    }

    /**
     * Initiate a withdrawal to Admin's GPay (UPI) or Bank Account.
     */
    public function createWithdrawal(
        int $adminId,
        AdminPayoutAccount $account,
        int $starsToWithdraw,
        ?string $notes = null
    ): Withdrawal {
        if ($starsToWithdraw <= 0) {
            throw new RuntimeException('Withdrawal amount must be greater than zero.');
        }

        return DB::transaction(function () use ($adminId, $account, $starsToWithdraw, $notes) {
            $summary = $this->getBalanceSummary();

            if ($starsToWithdraw > $summary['available_stars']) {
                throw new RuntimeException(
                    "Insufficient withdrawable balance. Available: {$summary['available_stars']} XTR Stars (₹{$summary['available_inr']} / \${$summary['available_usd']})."
                );
            }

            $usdAmount = round($starsToWithdraw * $this->usdPerStarRate(), 2);
            $inrAmount = round($usdAmount * $this->inrPerUsdRate(), 2);
            $refId = 'WDR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));

            $provider = 'direct_payout';
            $providerPayoutId = null;
            $status = 'pending';
            $processedAt = null;

            // If RazorpayX Payout API credentials are set in .env, execute live GPay UPI / IMPS Bank transfer
            if ($summary['razorpayx_configured']) {
                $provider = 'razorpayx';
                $payoutResult = $this->dispatchRazorpayXPayout($account, $inrAmount, $refId);
                if ($payoutResult['ok']) {
                    $providerPayoutId = $payoutResult['payout_id'];
                    $status = $payoutResult['status'];
                    if ($status === 'completed') {
                        $processedAt = now();
                    }
                }
            }

            $withdrawal = Withdrawal::create([
                'admin_user_id' => $adminId,
                'payout_account_id' => $account->id,
                'reference_id' => $refId,
                'payout_method' => $account->method_type,
                'stars_deducted' => $starsToWithdraw,
                'usd_amount' => $usdAmount,
                'inr_amount' => $inrAmount,
                'payout_currency' => 'INR',
                'payout_amount' => $inrAmount,
                'destination_summary' => $account->formattedDestination(),
                'provider' => $provider,
                'provider_payout_id' => $providerPayoutId,
                'status' => $status,
                'notes' => $notes,
                'processed_at' => $processedAt,
            ]);

            AuditLog::record(
                action: 'withdrawal.create',
                targetType: 'withdrawal',
                targetId: $withdrawal->id,
                reason: $notes ?: "Admin withdrawal to {$account->method_type}",
                metadata: [
                    'reference_id' => $refId,
                    'method' => $account->method_type,
                    'stars_deducted' => $starsToWithdraw,
                    'usd_amount' => $usdAmount,
                    'inr_amount' => $inrAmount,
                    'destination' => $account->formattedDestination(),
                ],
                actorAdminId: $adminId
            );

            return $withdrawal;
        });
    }

    public function updateWithdrawalStatus(
        Withdrawal $withdrawal,
        int $adminId,
        string $status, // completed, rejected
        ?string $utrReference = null,
        ?string $notes = null
    ): Withdrawal {
        return DB::transaction(function () use ($withdrawal, $adminId, $status, $utrReference, $notes) {
            $locked = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'status' => $status,
                'provider_payout_id' => $utrReference ?: $locked->provider_payout_id ?: ('UTR-' . strtoupper(Str::random(10))),
                'notes' => $notes ?: $locked->notes,
                'processed_at' => $status === 'completed' ? now() : $locked->processed_at,
            ]);

            AuditLog::record(
                action: "withdrawal.{$status}",
                targetType: 'withdrawal',
                targetId: $locked->id,
                reason: $notes ?: "Withdrawal marked as {$status}",
                metadata: [
                    'reference_id' => $locked->reference_id,
                    'utr' => $locked->provider_payout_id,
                    'inr_amount' => $locked->inr_amount,
                ],
                actorAdminId: $adminId
            );

            return $locked->fresh();
        });
    }

    /**
     * Dispatch instant GPay (UPI) or Bank Account (IMPS) payout via RazorpayX API when configured.
     *
     * @return array{ok: bool, payout_id?: string, status: string}
     */
    private function dispatchRazorpayXPayout(AdminPayoutAccount $account, float $inrAmount, string $referenceId): array
    {
        $keyId = (string) config('services.razorpayx.key_id');
        $keySecret = (string) config('services.razorpayx.key_secret');
        $sourceAccount = (string) config('services.razorpayx.account_number');

        if ($keyId === '' || $keySecret === '' || $sourceAccount === '') {
            return ['ok' => false, 'status' => 'pending'];
        }

        try {
            $amountPaise = (int) round($inrAmount * 100);
            $mode = $account->method_type === 'gpay_upi' ? 'UPI' : 'IMPS';

            $fundAccount = $account->method_type === 'gpay_upi'
                ? [
                    'account_type' => 'vpa',
                    'vpa' => ['address' => $account->gpay_upi_id],
                    'contact' => [
                        'name' => $account->account_holder_name,
                        'type' => 'self',
                        'contact' => $account->gpay_phone ?: '9999999999',
                    ],
                ]
                : [
                    'account_type' => 'bank_account',
                    'bank_account' => [
                        'name' => $account->account_holder_name,
                        'ifsc' => $account->ifsc_swift_code,
                        'account_number' => $account->account_number,
                    ],
                    'contact' => [
                        'name' => $account->account_holder_name,
                        'type' => 'self',
                    ],
                ];

            $response = Http::withBasicAuth($keyId, $keySecret)
                ->timeout(10)
                ->post('https://api.razorpay.com/v1/payouts', [
                    'account_number' => $sourceAccount,
                    'amount' => $amountPaise,
                    'currency' => 'INR',
                    'mode' => $mode,
                    'purpose' => 'payout',
                    'fund_account' => $fundAccount,
                    'queue_if_low_balance' => true,
                    'reference_id' => $referenceId,
                    'narration' => 'ChatMingle Admin Withdrawal',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'ok' => true,
                    'payout_id' => (string) ($data['id'] ?? $referenceId),
                    'status' => ($data['status'] ?? '') === 'processed' ? 'completed' : 'processing',
                ];
            }
        } catch (Throwable) {
            // Fall back to pending manual settlement if gateway call fails
        }

        return ['ok' => false, 'status' => 'pending'];
    }
}
