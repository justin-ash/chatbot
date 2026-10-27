@extends('admin.layout')

@section('title', 'Runtime Settings & Webhook')

@section('content')
    <div class="grid lg:grid-cols-2 gap-8">
        <div>
            <h1 class="text-xl font-bold text-white mb-2">⚙️ Runtime Bot Configuration</h1>
            <p class="text-xs text-slate-400 mb-6">Adjust daily free limits, Telegram Stars pack pricing, rate limits, and maintenance mode.</p>

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
                    <input type="text" name="reason" required placeholder="e.g., Updated Stars pricing..."
                        class="w-full rounded bg-slate-950 border border-slate-700 px-3 py-2 text-white">
                </div>

                <button type="submit" class="px-5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold">
                    Save Configuration
                </button>
            </form>
        </div>

        <div>
            <h2 class="text-xl font-bold text-white mb-2">🔗 Telegram Bot & Webhook Connection</h2>
            <p class="text-xs text-slate-400 mb-6">Connect your Laravel `/api/telegram/webhook` endpoint with Telegram Bot API using `secret_token` verification.</p>

            <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4 text-xs">
                <div class="flex justify-between items-center py-1 border-b border-slate-800">
                    <span class="text-slate-400">Bot Token (<code>.env</code>):</span>
                    <span class="font-mono {{ $webhookConfig['token_configured'] ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ $webhookConfig['token_masked'] }}
                    </span>
                </div>

                @if ($webhookConfig['bot_info'])
                    <div class="flex justify-between items-center py-1 border-b border-slate-800">
                        <span class="text-slate-400">Connected Bot Account:</span>
                        <span class="font-semibold text-indigo-300">
                            {{ $webhookConfig['bot_info']['first_name'] ?? 'Bot' }} (@{{ $webhookConfig['bot_info']['username'] ?? 'unknown' }})
                        </span>
                    </div>
                @endif

                @if ($webhookConfig['webhook_info'])
                    <div class="p-3 rounded-lg bg-slate-950 border border-slate-800 space-y-1">
                        <div class="text-slate-400 font-semibold mb-1">Live Telegram Webhook Status:</div>
                        <div>Registered URL: <code class="text-emerald-300">{{ $webhookConfig['webhook_info']['url'] ?: '(None — Polling Mode Active)' }}</code></div>
                        <div>Pending Updates: <strong>{{ $webhookConfig['webhook_info']['pending_update_count'] ?? 0 }}</strong></div>
                        @if (!empty($webhookConfig['webhook_info']['last_error_message']))
                            <div class="text-rose-400">Last Error: {{ $webhookConfig['webhook_info']['last_error_message'] }}</div>
                        @endif
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.settings.webhook.register') }}" class="space-y-3 pt-2">
                    @csrf
                    <div>
                        <label class="block text-slate-400 mb-1">HTTPS Webhook Endpoint URL (<code>/api/telegram/webhook</code>)</label>
                        <input type="url" name="webhook_url" value="{{ $webhookConfig['webhook_url'] }}" required
                            placeholder="https://yourdomain.com/api/telegram/webhook"
                            class="w-full rounded bg-slate-950 border border-slate-700 px-3 py-2 text-white font-mono text-xs">
                    </div>
                    <button type="submit" class="w-full py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold">
                        Register HTTPS Webhook & Bot Commands Now
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.settings.webhook.delete') }}">
                    @csrf
                    <button type="submit" class="w-full py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300">
                        Delete Webhook (Switch to Local Polling Mode)
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
