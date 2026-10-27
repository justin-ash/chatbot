<?php

namespace App\Jobs;

use App\Services\Telegram\UpdateRouter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessTelegramUpdate implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 2;

    public function __construct(
        public readonly array $update,
    ) {}

    public function handle(UpdateRouter $router): void
    {
        $router->route($this->update);
    }
}
