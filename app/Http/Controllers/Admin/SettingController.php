<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BotSetting;
use App\Services\Telegram\TelegramApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class SettingController extends Controller
{
    public function __construct(
        private readonly TelegramApi $telegram,
    ) {}

    public function index(): View
    {
        $settings = [
            'free_matches_per_day' => (int) BotSetting::getValue('free_matches_per_day', config('services.telegram.free_matches_per_day', 10)),
            'pack_credits' => (int) BotSetting::getValue('pack_credits', config('services.telegram.pack_credits', 50)),
            'pack_stars' => (int) BotSetting::getValue('pack_stars', config('services.telegram.pack_stars', 100)),
            'message_rate_limit_per_minute' => (int) BotSetting::getValue('message_rate_limit_per_minute', config('services.telegram.message_rate_limit_per_minute', 25)),
            'search_cooldown_seconds' => (int) BotSetting::getValue('search_cooldown_seconds', config('services.telegram.search_cooldown_seconds', 3)),
            'queue_timeout_minutes' => (int) BotSetting::getValue('queue_timeout_minutes', config('services.telegram.queue_timeout_minutes', 30)),
            'retention_days_metadata' => (int) BotSetting::getValue('retention_days_metadata', config('services.telegram.retention_days_metadata', 30)),
            'maintenance_mode' => (bool) BotSetting::getValue('maintenance_mode', false),
        ];

        $webhookConfig = [
            'token_configured' => trim((string) config('services.telegram.token')) !== '',
            'token_masked' => $this->maskToken((string) config('services.telegram.token')),
            'webhook_url' => (string) (config('services.telegram.webhook_url') ?: url('/api/telegram/webhook')),
            'webhook_secret' => (string) config('services.telegram.webhook_secret'),
            'bot_info' => null,
            'webhook_info' => null,
            'error' => null,
        ];

        if ($webhookConfig['token_configured']) {
            try {
                $webhookConfig['bot_info'] = $this->telegram->getMe();
                $webhookConfig['webhook_info'] = $this->telegram->getWebhookInfo();
            } catch (Throwable $e) {
                $webhookConfig['error'] = $e->getMessage();
            }
        }

        return view('admin.settings.index', compact('settings', 'webhookConfig'));
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'free_matches_per_day' => ['required', 'integer', 'min:0', 'max:1000'],
            'pack_credits' => ['required', 'integer', 'min:1', 'max:5000'],
            'pack_stars' => ['required', 'integer', 'min:1', 'max:100000'],
            'message_rate_limit_per_minute' => ['required', 'integer', 'min:1', 'max:300'],
            'search_cooldown_seconds' => ['required', 'integer', 'min:0', 'max:120'],
            'queue_timeout_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'retention_days_metadata' => ['required', 'integer', 'min:1', 'max:365'],
            'maintenance_mode' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $adminId = (int) Auth::id();

        $intKeys = [
            'free_matches_per_day',
            'pack_credits',
            'pack_stars',
            'message_rate_limit_per_minute',
            'search_cooldown_seconds',
            'queue_timeout_minutes',
            'retention_days_metadata',
        ];

        foreach ($intKeys as $key) {
            BotSetting::setValue($key, (int) $validated[$key], 'int', $adminId);
        }

        $maintenance = $request->boolean('maintenance_mode');
        BotSetting::setValue('maintenance_mode', $maintenance, 'bool', $adminId);

        AuditLog::record(
            action: 'settings.update',
            targetType: 'bot_settings',
            targetId: null,
            reason: $validated['reason'],
            metadata: array_merge(
                array_intersect_key($validated, array_flip($intKeys)),
                ['maintenance_mode' => $maintenance]
            ),
            actorAdminId: $adminId
        );

        return back()->with('status', 'Runtime bot configuration updated and recorded in audit trail.');
    }

    public function registerWebhook(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'webhook_url' => ['required', 'url', 'starts_with:https://'],
        ]);

        $secret = (string) config('services.telegram.webhook_secret');
        if ($secret === '') {
            return back()->withErrors(['webhook_url' => 'TELEGRAM_WEBHOOK_SECRET is not set in .env.']);
        }

        try {
            $this->telegram->setWebhook(
                $validated['webhook_url'],
                $secret,
                ['message', 'callback_query', 'pre_checkout_query']
            );

            $this->telegram->setMyCommands([
                ['command' => 'search', 'description' => 'Find a random person'],
                ['command' => 'next', 'description' => 'Find a new partner'],
                ['command' => 'stop', 'description' => 'Stop this chat'],
                ['command' => 'help', 'description' => 'How to use ChatMingle'],
                ['command' => 'language', 'description' => 'Change matching language'],
                ['command' => 'balance', 'description' => 'View remaining searches & payment link'],
                ['command' => 'buy', 'description' => 'Unlock 50 additional searches'],
            ]);

            AuditLog::record(
                action: 'webhook.register',
                targetType: 'telegram_webhook',
                targetId: null,
                reason: 'Registered Telegram HTTPS webhook and bot commands',
                metadata: ['url' => $validated['webhook_url']],
                actorAdminId: (int) Auth::id()
            );

            return back()->with('status', "✅ Webhook and ChatMingle commands registered at {$validated['webhook_url']}");
        } catch (Throwable $e) {
            return back()->withErrors(['webhook_url' => 'Failed to register webhook: ' . $e->getMessage()]);
        }
    }

    public function deleteWebhook(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        try {
            $this->telegram->deleteWebhook();

            AuditLog::record(
                action: 'webhook.delete',
                targetType: 'telegram_webhook',
                targetId: null,
                reason: 'Deleted Telegram webhook (switched to local polling mode)',
                actorAdminId: (int) Auth::id()
            );

            return back()->with('status', 'Webhook deleted. You can now use `php artisan telegram:poll` for local polling.');
        } catch (Throwable $e) {
            return back()->withErrors(['webhook_url' => 'Failed to delete webhook: ' . $e->getMessage()]);
        }
    }

    public function auditLogs(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $perPage = in_array((int) $request->query('per_page', 20), [10, 20, 50, 100], true)
            ? (int) $request->query('per_page', 20)
            : 20;

        $query = AuditLog::latest('created_at');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('target_type', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate($perPage)->withQueryString();
        return view('admin.audit.index', compact('logs', 'search', 'perPage'));
    }

    private function maskToken(string $token): string
    {
        $token = trim($token);
        if ($token === '' || strlen($token) < 12) {
            return 'Not Configured';
        }
        return substr($token, 0, 10) . '...' . substr($token, -5);
    }
}
