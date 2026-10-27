<?php

namespace App\Console\Commands;

use App\Models\ProcessedUpdate;
use App\Services\Telegram\TelegramApi;
use App\Services\Telegram\UpdateRouter;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll {--once : Poll once and exit}';

    protected $description = 'Run long-polling for local development (do not use concurrently with production webhook)';

    public function handle(TelegramApi $telegram, UpdateRouter $router): int
    {
        $token = trim((string) config('services.telegram.token'));
        if ($token === '') {
            $this->error('TELEGRAM_BOT_TOKEN is empty in .env. Set your token first.');
            return self::FAILURE;
        }

        $this->info('Starting Telegram long-polling loop... Press Ctrl+C to stop.');
        try {
            $telegram->setMyCommands([
                ['command' => 'search', 'description' => 'Search random users'],
                ['command' => 'next', 'description' => 'Stop and find next random user'],
                ['command' => 'stop', 'description' => 'Stop the conversation with current user'],
                ['command' => 'help', 'description' => 'How to use the bot'],
                ['command' => 'language', 'description' => 'Change matching language'],
                ['command' => 'balance', 'description' => 'View remaining searches & payment link'],
                ['command' => 'buy', 'description' => 'Unlock 50 additional searches'],
            ]);
        } catch (Throwable) {
            // Non-fatal if setMyCommands fails
        }
        $offset = 0;

        do {
            try {
                $updates = $telegram->getUpdates($offset, $this->option('once') ? 1 : 10);

                foreach ($updates as $update) {
                    if (!is_array($update) || !isset($update['update_id'])) {
                        continue;
                    }

                    $updateId = (int) $update['update_id'];
                    $offset = $updateId + 1;

                    try {
                        ProcessedUpdate::create([
                            'update_id' => $updateId,
                            'update_type' => 'polled',
                            'processed_at' => now(),
                        ]);
                    } catch (UniqueConstraintViolationException) {
                        continue;
                    }

                    $router->route($update);
                    $this->line("Processed update #{$updateId}");
                }
            } catch (Throwable $e) {
                $this->error('Polling error: ' . $e->getMessage());
                sleep(2);
            }
        } while (!$this->option('once'));

        return self::SUCCESS;
    }
}
