<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\BotSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@telegrambot.local'],
            [
                'name' => 'Platform Super Admin',
                'password' => Hash::make('Admin@123456'),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        AdminUser::updateOrCreate(
            ['web_user_id' => $admin->id],
            [
                'role' => 'super_admin',
                'enabled' => true,
            ]
        );

        BotSetting::setValue('free_matches_per_day', (int) config('services.telegram.free_matches_per_day', 10), 'int', $admin->id);
        BotSetting::setValue('pack_credits', (int) config('services.telegram.pack_credits', 50), 'int', $admin->id);
        BotSetting::setValue('pack_stars', (int) config('services.telegram.pack_stars', 100), 'int', $admin->id);
        BotSetting::setValue('message_rate_limit_per_minute', (int) config('services.telegram.message_rate_limit_per_minute', 25), 'int', $admin->id);
        BotSetting::setValue('search_cooldown_seconds', (int) config('services.telegram.search_cooldown_seconds', 3), 'int', $admin->id);
        BotSetting::setValue('queue_timeout_minutes', (int) config('services.telegram.queue_timeout_minutes', 30), 'int', $admin->id);
        BotSetting::setValue('retention_days_metadata', (int) config('services.telegram.retention_days_metadata', 30), 'int', $admin->id);
        BotSetting::setValue('maintenance_mode', false, 'bool', $admin->id);
    }
}
