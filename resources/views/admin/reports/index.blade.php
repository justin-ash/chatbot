@extends('admin.layout')

@section('title', 'Moderation & Feedback Queue')

@section('content')
    @if (isset($feedbackStats))
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6 text-xs">
            <div class="p-3 rounded-xl bg-slate-900 border border-slate-800">
                <div class="text-slate-400">👍 Thumbs Up</div>
                <div class="text-lg font-bold text-emerald-400 mt-0.5">{{ $feedbackStats['thumbs_up'] }}</div>
            </div>
            <div class="p-3 rounded-xl bg-slate-900 border border-slate-800">
                <div class="text-slate-400">👎 Thumbs Down</div>
                <div class="text-lg font-bold text-amber-400 mt-0.5">{{ $feedbackStats['thumbs_down'] }}</div>
            </div>
            <div class="p-3 rounded-xl bg-slate-900 border border-slate-800">
                <div class="text-slate-400">🚫 Spam</div>
                <div class="text-lg font-bold text-rose-400 mt-0.5">{{ $feedbackStats['spam'] }}</div>
            </div>
            <div class="p-3 rounded-xl bg-slate-900 border border-slate-800">
                <div class="text-slate-400">🤬 Vulgar Partner</div>
                <div class="text-lg font-bold text-rose-400 mt-0.5">{{ $feedbackStats['vulgar'] }}</div>
            </div>
            <div class="p-3 rounded-xl bg-slate-900 border border-slate-800">
                <div class="text-slate-400">🛡️ Underage</div>
                <div class="text-lg font-bold text-rose-400 mt-0.5">{{ $feedbackStats['underage'] }}</div>
            </div>
            <div class="p-3 rounded-xl bg-slate-900 border border-slate-800">
                <div class="text-slate-400">🚩 Other Report</div>
                <div class="text-lg font-bold text-rose-400 mt-0.5">{{ $feedbackStats['other_report'] }}</div>
            </div>
        </div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-white">🚩 ChatMingle Safety Reports Queue</h1>
        <div class="flex gap-2 text-xs">
            @foreach (['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed', 'all' => 'All'] as $key => $label)
                <a href="{{ route('admin.reports.index', ['status' => $key]) }}"
                    class="px-3 py-1.5 rounded-lg {{ $status === $key ? 'bg-indigo-600 text-white' : 'bg-slate-900 text-slate-300 border border-slate-800' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="space-y-4">
        @forelse ($reports as $report)
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 text-xs">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-3">
                    <div>
                        <span class="font-bold text-rose-400 text-sm">#{{ $report->id }} — {{ strtoupper($report->reason_code) }}</span>
                        <span class="ml-3 text-slate-400">
                            Reporter: <a href="{{ route('admin.users.show', $report->reporter_user_id) }}" class="text-indigo-400 underline">#{{ $report->reporter_user_id }}</a>
                            &rarr; Reported: <a href="{{ route('admin.users.show', $report->reported_user_id) }}" class="text-indigo-400 underline">#{{ $report->reported_user_id }} (TG: {{ $report->reportedUser?->telegram_id }})</a>
                        </span>
                    </div>
                    <span class="px-2.5 py-0.5 rounded uppercase font-semibold {{ $report->status === 'open' ? 'bg-amber-500/20 text-amber-300' : 'bg-slate-800 text-slate-400' }}">
                        {{ $report->status }}
                    </span>
                </div>

                @if ($report->details)
                    <div class="mb-3 p-2.5 rounded bg-slate-950 border border-slate-800 text-slate-200">
                        <span class="text-slate-400 font-semibold">Optional Description:</span> {{ $report->details }}
                    </div>
                @endif

                @if ($report->conversation)
                    <div class="mb-3 text-slate-400">
                        Match #{{ $report->conversation->id }} • Messages Exchanged (metadata count): {{ $report->conversation->messages->count() }} • Started: {{ $report->conversation->started_at?->format('Y-m-d H:i:s') }}
                    </div>
                @endif

                @if ($report->status === 'open')
                    <form method="POST" action="{{ route('admin.reports.resolve', $report) }}" class="flex flex-wrap items-center gap-2 pt-3 border-t border-slate-800">
                        @csrf
                        <select name="action" class="rounded bg-slate-950 border border-slate-700 px-2.5 py-1.5 text-white">
                            <option value="dismiss">Dismiss Report</option>
                            <option value="warn">Warn Reported User</option>
                            <option value="mute">Mute Reported User</option>
                            <option value="suspend">Suspend Reported User</option>
                            <option value="ban">Ban Reported User</option>
                        </select>
                        <input type="number" name="duration_minutes" placeholder="Mute/Suspend mins"
                            class="w-36 rounded bg-slate-950 border border-slate-700 px-2.5 py-1.5 text-white">
                        <input type="text" name="reason" required placeholder="Mandatory audit & resolution reason..."
                            class="flex-1 min-w-[220px] rounded bg-slate-950 border border-slate-700 px-2.5 py-1.5 text-white">
                        <button type="submit" class="px-4 py-1.5 rounded bg-indigo-600 hover:bg-indigo-500 text-white font-semibold">Resolve</button>
                    </form>
                @else
                    <div class="text-slate-400 pt-2 border-t border-slate-800">
                        Resolution: {{ $report->resolution }}
                    </div>
                @endif
            </div>
        @empty
            <div class="p-8 text-center text-slate-500 bg-slate-900 border border-slate-800 rounded-xl">
                No reports matching filter.
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $reports->links() }}
    </div>
@endsection
