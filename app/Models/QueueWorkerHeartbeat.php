<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueWorkerHeartbeat extends Model
{
    protected $table = 'queue_worker_heartbeats';

    protected $fillable = [
        'worker_name',
        'queue_connection',
        'queue_name',
        'host_name',
        'process_id',
        'last_seen_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
