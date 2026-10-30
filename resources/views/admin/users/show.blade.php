@extends('admin.layout')

@section('title', "User #{$user->id} Details")

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('admin.users.index') }}" class="text-xs text-indigo-400 hover:underline">&larr; Back to Users Directory</a>
            <h1 class="text-2xl font-bold text-white mt-1">
                {{ $user->displayName() }} — User #{{ $user->id }}
                <span class="text-base font-normal text-slate-400 font-mono">(TG ID: {{ $user->telegram_id }}{{ $user->username ? ' • @' . $user->username : '' }})</span>
            </h1>
        </div>
        <div class="flex items-center gap-2">
            @if ($user->is_premium)
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300">★ TELEGRAM PREMIUM</span>
            @endif
            <span class="px-3 py-1 rounded-full text-xs font-bold {{ $user->status === 'active' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                {{ strtoupper($user->status) }}
            </span>
        </div>
    </div>

    <!-- Top Summary Cards -->
    <div class="grid md:grid-cols-4 gap-4 mb-6 text-xs">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-1.5">
            <div class="text-slate-400 font-semibold uppercase text-[10px]">Profile & Timestamps</div>
            <div>Full Name: <strong class="text-white">{{ trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'N/A' }}</strong></div>
            <div>Username: <strong class="text-indigo-300 font-mono">{{ $user->username ? '@' . $user->username : 'N/A' }}</strong></div>
            <div>Gender: <strong class="text-indigo-300">{{ $user->genderLabel() }}</strong></div>
            <div>Language: <strong class="uppercase">{{ $user->language_code }}</strong></div>
            <div>Terms Accepted: <strong>{{ $user->accepted_terms_at?->format('Y-m-d H:i') ?? 'No' }}</strong></div>
            <div>Last Seen: <strong>{{ $user->last_seen_at?->diffForHumans() ?? 'Never' }}</strong></div>
            <form method="POST" action="{{ route('admin.users.profile', $user) }}" class="pt-2 border-t border-slate-800 flex items-center gap-1.5">
                @csrf
                <select name="gender" class="flex-1 rounded bg-slate-950 border border-slate-700 px-2 py-1 text-white">
                    <option value="" @selected(empty($user->gender))>Unspecified Gender</option>
                    <option value="male" @selected($user->gender === 'male')>👨 Male</option>
                    <option value="female" @selected($user->gender === 'female')>👩 Female</option>
                    <option value="other" @selected($user->gender === 'other')>🧑 Other</option>
                </select>
                <button type="submit" class="px-2.5 py-1 rounded bg-indigo-600 hover:bg-indigo-500 text-white font-semibold">Set</button>
            </form>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-1.5">
            <div class="text-slate-400 font-semibold uppercase text-[10px]">Quota & Stars Ledger</div>
            <div>Free Used Today ({{ $balance['usage_date'] }}): <strong>{{ $balance['free_used'] }} / {{ $balance['free_limit'] }}</strong></div>
            <div>Paid Credits Balance: <strong class="text-amber-300 text-sm">{{ $balance['paid_remaining'] }}</strong></div>
            <div>Stars Spent: <strong class="text-yellow-400">⭐ {{ number_format((int) ($user->total_stars_spent ?? 0)) }} XTR ({{ $user->paid_orders_count }} orders)</strong></div>
            <div>Ledger Reconciliation:
                @if ($reconciliation['is_consistent'])
                    <span class="text-emerald-400 font-semibold">Consistent ({{ $reconciliation['expected_balance'] }})</span>
                @else
                    <span class="text-rose-400 font-semibold">Mismatch ({{ $reconciliation['expected_balance'] }} vs {{ $reconciliation['actual_balance'] }})</span>
                @endif
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-1.5">
            <div class="text-slate-400 font-semibold uppercase text-[10px]">Activity & Reputation</div>
            <div>Total Conversations: <strong class="text-white text-sm">{{ $user->total_chats_count }}</strong></div>
            <div>Ratings Received: <span class="text-emerald-400 font-semibold">👍 {{ $user->thumbs_up_count }}</span> • <span class="text-amber-400 font-semibold">👎 {{ $user->thumbs_down_count }}</span></div>
            <div>Safety Reports Received: <strong class="text-rose-400">{{ $user->total_reports_received_count }} ({{ $user->open_reports_count }} open)</strong></div>
            <div>Reports Submitted: <strong>{{ $user->total_reports_made_count }}</strong></div>
            <div>Blocked By / Blocked: <strong>{{ $user->blocked_by_count }} / {{ $user->blocked_users_count }}</strong></div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-2">
            <div class="text-slate-400 font-semibold uppercase text-[10px]">Moderation & Credit Controls</div>
            <form method="POST" action="{{ route('admin.users.moderate', $user) }}" class="space-y-1.5">
                @csrf
                <div class="grid grid-cols-2 gap-1.5">
                    <select name="action" class="rounded bg-slate-950 border border-slate-700 px-2 py-1 text-white">
                        <option value="warn">Warn</option>
                        <option value="mute">Mute</option>
                        <option value="suspend">Suspend</option>
                        <option value="ban">Ban</option>
                        <option value="unban">Unban</option>
                    </select>
                    <input type="number" name="duration_minutes" placeholder="Mins" class="rounded bg-slate-950 border border-slate-700 px-2 py-1 text-white">
                </div>
                <div class="flex gap-1.5">
                    <input type="text" name="reason" required placeholder="Audit reason..." class="flex-1 rounded bg-slate-950 border border-slate-700 px-2 py-1 text-white">
                    <button type="submit" class="px-2.5 py-1 rounded bg-rose-600 hover:bg-rose-500 text-white font-semibold">Apply</button>
                </div>
            </form>

            @if (auth()->user()->isAdmin())
                <form method="POST" action="{{ route('admin.users.credits', $user) }}" class="space-y-1.5 pt-2 border-t border-slate-800">
                    @csrf
                    <div class="grid grid-cols-2 gap-1.5">
                        <select name="direction" class="rounded bg-slate-950 border border-slate-700 px-2 py-1 text-white">
                            <option value="credit">+ Grant Credits</option>
                            <option value="debit">- Deduct Credits</option>
                        </select>
                        <input type="number" name="amount" min="1" value="50" required class="rounded bg-slate-950 border border-slate-700 px-2 py-1 text-white">
                    </div>
                    <div class="flex gap-1.5">
                        <input type="text" name="reason" required placeholder="Credit reason..." class="flex-1 rounded bg-slate-950 border border-slate-700 px-2 py-1 text-white">
                        <button type="submit" class="px-2.5 py-1 rounded bg-indigo-600 hover:bg-indigo-500 text-white font-semibold">Save</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <!-- Paginated Sections -->
    <div class="grid lg:grid-cols-2 gap-6 mb-6">
        <!-- Paginated Conversations History -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 flex flex-col justify-between">
            <div>
                <h2 class="font-semibold text-white text-sm mb-3">💬 Match & Conversation History</h2>
                <div class="space-y-2 text-xs mb-4">
                    @forelse ($chats as $part)
                        @php
                            $conv = $part->conversation;
                            $partner = $conv?->participants->firstWhere('user_id', '!=', $user->id)?->user;
                        @endphp
                        <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-950 border border-slate-800">
                            <div>
                                <span class="font-bold text-indigo-300">Match #{{ $conv?->id }}</span>
                                @if ($partner)
                                    <span class="text-slate-300 ml-2">Partner: <a href="{{ route('admin.users.show', $partner->id) }}" class="text-indigo-400 underline">{{ $partner->displayName() }} (#{{ $partner->id }})</a> · <span class="text-slate-400">{{ $partner->genderLabel() }}</span></span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 text-slate-400">
                                <span>{{ $conv?->messages?->count() ?? 0 }} msgs • {{ $conv?->started_at?->format('Y-m-d H:i') }}</span>
                                @if ($conv)
                                    <a href="{{ route('admin.chats.show', $conv) }}" class="px-2 py-1 rounded bg-indigo-600/30 hover:bg-indigo-600 text-indigo-200 hover:text-white font-semibold">View Chat &rarr;</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-slate-500 text-xs">No conversations recorded yet.</p>
                    @endforelse
                </div>
            </div>
            {{ $chats->links() }}
        </div>

        <!-- Paginated Feedback & Safety Reports Received -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 flex flex-col justify-between">
            <div>
                <h2 class="font-semibold text-white text-sm mb-3">👍🚩 Feedback & Safety Reports Received</h2>
                <div class="space-y-2 text-xs mb-4">
                    @forelse ($feedbackReceived as $fb)
                        <div class="p-2.5 rounded-lg bg-slate-950 border border-slate-800">
                            <div class="flex items-center justify-between">
                                <span class="font-bold uppercase {{ in_array($fb->feedback_type, ['thumbs_up']) ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $fb->feedback_type }} (Match #{{ $fb->match_id }})
                                </span>
                                <span class="text-slate-500">From #{{ $fb->reporter_user_id }} • {{ $fb->created_at?->format('Y-m-d H:i') }}</span>
                            </div>
                            @if ($fb->description)
                                <div class="text-slate-300 mt-1">{{ $fb->description }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="text-slate-500 text-xs">No feedback or reports received.</p>
                    @endforelse
                </div>
            </div>
            {{ $feedbackReceived->links() }}
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Paginated Credit Ledger -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 flex flex-col justify-between">
            <div>
                <h2 class="font-semibold text-white text-sm mb-3">📒 Credit Ledger History</h2>
                <div class="space-y-2 text-xs mb-4">
                    @forelse ($ledgers as $entry)
                        <div class="flex items-center justify-between p-2 rounded bg-slate-950 border border-slate-800">
                            <div>
                                <span class="font-bold {{ $entry->direction === 'credit' ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $entry->direction === 'credit' ? '+' : '-' }}{{ $entry->amount }}
                                </span>
                                <span class="text-slate-300 ml-2">{{ $entry->reason }}</span>
                            </div>
                            <div class="text-slate-500">Bal: {{ $entry->balance_after }} • {{ $entry->created_at?->format('m-d H:i') }}</div>
                        </div>
                    @empty
                        <p class="text-slate-500 text-xs">No credit ledger entries.</p>
                    @endforelse
                </div>
            </div>
            {{ $ledgers->links() }}
        </div>

        <!-- Paginated Payments -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 flex flex-col justify-between">
            <div>
                <h2 class="font-semibold text-white text-sm mb-3">⭐ Stars Payments</h2>
                <div class="space-y-2 text-xs mb-4">
                    @forelse ($payments as $p)
                        <div class="flex items-center justify-between p-2 rounded bg-slate-950 border border-slate-800">
                            <div>
                                <span class="font-bold text-yellow-400">#{{ $p->id }} ({{ $p->total_amount }} {{ $p->currency }})</span>
                                <span class="text-slate-400 ml-1">+{{ $p->credits_granted }}</span>
                            </div>
                            <div class="uppercase font-semibold text-slate-300">{{ $p->status }}</div>
                        </div>
                    @empty
                        <p class="text-slate-500 text-xs">No payments recorded.</p>
                    @endforelse
                </div>
            </div>
            {{ $payments->links() }}
        </div>

        <!-- Paginated Ban & Moderation History -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 flex flex-col justify-between">
            <div>
                <h2 class="font-semibold text-white text-sm mb-3">⚖️ Moderation & Ban History</h2>
                <div class="space-y-2 text-xs mb-4">
                    @forelse ($bans as $ban)
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
            {{ $bans->links() }}
        </div>
    </div>
@endsection
