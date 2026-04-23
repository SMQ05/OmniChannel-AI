<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\QueueWorkerHeartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SmokeCheckCommand extends Command
{
    protected $signature = 'ops:smoke
        {--url= : Optional live URL to verify, e.g. https://app.example.com/api/health}
        {--strict-runtime : Fail when worker or scheduler heartbeats are missing or stale}
        {--max-worker-heartbeat-minutes=10 : Maximum acceptable age for the latest worker heartbeat in strict mode}
        {--max-scheduler-heartbeat-minutes=10 : Maximum acceptable age for scheduler heartbeats in strict mode}
        {--max-failed-jobs=0 : Maximum allowed failed jobs count in strict mode}';

    protected $description = 'Run deployment smoke checks for DB, cache, queue tables, scheduler registration, and optional live health endpoint.';

    public function handle(): int
    {
        $checks = [
            'database' => $this->databaseCheck(),
            'cache' => $this->cacheCheck(),
            'queue_tables' => $this->queueTablesCheck(),
            'scheduler' => $this->schedulerCheck(),
        ];

        if ((bool) $this->option('strict-runtime')) {
            $checks['worker_heartbeat'] = $this->workerHeartbeatCheck((int) $this->option('max-worker-heartbeat-minutes'));
            $checks['scheduler_heartbeats'] = $this->schedulerHeartbeatCheck((int) $this->option('max-scheduler-heartbeat-minutes'));
            $checks['failed_jobs'] = $this->failedJobsCheck((int) $this->option('max-failed-jobs'));
        }

        $url = (string) $this->option('url');

        if ($url !== '') {
            $checks['live_health'] = $this->liveHealthCheck($url);
        }

        foreach ($checks as $name => $check) {
            $this->line(sprintf(
                '[%s] %s - %s',
                $check['ok'] ? 'OK' : 'FAIL',
                $name,
                $check['detail'],
            ));
        }

        $failed = collect($checks)->contains(fn (array $check): bool => $check['ok'] !== true);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function databaseCheck(): array
    {
        try {
            DB::select('select 1');

            return ['ok' => true, 'detail' => 'database connected'];
        } catch (Throwable $exception) {
            return ['ok' => false, 'detail' => $exception->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function cacheCheck(): array
    {
        try {
            Cache::put('ops:smoke', 'ok', now()->addMinute());

            return [
                'ok' => Cache::get('ops:smoke') === 'ok',
                'detail' => 'cache write/read ' . (Cache::get('ops:smoke') === 'ok' ? 'ok' : 'failed'),
            ];
        } catch (Throwable $exception) {
            return ['ok' => false, 'detail' => $exception->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function queueTablesCheck(): array
    {
        if (config('queue.default') !== 'database') {
            return ['ok' => true, 'detail' => 'database queue not enabled'];
        }

        $required = ['jobs', 'failed_jobs', 'job_batches'];
        $missing = array_values(array_filter($required, static fn (string $table): bool => !Schema::hasTable($table)));

        return empty($missing)
            ? ['ok' => true, 'detail' => 'jobs, failed_jobs, and job_batches present']
            : ['ok' => false, 'detail' => 'missing tables: ' . implode(', ', $missing)];
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function schedulerCheck(): array
    {
        try {
            $exitCode = Artisan::call('schedule:list');
            $output = Artisan::output();

            return [
                'ok' => $exitCode === 0 && str_contains($output, 'reminders:send'),
                'detail' => $exitCode === 0 ? 'schedule:list includes reminders:send' : 'schedule:list failed',
            ];
        } catch (Throwable $exception) {
            return ['ok' => false, 'detail' => $exception->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function liveHealthCheck(string $url): array
    {
        try {
            $response = Http::timeout(15)->get($url);

            return [
                'ok' => $response->successful(),
                'detail' => sprintf('HTTP %d', $response->status()),
            ];
        } catch (Throwable $exception) {
            return ['ok' => false, 'detail' => $exception->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function workerHeartbeatCheck(int $maxAgeMinutes): array
    {
        if (!Schema::hasTable('queue_worker_heartbeats')) {
            return ['ok' => false, 'detail' => 'queue_worker_heartbeats table missing'];
        }

        $heartbeat = QueueWorkerHeartbeat::query()->latest('last_seen_at')->first();

        if ($heartbeat === null || $heartbeat->last_seen_at === null) {
            return ['ok' => false, 'detail' => 'worker heartbeat missing'];
        }

        $ageMinutes = CarbonImmutable::parse($heartbeat->last_seen_at)->diffInMinutes(CarbonImmutable::now());

        return $ageMinutes <= $maxAgeMinutes
            ? ['ok' => true, 'detail' => sprintf('worker heartbeat age %d minute(s)', $ageMinutes)]
            : ['ok' => false, 'detail' => sprintf('worker heartbeat stale at %d minute(s)', $ageMinutes)];
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function schedulerHeartbeatCheck(int $maxAgeMinutes): array
    {
        $heartbeatKeys = [
            'reminders' => Cache::get('scheduler:heartbeat:reminders'),
            'billing' => Cache::get('scheduler:heartbeat:billing'),
        ];

        $stale = [];

        foreach ($heartbeatKeys as $name => $value) {
            if (!is_string($value) || trim($value) === '') {
                $stale[] = sprintf('%s missing', $name);

                continue;
            }

            $ageMinutes = CarbonImmutable::parse($value)->diffInMinutes(CarbonImmutable::now());

            if ($ageMinutes > $maxAgeMinutes) {
                $stale[] = sprintf('%s stale at %d minute(s)', $name, $ageMinutes);
            }
        }

        return $stale === []
            ? ['ok' => true, 'detail' => 'reminders and billing scheduler heartbeats are current']
            : ['ok' => false, 'detail' => implode('; ', $stale)];
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function failedJobsCheck(int $maxFailedJobs): array
    {
        if (!Schema::hasTable('failed_jobs')) {
            return ['ok' => false, 'detail' => 'failed_jobs table missing'];
        }

        $count = DB::table('failed_jobs')->count();

        return $count <= $maxFailedJobs
            ? ['ok' => true, 'detail' => sprintf('%d failed job(s)', $count)]
            : ['ok' => false, 'detail' => sprintf('%d failed job(s) exceeds limit %d', $count, $maxFailedJobs)];
    }
}
