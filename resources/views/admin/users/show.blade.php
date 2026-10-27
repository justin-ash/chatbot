@extends('admin.layout')

@section('title', "User #{$user->id}")

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="{{ route('admin.users.index') }}" class="text-xs text-indigo-400 hover:underline">&larr; Back to Users</a>
            <h1 class="text-2xl font-bold text-white mt-1">User #{{ $user->id }} (TG: <code>{{ $user->telegram_id }}</code>)</h1>
        </div>
        <span class="px-3 py-1 rounded-full text-xs font-bold {{ $user->status === 'active' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
            {{ strtoupper($user->status) }}
        </span>
    </div>

    <div class="grid md:grid-cols-3 gap-6 mb-8">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-2 text-xs">
            <h2 class="font-semibold text-white text-sm mb-2">📊 Quota & Ledger Balance</h2>
            <div>Free Used Today ({{ $balance['usage_date'] }}): <strong>{{ $balance['free_used'] }} / {{ $balance['free_limit'] }}</strong></div>
            <div>Paid Credits Balance: <strong class="text-amber-300">{{ $balance['paid_remaining'] }}</strong></div>
            <div>Ledger Reconciliation:
                @if ($reconciliation['is_consistent'])
                    <span class="text-emerald-400 font-semibold">Consistent ({{ $reconciliation['expected_balance'] }})</span>
                @else
                    <span class="text-rose-400 font-semibold">Mismatch (Ledger: {{ $reconciliation['expected_balance'] }} vs Balance: {{ $reconciliation['actual_balance'] }})</span>
                @endif
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
            <h2 class="font-semibold text-white text-sm mb-3">🛡️ Apply Moderation Action</h2>
            <form method="POST" action="{{ route('admin.users.moderate', $user) }}" class="space-y-3 text-xs">
                @csrf
                <div class="grid grid-cols-2 gap-2">
                    <select name="action" class="rounded bg-slate-950 border border-slate-700 px-2.5 py-1.5 text-white">
                        <option value="warn">Warn</option>
                        <option value="mute">Mute</option>
                        <option value="suspend">Suspend</option>
                        <option value="ban">Ban</option>
                        <option value="unban">Unban / Restore</option>
                    </select>
                    <input type="number" name="duration_minutes" placeholder="Duration (mins, optional)"
                        class="rounded bg-slate-950 border border-slate-700 px-2.5 py-1.5 text-white">
                </div>
                <input type="text" name="reason" required placeholder="Mandatory audit reason..."
                    class="w-full rounded bg-slate-950 border border-slate-700 px-2.5 py-1.5 text-white">
                <button type="submit" class="w-full py-1.5 rounded bg-rose-600 hover:bg-rose-500 text-white font-semibold">Execute Action</button>
            </form>
        </div>

        @if (auth()->user()->isAdmin())
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                <h2 class="font-semibold text-white text-sm mb-3">⭐ Adjust Paid Credits</h2>
                <form method="POST" action="{{ route('admin.users.credits', $user) }}" class="space-y-3 text-xs">
                    @csrf
                    <div class="grid grid-cols-2 gap-2">
                        <select name="direction" class="rounded bg-slate-950 border border-slate-700 px-2.5 py-1.5 text-white">
                            <option value="credit">Grant (+)</option>
                            <option value="debit">Deduct (-)</option>
                        </select>
                        <input type="number" name="amount" min="1" value="50" required
                            class="rounded bg-slate-950 border border-slate-700 px-2.5 py-1.5 text-white">
                    </div>
                    <input type="text" name="reason" required placeholder="Mandatory audit reason..."
                        class="w-full rounded bg-slate-950 border border-slate-700 px-2.5 py-1.5 text-white">
                    <button type="submit" class="w-full py-1.5 rounded bg-indigo-600 hover:bg-indigo-500 text-white font-semibold">Save Credit Adjustment</button>
                </form>
            </div>
        @endif
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
            <h2 class="font-semibold text-white text-sm mb-3">📒 Credit Ledger History</h2>
            <div class="space-y-2 text-xs max-h-80 overflow-y-auto">
                @forelse ($user->creditLedgers as $entry)
                    <div class="flex items-center justify-between p-2 rounded bg-slate-950 border border-slate-800">
                        <div>
                            <span class="font-bold {{ $entry->direction === 'credit' ? 'text-emerald-400' : 'text-rose-400' }}">
                                {{ $entry->direction === 'credit' ? '+' : '-' }}{{ $entry->amount }}
                            </span>
                            <span class="text-slate-300 ml-2">{{ $entry->reason }}</span>
                        </div>
                        <div class="text-slate-500">Bal: {{ $entry->balance_after }} • {{ $entry->created_at?->format('Y-m-d H:i') }}</div>
                    </div>
                @empty
                    <p class="text-slate-500 text-xs">No credit ledger entries.</p>
                @endforelse
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
            <h2 class="font-semibold text-white text-sm mb-3">⚖️ Moderation & Ban History</h2>
            <div class="space-y-2 text-xs max-h-80 overflow-y-auto">
                @forelse ($user->bans as $ban)
                    <div class="p-2 rounded bg-slate-950 border border-slate-800">
                        <div class="flex justify-between">
                            <span class="font-bold text-rose-400 uppercase">{{ $ban->action }}</span>
                            <span class="text-slate-500">{{ $ban->starts_at?->format('Y-m-d H:i') }}</span>
                        </div>
                        <div class="text-slate-300 mt-1">{{ $ban->reason }}</div>
                    </div>
                @empty
                    <p class="text-slate-500 text-xs">No moderation history.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
