<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Schedule
|--------------------------------------------------------------------------
|
| This file defines all scheduled Artisan commands for the application.
| The scheduler is run by a single cron entry on the server:
|
|   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
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
