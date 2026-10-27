@extends('admin.layout')

@section('title', 'Telegram Stars Payments')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-xl font-bold text-white">⭐ Telegram Stars Payments (XTR)</h1>
        <form method="GET" action="{{ route('admin.payments.index') }}" class="flex items-center gap-2">
            <input type="text" name="q" value="{{ $search }}" placeholder="Payload, Charge ID, or User ID..."
                class="rounded-lg bg-slate-900 border border-slate-700 px-3 py-1.5 text-sm text-white">
            <select name="status" class="rounded-lg bg-slate-900 border border-slate-700 px-3 py-1.5 text-sm text-white">
                <option value="">All Statuses</option>
                @foreach (['pending', 'paid', 'refunded', 'failed'] as $st)
                    <option value="{{ $st }}" @selected($status === $st)>{{ ucfirst($st) }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm">Search</button>
        </form>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-950 text-slate-400 uppercase">
                <tr>
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3">Credits</th>
                    <th class="px-4 py-3">Payload / Charge ID</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Credited At</th>
                    <th class="px-4 py-3 text-right">Refund Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse ($payments as $p)
                    <tr>
                        <td class="px-4 py-3 font-mono">#{{ $p->id }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.users.show', $p->user_id) }}" class="text-indigo-400 hover:underline">#{{ $p->user_id }} (TG: {{ $p->user?->telegram_id }})</a>
                        </td>
                        <td class="px-4 py-3 font-bold text-yellow-400">{{ $p->total_amount }} {{ $p->currency }}</td>
                        <td class="px-4 py-3">+{{ $p->credits_granted }}</td>
                        <td class="px-4 py-3 font-mono text-[11px] text-slate-400">
                            <div>P: {{ Str::limit($p->invoice_payload, 24) }}</div>
                            @if ($p->telegram_payment_charge_id)
                                <div class="text-slate-300">C: {{ Str::limit($p->telegram_payment_charge_id, 28) }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 uppercase font-semibold">{{ $p->status }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ $p->credited_at?->format('Y-m-d H:i') ?? '-' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($p->status === 'paid' && auth()->user()->isAdmin())
                                <form method="POST" action="{{ route('admin.payments.refund', $p) }}" class="inline-flex items-center gap-1">
                                    @csrf
                                    <input type="text" name="reason" required placeholder="Refund reason..."
                                        class="w-32 rounded bg-slate-950 border border-slate-700 px-2 py-1 text-[11px] text-white">
                                    <button type="submit" class="px-2.5 py-1 rounded bg-rose-600 hover:bg-rose-500 text-white text-[11px]">Refund XTR</button>
                                </form>
                            @else
                                <span class="text-slate-600">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">No payments found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $payments->links() }}
    </div>
@endsection
