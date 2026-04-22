<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\Business;
use App\Models\DataGovernanceRequest;
use App\Models\MonitoringSnapshot;
use App\Services\Diagnostics\DiagnosticsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class MonitoringSnapshotService
{
    public function __construct(
        private readonly DiagnosticsService $diagnosticsService,
    ) {
    }

    public function latestPlatformSnapshot(): ?MonitoringSnapshot
    {
        return MonitoringSnapshot::query()
            ->where('scope', 'platform')
            ->where('snapshot_type', 'platform_health')
            ->latest('captured_at')
            ->first();
    }

    public function capturePlatformSnapshot(): MonitoringSnapshot
    {
        $totalBusinesses = Business::query()->count();

        $aggregates = [
            'derived_only' => true,
            'runtime_truth_note' => 'Derived control-plane summary only. Runtime, messaging, voice, webhook, and billing truth remain distributed in their own services and tables.',
            'businesses' => [
                'total' => $totalBusinesses,
                'active' => Business::query()->where('is_active', true)->count(),
                'inactive' => Business::query()->where('is_active', false)->count(),
            ],
            'queue' => [
                'default_connection' => (string) config('queue.default'),
                'depths' => $this->queueDepths(),
                'failed_jobs_count' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
            ],
            'governance' => [
                'pending_approval' => DataGovernanceRequest::query()->where('status', DataGovernanceRequest::STATUS_PENDING_APPROVAL)->count(),
                'running' => DataGovernanceRequest::query()->where('status', DataGovernanceRequest::STATUS_RUNNING)->count(),
                'failed' => DataGovernanceRequest::query()->where('status', DataGovernanceRequest::STATUS_FAILED)->count(),
                'expired_artifacts' => DataGovernanceRequest::query()
                    ->whereNotNull('artifact_expires_at')
                    ->where('artifact_expires_at', '<', now())
                    ->count(),
            ],
            'messaging' => [
                'failed_outbound_last_24h' => DB::table('outbound_message_attempts')
                    ->where('status', 'failed')
                    ->where('created_at', '>=', now()->subDay())
                    ->count(),
                'failed_inbound_last_24h' => DB::table('inbound_webhooks')
                    ->where('status', 'failed')
                    ->where('created_at', '>=', now()->subDay())
                    ->count(),
            ],
        ];

        return MonitoringSnapshot::query()->create([
            'scope' => 'platform',
            'business_id' => null,
            'snapshot_type' => 'platform_health',
            'aggregates' => $aggregates,
            'source_context' => [
                'captured_from' => ['businesses', 'inbound_webhooks', 'outbound_message_attempts', 'failed_jobs', 'data_governance_requests'],
                'captured_by' => 'admin_monitoring',
            ],
            'captured_at' => now(),
        ]);
    }

    public function captureTenantSnapshot(Business $business): MonitoringSnapshot
    {
        $diagnostics = $this->diagnosticsService->forBusiness($business);

        $warningUsageMetrics = collect($diagnostics['usage']['metrics'] ?? [])
            ->where('warning', true)
            ->count();

        return MonitoringSnapshot::query()->create([
            'scope' => 'tenant',
            'business_id' => $business->id,
            'snapshot_type' => 'tenant_operability',
            'aggregates' => [
                'derived_only' => true,
                'runtime_truth_note' => 'Tenant operability summary only. Business runtime execution remains in distributed channel, voice, queue, and billing components.',
                'queue' => [
                    'failed_jobs_count' => $diagnostics['queue']['failed_jobs_count'] ?? null,
                    'queue_connection' => $diagnostics['queue']['connection'] ?? null,
                ],
                'channels' => [
                    'total' => collect($diagnostics['channels'] ?? [])->count(),
                    'ready' => collect($diagnostics['channels'] ?? [])->where('ready', true)->count(),
                ],
                'issues' => [
                    'count' => count($diagnostics['issues'] ?? []),
                    'critical_count' => collect($diagnostics['issues'] ?? [])->where('severity', 'critical')->count(),
                ],
                'usage' => [
                    'warning_metric_count' => $warningUsageMetrics,
                ],
                'voice' => [
                    'ready' => (bool) ($diagnostics['voice']['ready'] ?? false),
                    'issue_count' => count($diagnostics['voice']['issues'] ?? []),
                ],
            ],
            'source_context' => [
                'captured_from' => ['diagnostics_service'],
                'captured_by' => 'admin_monitoring',
            ],
            'captured_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, MonitoringSnapshot>
     */
    public function recentTenantSnapshots(int $limit = 50): Collection
    {
        return MonitoringSnapshot::query()
            ->where('scope', 'tenant')
            ->with('business')
            ->latest('captured_at')
            ->limit($limit)
            ->get();
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
            return collect($queues)->mapWithKeys(function (string $queue, string $name): array {
                try {
                    return [$name => (int) Redis::llen("queues:{$queue}")];
                } catch (\Throwable) {
                    return [$name => null];
                }
            })->all();
        }

        return collect($queues)->mapWithKeys(static fn (string $queue, string $name): array => [$name => null])->all();
    }
}
