@extends('admin.layout')

@section('title', 'Runtime Settings')

@section('content')
    <div class="max-w-2xl">
        <h1 class="text-xl font-bold text-white mb-2">⚙️ Runtime Bot Configuration</h1>
        <p class="text-xs text-slate-400 mb-6">Adjust daily free limits, Telegram Stars pack pricing, rate limits, and maintenance mode. All changes require an audit reason.</p>

        <form method="POST" action="{{ route('admin.settings.update') }}" class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4 text-sm">
            @csrf
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Daily Free Matches (UTC)</label>
                    <input type="number" name="free_matches_per_day" value="{{ $settings['free_matches_per_day'] }}" required
                        class="w-full rounded bg-slate-950 border border-slate-700 px-3 py-2 text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Paid Pack Credits Granted</label>
                    <input type="number" name="pack_credits" value="{{ $settings['pack_credits'] }}" required
                        class="w-full rounded bg-slate-950 border border-slate-700 px-3 py-2 text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Paid Pack Price (XTR Telegram Stars)</label>
                    <input type="number" name="pack_stars" value="{{ $settings['pack_stars'] }}" required
                        class="w-full rounded bg-slate-950 border border-slate-700 px-3 py-2 text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Message Rate Limit (per minute)</label>
                    <input type="number" name="message_rate_limit_per_minute" value="{{ $settings['message_rate_limit_per_minute'] }}" required
                        class="w-full rounded bg-slate-950 border border-slate-700 px-3 py-2 text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Search Cooldown (seconds)</label>
                    <input type="number" name="search_cooldown_seconds" value="{{ $settings['search_cooldown_seconds'] }}" required
                        class="w-full rounded bg-slate-950 border border-slate-700 px-3 py-2 text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Queue Timeout (minutes)</label>
                    <input type="number" name="queue_timeout_minutes" value="{{ $settings['queue_timeout_minutes'] }}" required
                        class="w-full rounded bg-slate-950 border border-slate-700 px-3 py-2 text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Metadata Retention Window (days)</label>
                    <input type="number" name="retention_days_metadata" value="{{ $settings['retention_days_metadata'] }}" required
                        class="w-full rounded bg-slate-950 border border-slate-700 px-3 py-2 text-white">
                </div>
                <div class="flex items-center pt-5">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="maintenance_mode" value="1" @checked($settings['maintenance_mode'])
                            class="rounded bg-slate-950 border-slate-700">
                        <span class="text-xs font-semibold text-amber-300">Enable Maintenance Mode (Pause Matching)</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1">Audit Reason for Change</label>
                <input type="text" name="reason" required placeholder="e.g., Reviewed Stars USD equivalent pricing..."
                    class="w-full rounded bg-slate-950 border border-slate-700 px-3 py-2 text-white">
            </div>

            <button type="submit" class="px-5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold">
                Save Configuration
            </button>
        </form>
    </div>
@endsection
