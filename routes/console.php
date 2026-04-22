<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Schedule
|--------------------------------------------------------------------------
|
| This file defines all scheduled Artisan commands for the application.
| The scheduler can be run as a dedicated long-lived worker:
|
|   php artisan schedule:work
|
*/

/**
 * Reminder evaluation — runs every 5 minutes.
 *
 * Queries all confirmed upcoming appointments and dispatches SendReminderJob
 * for every (appointment × offset_hours) pair that is due and unsent.
 * The command is fast (DB read + dispatch) so a 5-minute cadence is safe.
 *
 * withoutOverlapping(5) prevents a second run from starting if the first
 * is still chunking through a very large appointments table.
 */
Schedule::command('reminders:send')
    ->everyFiveMinutes()
    ->withoutOverlapping(5)
    ->runInBackground()
    ->onFailure(function (): void {
        \Illuminate\Support\Facades\Log::error(
            'Scheduled command reminders:send failed.',
        );
    });

Schedule::call(function (): void {
    \Illuminate\Support\Facades\Cache::put(
        'scheduler:heartbeat:reminders',
        now()->toISOString(),
        now()->addMinutes(10),
    );
})->everyMinute()->name('scheduler:heartbeat:reminders');

Schedule::command('billing:run-cycles')
    ->everyFifteenMinutes()
    ->withoutOverlapping(15)
    ->runInBackground()
    ->onFailure(function (): void {
        \Illuminate\Support\Facades\Log::error(
            'Scheduled command billing:run-cycles failed.',
        );
    });

Schedule::call(function (): void {
    \Illuminate\Support\Facades\Cache::put(
        'scheduler:heartbeat:billing',
        now()->toISOString(),
        now()->addMinutes(20),
    );
})->everyFiveMinutes()->name('scheduler:heartbeat:billing');

Schedule::call(function (): void {
    \App\Jobs\CaptureMonitoringSnapshotJob::dispatch();
})->hourly()->name('monitoring:snapshot:platform');

Schedule::command('data-controls:enforce-retention --dry-run')
    ->dailyAt('02:00')
    ->withoutOverlapping(120)
    ->runInBackground()
    ->onFailure(function (): void {
        \Illuminate\Support\Facades\Log::error(
            'Scheduled command data-controls:enforce-retention --dry-run failed.',
        );
    });
