<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TelegramApi
{
    public function call(string $method, array $payload = [], bool $retryable = true): array
    {
        $token = (string) config('services.telegram.token');

        if ($token === '' && !app()->runningUnitTests()) {
            Log::warning('Telegram API called without TELEGRAM_BOT_TOKEN configured.', [
                'method' => $method,
            ]);
            return [];
        }

        $effectiveToken = $token !== '' ? $token : 'test-token';
        $url = "https://api.telegram.org/bot{$effectiveToken}/{$method}";

        $attempts = $retryable ? 3 : 1;
        $attempt = 0;

        while (true) {
            $attempt++;
            $response = Http::asJson()
                ->timeout(8)
                ->post($url, $payload);

            if ($response->status() === 429 && $attempt < $attempts) {
                $retryAfter = (int) ($response->json('parameters.retry_after') ?? 1);
                usleep(min(max($retryAfter, 1), 3) * 100000);
                continue;
            }

            if ($response->serverError() && $attempt < $attempts) {
                usleep(200000 * $attempt);
                continue;
            }

            try {
                $response->throw();
            } catch (RequestException $e) {
                Log::error('Telegram API HTTP error', [
                    'method' => $method,
                    'status' => $response->status(),
                    'description' => $response->json('description'),
                ]);
                throw new RuntimeException('Telegram API call failed: ' . ($response->json('description') ?? $e->getMessage()));
            }

            $data = $response->json();

            if (!($data['ok'] ?? false)) {
                throw new RuntimeException('Telegram API call failed: ' . ($data['description'] ?? 'Unknown error'));
            }

            $result = $data['result'] ?? [];

            return is_array($result) ? $result : ['value' => $result];
        }
    }

    public function sendMessage(int|string $chatId, string $text, array $extra = []): array
    {
        return $this->call('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'protect_content' => true,
        ], $extra));
    }

    public function editMessageText(int|string $chatId, int $messageId, string $text, array $extra = []): array
    {
        return $this->call('editMessageText', array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ], $extra));
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): array
    {
        $payload = [
            'callback_query_id' => $callbackQueryId,
            'show_alert' => $showAlert,
        ];

        if ($text !== null) {
            $payload['text'] = $text;
        }

        return $this->call('answerCallbackQuery', $payload);
    }

    public function sendInvoice(int|string $chatId, array $invoiceData): array
    {
        return $this->call('sendInvoice', array_merge([
            'chat_id' => $chatId,
            'currency' => 'XTR',
            'provider_token' => '', // Empty string required for Telegram Stars (XTR)
            'protect_content' => true,
        ], $invoiceData), retryable: false);
    }

    public function createInvoiceLink(array $invoiceData): ?string
    {
        try {
            $res = $this->call('createInvoiceLink', array_merge([
                'currency' => 'XTR',
                'provider_token' => '',
            ], $invoiceData), retryable: false);

            $link = $res['value'] ?? null;
            return is_string($link) && str_starts_with($link, 'http') ? $link : null;
        } catch (Throwable $e) {
            Log::warning('Failed to create Telegram Stars invoice link', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function setMyCommands(array $commands): array
    {
        return $this->call('setMyCommands', [
            'commands' => $commands,
        ], retryable: false);
    }

    public function answerPreCheckoutQuery(string $preCheckoutQueryId, bool $ok, ?string $errorMessage = null): array
    {
        $payload = [
            'pre_checkout_query_id' => $preCheckoutQueryId,
            'ok' => $ok,
        ];

        if (!$ok && $errorMessage !== null) {
            $payload['error_message'] = $errorMessage;
        }

        return $this->call('answerPreCheckoutQuery', $payload, retryable: false);
    }

    public function refundStarPayment(int $userId, string $telegramPaymentChargeId): array
    {
        return $this->call('refundStarPayment', [
            'user_id' => $userId,
            'telegram_payment_charge_id' => $telegramPaymentChargeId,
        ], retryable: false);
    }

    public function setWebhook(string $url, string $secretToken, array $allowedUpdates = ['message', 'callback_query', 'pre_checkout_query']): array
    {
        return $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
            'allowed_updates' => $allowedUpdates,
            'drop_pending_updates' => false,
        ], retryable: false);
    }

    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo', [], retryable: false);
    }

    public function deleteWebhook(bool $dropPendingUpdates = false): array
    {
        return $this->call('deleteWebhook', [
            'drop_pending_updates' => $dropPendingUpdates,
        ], retryable: false);
    }

    public function getUpdates(int $offset = 0, int $timeout = 15): array
    {
        return $this->call('getUpdates', [
            'offset' => $offset,
            'timeout' => $timeout,
            'allowed_updates' => ['message', 'callback_query', 'pre_checkout_query'],
        ], retryable: false);
    }
}
