<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-200 min-h-screen py-12 px-6">
    <div class="max-w-3xl mx-auto bg-slate-900 border border-slate-800 rounded-2xl p-8 space-y-6">
        <a href="{{ route('home') }}" class="text-sm text-indigo-400 hover:underline">&larr; Back to Home</a>
        <h1 class="text-3xl font-bold text-white">Terms of Service & Community Rules</h1>
        <p class="text-sm text-slate-400">Last Updated: {{ date('F j, Y') }}</p>

        <section class="space-y-2">
            <h2 class="text-lg font-semibold text-white">1. Minimum Age & Eligibility</h2>
            <p class="text-sm text-slate-300 leading-relaxed">
                You must be at least {{ config('services.telegram.minimum_age', 18) }} years of age to use this Telegram bot. By tapping Accept on <code>/start</code>, you confirm you meet this age requirement.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-semibold text-white">2. Anonymous Relay & Operator Metadata</h2>
            <p class="text-sm text-slate-300 leading-relaxed">
                Messages are relayed through the bot so that chat partners cannot see each other's Telegram username, display name, profile photo, or numeric Telegram ID. However, anonymity applies between chat partners only: the service operator necessarily processes Telegram numeric user IDs, timestamps, quota balances, and moderation records to operate the service safely.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-semibold text-white">3. Community Safety Rules</h2>
            <p class="text-sm text-slate-300 leading-relaxed">
                Users are strictly prohibited from sending spam, commercial advertisements, harassment, hate speech, threats, non-consensual sexual content, scams, or any unlawful content. Violations may result in immediate warning, temporary mute, suspension, or permanent ban without refund of consumed credits.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-semibold text-white">4. Quotas, Telegram Stars Payments & Refunds</h2>
            <p class="text-sm text-slate-300 leading-relaxed">
                Each user receives {{ config('services.telegram.free_matches_per_day', 10) }} free successful conversation matches per UTC calendar day. A match credit is consumed only when two users are paired into an active conversation. Paid credit packs ({{ config('services.telegram.pack_credits', 50) }} credits for {{ config('services.telegram.pack_stars', 100) }} Telegram Stars / <code>XTR</code>) are credited only after a verified <code>successful_payment</code> event. Refund requests for unused packs may be reviewed by support ({{ config('services.telegram.support_contact') }}), and refunded orders have their granted credits reversed.
            </p>
        </section>
    </div>
</body>
</html>
