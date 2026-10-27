<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unlock {{ $payment->credits_granted }} Random Searches - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
        <div class="flex items-center justify-between mb-6">
            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/10 border border-amber-500/30 text-amber-300">
                ⭐ Telegram Random Chat Pack
            </span>
            <span class="text-xs font-mono text-slate-400">#{{ $payment->id }}</span>
        </div>

        <h1 class="text-2xl font-bold text-white mb-2">
            +{{ $payment->credits_granted }} Random User Searches
        </h1>
        <p class="text-sm text-slate-400 mb-6">
            You have completed your {{ config('services.telegram.free_matches_per_day', 10) }} free daily random user searches. Unlock {{ $payment->credits_granted }} additional searches immediately.
        </p>

        @if (session('status'))
            <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-2 text-sm mb-6">
            <div class="flex justify-between">
                <span class="text-slate-400">Telegram Account ID:</span>
                <span class="font-mono text-slate-200">{{ $payment->user?->telegram_id }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-400">Pack Credits:</span>
                <span class="font-semibold text-emerald-400">+{{ $payment->credits_granted }} Random Searches</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-400">Price:</span>
                <span class="font-bold text-amber-400">{{ $payment->total_amount }} {{ $payment->currency }} (~$2.00 USD)</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-400">Order Status:</span>
                <span class="uppercase font-bold {{ $payment->status === 'paid' ? 'text-emerald-400' : 'text-amber-300' }}">
                    {{ $payment->status }}
                </span>
            </div>
        </div>

        @if ($payment->status === 'pending')
            <form method="POST" action="{{ route('payment.complete', $payment->invoice_payload) }}">
                @csrf
                <button type="submit" class="w-full py-3 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm transition">
                    💳 Complete Payment & Unlock +{{ $payment->credits_granted }} Searches
                </button>
            </form>
            <p class="text-[11px] text-slate-500 text-center mt-3">
                You can also pay directly inside the Telegram app using the Stars invoice sent by the bot.
            </p>
        @else
            <div class="text-center p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm font-medium">
                ✅ Payment Completed! Return to Telegram and send <code>/search</code> or <code>/next</code>.
            </div>
        @endif
    </div>
</body>
</html>
