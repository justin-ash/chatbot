<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BotSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SettingController extends Controller
{
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

        return view('admin.settings.index', compact('settings'));
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

    public function auditLogs(): View
    {
        $logs = AuditLog::latest('created_at')->paginate(30);
        return view('admin.audit.index', compact('logs'));
    }
}
