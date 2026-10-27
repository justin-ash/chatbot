@extends('admin.layout')

@section('title', 'GPay & Bank Account Withdrawals')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">💸 Admin Payouts — GPay (UPI) & Bank Account Withdrawal</h1>
        <p class="text-xs text-slate-400 mt-1">
            Manage your GPay UPI ID and Bank Account details, and withdraw collected bot revenue directly to your account.
        </p>
    </div>

    <div class="grid md:grid-cols-3 gap-4 mb-8">
        <div class="p-5 rounded-xl bg-slate-900 border border-slate-800">
            <div class="text-xs text-slate-400">Total Collected Revenue</div>
            <div class="text-2xl font-bold text-white mt-1">
                ₹{{ number_format($balance['gross_inr'], 2) }}
                <span class="text-sm font-normal text-slate-400">(${{ number_format($balance['gross_usd'], 2) }})</span>
            </div>
            <div class="text-xs text-yellow-400 mt-1">⭐ {{ number_format($balance['total_paid_stars']) }} XTR Stars</div>
        </div>

        <div class="p-5 rounded-xl bg-slate-900 border border-slate-800">
            <div class="text-xs text-slate-400">Withdrawn / Processing</div>
            <div class="text-2xl font-bold text-indigo-400 mt-1">
                ₹{{ number_format($balance['withdrawn_inr'], 2) }}
                <span class="text-sm font-normal text-slate-400">(${{ number_format($balance['withdrawn_usd'], 2) }})</span>
            </div>
            <div class="text-xs text-slate-400 mt-1">⭐ {{ number_format($balance['withdrawn_stars']) }} XTR Stars</div>
        </div>

        <div class="p-5 rounded-xl bg-emerald-950/30 border border-emerald-500/40">
            <div class="text-xs text-emerald-300 font-semibold">Available Withdrawable Balance</div>
            <div class="text-2xl font-bold text-emerald-400 mt-1">
                ₹{{ number_format($balance['available_inr'], 2) }}
                <span class="text-sm font-normal text-emerald-200">(${{ number_format($balance['available_usd'], 2) }})</span>
            </div>
            <div class="text-xs text-emerald-300 mt-1">⭐ {{ number_format($balance['available_stars']) }} XTR Stars Available</div>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6 mb-8">
        <!-- Withdraw Funds Form -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
            <h2 class="text-lg font-bold text-white mb-1">🚀 Withdraw to GPay (UPI) or Bank Account</h2>
            <p class="text-xs text-slate-400 mb-4">
                Select your saved GPay UPI ID or Bank Account to transfer funds.
                @if ($balance['razorpayx_configured'])
                    <span class="text-emerald-400 font-semibold">(RazorpayX Instant UPI/IMPS Payout Active)</span>
                @endif
            </p>

            @if ($accounts->isEmpty())
                <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs">
                    Please add your <strong>GPay UPI ID</strong> or <strong>Bank Account</strong> in the panel on the right first.
                </div>
            @else
                <form method="POST" action="{{ route('admin.withdrawals.store') }}" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Select Destination (GPay UPI / Bank Account)</label>
                        <select name="payout_account_id" required class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2.5 text-white">
                            @foreach ($accounts as $acc)
                                <option value="{{ $acc->id }}">
                                    {{ $acc->account_label }} — {{ $acc->formattedDestination() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">
                            Amount to Withdraw (in Stars units • Max Available: {{ $balance['available_stars'] }} XTR = ₹{{ number_format($balance['available_inr'], 2) }})
                        </label>
                        <input type="number" name="stars_to_withdraw" min="1" max="{{ max(1, $balance['available_stars']) }}"
                            value="{{ $balance['available_stars'] > 0 ? $balance['available_stars'] : 100 }}" required
                            class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-white font-semibold">
                        <p class="text-[11px] text-slate-500 mt-1">
                            Rate: 100 Stars = ${{ number_format(100 * $balance['usd_per_star'], 2) }} USD = ₹{{ number_format(100 * $balance['usd_per_star'] * $balance['inr_per_usd'], 2) }} INR
                        </p>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Transfer Note / Narration (Optional)</label>
                        <input type="text" name="notes" placeholder="e.g., Weekly GPay settlement to admin account"
                            class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-white">
                    </div>

                    <button type="submit" @disabled($balance['available_stars'] <= 0)
                        class="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 text-white font-bold text-sm transition">
                        💸 Withdraw Funds to Selected GPay / Bank Account
                    </button>
                </form>
            @endif
        </div>

        <!-- Add GPay UPI / Bank Account Form -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
            <h2 class="text-lg font-bold text-white mb-1">🏦 Add Admin GPay (UPI) or Bank Account</h2>
            <p class="text-xs text-slate-400 mb-4">Save your GPay UPI VPA (e.g., <code>yourname@okaxis</code>) or Bank Account (Account No + IFSC).</p>

            <form method="POST" action="{{ route('admin.withdrawals.accounts.store') }}" class="space-y-3 text-xs">
                @csrf
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-slate-400 mb-1">Payout Method</label>
                        <select name="method_type" id="method_type" onchange="togglePayoutFields(this.value)"
                            class="w-full rounded bg-slate-950 border border-slate-700 px-3 py-2 text-white">
                            <option value="gpay_upi">📱 Google Pay (GPay / UPI ID)</option>
                            <option value="bank_account">🏦 Direct Bank Account (IMPS/NEFT)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-400 mb-1">Account Label</label>
                        <input type="text" name="account_label" required placeholder="e.g., Admin Primary GPay"
                            class="w-full rounded bg-slate-950 border border-slate-700 px-3 py-2 text-white">
                    </div>
                </div>

                <div>
                    <label class="block text-slate-400 mb-1">Account Holder Full Name</label>
                    <input type="text" name="account_holder_name" required placeholder="Name as registered on GPay / Bank"
                        class="w-full rounded bg-slate-950 border border-slate-700 px-3 py-2 text-white">
                </div>

                <div id="gpay_fields" class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-slate-400 mb-1">GPay UPI ID (VPA)</label>
                        <input type="text" name="gpay_upi_id" placeholder="yourname@okicici / @okaxis"
                            class="w-full rounded bg-slate-950 border border-slate-700 px-3 py-2 text-white font-mono">
                    </div>
                    <div>
                        <label class="block text-slate-400 mb-1">GPay Mobile Number</label>
                        <input type="text" name="gpay_phone" placeholder="+91 9876543210"
                            class="w-full rounded bg-slate-950 border border-slate-700 px-3 py-2 text-white font-mono">
                    </div>
                </div>

                <div id="bank_fields" class="grid sm:grid-cols-3 gap-3 hidden">
                    <div>
                        <label class="block text-slate-400 mb-1">Bank Name</label>
                        <input type="text" name="bank_name" placeholder="HDFC / SBI / ICICI"
                            class="w-full rounded bg-slate-950 border border-slate-700 px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-slate-400 mb-1">Account Number</label>
                        <input type="text" name="account_number" placeholder="000123456789"
                            class="w-full rounded bg-slate-950 border border-slate-700 px-3 py-2 text-white font-mono">
                    </div>
                    <div>
                        <label class="block text-slate-400 mb-1">IFSC / SWIFT Code</label>
                        <input type="text" name="ifsc_swift_code" placeholder="HDFC0001234"
                            class="w-full rounded bg-slate-950 border border-slate-700 px-3 py-2 text-white font-mono uppercase">
                    </div>
                </div>

                <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold">
                    + Save GPay / Bank Account
                </button>
            </form>

            @if ($accounts->isNotEmpty())
                <div class="mt-5 pt-4 border-t border-slate-800 space-y-2">
                    <div class="text-xs font-semibold text-slate-300">Saved Payout Destinations:</div>
                    @foreach ($accounts as $acc)
                        <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-950 border border-slate-800 text-xs">
                            <div>
                                <span class="font-semibold text-white">{{ $acc->account_label }}</span>
                                <span class="text-slate-400 ml-2">{{ $acc->formattedDestination() }}</span>
                            </div>
                            <form method="POST" action="{{ route('admin.withdrawals.accounts.delete', $acc) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-400 hover:underline">Remove</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Withdrawal Ledger & Settlement History -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
            <h2 class="font-bold text-white text-sm">📋 Withdrawal & GPay / Bank Transfer History</h2>
            <span class="text-xs text-slate-400">Audited Payout Ledger</span>
        </div>

        <table class="w-full text-left text-xs">
            <thead class="bg-slate-950 text-slate-400 uppercase">
                <tr>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3">Method & Destination</th>
                    <th class="px-4 py-3">Stars Deducted</th>
                    <th class="px-4 py-3">Payout Amount</th>
                    <th class="px-4 py-3">UTR / Txn ID</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Confirm / Settle</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse ($withdrawals as $w)
                    <tr>
                        <td class="px-4 py-3 font-mono font-semibold text-indigo-300">{{ $w->reference_id }}</td>
                        <td class="px-4 py-3">
                            <span class="uppercase font-semibold text-[11px] px-2 py-0.5 rounded bg-slate-800 text-slate-200 mr-1">
                                {{ $w->payout_method === 'gpay_upi' ? 'GPay UPI' : 'Bank' }}
                            </span>
                            <span class="text-slate-300">{{ $w->destination_summary }}</span>
                        </td>
                        <td class="px-4 py-3 text-yellow-400 font-semibold">⭐ {{ number_format($w->stars_deducted) }}</td>
                        <td class="px-4 py-3 font-bold text-emerald-400">
                            ₹{{ number_format($w->inr_amount, 2) }}
                            <span class="text-slate-400 font-normal">(${{ number_format($w->usd_amount, 2) }})</span>
                        </td>
                        <td class="px-4 py-3 font-mono text-slate-300">{{ $w->provider_payout_id ?: 'Pending UTR' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded uppercase text-[11px] font-semibold {{ $w->status === 'completed' ? 'bg-emerald-500/20 text-emerald-300' : ($w->status === 'rejected' ? 'bg-rose-500/20 text-rose-300' : 'bg-amber-500/20 text-amber-300') }}">
                                {{ $w->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($w->status !== 'completed' && $w->status !== 'rejected')
                                <form method="POST" action="{{ route('admin.withdrawals.status', $w) }}" class="inline-flex items-center gap-1">
                                    @csrf
                                    <input type="hidden" name="status" value="completed">
                                    <input type="text" name="provider_payout_id" placeholder="Enter GPay/Bank UTR..."
                                        class="w-36 rounded bg-slate-950 border border-slate-700 px-2 py-1 text-[11px] text-white font-mono">
                                    <button type="submit" class="px-2.5 py-1 rounded bg-emerald-600 hover:bg-emerald-500 text-white text-[11px] font-semibold">
                                        Mark Paid
                                    </button>
                                </form>
                            @else
                                <span class="text-slate-500">{{ $w->processed_at?->format('Y-m-d H:i') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-slate-500">No withdrawals initiated yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
        function togglePayoutFields(method) {
            const gpay = document.getElementById('gpay_fields');
            const bank = document.getElementById('bank_fields');
            if (method === 'gpay_upi') {
                gpay.classList.remove('hidden');
                bank.classList.add('hidden');
            } else {
                gpay.classList.add('hidden');
                bank.classList.remove('hidden');
            }
        }
    </script>
@endsection
