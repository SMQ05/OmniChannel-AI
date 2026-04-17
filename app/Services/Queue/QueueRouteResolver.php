<?php

declare(strict_types=1);

namespace App\Services\Queue;

final class QueueRouteResolver
{
    /**
     * @return array{connection: string, queue: string}
     */
    public function route(string $purpose): array
    {
        $route = config("kynex.queues.named.{$purpose}");

        return [
            'connection' => (string) ($route['connection'] ?? config('queue.default')),
            'queue' => (string) ($route['queue'] ?? 'default'),
        ];
    }

    public function apply(object $job, string $purpose): void
    {
        $route = $this->route($purpose);

        if (method_exists($job, 'onConnection')) {
            $job->onConnection($route['connection']);
        }

        if (method_exists($job, 'onQueue')) {
            $job->onQueue($route['queue']);
        }
    }
}
