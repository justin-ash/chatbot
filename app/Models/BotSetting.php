<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BotSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'updated_by_admin_id',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        try {
            $cached = Cache::remember("bot_setting:{$key}", 60, function () use ($key) {
                if (!Schema::hasTable('bot_settings')) {
                    return null;
                }
                return self::query()->where('key', $key)->first();
            });

            if (!$cached) {
                return $default;
            }

            return match ($cached->type) {
                'int' => (int) $cached->value,
                'bool' => filter_var($cached->value, FILTER_VALIDATE_BOOLEAN),
                'json' => json_decode((string) $cached->value, true) ?? $default,
                default => (string) $cached->value,
            };
        } catch (Throwable) {
            return $default;
        }
    }

    public static function setValue(string $key, mixed $value, string $type = 'string', ?int $adminId = null): self
    {
        $storedValue = match ($type) {
            'bool' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };

        $setting = self::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'type' => $type,
                'updated_by_admin_id' => $adminId,
            ]
        );

        Cache::forget("bot_setting:{$key}");

        return $setting;
    }
}
