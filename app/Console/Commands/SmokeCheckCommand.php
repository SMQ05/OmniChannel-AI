<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SmokeCheckCommand extends Command
{
    protected $signature = 'ops:smoke {--url= : Optional live URL to verify, e.g. https://app.example.com/api/health}';

    protected $description = 'Run deployment smoke checks for DB, cache, queue tables, scheduler registration, and optional live health endpoint.';

    public function handle(): int
    {
        $checks = [
            'database' => $this->databaseCheck(),
            'cache' => $this->cacheCheck(),
            'queue_tables' => $this->queueTablesCheck(),
            'scheduler' => $this->schedulerCheck(),
        ];

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
}
