@extends('admin.layout')
@section('title', 'Chats & Messages')
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
        <div>
            <h2 style="margin:0;">💬 Conversations & User Messages</h2>
            <p class="muted" style="margin:4px 0 0;">Search and inspect anonymous chat sessions, participant genders, and stored message transcripts</p>
        </div>
        <div class="muted" style="font-size:13px;">
            Total Matches Found: <strong style="color:#f8fafc;">{{ number_format($conversations->total()) }}</strong>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.chats.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <div style="flex:1;min-width:240px;">
            <label style="font-size:11px;color:#94a3b8;text-transform:uppercase;">Search Match ID, User, or Message Content</label>
            <input type="text" name="q" value="{{ $search }}" placeholder="Match #ID, @username, Telegram ID, or text inside message...">
        </div>
        <div style="width:150px;">
            <label style="font-size:11px;color:#94a3b8;text-transform:uppercase;">Status</label>
            <select name="status">
                <option value="">All Statuses</option>
                <option value="active" @selected($status === 'active')>Active Now</option>
                <option value="ended" @selected($status === 'ended')>Ended</option>
            </select>
        </div>
        <div style="width:150px;">
            <label style="font-size:11px;color:#94a3b8;text-transform:uppercase;">Language</label>
            <select name="language">
                <option value="">All Languages</option>
                @foreach($supportedLanguages as $code => $label)
                    <option value="{{ $code }}" @selected($language === $code)>{{ strtoupper($code) }} — {{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div style="width:120px;">
            <label style="font-size:11px;color:#94a3b8;text-transform:uppercase;">Per Page</label>
            <select name="per_page">
                @foreach([10, 15, 25, 50, 100] as $size)
                    <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} rows</option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;gap:6px;">
            <button type="submit" class="btn">Filter</button>
            @if($search !== '' || $status !== '' || $language !== '')
                <a href="{{ route('admin.chats.index') }}" class="btn btn-secondary">Reset</a>
            @endif
        </div>
    </form>
</div>

<div class="card" style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>Match ID</th>
                <th>Status / Lang</th>
                <th>Participant 1 (Gender)</th>
                <th>Participant 2 (Gender)</th>
                <th>Messages Stored</th>
                <th>Started / Ended</th>
                <th>End Reason</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($conversations as $conv)
                @php
                    $parts = $conv->participants->values();
                    $userA = $parts->get(0)?->user;
                    $userB = $parts->get(1)?->user;
                @endphp
                <tr>
                    <td>
                        <strong>#{{ $conv->id }}</strong>
                    </td>
                    <td>
                        <span class="badge badge-{{ $conv->status === 'active' ? 'active' : 'closed' }}">{{ strtoupper($conv->status) }}</span>
                        <span class="badge" style="background:#1e293b;color:#cbd5e1;margin-left:4px;">{{ strtoupper($conv->language_code) }}</span>
                    </td>
                    <td>
                        @if($userA)
                            <div>
                                <a href="{{ route('admin.users.show', $userA) }}" style="font-weight:600;">{{ $userA->displayName() }}</a>
                                <span class="muted" style="font-size:11px;">(#{{ $userA->id }})</span>
                            </div>
                            <div style="font-size:11px;margin-top:3px;">
                                <span class="badge" style="background:#1e293b;color:#38bdf8;">{{ $userA->genderLabel() }}</span>
                                <span class="muted" style="font-family:monospace;">{{ $userA->telegram_id }}</span>
                            </div>
                        @else
                            <span class="muted">Unknown</span>
                        @endif
                    </td>
                    <td>
                        @if($userB)
                            <div>
                                <a href="{{ route('admin.users.show', $userB) }}" style="font-weight:600;">{{ $userB->displayName() }}</a>
                                <span class="muted" style="font-size:11px;">(#{{ $userB->id }})</span>
                            </div>
                            <div style="font-size:11px;margin-top:3px;">
                                <span class="badge" style="background:#1e293b;color:#f472b6;">{{ $userB->genderLabel() }}</span>
                                <span class="muted" style="font-family:monospace;">{{ $userB->telegram_id }}</span>
                            </div>
                        @else
                            <span class="muted">Unknown</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge" style="background:#0f172a;border:1px solid #334155;color:#f8fafc;font-size:12px;">
                            💬 {{ number_format($conv->messages_count) }} msgs
                        </span>
                    </td>
                    <td class="muted" style="font-size:12px;">
                        <div>Start: {{ $conv->started_at?->format('Y-m-d H:i:s') }}</div>
                        <div>End: {{ $conv->ended_at?->format('Y-m-d H:i:s') ?? 'Active Now' }}</div>
                    </td>
                    <td class="muted" style="font-size:12px;">
                        {{ $conv->end_reason ?? '-' }}
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <button type="button"
                                    class="btn"
                                    style="padding:5px 10px;font-size:12px;background:#0284c7;"
                                    onclick="openChatModal({{ $conv->id }}, '{{ route('admin.chats.messages', $conv) }}')">
                                👁️ Quick View
                            </button>
                            <a href="{{ route('admin.chats.show', $conv) }}"
                               class="btn btn-secondary"
                               style="padding:5px 10px;font-size:12px;">
                                Full View →
                            </a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="muted" style="text-align:center;padding:24px;">No conversations found matching your criteria.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @include('admin.partials.pagination', ['paginator' => $conversations])
</div>

{{-- Interactive Chat Transcript Popup Modal --}}
<div id="chatTranscriptModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(2,6,23,0.82);backdrop-filter:blur(4px);overflow-y:auto;padding:24px 14px;">
    <div style="max-width:860px;margin:0 auto;background:#0f172a;border:1px solid #334155;border-radius:14px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.7);overflow:hidden;">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;background:#1e293b;border-bottom:1px solid #334155;">
            <div>
                <h3 id="cmTitle" style="margin:0;font-size:17px;color:#f8fafc;">Chat Transcript</h3>
                <div id="cmSub" class="muted" style="font-size:12px;margin-top:3px;">Loading conversation...</div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <a id="cmFullLink" href="#" class="btn btn-secondary" style="font-size:12px;padding:6px 12px;">Open Full Page →</a>
                <button type="button" onclick="closeChatModal()" style="background:#334155;color:#f8fafc;border:none;border-radius:8px;padding:6px 12px;cursor:pointer;font-weight:700;">✕ Close</button>
            </div>
        </div>

        <div id="cmParticipants" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;padding:14px 20px;background:#090d16;border-bottom:1px solid #1e293b;"></div>

        <div id="cmMessages" style="padding:18px 20px;max-height:60vh;overflow-y:auto;display:flex;flex-direction:column;gap:10px;background:#0b1120;">
            <div class="muted" style="text-align:center;padding:24px;">Loading messages...</div>
        </div>
    </div>
</div>

<script>
function escapeChatHtml(val) {
    if (val === null || val === undefined) return '';
    return String(val)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function openChatModal(convId, url) {
    const modal = document.getElementById('chatTranscriptModal');
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';

    document.getElementById('cmTitle').textContent = 'Conversation #' + convId + ' Transcript';
    document.getElementById('cmSub').textContent = 'Fetching messages...';
    document.getElementById('cmParticipants').innerHTML = '';
    document.getElementById('cmMessages').innerHTML = '<div class="muted" style="text-align:center;padding:24px;">Loading messages...</div>';

    fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            const c = data.conversation;
            document.getElementById('cmTitle').textContent = `Conversation #${c.id} (${c.status} · ${c.language_code})`;
            document.getElementById('cmSub').textContent = `Started: ${c.started_at} | Ended: ${c.ended_at} | End Reason: ${c.end_reason}`;
            document.getElementById('cmFullLink').href = c.show_url;

            const partsHtml = (data.participants || []).map((p, idx) => `
                <div style="background:#1e293b;padding:10px 14px;border-radius:8px;border-left:3px solid ${idx === 0 ? '#38bdf8' : '#f472b6'};">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <a href="${escapeChatHtml(p.show_url)}" style="font-weight:700;color:#f8fafc;">${escapeChatHtml(p.display_name)} (#${p.user_id})</a>
                        <span class="badge" style="background:#0f172a;color:${idx === 0 ? '#38bdf8' : '#f472b6'};">${escapeChatHtml(p.gender)}</span>
                    </div>
                    <div class="muted" style="font-size:11px;margin-top:4px;">
                        TG ID: <code>${escapeChatHtml(p.telegram_id)}</code> · ${escapeChatHtml(p.username)} · ${escapeChatHtml(p.status)}
                    </div>
                </div>
            `).join('');
            document.getElementById('cmParticipants').innerHTML = partsHtml;

            const msgs = data.messages || [];
            if (!msgs.length) {
                document.getElementById('cmMessages').innerHTML = '<div class="muted" style="text-align:center;padding:28px;">No messages were sent in this conversation.</div>';
                return;
            }

            document.getElementById('cmMessages').innerHTML = msgs.map(m => {
                const align = m.is_first_user ? 'flex-start' : 'flex-end';
                const bubbleBg = m.is_first_user ? '#1e293b' : '#1e3a8a';
                const borderCol = m.is_first_user ? '#38bdf8' : '#f472b6';
                return `
                    <div style="display:flex;flex-direction:column;align-items:${align};">
                        <div style="max-width:78%;background:${bubbleBg};border:1px solid #334155;border-left:3px solid ${borderCol};border-radius:10px;padding:10px 14px;">
                            <div style="display:flex;justify-content:space-between;gap:12px;font-size:11px;color:#94a3b8;margin-bottom:4px;">
                                <strong style="color:#f8fafc;">${escapeChatHtml(m.sender_name)} (#${m.sender_user_id}) · ${escapeChatHtml(m.sender_gender)}</strong>
                                <span>${escapeChatHtml(m.created_at)}</span>
                            </div>
                            <div style="color:#f1f5f9;font-size:13.5px;white-space:pre-wrap;word-break:break-word;">${escapeChatHtml(m.message_text)}</div>
                            <div style="font-size:10px;color:#64748b;margin-top:4px;text-align:right;">
                                ${m.char_length} chars · ${escapeChatHtml(m.moderation_state)}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        })
        .catch(() => {
            document.getElementById('cmMessages').innerHTML = '<div style="color:#fca5a5;text-align:center;padding:24px;">Failed to load chat transcript.</div>';
        });
}

function closeChatModal() {
    document.getElementById('chatTranscriptModal').style.display = 'none';
    document.body.style.overflow = '';
}
</script>
@endsection
