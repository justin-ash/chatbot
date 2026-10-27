<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramApi;
use Illuminate\Console\Command;
use Throwable;

class TelegramWebhookSetupCommand extends Command
{
    protected $signature = 'telegram:webhook:setup
        {--url= : Custom HTTPS webhook URL (defaults to APP_URL/telegram/webhook)}
        {--info : Only fetch and display current getWebhookInfo}
        {--delete : Delete current webhook}';

    protected $description = 'Register, inspect, or delete the Telegram Bot HTTPS webhook with secret_token';

    public function handle(TelegramApi $telegram): int
    {
        $token = trim((string) config('services.telegram.token'));
        if ($token === '') {
            $this->error('TELEGRAM_BOT_TOKEN is not set in your .env file.');
            return self::FAILURE;
        }

        try {
            if ($this->option('info')) {
                $info = $telegram->getWebhookInfo();
                $this->info('Current Webhook Info:');
                $this->line(json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return self::SUCCESS;
            }

            if ($this->option('delete')) {
                $telegram->deleteWebhook();
                $this->info('Webhook deleted successfully.');
                return self::SUCCESS;
            }

            $url = (string) ($this->option('url') ?: rtrim((string) config('app.url'), '/') . '/telegram/webhook');
            $secret = (string) config('services.telegram.webhook_secret');

            if ($secret === '') {
                $this->error('TELEGRAM_WEBHOOK_SECRET must be set in your .env file.');
                return self::FAILURE;
            }

            $telegram->setWebhook($url, $secret, ['message', 'callback_query', 'pre_checkout_query']);
            $telegram->setMyCommands([
                ['command' => 'search', 'description' => 'Search random users'],
                ['command' => 'next', 'description' => 'Stop and find next random user'],
                ['command' => 'stop', 'description' => 'Stop the conversation with current user'],
                ['command' => 'help', 'description' => 'How to use the bot'],
                ['command' => 'language', 'description' => 'Change matching language'],
                ['command' => 'balance', 'description' => 'View remaining searches & payment link'],
                ['command' => 'buy', 'description' => 'Unlock 50 additional searches'],
            ]);
            $this->info("✅ Webhook and bot commands (/search, /next, /stop, /help) registered successfully at: {$url}");

            $info = $telegram->getWebhookInfo();
            $this->line(json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Telegram API Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
