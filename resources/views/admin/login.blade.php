<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8">
        <h1 class="text-2xl font-bold text-white mb-2">🛡️ Bot Admin Sign In</h1>
        <p class="text-xs text-slate-400 mb-6">Role-gated moderation, Stars payment review, and configuration console.</p>

        @if ($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email', 'admin@telegrambot.local') }}" required autofocus
                    class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Password</label>
                <input type="password" name="password" required
                    class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
            <button type="submit" class="w-full py-2.5 px-4 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm">
                Sign In
            </button>
        </form>
    </div>
</body>
</html>
