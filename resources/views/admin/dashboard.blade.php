@extends('admin.layout')

@section('title', 'Dashboard')

@section('content')
    @if (!$metrics['token_configured'])
        <div class="mb-6 p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-sm">
            <strong>Action Required:</strong> <code>TELEGRAM_BOT_TOKEN</code> is currently empty in <code>.env</code>. Add your bot token and run <code>php artisan telegram:webhook:setup</code> (or <code>php artisan telegram:poll</code> for local testing).
        </div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="p-5 rounded-xl bg-slate-900 border border-slate-800">
            <div class="text-xs text-slate-400">Total Users (24h Active)</div>
            <div class="text-2xl font-bold text-white mt-1">{{ number_format($metrics['total_users']) }} <span class="text-sm font-normal text-indigo-400">({{ $metrics['dau'] }} DAU)</span></div>
        </div>
        <div class="p-5 rounded-xl bg-slate-900 border border-slate-800">
            <div class="text-xs text-slate-400">Active Chats (Matches Today)</div>
            <div class="text-2xl font-bold text-emerald-400 mt-1">{{ $metrics['active_conversations'] }} <span class="text-sm font-normal text-slate-400">({{ $metrics['matches_today'] }} today)</span></div>
        </div>
        <div class="p-5 rounded-xl bg-slate-900 border border-slate-800">
            <div class="text-xs text-slate-400">Queue Depth (Avg Wait)</div>
            <div class="text-2xl font-bold text-amber-400 mt-1">{{ $metrics['queue_depth'] }} <span class="text-sm font-normal text-slate-400">({{ $metrics['avg_wait_seconds'] }}s)</span></div>
        </div>
        <div class="p-5 rounded-xl bg-slate-900 border border-slate-800">
            <div class="text-xs text-slate-400">Stars Revenue (Paid Packs)</div>
            <div class="text-2xl font-bold text-yellow-400 mt-1">⭐ {{ number_format($metrics['stars_volume']) }} XTR <span class="text-sm font-normal text-slate-400">({{ $metrics['paid_payments_count'] }})</span></div>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-white">🚩 Open Moderation Reports ({{ $metrics['open_reports'] }})</h2>
                <a href="{{ route('admin.reports.index') }}" class="text-xs text-indigo-400 hover:underline">View All &rarr;</a>
            </div>
            @if ($recentReports->isEmpty())
                <p class="text-xs text-slate-500">No open reports pending review.</p>
            @else
                <div class="space-y-2.5">
                    @foreach ($recentReports as $report)
                        <div class="flex items-center justify-between text-xs p-2.5 rounded-lg bg-slate-950 border border-slate-800">
                            <div>
                                <span class="font-semibold text-rose-400">#{{ $report->id }} {{ strtoupper($report->reason_code) }}</span>
                                <span class="text-slate-400 ml-2">Reported User #{{ $report->reported_user_id }}</span>
                            </div>
                            <span class="text-slate-500">{{ $report->created_at?->diffForHumans() }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-white">🕒 Recent Admin Audit Events</h2>
                <a href="{{ route('admin.audit.index') }}" class="text-xs text-indigo-400 hover:underline">Full Audit Log &rarr;</a>
            </div>
            @if ($recentAudits->isEmpty())
                <p class="text-xs text-slate-500">No audit events recorded yet.</p>
            @else
                <div class="space-y-2.5">
                    @foreach ($recentAudits as $audit)
                        <div class="flex items-center justify-between text-xs p-2.5 rounded-lg bg-slate-950 border border-slate-800">
                            <div>
                                <span class="font-mono text-indigo-300">{{ $audit->action }}</span>
                                <span class="text-slate-400 ml-2">{{ Str::limit($audit->reason, 45) }}</span>
                            </div>
                            <span class="text-slate-500">{{ $audit->created_at?->diffForHumans() }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
