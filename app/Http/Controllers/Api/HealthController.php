<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'app' => ['ok' => true, 'detail' => 'ok'],
            'database' => $this->databaseCheck(),
            'queue_tables' => $this->queueTableCheck(),
            'cache' => $this->cacheCheck(),
        ];

        $ok = collect($checks)->every(fn (array $check): bool => $check['ok'] === true);

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'timestamp' => now()->toISOString(),
            'app_version' => config('kynex.app_version'),
            'git_commit' => config('kynex.git_commit'),
            'checks' => $checks,
        ], $ok ? 200 : 503);
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function databaseCheck(): array
    {
        try {
            DB::select('select 1');

            return ['ok' => true, 'detail' => 'connected'];
        } catch (Throwable $exception) {
            return ['ok' => false, 'detail' => $exception->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function queueTableCheck(): array
    {
        if (config('queue.default') !== 'database') {
            return ['ok' => true, 'detail' => 'database queue not enabled'];
        }

        $required = ['jobs', 'failed_jobs', 'job_batches'];
        $missing = array_values(array_filter($required, static fn (string $table): bool => !Schema::hasTable($table)));

        return empty($missing)
            ? ['ok' => true, 'detail' => 'queue tables present']
            : ['ok' => false, 'detail' => 'missing: ' . implode(', ', $missing)];
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function cacheCheck(): array
    {
        try {
            Cache::put('health:ping', 'ok', now()->addMinute());
            $value = Cache::get('health:ping');

            return ['ok' => $value === 'ok', 'detail' => $value === 'ok' ? 'cache write/read ok' : 'cache mismatch'];
        } catch (Throwable $exception) {
            return ['ok' => false, 'detail' => $exception->getMessage()];
        }
    }
}
