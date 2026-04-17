<?php

declare(strict_types=1);

namespace App\Services\Queue;

use App\Models\QueueWorkerHeartbeat;

class WorkerHeartbeatService
{
    public function beat(string $queueConnection, string $queueName, array $meta = []): void
    {
        $workerName = implode(':', [
            gethostname() ?: 'unknown-host',
            $queueConnection,
            $queueName,
            getmypid() ?: '0',
        ]);

        QueueWorkerHeartbeat::query()->updateOrCreate(
            ['worker_name' => $workerName],
            [
                'queue_connection' => $queueConnection,
                'queue_name' => $queueName,
                'host_name' => gethostname() ?: 'unknown-host',
                'process_id' => getmypid(),
                'last_seen_at' => now(),
                'meta' => $meta,
            ],
        );
    }
}
