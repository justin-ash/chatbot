<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_admin_id',
        'actor_telegram_id',
        'action',
        'target_type',
        'target_id',
        'reason',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'actor_admin_id' => 'integer',
            'actor_telegram_id' => 'integer',
            'target_id' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function record(
        string $action,
        string $targetType,
        ?int $targetId,
        string $reason,
        array $metadata = [],
        ?int $actorAdminId = null,
        ?int $actorTelegramId = null
    ): self {
        return self::create([
            'actor_admin_id' => $actorAdminId,
            'actor_telegram_id' => $actorTelegramId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reason' => $reason,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
