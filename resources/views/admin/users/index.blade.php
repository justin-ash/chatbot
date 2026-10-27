@extends('admin.layout')

@section('title', 'Users')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-xl font-bold text-white">👥 Telegram Users</h1>
        <form method="GET" action="{{ route('admin.users.index') }}" class="flex items-center gap-2">
            <input type="text" name="q" value="{{ $search }}" placeholder="Internal ID, Telegram ID, or Lang..."
                class="rounded-lg bg-slate-900 border border-slate-700 px-3 py-1.5 text-sm text-white">
            <select name="status" class="rounded-lg bg-slate-900 border border-slate-700 px-3 py-1.5 text-sm text-white">
                <option value="">All Statuses</option>
                @foreach (['active', 'muted', 'suspended', 'banned'] as $st)
                    <option value="{{ $st }}" @selected($status === $st)>{{ ucfirst($st) }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm">Filter</button>
        </form>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-950 text-slate-400 uppercase">
                <tr>
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">Telegram ID</th>
                    <th class="px-4 py-3">Lang</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Paid Credits</th>
                    <th class="px-4 py-3">Terms Accepted</th>
                    <th class="px-4 py-3">Last Seen</th>
                    <th class="px-4 py-3 text-right">Manage</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse ($users as $u)
                    <tr>
                        <td class="px-4 py-3 font-mono">#{{ $u->id }}</td>
                        <td class="px-4 py-3 font-mono text-indigo-300">{{ $u->telegram_id }}</td>
                        <td class="px-4 py-3 uppercase">{{ $u->language_code }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold {{ $u->status === 'active' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                                {{ strtoupper($u->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-semibold text-amber-300">{{ $u->paid_credits_balance }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ $u->accepted_terms_at?->format('Y-m-d H:i') ?? 'No' }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ $u->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.users.show', $u) }}" class="text-indigo-400 hover:underline font-medium">Inspect &rarr;</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
@endsection
