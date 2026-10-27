<?php

namespace App\Console\Commands;

use App\Models\Ban;
use App\Models\BotSetting;
use App\Models\ChatMessage;
use App\Models\ProcessedUpdate;
use App\Models\TelegramUser;
use App\Services\Chat\MatchingService;
use Illuminate\Console\Command;

class TelegramCleanupCommand extends Command
{
    protected $signature = 'telegram:cleanup';

    protected $description = 'Clean stale queue waiters, expire temporary bans/mutes, and enforce data retention';

    public function handle(MatchingService $matchingService): int
    {
        // 1. Clean stale queue entries
        $staleRemoved = $matchingService->cleanupStaleQueue();

        // 2. Lift expired mutes and temporary bans
        $expiredBans = Ban::query()
            ->where('is_active', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->get();

        foreach ($expiredBans as $ban) {
            $ban->update(['is_active' => false]);
        }

        $unmutedCount = TelegramUser::query()
            ->where('status', 'muted')
            ->whereNotNull('muted_until')
            ->where('muted_until', '<=', now())
            ->update([
                'status' => 'active',
                'muted_until' => null,
            ]);

        // 3. Purge old message metadata and processed_updates according to retention policy
        $retentionDays = (int) BotSetting::getValue(
            'retention_days_metadata',
            (int) config('services.telegram.retention_days_metadata', 30)
        );

        $cutoff = now()->subDays(max(1, $retentionDays));
        $deletedMessages = ChatMessage::where('created_at', '<', $cutoff)->delete();
        $deletedUpdates = ProcessedUpdate::where('processed_at', '<', now()->subDays(7))->delete();

        $this->info("Cleanup complete: {$staleRemoved} stale queue entries, {$unmutedCount} unmuted users, {$deletedMessages} old message metadata rows, {$deletedUpdates} old update IDs.");

        return self::SUCCESS;
    }
}
