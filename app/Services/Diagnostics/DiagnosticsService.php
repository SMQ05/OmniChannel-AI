<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\InboundWebhook;
use App\Models\OutboundMessageAttempt;
use App\Models\QueueWorkerHeartbeat;
use App\Services\Usage\UsageSummaryService;
use App\Services\Voice\VoiceConfigurationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DiagnosticsService
{
    public function __construct(
        private readonly StartupCheckService $startupCheckService,
        private readonly UsageSummaryService $usageSummaryService,
        private readonly VoiceConfigurationService $voiceConfigurationService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forBusiness(Business $business): array
    {
        $queueConnection = (string) config('queue.default');
        $queueName = (string) config('kynex.queues.named.webhooks.queue', 'webhooks');
        $workerHeartbeat = QueueWorkerHeartbeat::query()->latest('last_seen_at')->first();
        $latestSync = Appointment::query()->latest('updated_at')->first();
        $latestInbound = InboundWebhook::query()
            ->where('business_id', $business->id)
            ->latest('created_at')
            ->first();
        $usageSummary = $this->usageSummaryService->forBusiness($business);
        $voiceSummary = $this->voiceConfigurationService->forBusiness($business);

        return [
            'webhooks' => [
                'whatsapp' => route('webhook.whatsapp.receive', ['slug' => $business->slug]),
                'messenger' => route('webhook.messenger.receive', ['slug' => $business->slug]),
            ],
            'queue' => [
                'connection' => $queueConnection,
                'queue_name' => $queueName,
                'status' => $this->queueStatus($queueConnection, $queueName),
                'depths' => $this->queueDepths(),
                'worker_last_heartbeat' => $workerHeartbeat?->last_seen_at,
                'failed_jobs_count' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
                'recent_failed_jobs' => Schema::hasTable('failed_jobs')
                    ? DB::table('failed_jobs')->latest('failed_at')->limit(10)->get()
                    : collect(),
            ],
            'redis' => $this->redisStatus(),
            'database_queue' => $this->databaseQueueStatus(),
            'scheduler' => [
                'last_seen' => Cache::get('scheduler:heartbeat:reminders'),
            ],
            'latest_sync' => $latestSync,
            'latest_inbound' => $latestInbound,
            'latest_outbound_failures' => OutboundMessageAttempt::query()
                ->where('business_id', $business->id)
                ->where('status', 'failed')
                ->latest('updated_at')
                ->limit(5)
                ->get(),
            'usage' => [
                'plan' => $usageSummary['plan'],
                'metrics' => $usageSummary['metrics'],
            ],
            'voice' => [
                'ready' => $voiceSummary['ready'],
                'issues' => $voiceSummary['issues'],
                'summary' => $voiceSummary['summary'],
                'platform' => $voiceSummary['platform'],
            ],
            'version' => [
                'app_version' => config('kynex.app_version'),
                'git_commit' => config('kynex.git_commit'),
            ],
            'issues' => array_merge(
                $this->startupCheckService->globalIssues(),
                $this->startupCheckService->businessIssues($business),
            ),
        ];
    }

    /**
     * @return array{enabled: bool, ok: bool, detail: string}
     */
    private function redisStatus(): array
    {
        $enabled = config('queue.default') === 'redis' || config('cache.default') === 'redis';

        if (!$enabled) {
            return ['enabled' => false, 'ok' => true, 'detail' => 'Redis not enabled for queue/cache.'];
        }

        try {
            $reply = Redis::connection()->ping();

            return ['enabled' => true, 'ok' => true, 'detail' => (string) $reply];
        } catch (Throwable $exception) {
            return ['enabled' => true, 'ok' => false, 'detail' => $exception->getMessage()];
        }
    }

    /**
     * @return array{enabled: bool, ok: bool, detail: string}
     */
    private function databaseQueueStatus(): array
    {
        if (config('queue.default') !== 'database') {
            return ['enabled' => false, 'ok' => true, 'detail' => 'Database queue not enabled.'];
        }

        if (!Schema::hasTable('jobs')) {
            return ['enabled' => true, 'ok' => false, 'detail' => 'jobs table missing'];
        }

        return [
            'enabled' => true,
            'ok' => true,
            'detail' => 'Pending jobs: ' . DB::table('jobs')->count(),
        ];
    }

    /**
     * @return array<string, int|null>
     */
    private function queueDepths(): array
    {
        $queues = collect(config('kynex.queues.named', []))
            ->mapWithKeys(static fn (array $route, string $name): array => [$name => $route['queue'] ?? $name])
            ->all();

        if ($queues === []) {
            return [];
        }

        if (config('queue.default') === 'database' && Schema::hasTable('jobs')) {
            return collect($queues)
                ->mapWithKeys(static fn (string $queue, string $name): array => [
                    $name => DB::table('jobs')->where('queue', $queue)->count(),
                ])
                ->all();
        }

        if (config('queue.default') === 'redis') {
            return collect($queues)
                ->mapWithKeys(function (string $queue, string $name): array {
                    try {
                        return [$name => (int) Redis::llen("queues:{$queue}")];
                    } catch (Throwable) {
                        return [$name => null];
                    }
                })
                ->all();
        }

        return collect($queues)->mapWithKeys(static fn (string $queue, string $name): array => [$name => null])->all();
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function queueStatus(string $queueConnection, string $queueName): array
    {
        if ($queueConnection === 'database') {
            return $this->databaseQueueStatus();
        }

        if ($queueConnection === 'redis') {
            return $this->redisStatus();
        }

        return [
            'ok' => true,
            'detail' => sprintf('Queue connection "%s" configured for queue "%s".', $queueConnection, $queueName),
        ];
    }
}
