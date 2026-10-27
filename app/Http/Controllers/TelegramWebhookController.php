<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\ProcessedUpdate;
use App\Services\Telegram\UpdateRouter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request, UpdateRouter $router): JsonResponse
    {
        $expected = (string) config('services.telegram.webhook_secret');
        $provided = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        abort_unless(
            $expected !== '' && hash_equals($expected, $provided),
            403,
            'Invalid webhook secret token.'
        );

        // Enforce reasonable payload size limit (64 KB)
        abort_if(strlen((string) $request->getContent()) > 65536, 413, 'Payload too large.');

        $validated = $request->validate([
            'update_id' => ['required', 'integer'],
        ]);

        $updateId = (int) $validated['update_id'];
        $payload = $request->all();
        $updateType = $this->detectUpdateType($payload);

        // Idempotent deduplication via processed_updates UNIQUE(update_id)
        try {
            ProcessedUpdate::create([
                'update_id' => $updateId,
                'update_type' => $updateType,
                'processed_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        // Pre-checkout queries MUST be answered within 10 seconds; process immediately
        if (isset($payload['pre_checkout_query'])) {
            $router->route($payload);
            return response()->json(['ok' => true]);
        }

        if (config('services.telegram.queue_updates', false)) {
            ProcessTelegramUpdate::dispatch($payload);
        } else {
            ProcessTelegramUpdate::dispatchSync($payload);
        }

        return response()->json(['ok' => true]);
    }

    private function detectUpdateType(array $payload): string
    {
        if (isset($payload['pre_checkout_query'])) {
            return 'pre_checkout_query';
        }
        if (isset($payload['message']['successful_payment'])) {
            return 'successful_payment';
        }
        if (isset($payload['callback_query'])) {
            return 'callback_query';
        }
        if (isset($payload['message'])) {
            return 'message';
        }
        return 'other';
    }
}
