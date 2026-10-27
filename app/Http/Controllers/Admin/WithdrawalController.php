<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminPayoutAccount;
use App\Models\AuditLog;
use App\Models\Withdrawal;
use App\Services\Billing\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class WithdrawalController extends Controller
{
    public function __construct(
        private readonly WithdrawalService $withdrawalService,
    ) {}

    public function index(): View
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $balance = $this->withdrawalService->getBalanceSummary();

        $accounts = AdminPayoutAccount::where('admin_user_id', Auth::id())
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        $withdrawals = Withdrawal::with('payoutAccount')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.withdrawals.index', compact('balance', 'accounts', 'withdrawals'));
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'method_type' => ['required', 'in:gpay_upi,bank_account'],
            'account_label' => ['required', 'string', 'max:100'],
            'account_holder_name' => ['required', 'string', 'max:120'],
            'gpay_upi_id' => ['nullable', 'required_if:method_type,gpay_upi', 'string', 'max:120'],
            'gpay_phone' => ['nullable', 'string', 'max:30'],
            'bank_name' => ['nullable', 'required_if:method_type,bank_account', 'string', 'max:120'],
            'account_number' => ['nullable', 'required_if:method_type,bank_account', 'string', 'max:64'],
            'ifsc_swift_code' => ['nullable', 'required_if:method_type,bank_account', 'string', 'max:40'],
        ]);

        $adminId = (int) Auth::id();

        $account = AdminPayoutAccount::create([
            'admin_user_id' => $adminId,
            'method_type' => $validated['method_type'],
            'account_label' => $validated['account_label'],
            'account_holder_name' => $validated['account_holder_name'],
            'gpay_upi_id' => $validated['gpay_upi_id'] ?? null,
            'gpay_phone' => $validated['gpay_phone'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'account_number' => $validated['account_number'] ?? null,
            'ifsc_swift_code' => isset($validated['ifsc_swift_code']) ? strtoupper($validated['ifsc_swift_code']) : null,
            'is_default' => true,
        ]);

        AuditLog::record(
            action: 'payout_account.create',
            targetType: 'admin_payout_account',
            targetId: $account->id,
            reason: "Added admin payout destination ({$account->method_type})",
            metadata: [
                'method_type' => $account->method_type,
                'destination' => $account->formattedDestination(),
            ],
            actorAdminId: $adminId
        );

        return back()->with('status', "✅ Saved payout destination: {$account->formattedDestination()}");
    }

    public function deleteAccount(Request $request, AdminPayoutAccount $account): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin() && $account->admin_user_id === (int) Auth::id(), 403);

        $summary = $account->formattedDestination();
        $account->delete();

        AuditLog::record(
            action: 'payout_account.delete',
            targetType: 'admin_payout_account',
            targetId: $account->id,
            reason: 'Deleted admin payout destination',
            metadata: ['destination' => $summary],
            actorAdminId: (int) Auth::id()
        );

        return back()->with('status', 'Payout account removed.');
    }

    public function storeWithdrawal(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'payout_account_id' => ['required', 'integer', 'exists:admin_payout_accounts,id'],
            'stars_to_withdraw' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $account = AdminPayoutAccount::whereKey($validated['payout_account_id'])
            ->where('admin_user_id', Auth::id())
            ->firstOrFail();

        try {
            $withdrawal = $this->withdrawalService->createWithdrawal(
                adminId: (int) Auth::id(),
                account: $account,
                starsToWithdraw: (int) $validated['stars_to_withdraw'],
                notes: $validated['notes'] ?? null
            );

            return back()->with(
                'status',
                "💸 Withdrawal {$withdrawal->reference_id} initiated for ₹" . number_format($withdrawal->inr_amount, 2)
                . " (\${$withdrawal->usd_amount}) to {$withdrawal->destination_summary}."
            );
        } catch (Throwable $e) {
            return back()->withErrors(['withdrawal' => $e->getMessage()]);
        }
    }

    public function updateStatus(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:completed,rejected'],
            'provider_payout_id' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $updated = $this->withdrawalService->updateWithdrawalStatus(
            withdrawal: $withdrawal,
            adminId: (int) Auth::id(),
            status: $validated['status'],
            utrReference: $validated['provider_payout_id'] ?? null,
            notes: $validated['notes'] ?? null
        );

        return back()->with(
            'status',
            "Withdrawal {$updated->reference_id} marked as " . strtoupper($updated->status)
            . ($updated->provider_payout_id ? " (UTR/Ref: {$updated->provider_payout_id})" : '')
        );
    }
}
