<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-200 min-h-screen py-12 px-6">
    <div class="max-w-3xl mx-auto bg-slate-900 border border-slate-800 rounded-2xl p-8 space-y-6">
        <a href="{{ route('home') }}" class="text-sm text-indigo-400 hover:underline">&larr; Back to Home</a>
        <h1 class="text-3xl font-bold text-white">Privacy & Data Retention Policy</h1>
        <p class="text-sm text-slate-400">Last Updated: {{ date('F j, Y') }}</p>

        <section class="space-y-2">
            <h2 class="text-lg font-semibold text-white">1. Data We Collect</h2>
            <p class="text-sm text-slate-300 leading-relaxed">
                We store your numeric Telegram user ID, selected language code, terms acceptance timestamp, daily match counters, paid credit balance, block list pairs, moderation reports, and Telegram Stars payment identifiers (<code>invoice_payload</code> and <code>telegram_payment_charge_id</code>). We do not request your phone number, legal name, or email address.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-semibold text-white">2. Message Content Privacy</h2>
            <p class="text-sm text-slate-300 leading-relaxed">
                By default, our database stores only message metadata (conversation ID, sender internal ID, message length, and timestamp) and does <strong>not</strong> store the text body of your conversations.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-semibold text-white">3. Data Retention Schedule</h2>
            <ul class="list-disc list-inside text-sm text-slate-300 space-y-1">
                <li><strong>Message Metadata:</strong> Automatically purged after {{ config('services.telegram.retention_days_metadata', 30) }} days via scheduled cleanup jobs.</li>
                <li><strong>Webhook Deduplication IDs:</strong> Purged after 7 days.</li>
                <li><strong>Payment & Credit Ledger Records:</strong> Retained as required for financial reconciliation, audit, and Telegram Stars refund handling.</li>
                <li><strong>Moderation & Audit Logs:</strong> Retained to enforce bans and protect user safety.</li>
            </ul>
        </section>
    </div>
</body>
</html>
