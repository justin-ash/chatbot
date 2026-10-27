<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TelegramUser;
use App\Services\Moderation\ReportService;
use App\Services\Usage\QuotaService;
use Illuminate\Http\JsonResponse;
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
        $language = trim((string) $request->query('language', ''));
        $perPage = in_array((int) $request->query('per_page', 15), [10, 15, 20, 50, 100], true)
            ? (int) $request->query('per_page', 15)
            : 15;

        $today = $this->quotaService->todayDateString();
        $freeLimit = $this->quotaService->dailyFreeLimit();

        $query = TelegramUser::query()
            ->with([
                'queueEntry',
                'dailyUsages' => fn ($q) => $q->where('usage_date', $today),
            ])
            ->withCount([
                'participantRecords as total_chats_count',
                'payments as paid_orders_count' => fn ($q) => $q->where('status', 'paid'),
                'feedbackReceived as thumbs_up_count' => fn ($q) => $q->where('feedback_type', 'thumbs_up'),
                'feedbackReceived as thumbs_down_count' => fn ($q) => $q->where('feedback_type', 'thumbs_down'),
                'reportsReceived as open_reports_count' => fn ($q) => $q->where('status', 'open'),
                'reportsReceived as total_reports_received_count',
                'reportsMade as total_reports_made_count',
                'blockedByUsers as blocked_by_count',
                'blockedUsers as blocked_users_count',
                'bans as active_bans_count' => fn ($q) => $q->where('is_active', true),
            ])
            ->withSum(['payments as total_stars_spent' => fn ($q) => $q->where('status', 'paid')], 'total_amount')
            ->latest('id');

        if ($search !== '') {
            $cleanSearch = ltrim($search, '@#');
            $query->where(function ($q) use ($search, $cleanSearch) {
                if (is_numeric($cleanSearch)) {
                    $q->where('id', (int) $cleanSearch)
                        ->orWhere('telegram_id', (int) $cleanSearch);
                }
                $q->orWhere('username', 'like', "%{$cleanSearch}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('language_code', 'like', "%{$search}%");
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($language !== '') {
            $query->where('language_code', $language);
        }

        $users = $query->paginate($perPage)->withQueryString();
        $supportedLanguages = config('services.telegram.supported_languages', []);

        return view('admin.users.index', compact(
            'users',
            'search',
            'status',
            'language',
            'perPage',
            'freeLimit',
            'supportedLanguages'
        ));
    }

    /**
     * JSON endpoint for the interactive User Details View Popup Modal on /admin/users
     */
    public function details(Request $request, TelegramUser $user): JsonResponse
    {
        $balance = $this->quotaService->currentBalance($user);
        $reconciliation = $this->quotaService->reconcileUserLedger($user);
        $activeConv = $user->activeConversation();

        $user->loadCount([
            'participantRecords as total_chats_count',
            'payments as paid_orders_count' => fn ($q) => $q->where('status', 'paid'),
            'feedbackReceived as thumbs_up_count' => fn ($q) => $q->where('feedback_type', 'thumbs_up'),
            'feedbackReceived as thumbs_down_count' => fn ($q) => $q->where('feedback_type', 'thumbs_down'),
            'reportsReceived as open_reports_count' => fn ($q) => $q->where('status', 'open'),
            'reportsReceived as total_reports_received_count',
            'reportsMade as total_reports_made_count',
            'blockedByUsers as blocked_by_count',
            'blockedUsers as blocked_users_count',
            'bans as active_bans_count' => fn ($q) => $q->where('is_active', true),
        ]);
        $user->loadSum(['payments as total_stars_spent' => fn ($q) => $q->where('status', 'paid')], 'total_amount');
        $user->load('queueEntry');

        $chats = $user->participantRecords()
            ->with(['conversation.participants.user', 'conversation.messages'])
            ->latest('joined_at')
            ->limit(25)
            ->get()
            ->map(function ($part) use ($user) {
                $conv = $part->conversation;
                $partner = $conv?->participants->firstWhere('user_id', '!=', $user->id)?->user;
                return [
                    'conversation_id' => $conv?->id,
                    'status' => $conv?->status ?? 'unknown',
                    'language_code' => strtoupper($conv?->language_code ?? '-'),
                    'partner_id' => $partner?->id,
                    'partner_telegram_id' => $partner?->telegram_id,
                    'partner_name' => $partner?->displayName() ?? 'Unknown',
                    'messages_count' => $conv?->messages?->count() ?? 0,
                    'started_at' => $conv?->started_at?->format('Y-m-d H:i:s'),
                    'ended_at' => $conv?->ended_at?->format('Y-m-d H:i:s') ?? 'Active',
                    'end_reason' => $conv?->end_reason ?? '-',
                ];
            });

        $ledger = $user->creditLedgers()
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'direction' => $l->direction,
                'amount' => $l->amount,
                'balance_after' => $l->balance_after,
                'reason' => $l->reason,
                'created_at' => $l->created_at?->format('Y-m-d H:i:s'),
            ]);

        $payments = $user->payments()
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'invoice_payload' => $p->invoice_payload,
                'total_amount' => $p->total_amount,
                'currency' => $p->currency,
                'credits_granted' => $p->credits_granted,
                'status' => $p->status,
                'charge_id' => $p->telegram_payment_charge_id ?? '-',
                'credited_at' => $p->credited_at?->format('Y-m-d H:i:s') ?? '-',
            ]);

        $feedback = $user->feedbackReceived()
            ->with('reporter')
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'match_id' => $f->match_id,
                'feedback_type' => $f->feedback_type,
                'description' => $f->description ?? '-',
                'status' => $f->status,
                'reporter_id' => $f->reporter_user_id,
                'created_at' => $f->created_at?->format('Y-m-d H:i:s'),
            ]);

        $bans = $user->bans()
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'action' => strtoupper($b->action),
                'reason' => $b->reason,
                'is_active' => $b->is_active,
                'starts_at' => $b->starts_at?->format('Y-m-d H:i'),
                'ends_at' => $b->ends_at?->format('Y-m-d H:i') ?? 'Permanent / N/A',
            ]);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'telegram_id' => $user->telegram_id,
                'username' => $user->username ? '@' . $user->username : 'Not set',
                'first_name' => $user->first_name ?? '-',
                'last_name' => $user->last_name ?? '-',
                'display_name' => $user->displayName(),
                'is_premium' => (bool) $user->is_premium,
                'language_code' => strtoupper($user->language_code),
                'status' => strtoupper($user->status),
                'muted_until' => $user->muted_until?->format('Y-m-d H:i:s') ?? '-',
                'accepted_terms_at' => $user->accepted_terms_at?->format('Y-m-d H:i:s') ?? 'Not accepted',
                'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
                'last_seen_at' => $user->last_seen_at ? $user->last_seen_at->format('Y-m-d H:i:s') . ' (' . $user->last_seen_at->diffForHumans() . ')' : 'Never',
                'queue_state' => $user->queueEntry ? 'Waiting in Queue (' . strtoupper($user->queueEntry->language_code) . ')' : ($activeConv ? "In Active Chat #{$activeConv->id}" : 'Idle'),
                'show_url' => route('admin.users.show', $user),
                'moderate_url' => route('admin.users.moderate', $user),
                'credits_url' => route('admin.users.credits', $user),
            ],
            'stats' => [
                'free_used' => $balance['free_used'],
                'free_limit' => $balance['free_limit'],
                'free_remaining' => $balance['free_remaining'],
                'paid_remaining' => $balance['paid_remaining'],
                'ledger_consistent' => $reconciliation['is_consistent'],
                'total_chats' => (int) $user->total_chats_count,
                'paid_orders_count' => (int) $user->paid_orders_count,
                'total_stars_spent' => (int) ($user->total_stars_spent ?? 0),
                'thumbs_up' => (int) $user->thumbs_up_count,
                'thumbs_down' => (int) $user->thumbs_down_count,
                'open_reports' => (int) $user->open_reports_count,
                'reports_received' => (int) $user->total_reports_received_count,
                'reports_made' => (int) $user->total_reports_made_count,
                'blocked_by' => (int) $user->blocked_by_count,
                'blocked_users' => (int) $user->blocked_users_count,
                'active_bans' => (int) $user->active_bans_count,
            ],
            'chats' => $chats,
            'ledger' => $ledger,
            'payments' => $payments,
            'feedback' => $feedback,
            'bans' => $bans,
        ]);
    }

    public function show(TelegramUser $user): View
    {
        $balance = $this->quotaService->currentBalance($user);
        $reconciliation = $this->quotaService->reconcileUserLedger($user);

        $user->loadCount([
            'participantRecords as total_chats_count',
            'payments as paid_orders_count' => fn ($q) => $q->where('status', 'paid'),
            'feedbackReceived as thumbs_up_count' => fn ($q) => $q->where('feedback_type', 'thumbs_up'),
            'feedbackReceived as thumbs_down_count' => fn ($q) => $q->where('feedback_type', 'thumbs_down'),
            'reportsReceived as open_reports_count' => fn ($q) => $q->where('status', 'open'),
            'reportsReceived as total_reports_received_count',
            'reportsMade as total_reports_made_count',
            'blockedByUsers as blocked_by_count',
            'blockedUsers as blocked_users_count',
        ]);
        $user->loadSum(['payments as total_stars_spent' => fn ($q) => $q->where('status', 'paid')], 'total_amount');

        // Multi-table pagination on User Detail page
        $chats = $user->participantRecords()
            ->with(['conversation.participants.user', 'conversation.messages'])
            ->latest('joined_at')
            ->paginate(8, ['*'], 'chats_page')
            ->withQueryString();

        $ledgers = $user->creditLedgers()
            ->latest()
            ->paginate(8, ['*'], 'ledger_page')
            ->withQueryString();

        $payments = $user->payments()
            ->latest()
            ->paginate(8, ['*'], 'payments_page')
            ->withQueryString();

        $feedbackReceived = $user->feedbackReceived()
            ->with('reporter')
            ->latest()
            ->paginate(8, ['*'], 'feedback_page')
            ->withQueryString();

        $bans = $user->bans()
            ->latest()
            ->paginate(8, ['*'], 'bans_page')
            ->withQueryString();

        return view('admin.users.show', compact(
            'user',
            'balance',
            'reconciliation',
            'chats',
            'ledgers',
            'payments',
            'feedbackReceived',
            'bans'
        ));
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
