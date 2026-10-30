@extends('admin.layout')
@section('title', 'Conversation #' . $conversation->id)
@section('content')
@php
    $parts = $conversation->participants->values();
    $userA = $parts->get(0)?->user;
    $userB = $parts->get(1)?->user;
    $firstUserId = $userA?->id ?? 0;
@endphp

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
    <div>
        <a href="{{ route('admin.chats.index') }}" class="muted" style="font-size:13px;">← Back to Chats & Messages</a>
        <h2 style="margin:4px 0 0;">💬 Conversation #{{ $conversation->id }} Transcript</h2>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <span class="badge badge-{{ $conversation->status === 'active' ? 'active' : 'closed' }}" style="font-size:13px;padding:5px 12px;">
            {{ strtoupper($conversation->status) }}
        </span>
        <span class="badge" style="background:#1e293b;color:#38bdf8;font-size:13px;padding:5px 12px;">
            LANG: {{ strtoupper($conversation->language_code) }}
        </span>
    </div>
</div>

<div class="grid grid-3" style="margin-bottom:16px;">
    <div class="card" style="margin-bottom:0;border-left:4px solid #38bdf8;">
        <div class="muted" style="font-size:11px;text-transform:uppercase;">Participant 1</div>
        @if($userA)
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;">
                <a href="{{ route('admin.users.show', $userA) }}" style="font-size:16px;font-weight:700;">{{ $userA->displayName() }} (#{{ $userA->id }})</a>
                <span class="badge" style="background:#1e293b;color:#38bdf8;">{{ $userA->genderLabel() }}</span>
            </div>
            <div class="muted" style="font-size:12px;margin-top:6px;">
                Telegram ID: <code>{{ $userA->telegram_id }}</code><br>
                Username: {{ $userA->username ? '@' . $userA->username : 'None' }}<br>
                Status: <span class="badge badge-{{ $userA->status }}">{{ strtoupper($userA->status) }}</span>
            </div>
        @else
            <p class="muted">Unknown User</p>
        @endif
    </div>

    <div class="card" style="margin-bottom:0;border-left:4px solid #f472b6;">
        <div class="muted" style="font-size:11px;text-transform:uppercase;">Participant 2</div>
        @if($userB)
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;">
                <a href="{{ route('admin.users.show', $userB) }}" style="font-size:16px;font-weight:700;">{{ $userB->displayName() }} (#{{ $userB->id }})</a>
                <span class="badge" style="background:#1e293b;color:#f472b6;">{{ $userB->genderLabel() }}</span>
            </div>
            <div class="muted" style="font-size:12px;margin-top:6px;">
                Telegram ID: <code>{{ $userB->telegram_id }}</code><br>
                Username: {{ $userB->username ? '@' . $userB->username : 'None' }}<br>
                Status: <span class="badge badge-{{ $userB->status }}">{{ strtoupper($userB->status) }}</span>
            </div>
        @else
            <p class="muted">Unknown User</p>
        @endif
    </div>

    <div class="card" style="margin-bottom:0;">
        <div class="muted" style="font-size:11px;text-transform:uppercase;">Session Metadata</div>
        <div style="font-size:13px;margin-top:8px;line-height:1.7;">
            <div><strong>Started:</strong> {{ $conversation->started_at?->format('Y-m-d H:i:s') }}</div>
            <div><strong>Ended:</strong> {{ $conversation->ended_at?->format('Y-m-d H:i:s') ?? 'Active Now' }}</div>
            <div><strong>End Reason:</strong> <code>{{ $conversation->end_reason ?? '-' }}</code></div>
            <div><strong>Ended By:</strong> {{ $conversation->endedBy ? $conversation->endedBy->displayName() . ' (#' . $conversation->ended_by_user_id . ')' : '-' }}</div>
            <div><strong>Total Messages:</strong> {{ number_format($messages->total()) }}</div>
        </div>
    </div>
</div>

<div class="card">
    <h3 style="margin-top:0;">📨 Stored Chat Messages ({{ number_format($messages->total()) }})</h3>

    <div style="background:#0b1120;border:1px solid #1e293b;border-radius:12px;padding:18px;display:flex;flex-direction:column;gap:12px;">
        @forelse($messages as $msg)
            @php
                $isFirst = (int) $msg->sender_user_id === (int) $firstUserId;
                $sender = $msg->sender;
            @endphp
            <div style="display:flex;flex-direction:column;align-items:{{ $isFirst ? 'flex-start' : 'flex-end' }};">
                <div style="max-width:75%;background:{{ $isFirst ? '#1e293b' : '#1e3a8a' }};border:1px solid #334155;border-left:4px solid {{ $isFirst ? '#38bdf8' : '#f472b6' }};border-radius:10px;padding:10px 14px;">
                    <div style="display:flex;justify-content:space-between;gap:16px;font-size:11px;color:#94a3b8;margin-bottom:5px;">
                        <span>
                            @if($sender)
                                <a href="{{ route('admin.users.show', $sender) }}" style="color:#f8fafc;font-weight:700;">{{ $sender->displayName() }} (#{{ $sender->id }})</a>
                                · <span style="color:{{ $isFirst ? '#38bdf8' : '#f472b6' }};">{{ $sender->genderLabel() }}</span>
                            @else
                                <strong style="color:#f8fafc;">User #{{ $msg->sender_user_id }}</strong>
                            @endif
                        </span>
                        <span>{{ $msg->created_at?->format('Y-m-d H:i:s') }}</span>
                    </div>
                    <div style="color:#f8fafc;font-size:14px;white-space:pre-wrap;word-break:break-word;">{{ $msg->message_text ?? '[Metadata only — sent prior to text logging]' }}</div>
                    <div style="display:flex;justify-content:space-between;gap:10px;margin-top:6px;font-size:10px;color:#64748b;">
                        <span>Msg ID: #{{ $msg->id }} · Hash: <code>{{ substr($msg->content_hash, 0, 10) }}...</code></span>
                        <span>{{ $msg->char_length }} chars · {{ strtoupper($msg->moderation_state) }}</span>
                    </div>
                </div>
            </div>
        @empty
            <div class="muted" style="text-align:center;padding:30px;">No messages were sent during this conversation.</div>
        @endforelse
    </div>

    @include('admin.partials.pagination', ['paginator' => $messages])
</div>

@if($feedbacks->isNotEmpty())
<div class="card">
    <h3 style="margin-top:0;">🗳️ Post-Chat Feedback & Safety Reports for Match #{{ $conversation->id }}</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Reporter</th>
                <th>Target Partner</th>
                <th>Feedback Type</th>
                <th>Description</th>
                <th>Status</th>
                <th>Submitted At</th>
            </tr>
        </thead>
        <tbody>
            @foreach($feedbacks as $fb)
                <tr>
                    <td>#{{ $fb->id }}</td>
                    <td>
                        @if($fb->reporter)
                            <a href="{{ route('admin.users.show', $fb->reporter) }}">{{ $fb->reporter->displayName() }} (#{{ $fb->reporter_user_id }})</a>
                        @else
                            User #{{ $fb->reporter_user_id }}
                        @endif
                    </td>
                    <td>
                        @if($fb->targetUser)
                            <a href="{{ route('admin.users.show', $fb->targetUser) }}">{{ $fb->targetUser->displayName() }} (#{{ $fb->target_user_id }})</a>
                        @else
                            User #{{ $fb->target_user_id }}
                        @endif
                    </td>
                    <td><span class="badge" style="background:#1e293b;color:#f8fafc;">{{ $fb->typeLabel() }}</span></td>
                    <td>{{ $fb->description ?? '-' }}</td>
                    <td><span class="badge badge-{{ $fb->status === 'pending' ? 'open' : 'closed' }}">{{ strtoupper($fb->status) }}</span></td>
                    <td class="muted">{{ $fb->created_at?->format('Y-m-d H:i:s') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
