<?php

use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Matches: POST https://yourdomain.com/api/telegram/webhook
|
*/

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('api.telegram.webhook');

Route::get('/telegram/webhook/health', function () {
    return response()->json([
        'ok' => true,
        'app' => config('app.name'),
        'endpoint' => url('/api/telegram/webhook'),
        'token_configured' => trim((string) config('services.telegram.token')) !== '',
        'webhook_secret_configured' => trim((string) config('services.telegram.webhook_secret')) !== '',
    ]);
})->name('api.telegram.webhook.health');
