<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatFeedback;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $language = trim((string) $request->query('language', ''));
        $perPage = in_array((int) $request->query('per_page', 15), [10, 15, 25, 50, 100], true)
            ? (int) $request->query('per_page', 15)
            : 15;

        $query = Conversation::query()
            ->with(['participants.user'])
            ->withCount('messages')
            ->latest('started_at');

        if ($search !== '') {
            $cleanSearch = ltrim($search, '@#');
            $query->where(function ($q) use ($search, $cleanSearch) {
                if (is_numeric($cleanSearch)) {
                    $q->where('id', (int) $cleanSearch);
                }

                $q->orWhereHas('participants.user', function ($uq) use ($search, $cleanSearch) {
                    if (is_numeric($cleanSearch)) {
                        $uq->where('id', (int) $cleanSearch)
                            ->orWhere('telegram_id', (int) $cleanSearch);
                    }
                    $uq->orWhere('username', 'like', "%{$cleanSearch}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                })
                ->orWhereHas('messages', function ($mq) use ($search) {
                    $mq->where('message_text', 'like', "%{$search}%");
                });
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($language !== '') {
            $query->where('language_code', $language);
        }

        $conversations = $query->paginate($perPage)->withQueryString();
        $supportedLanguages = config('services.telegram.supported_languages', []);

        return view('admin.chats.index', compact(
            'conversations',
            'search',
            'status',
            'language',
            'perPage',
            'supportedLanguages'
        ));
    }

    public function show(Conversation $conversation): View
    {
        $conversation->load(['participants.user', 'endedBy']);

        $messages = $conversation->messages()
            ->with('sender')
            ->orderBy('id')
            ->paginate(30, ['*'], 'messages_page')
            ->withQueryString();

        $feedbacks = ChatFeedback::with(['reporter', 'targetUser'])
            ->where('match_id', $conversation->id)
            ->latest()
            ->get();

        return view('admin.chats.show', compact('conversation', 'messages', 'feedbacks'));
    }

    public function messagesJson(Conversation $conversation): JsonResponse
    {
        $conversation->load(['participants.user', 'endedBy']);

        $participants = $conversation->participants->map(function ($p) {
            $u = $p->user;
            return [
                'user_id' => $u?->id,
                'telegram_id' => $u?->telegram_id,
                'display_name' => $u?->displayName() ?? 'Unknown',
                'username' => $u?->username ? '@' . $u->username : '-',
                'gender' => $u?->genderLabel() ?? 'Unspecified',
                'status' => strtoupper($u?->status ?? 'unknown'),
                'show_url' => $u ? route('admin.users.show', $u) : '#',
            ];
        })->values();

        $firstUserId = $participants->first()['user_id'] ?? 0;

        $messages = $conversation->messages()
            ->with('sender')
            ->orderBy('id')
            ->get()
            ->map(function ($m) use ($firstUserId) {
                $sender = $m->sender;
                return [
                    'id' => $m->id,
                    'sender_user_id' => $m->sender_user_id,
                    'is_first_user' => (int) $m->sender_user_id === (int) $firstUserId,
                    'sender_name' => $sender?->displayName() ?? "User #{$m->sender_user_id}",
                    'sender_gender' => $sender?->genderLabel() ?? 'Unspecified',
                    'message_text' => $m->message_text ?? '[Metadata only — sent prior to text logging]',
                    'char_length' => $m->char_length,
                    'moderation_state' => $m->moderation_state,
                    'created_at' => $m->created_at?->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'status' => strtoupper($conversation->status),
                'language_code' => strtoupper($conversation->language_code),
                'started_at' => $conversation->started_at?->format('Y-m-d H:i:s'),
                'ended_at' => $conversation->ended_at?->format('Y-m-d H:i:s') ?? 'Active Now',
                'end_reason' => $conversation->end_reason ?? '-',
                'show_url' => route('admin.chats.show', $conversation),
            ],
            'participants' => $participants,
            'messages' => $messages,
        ]);
    }
}
