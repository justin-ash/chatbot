@extends('admin.layout')

@section('title', 'Users Management')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">👥 Telegram Users Directory</h1>
            <p class="text-xs text-slate-400 mt-0.5">Search, filter, and inspect complete user profiles, match history, ratings, reports, and credit ledgers.</p>
        </div>

        <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap items-center gap-2 text-xs">
            <input type="text" name="q" value="{{ $search }}" placeholder="ID, Telegram ID, @username, name..."
                class="rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 text-white w-56">

            <select name="status" class="rounded-lg bg-slate-900 border border-slate-700 px-2.5 py-2 text-white">
                <option value="">All Statuses</option>
                @foreach (['active', 'muted', 'suspended', 'banned'] as $st)
                    <option value="{{ $st }}" @selected($status === $st)>{{ ucfirst($st) }}</option>
                @endforeach
            </select>

            <select name="language" class="rounded-lg bg-slate-900 border border-slate-700 px-2.5 py-2 text-white">
                <option value="">All Languages</option>
                @foreach ($supportedLanguages as $code => $label)
                    <option value="{{ $code }}" @selected($language === $code)>{{ $label }} ({{ strtoupper($code) }})</option>
                @endforeach
            </select>

            <select name="per_page" onchange="this.form.submit()" class="rounded-lg bg-slate-900 border border-slate-700 px-2.5 py-2 text-white">
                @foreach ([10, 15, 20, 50, 100] as $size)
                    <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} / page</option>
                @endforeach
            </select>

            <button type="submit" class="px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold">Filter</button>
            @if ($search !== '' || $status !== '' || $language !== '')
                <a href="{{ route('admin.users.index') }}" class="px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300">Reset</a>
            @endif
        </form>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-x-auto mb-4">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-950 text-slate-400 uppercase border-b border-slate-800">
                <tr>
                    <th class="px-4 py-3">User / Identity</th>
                    <th class="px-4 py-3">Lang & State</th>
                    <th class="px-4 py-3">Account Status</th>
                    <th class="px-4 py-3">Daily Free / Paid Credits</th>
                    <th class="px-4 py-3">Chats & Feedback</th>
                    <th class="px-4 py-3">Safety / Blocks</th>
                    <th class="px-4 py-3">Joined / Last Seen</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse ($users as $u)
                    @php
                        $todayUsage = $u->dailyUsages->first();
                        $freeUsed = (int) ($todayUsage?->free_matches_used ?? 0);
                    @endphp
                    <tr class="hover:bg-slate-800/40 transition">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-white">#{{ $u->id }}</span>
                                <span class="font-semibold text-indigo-300">{{ $u->displayName() }}</span>
                                @if ($u->is_premium)
                                    <span class="px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 text-[10px] font-bold">★ PREMIUM</span>
                                @endif
                            </div>
                            <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                                TG ID: <span class="text-slate-200">{{ $u->telegram_id }}</span>
                                @if ($u->username)
                                    • <span class="text-indigo-400">{{ '@' . $u->username }}</span>
                                @endif
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded bg-slate-800 text-slate-200 font-mono uppercase font-semibold">
                                {{ $u->language_code }}
                            </span>
                            @if ($u->queueEntry)
                                <div class="text-[11px] text-amber-400 font-medium mt-1">⏳ Queued</div>
                            @else
                                <div class="text-[11px] text-slate-500 mt-1">
                                    Terms: {{ $u->accepted_terms_at ? '✅ Yes' : '❌ No' }}
                                </div>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold {{ $u->status === 'active' ? 'bg-emerald-500/20 text-emerald-300' : ($u->status === 'muted' ? 'bg-amber-500/20 text-amber-300' : 'bg-rose-500/20 text-rose-300') }}">
                                {{ strtoupper($u->status) }}
                            </span>
                            @if ($u->muted_until && $u->muted_until->isFuture())
                                <div class="text-[10px] text-amber-400 mt-1">Until {{ $u->muted_until->format('M d H:i') }}</div>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <div class="text-slate-200">
                                Free Today: <span class="font-semibold {{ $freeUsed >= $freeLimit ? 'text-rose-400' : 'text-emerald-400' }}">{{ $freeUsed }}/{{ $freeLimit }}</span>
                            </div>
                            <div class="text-[11px] mt-0.5">
                                Paid Credits: <span class="font-bold text-amber-300">{{ $u->paid_credits_balance }}</span>
                                @if (($u->total_stars_spent ?? 0) > 0)
                                    <span class="text-yellow-400 ml-1">(⭐ {{ number_format((int) $u->total_stars_spent) }} XTR)</span>
                                @endif
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            <div class="font-semibold text-white">💬 {{ $u->total_chats_count }} Chats</div>
                            <div class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-2">
                                <span class="text-emerald-400">👍 {{ $u->thumbs_up_count }}</span>
                                <span class="text-amber-400">👎 {{ $u->thumbs_down_count }}</span>
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="{{ $u->open_reports_count > 0 ? 'text-rose-400 font-bold' : 'text-slate-400' }}">
                                    🚩 {{ $u->total_reports_received_count }} Rcvd ({{ $u->open_reports_count }} open)
                                </span>
                            </div>
                            <div class="text-[11px] text-slate-500 mt-0.5">
                                🚫 Blocked by {{ $u->blocked_by_count }} • Blocked {{ $u->blocked_users_count }}
                            </div>
                        </td>

                        <td class="px-4 py-3 text-[11px] text-slate-400">
                            <div>Joined: <span class="text-slate-300">{{ $u->created_at?->format('Y-m-d') }}</span></div>
                            <div class="mt-0.5">Seen: <span class="text-slate-300">{{ $u->last_seen_at?->diffForHumans() ?? 'Never' }}</span></div>
                        </td>

                        <td class="px-4 py-3 text-right whitespace-nowrap space-x-1.5">
                            <button type="button"
                                onclick="openUserDetailsModal('{{ route('admin.users.details', $u) }}')"
                                class="px-2.5 py-1.5 rounded-lg bg-indigo-600/20 border border-indigo-500/40 hover:bg-indigo-600 text-indigo-300 hover:text-white font-semibold transition">
                                👁️ Details Popup
                            </button>
                            <a href="{{ route('admin.users.show', $u) }}"
                                class="inline-block px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 font-medium transition">
                                Full Page &rarr;
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-slate-500">No users found matching your filter criteria.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}

    <!-- Interactive User Details View Popup Modal -->
    <div id="userDetailsModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-5xl max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-slate-950 border-b border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-xl">👤</span>
                    <div>
                        <h3 id="modalUserTitle" class="text-base font-bold text-white">User Details</h3>
                        <p id="modalUserSubtitle" class="text-xs text-slate-400 font-mono">Loading user telemetry...</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a id="modalFullPageLink" href="#" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold">
                        Open Full Page &rarr;
                    </a>
                    <button type="button" onclick="closeUserDetailsModal()" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold">
                        ✕ Close
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto space-y-6 text-xs flex-1">
                <!-- Profile & Metrics Grid -->
                <div class="grid md:grid-cols-3 gap-4">
                    <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-1.5">
                        <div class="text-slate-400 font-semibold uppercase text-[10px] tracking-wider mb-1">Identity & Profile</div>
                        <div class="flex justify-between"><span class="text-slate-400">Internal ID:</span> <span id="m_id" class="font-mono text-white font-bold">-</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Telegram ID:</span> <span id="m_tg_id" class="font-mono text-indigo-300">-</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Username:</span> <span id="m_username" class="font-mono text-indigo-400">-</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">First / Last Name:</span> <span id="m_fullname" class="text-slate-200">-</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Language:</span> <span id="m_lang" class="font-mono font-semibold text-white">-</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Live State:</span> <span id="m_queue_state" class="text-emerald-400 font-medium">-</span></div>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-1.5">
                        <div class="text-slate-400 font-semibold uppercase text-[10px] tracking-wider mb-1">Quota, Credits & Billing</div>
                        <div class="flex justify-between"><span class="text-slate-400">Account Status:</span> <span id="m_status" class="font-bold text-emerald-400">-</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Free Used Today:</span> <span id="m_free_quota" class="font-semibold text-white">-</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Paid Credits Balance:</span> <span id="m_paid_credits" class="font-bold text-amber-300">-</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Paid Orders / Stars:</span> <span id="m_stars_spent" class="text-yellow-400 font-semibold">-</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Terms Accepted:</span> <span id="m_terms" class="text-slate-300">-</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Last Seen:</span> <span id="m_last_seen" class="text-slate-300">-</span></div>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-1.5">
                        <div class="text-slate-400 font-semibold uppercase text-[10px] tracking-wider mb-1">Reputation & Moderation</div>
                        <div class="flex justify-between"><span class="text-slate-400">Total Conversations:</span> <span id="m_total_chats" class="font-bold text-white">-</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Ratings Received:</span> <span id="m_ratings" class="text-slate-200">-</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Reports Received:</span> <span id="m_reports_rcvd" class="text-rose-400 font-semibold">-</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Reports Submitted:</span> <span id="m_reports_made" class="text-slate-300">-</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Blocked By / Blocked:</span> <span id="m_blocks" class="text-slate-300">-</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Muted Until:</span> <span id="m_muted_until" class="text-amber-300">-</span></div>
                    </div>
                </div>

                <!-- Quick Action Forms inside Popup -->
                <div class="grid md:grid-cols-2 gap-4">
                    <form id="modalModerateForm" method="POST" action="#" class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-2.5">
                        @csrf
                        <div class="font-semibold text-white">🛡️ Quick Moderation Action</div>
                        <div class="grid grid-cols-2 gap-2">
                            <select name="action" class="rounded bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-white">
                                <option value="warn">Warn</option>
                                <option value="mute">Mute</option>
                                <option value="suspend">Suspend</option>
                                <option value="ban">Ban</option>
                                <option value="unban">Unban / Restore</option>
                            </select>
                            <input type="number" name="duration_minutes" placeholder="Duration (mins)"
                                class="rounded bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-white">
                        </div>
                        <div class="flex gap-2">
                            <input type="text" name="reason" required placeholder="Mandatory audit reason..."
                                class="flex-1 rounded bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-white">
                            <button type="submit" class="px-3 py-1.5 rounded bg-rose-600 hover:bg-rose-500 text-white font-semibold">Apply</button>
                        </div>
                    </form>

                    @if (auth()->user()->isAdmin())
                        <form id="modalCreditsForm" method="POST" action="#" class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-2.5">
                            @csrf
                            <div class="font-semibold text-white">⭐ Quick Paid Credits Adjustment</div>
                            <div class="grid grid-cols-2 gap-2">
                                <select name="direction" class="rounded bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-white">
                                    <option value="credit">Grant (+)</option>
                                    <option value="debit">Deduct (-)</option>
                                </select>
                                <input type="number" name="amount" min="1" value="50" required
                                    class="rounded bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-white">
                            </div>
                            <div class="flex gap-2">
                                <input type="text" name="reason" required placeholder="Mandatory audit reason..."
                                    class="flex-1 rounded bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-white">
                                <button type="submit" class="px-3 py-1.5 rounded bg-indigo-600 hover:bg-indigo-500 text-white font-semibold">Save</button>
                            </div>
                        </form>
                    @endif
                </div>

                <!-- Paginated Tabs inside Popup -->
                <div>
                    <div class="flex flex-wrap gap-2 border-b border-slate-800 pb-2 mb-3">
                        <button type="button" onclick="switchModalTab('chats')" id="tab_btn_chats" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-semibold">💬 Recent Chats</button>
                        <button type="button" onclick="switchModalTab('feedback')" id="tab_btn_feedback" class="px-3 py-1.5 rounded-lg bg-slate-800 text-slate-300">👍🚩 Feedback & Reports</button>
                        <button type="button" onclick="switchModalTab('ledger')" id="tab_btn_ledger" class="px-3 py-1.5 rounded-lg bg-slate-800 text-slate-300">📒 Credit Ledger</button>
                        <button type="button" onclick="switchModalTab('payments')" id="tab_btn_payments" class="px-3 py-1.5 rounded-lg bg-slate-800 text-slate-300">⭐ Stars Payments</button>
                        <button type="button" onclick="switchModalTab('bans')" id="tab_btn_bans" class="px-3 py-1.5 rounded-lg bg-slate-800 text-slate-300">⚖️ Ban History</button>
                    </div>

                    <div id="modalTabContainer" class="bg-slate-950 border border-slate-800 rounded-xl p-4 min-h-[180px]">
                        Loading records...
                    </div>

                    <!-- Popup Tab Pagination Controls -->
                    <div class="flex items-center justify-between mt-3 px-2 text-slate-400">
                        <div id="modalPageInfo">Page 1</div>
                        <div class="flex gap-2">
                            <button type="button" id="modalPrevBtn" onclick="changeModalTabPage(-1)" class="px-3 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-200 disabled:opacity-40">&laquo; Prev</button>
                            <button type="button" id="modalNextBtn" onclick="changeModalTabPage(1)" class="px-3 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-200 disabled:opacity-40">Next &raquo;</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let modalData = null;
        let currentTab = 'chats';
        let currentTabPage = 1;
        const pageSize = 5;

        async function openUserDetailsModal(detailsUrl) {
            const modal = document.getElementById('userDetailsModal');
            modal.classList.remove('hidden');
            document.getElementById('modalTabContainer').innerHTML = '<div class="text-slate-400 py-6 text-center">Loading user details...</div>';

            try {
                const res = await fetch(detailsUrl, { headers: { 'Accept': 'application/json' } });
                modalData = await res.json();

                const u = modalData.user;
                const s = modalData.stats;

                document.getElementById('modalUserTitle').textContent = `${u.display_name} (#${u.id})`;
                document.getElementById('modalUserSubtitle').textContent = `Telegram ID: ${u.telegram_id} • ${u.username} • Joined: ${u.created_at}`;
                document.getElementById('modalFullPageLink').href = u.show_url;
                document.getElementById('modalModerateForm').action = u.moderate_url;
                const creditsForm = document.getElementById('modalCreditsForm');
                if (creditsForm) creditsForm.action = u.credits_url;

                document.getElementById('m_id').textContent = '#' + u.id;
                document.getElementById('m_tg_id').textContent = u.telegram_id;
                document.getElementById('m_username').textContent = u.username;
                document.getElementById('m_fullname').textContent = `${u.first_name} ${u.last_name}` + (u.is_premium ? ' (★ Premium)' : '');
                document.getElementById('m_lang').textContent = u.language_code;
                document.getElementById('m_queue_state').textContent = u.queue_state;

                document.getElementById('m_status').textContent = u.status;
                document.getElementById('m_free_quota').textContent = `${s.free_used} / ${s.free_limit} (${s.free_remaining} left)`;
                document.getElementById('m_paid_credits').textContent = s.paid_remaining + (s.ledger_consistent ? ' (Verified)' : ' (Mismatch)');
                document.getElementById('m_stars_spent').textContent = `${s.paid_orders_count} orders (⭐ ${s.total_stars_spent} XTR)`;
                document.getElementById('m_terms').textContent = u.accepted_terms_at;
                document.getElementById('m_last_seen').textContent = u.last_seen_at;

                document.getElementById('m_total_chats').textContent = s.total_chats;
                document.getElementById('m_ratings').textContent = `👍 ${s.thumbs_up}  |  👎 ${s.thumbs_down}`;
                document.getElementById('m_reports_rcvd').textContent = `${s.reports_received} total (${s.open_reports} open)`;
                document.getElementById('m_reports_made').textContent = s.reports_made;
                document.getElementById('m_blocks').textContent = `Blocked by ${s.blocked_by} • Blocked ${s.blocked_users}`;
                document.getElementById('m_muted_until').textContent = u.muted_until;

                switchModalTab('chats');
            } catch (err) {
                document.getElementById('modalTabContainer').innerHTML = '<div class="text-rose-400 py-6 text-center">Failed to load user details.</div>';
            }
        }

        function closeUserDetailsModal() {
            document.getElementById('userDetailsModal').classList.add('hidden');
        }

        function switchModalTab(tab) {
            currentTab = tab;
            currentTabPage = 1;
            ['chats', 'feedback', 'ledger', 'payments', 'bans'].forEach(t => {
                const btn = document.getElementById('tab_btn_' + t);
                if (t === tab) {
                    btn.className = 'px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-semibold';
                } else {
                    btn.className = 'px-3 py-1.5 rounded-lg bg-slate-800 text-slate-300 hover:bg-slate-700';
                }
            });
            renderModalTab();
        }

        function changeModalTabPage(delta) {
            currentTabPage += delta;
            renderModalTab();
        }

        function renderModalTab() {
            if (!modalData) return;
            const list = modalData[currentTab] || [];
            const totalPages = Math.max(1, Math.ceil(list.length / pageSize));
            currentTabPage = Math.min(Math.max(1, currentTabPage), totalPages);

            const slice = list.slice((currentTabPage - 1) * pageSize, currentTabPage * pageSize);
            const container = document.getElementById('modalTabContainer');

            document.getElementById('modalPageInfo').textContent = `Showing ${slice.length} of ${list.length} records (Page ${currentTabPage} of ${totalPages})`;
            document.getElementById('modalPrevBtn').disabled = currentTabPage <= 1;
            document.getElementById('modalNextBtn').disabled = currentTabPage >= totalPages;

            if (slice.length === 0) {
                container.innerHTML = '<div class="text-slate-500 py-6 text-center">No records in this category.</div>';
                return;
            }

            if (currentTab === 'chats') {
                container.innerHTML = slice.map(c => `
                    <div class="flex items-center justify-between py-2 border-b border-slate-800/80 last:border-0">
                        <div>
                            <span class="font-bold text-indigo-300">Match #${c.conversation_id}</span>
                            <span class="ml-2 text-slate-300">Partner: ${c.partner_name} (#${c.partner_id ?? '-'})</span>
                            <span class="ml-2 px-1.5 py-0.5 rounded bg-slate-800 text-slate-400">${c.language_code}</span>
                        </div>
                        <div class="text-slate-400">
                            💬 ${c.messages_count} msgs • ${c.started_at} (${c.status.toUpperCase()})
                        </div>
                    </div>
                `).join('');
            } else if (currentTab === 'feedback') {
                container.innerHTML = slice.map(f => `
                    <div class="flex items-center justify-between py-2 border-b border-slate-800/80 last:border-0">
                        <div>
                            <span class="font-bold text-amber-300 uppercase">${f.feedback_type}</span>
                            <span class="ml-2 text-slate-400">Match #${f.match_id} • From User #${f.reporter_id}</span>
                            <div class="text-slate-300 mt-0.5">${f.description}</div>
                        </div>
                        <div class="text-slate-500">${f.status.toUpperCase()} • ${f.created_at}</div>
                    </div>
                `).join('');
            } else if (currentTab === 'ledger') {
                container.innerHTML = slice.map(l => `
                    <div class="flex items-center justify-between py-2 border-b border-slate-800/80 last:border-0">
                        <div>
                            <span class="font-bold ${l.direction === 'credit' ? 'text-emerald-400' : 'text-rose-400'}">${l.direction === 'credit' ? '+' : '-'}${l.amount}</span>
                            <span class="ml-2 text-slate-200">${l.reason}</span>
                        </div>
                        <div class="text-slate-400">Balance After: <strong>${l.balance_after}</strong> • ${l.created_at}</div>
                    </div>
                `).join('');
            } else if (currentTab === 'payments') {
                container.innerHTML = slice.map(p => `
                    <div class="flex items-center justify-between py-2 border-b border-slate-800/80 last:border-0">
                        <div>
                            <span class="font-bold text-yellow-400">#${p.id} — ${p.total_amount} ${p.currency}</span>
                            <span class="ml-2 text-emerald-300">+${p.credits_granted} credits</span>
                            <span class="ml-2 font-mono text-slate-500">${p.charge_id}</span>
                        </div>
                        <div class="text-slate-400 uppercase font-semibold">${p.status} • ${p.credited_at}</div>
                    </div>
                `).join('');
            } else if (currentTab === 'bans') {
                container.innerHTML = slice.map(b => `
                    <div class="flex items-center justify-between py-2 border-b border-slate-800/80 last:border-0">
                        <div>
                            <span class="font-bold text-rose-400">${b.action}</span>
                            <span class="ml-2 text-slate-200">${b.reason}</span>
                        </div>
                        <div class="text-slate-400">${b.starts_at} &rarr; ${b.ends_at}</div>
                    </div>
                `).join('');
            }
        }
    </script>
@endsection
