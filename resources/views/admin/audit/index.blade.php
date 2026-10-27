@extends('admin.layout')

@section('title', 'Audit Trail')

@section('content')
    <h1 class="text-xl font-bold text-white mb-6">📜 Immutable Admin Audit Trail</h1>

    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-950 text-slate-400 uppercase">
                <tr>
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">Timestamp</th>
                    <th class="px-4 py-3">Actor</th>
                    <th class="px-4 py-3">Action</th>
                    <th class="px-4 py-3">Target</th>
                    <th class="px-4 py-3">Reason</th>
                    <th class="px-4 py-3">Metadata</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-4 py-3 font-mono">#{{ $log->id }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td class="px-4 py-3">
                            @if ($log->actor_admin_id)
                                Web Admin #{{ $log->actor_admin_id }}
                            @elseif ($log->actor_telegram_id)
                                TG Admin {{ $log->actor_telegram_id }}
                            @else
                                System
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-indigo-300">{{ $log->action }}</td>
                        <td class="px-4 py-3">{{ $log->target_type }} {{ $log->target_id ? '#' . $log->target_id : '' }}</td>
                        <td class="px-4 py-3 text-slate-200">{{ $log->reason }}</td>
                        <td class="px-4 py-3 font-mono text-[11px] text-slate-400">{{ json_encode($log->metadata) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-slate-500">No audit events recorded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
@endsection
