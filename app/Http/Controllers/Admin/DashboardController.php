<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Ban;
use App\Models\Conversation;
use App\Models\MatchQueue;
use App\Models\Payment;
use App\Models\Report;
use App\Models\TelegramUser;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $todayStart = now()->startOfDay();

        $metrics = [
            'total_users' => TelegramUser::count(),
            'dau' => TelegramUser::where('last_seen_at', '>=', now()->subDay())->count(),
            'active_conversations' => Conversation::where('status', 'active')->count(),
            'matches_today' => Conversation::where('started_at', '>=', $todayStart)->count(),
            'queue_depth' => MatchQueue::where('status', 'waiting')->count(),
            'avg_wait_seconds' => (int) MatchQueue::where('status', 'waiting')
                ->get()
                ->avg(fn (MatchQueue $q) => max(0, $q->queued_at ? (int) $q->queued_at->diffInSeconds(now()) : 0)),
            'open_reports' => Report::where('status', 'open')->count(),
            'active_bans' => Ban::where('is_active', true)->count(),
            'paid_payments_count' => Payment::where('status', 'paid')->count(),
            'stars_volume' => (int) Payment::where('status', 'paid')->sum('total_amount'),
            'token_configured' => trim((string) config('services.telegram.token')) !== '',
        ];

        $recentReports = Report::with(['reporter', 'reportedUser'])
            ->where('status', 'open')
            ->latest()
            ->limit(6)
            ->get();

        $recentPayments = Payment::with('user')
            ->latest()
            ->limit(6)
            ->get();

        $recentAudits = AuditLog::latest('created_at')
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact('metrics', 'recentReports', 'recentPayments', 'recentAudits'));
    }
}
