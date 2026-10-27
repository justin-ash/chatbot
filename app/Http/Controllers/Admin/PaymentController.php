<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Billing\StarsPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        private readonly StarsPaymentService $paymentService,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $query = Payment::with('user')->latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_payload', 'like', "%{$search}%")
                    ->orWhere('telegram_payment_charge_id', 'like', "%{$search}%");
                if (is_numeric($search)) {
                    $q->orWhere('user_id', (int) $search);
                }
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $payments = $query->paginate(20)->withQueryString();

        return view('admin.payments.index', compact('payments', 'search', 'status'));
    }

    public function refund(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $this->paymentService->refundPayment($payment, (int) Auth::id(), $validated['reason']);
            return back()->with('status', "Payment #{$payment->id} refunded via Telegram Stars and credits reversed.");
        } catch (Throwable $e) {
            return back()->withErrors(['refund' => 'Refund failed: ' . $e->getMessage()]);
        }
    }
}
