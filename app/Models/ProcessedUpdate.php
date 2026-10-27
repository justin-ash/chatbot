<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedUpdate extends Model
{
    protected $fillable = [
        'update_id',
        'update_type',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'update_id' => 'integer',
            'processed_at' => 'datetime',
        ];
    }
}
