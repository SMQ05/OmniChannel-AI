<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\QueueWorkerHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SmokeCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_ops_smoke_strict_runtime_fails_when_runtime_heartbeats_are_missing(): void
    {
        $this->artisan('ops:smoke', ['--strict-runtime' => true])
            ->assertExitCode(1);
    }

    public function test_ops_smoke_strict_runtime_passes_with_current_heartbeats_and_no_failed_jobs(): void
    {
        QueueWorkerHeartbeat::query()->create([
            'worker_name' => 'worker-1',
            'queue_connection' => 'database',
            'queue_name' => 'billing',
            'host_name' => 'host-1',
            'process_id' => 1234,
            'last_seen_at' => now()->subMinute(),
            'meta' => [],
        ]);

        Cache::put('scheduler:heartbeat:reminders', now()->subMinute()->toISOString(), now()->addMinutes(10));
        Cache::put('scheduler:heartbeat:billing', now()->subMinutes(2)->toISOString(), now()->addMinutes(10));

        $exitCode = Artisan::call('ops:smoke', [
            '--strict-runtime' => true,
            '--max-worker-heartbeat-minutes' => 10,
            '--max-scheduler-heartbeat-minutes' => 10,
            '--max-failed-jobs' => 0,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
    }

    public function test_ops_smoke_strict_runtime_fails_when_failed_jobs_exceed_limit(): void
    {
        QueueWorkerHeartbeat::query()->create([
            'worker_name' => 'worker-1',
            'queue_connection' => 'database',
            'queue_name' => 'billing',
            'host_name' => 'host-1',
            'process_id' => 1234,
            'last_seen_at' => now()->subMinute(),
            'meta' => [],
        ]);

        Cache::put('scheduler:heartbeat:reminders', now()->subMinute()->toISOString(), now()->addMinutes(10));
        Cache::put('scheduler:heartbeat:billing', now()->subMinute()->toISOString(), now()->addMinutes(10));

        DB::table('failed_jobs')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'database',
            'queue' => 'billing',
            'payload' => '{"displayName":"TestJob"}',
            'exception' => 'RuntimeException: test failure',
            'failed_at' => now(),
        ]);

        $this->artisan('ops:smoke', [
            '--strict-runtime' => true,
            '--max-failed-jobs' => 0,
        ])->assertExitCode(1);
    }
}
