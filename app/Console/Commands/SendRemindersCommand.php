<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendReminderJob;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Artisan command that evaluates all confirmed upcoming appointments and
 * dispatches SendReminderJob for every reminder rule that is due and
 * has not yet been sent.
 *
 * Scheduled: every 5 minutes via routes/console.php
 *
 * Algorithm:
 *  1. Chunk confirmed future appointments (500 at a time).
 *  2. For each appointment, iterate the business reminder_settings.reminders array.
 *  3. For each rule:
 *     - due_at  = start_time - offset_hours
 *     - if NOW() >= due_at AND reminder_sent_at[offset_hours] IS NULL → dispatch job.
 *  4. Jobs run on the `reminders` queue (routed in AppServiceProvider).
 *
 * The command itself is intentionally thin — it only decides *whether* to
 * dispatch; all rendering and sending logic lives in SendReminderJob.
 */
class SendRemindersCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'reminders:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch reminder jobs for confirmed upcoming appointments that have pending reminders due.';

    /**
     * Number of appointments to load per database chunk to prevent OOM on large tenants.
     */
    private const CHUNK_SIZE = 500;

    /**
     * Execute the console command.
     *
     * Iterates all confirmed upcoming appointments in chunks and dispatches
     * a SendReminderJob for each (appointment, offset_hours) pair that is
     * due but has not been sent.
     *
     * @return int  Command::SUCCESS always — failures are logged inside the jobs.
     */
    public function handle(): int
    {
        $now        = Carbon::now()->utc();
        $dispatched = 0;

        Log::info('SendRemindersCommand: starting reminder evaluation.', [
            'now_utc' => $now->toISOString(),
        ]);

        Appointment::query()
            ->with(['business', 'patient', 'provider'])
            ->where('status', 'confirmed')
            ->where('start_time', '>', $now)
            ->chunkById(self::CHUNK_SIZE, function (iterable $appointments) use ($now, &$dispatched): void {
                foreach ($appointments as $appointment) {
                    $dispatched += $this->processAppointment($appointment, $now);
                }
            });

        Log::info('SendRemindersCommand: evaluation complete.', [
            'dispatched' => $dispatched,
        ]);

        $this->info("Dispatched {$dispatched} reminder job(s).");

        return Command::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Evaluate every reminder rule for a single appointment and dispatch
     * a SendReminderJob for each rule that is due and unsent.
     *
     * @param  Appointment  $appointment
     * @param  Carbon        $now          Current UTC time (passed in for consistency)
     * @return int                         Number of jobs dispatched for this appointment
     */
    private function processAppointment(Appointment $appointment, Carbon $now): int
    {
        $business        = $appointment->business;
        $reminderRules   = $business->reminder_settings['reminders'] ?? [];
        $reminderSentAt  = $appointment->reminder_sent_at ?? [];
        $startTime       = Carbon::parse($appointment->start_time)->utc();
        $dispatched      = 0;

        foreach ($reminderRules as $rule) {
            $offsetHours = (int) ($rule['offset_hours'] ?? 0);

            if ($offsetHours <= 0) {
                continue;
            }

            $key    = (string) $offsetHours;
            $dueAt  = $startTime->copy()->subHours($offsetHours);

            // Only dispatch if the reminder window has opened and it hasn't been sent
            if ($now->lt($dueAt)) {
                continue;
            }

            if (!empty($reminderSentAt[$key])) {
                continue;
            }

            SendReminderJob::dispatch($appointment, $offsetHours);

            $dispatched++;

            Log::info('SendRemindersCommand: dispatched reminder job.', [
                'appointment_id' => $appointment->id,
                'offset_hours'   => $offsetHours,
                'due_at'         => $dueAt->toISOString(),
            ]);
        }

        return $dispatched;
    }
}
