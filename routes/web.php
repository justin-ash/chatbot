<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\PaymentCheckoutController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Middleware\EnsureAdminRole;
use Illuminate\Support\Facades\Route;

// Public Landing, Legal Policy & Payment Link Checkout Pages
Route::view('/', 'public.home')->name('home');
Route::view('/terms', 'public.terms')->name('terms');
Route::view('/privacy', 'public.privacy')->name('privacy');

Route::get('/pay/{payload}', [PaymentCheckoutController::class, 'show'])->name('payment.checkout');
Route::post('/pay/{payload}/complete', [PaymentCheckoutController::class, 'complete'])->name('payment.complete');

// Telegram Bot Webhook Endpoint
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook');

// Admin Authentication
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    });

    Route::middleware(['auth', EnsureAdminRole::class])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // User Management & Moderation
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::post('/users/{user}/moderate', [UserController::class, 'moderate'])->name('users.moderate');
        Route::post('/users/{user}/credits', [UserController::class, 'adjustCredits'])->name('users.credits');

        // Moderation Queue
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::post('/reports/{report}/resolve', [ReportController::class, 'resolve'])->name('reports.resolve');

        // Payments & Refunds
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund');

        // Runtime Configuration, Webhook Connection & Audit Trail
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/webhook/register', [SettingController::class, 'registerWebhook'])->name('settings.webhook.register');
        Route::post('/settings/webhook/delete', [SettingController::class, 'deleteWebhook'])->name('settings.webhook.delete');
        Route::get('/audit-logs', [SettingController::class, 'auditLogs'])->name('audit.index');
    });
});
