<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TelegramUser;
use App\Services\Moderation\ReportService;
use App\Services\Usage\QuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private readonly QuotaService $quotaService,
        private readonly ReportService $reportService,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $query = TelegramUser::query()->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                if (is_numeric($search)) {
                    $q->where('id', (int) $search)
                        ->orWhere('telegram_id', (int) $search);
                } else {
                    $q->where('language_code', 'like', "%{$search}%");
                }
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $users = $query->paginate(20)->withQueryString();

        return view('admin.users.index', compact('users', 'search', 'status'));
    }

    public function show(TelegramUser $user): View
    {
        $user->load([
            'bans' => fn ($q) => $q->latest(),
            'creditLedgers' => fn ($q) => $q->latest()->limit(30),
            'reportsReceived.reporter',
            'payments' => fn ($q) => $q->latest()->limit(20),
        ]);

        $balance = $this->quotaService->currentBalance($user);
        $reconciliation = $this->quotaService->reconcileUserLedger($user);

        return view('admin.users.show', compact('user', 'balance', 'reconciliation'));
    }

    public function moderate(Request $request, TelegramUser $user): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:warn,mute,suspend,ban,unban'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
        ]);

        $this->reportService->applyModerationAction(
            targetUser: $user,
            action: $validated['action'],
            reason: $validated['reason'],
            durationMinutes: isset($validated['duration_minutes']) ? (int) $validated['duration_minutes'] : null,
            actorAdminId: (int) Auth::id()
        );

        return back()->with('status', "Moderation action [{$validated['action']}] applied to user #{$user->id}.");
    }

    public function adjustCredits(Request $request, TelegramUser $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'direction' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'integer', 'min:1', 'max:10000'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $adminId = (int) Auth::id();
        $amount = (int) $validated['amount'];

        if ($validated['direction'] === 'credit') {
            $newBalance = $this->quotaService->grantPaidCredits(
                user: $user,
                credits: $amount,
                reason: 'admin_adjustment',
                adminId: $adminId,
                metadata: ['reason' => $validated['reason']]
            );
        } else {
            $newBalance = $this->quotaService->deductPaidCredits(
                user: $user,
                creditsToDeduct: $amount,
                reason: 'admin_adjustment',
                adminId: $adminId,
                metadata: ['reason' => $validated['reason']]
            );
        }

        AuditLog::record(
            action: "user.credits_{$validated['direction']}",
            targetType: 'telegram_user',
            targetId: $user->id,
            reason: $validated['reason'],
            metadata: [
                'amount' => $amount,
                'balance_after' => $newBalance,
            ],
            actorAdminId: $adminId
        );

        return back()->with('status', "Adjusted paid credits ({$validated['direction']} {$amount}). New balance: {$newBalance}.");
    }
}
