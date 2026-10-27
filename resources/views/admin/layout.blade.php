<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin Console') - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col">
    <header class="bg-slate-900 border-b border-slate-800">
        <div class="max-w-7xl mx-auto px-6 py-3.5 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-6">
                <a href="{{ route('admin.dashboard') }}" class="font-bold text-white flex items-center gap-2">
                    <span>🛡️</span>
                    <span>Bot Admin</span>
                </a>
                <nav class="flex items-center gap-4 text-sm">
                    <a href="{{ route('admin.dashboard') }}" class="px-2.5 py-1.5 rounded-md {{ request()->routeIs('admin.dashboard') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:text-white' }}">Dashboard</a>
                    <a href="{{ route('admin.users.index') }}" class="px-2.5 py-1.5 rounded-md {{ request()->routeIs('admin.users.*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:text-white' }}">Users</a>
                    <a href="{{ route('admin.reports.index') }}" class="px-2.5 py-1.5 rounded-md {{ request()->routeIs('admin.reports.*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:text-white' }}">Reports</a>
                    <a href="{{ route('admin.payments.index') }}" class="px-2.5 py-1.5 rounded-md {{ request()->routeIs('admin.payments.*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:text-white' }}">Payments (XTR)</a>
                    <a href="{{ route('admin.settings.index') }}" class="px-2.5 py-1.5 rounded-md {{ request()->routeIs('admin.settings.*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:text-white' }}">Settings</a>
                    <a href="{{ route('admin.audit.index') }}" class="px-2.5 py-1.5 rounded-md {{ request()->routeIs('admin.audit.*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:text-white' }}">Audit Trail</a>
                </nav>
            </div>
            <div class="flex items-center gap-4 text-xs">
                <span class="text-slate-400">{{ auth()->user()->name }} ({{ strtoupper(auth()->user()->role) }})</span>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-200">Sign Out</button>
                </form>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto px-6 py-8">
        @if (session('status'))
            <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
