<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col">
    <header class="border-b border-slate-800 bg-slate-900/60">
        <div class="max-w-5xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="text-2xl">💬</span>
                <span class="font-bold text-lg tracking-tight">{{ config('app.name') }}</span>
            </div>
            <nav class="flex items-center gap-6 text-sm text-slate-300">
                <a href="{{ route('terms') }}" class="hover:text-white">Terms</a>
                <a href="{{ route('privacy') }}" class="hover:text-white">Privacy Policy</a>
                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">Admin Console</a>
            </nav>
        </div>
    </header>

    <main class="flex-1 max-w-5xl mx-auto px-6 py-16">
        <div class="max-w-2xl">
            <span class="inline-block px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 text-xs font-semibold uppercase tracking-wider mb-4">
                Anonymous Language-Matched Telegram Bot
            </span>
            <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl mb-6">
                Connect Anonymously by Language on Telegram.
            </h1>
            <p class="text-lg text-slate-300 leading-relaxed mb-8">
                Chat one-on-one in real time with consenting users who speak your language. Your Telegram username, display name, and numeric ID are never revealed to your chat partner.
            </p>
            <div class="grid sm:grid-cols-3 gap-4 mb-10">
                <div class="p-4 rounded-xl bg-slate-900 border border-slate-800">
                    <div class="text-indigo-400 font-bold text-xl mb-1">{{ config('services.telegram.free_matches_per_day', 10) }} Free / Day</div>
                    <p class="text-xs text-slate-400">Daily free successful match allowance per UTC calendar day.</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-900 border border-slate-800">
                    <div class="text-amber-400 font-bold text-xl mb-1">+{{ config('services.telegram.pack_credits', 50) }} Credits Pack</div>
                    <p class="text-xs text-slate-400">Available via Telegram Stars ({{ config('services.telegram.pack_stars', 100) }} XTR Stars).</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-900 border border-slate-800">
                    <div class="text-emerald-400 font-bold text-xl mb-1">Safety First</div>
                    <p class="text-xs text-slate-400">Instant /stop, /next, /block, and guided /report moderation.</p>
                </div>
            </div>
        </div>
    </main>

    <footer class="border-t border-slate-800 py-6 text-center text-xs text-slate-500">
        &copy; {{ date('Y') }} {{ config('app.name') }} • Minimum Age {{ config('services.telegram.minimum_age', 18) }}+ • <a href="{{ route('terms') }}" class="underline">Terms</a> • <a href="{{ route('privacy') }}" class="underline">Privacy</a>
    </footer>
</body>
</html>
