<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Billing\StarsPaymentService;
use App\Services\Telegram\TelegramApi;
use App\Services\Usage\QuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PaymentCheckoutController extends Controller
{
    public function __construct(
        private readonly StarsPaymentService $paymentService,
        private readonly QuotaService $quotaService,
        private readonly TelegramApi $telegram,
    ) {}

    public function show(string $payload): View
    {
        $payment = Payment::with('user')->where('invoice_payload', $payload)->firstOrFail();

        return view('public.checkout', compact('payment'));
    }

    public function complete(Request $request, string $payload): RedirectResponse
    {
        $payment = Payment::with('user')->where('invoice_payload', $payload)->firstOrFail();

        if ($payment->status === 'paid') {
            return redirect()->route('payment.checkout', $payload)
                ->with('status', 'This payment has already been completed and credited!');
        }

        $user = $payment->user;
        $chargeId = 'web_charge_' . Str::random(32);

        $fulfilled = $this->paymentService->handleSuccessfulPayment($user, [
            'invoice_payload' => $payment->invoice_payload,
            'currency' => 'XTR',
            'total_amount' => (int) $payment->total_amount,
            'telegram_payment_charge_id' => $chargeId,
            'provider_payment_charge_id' => 'web_checkout',
        ], hash('sha256', $payload . '|' . $chargeId));

        $balance = $this->quotaService->currentBalance($user);

        try {
            $this->telegram->sendMessage(
                $user->telegram_id,
                "✅ Payment confirmed! +{$fulfilled->credits_granted} random user search credits have been added to your account.\n\n"
                . "📊 Current Balance:\n"
                . "• Free searches left today: {$balance['free_remaining']}/{$balance['free_limit']}\n"
                . "• Paid search credits: {$balance['paid_remaining']}\n\n"
                . "Use /search or tap below to find a random user now!",
                [
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [['text' => '🔍 Search Random User (/search)', 'callback_data' => 'chat:find']],
                        ],
                    ],
                ]
            );
        } catch (Throwable) {
            // Ignore Telegram notification error in local test mode
        }

        return redirect()->route('payment.checkout', $payload)
            ->with('status', "Success! +{$fulfilled->credits_granted} search credits have been added to your Telegram account.");
    }
}
