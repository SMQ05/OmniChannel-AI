<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Appointment;
use App\Queue\Attributes\Backoff;
use App\Queue\Attributes\Tries;
use App\Queue\Concerns\InteractsWithQueueAttributes;
use App\Services\Appointments\AppointmentSyncService;
use App\Services\Queue\QueueRouteResolver;
use App\Services\Queue\WorkerHeartbeatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Syncs a single Appointment to all enabled third-party integrations.
 *
 * Routed to the `integrations` Redis queue via AppServiceProvider::boot().
 *
 * Integrations attempted (independently):
 *  - Google Calendar (if business.integration_config.google_calendar.enabled)
 *  - Google Sheets   (if business.integration_config.google_sheets.enabled)
 *
 * Secret material for these integrations lives in businesses.integration_secrets,
 * not in the legacy integration_config JSON preferences blob.
 *
 * Graceful degradation contract:
 *  Both services are always attempted in independent try/catch blocks.
 *  A Calendar failure does NOT prevent the Sheets sync from running.
 *  If EITHER service fails, the failure is recorded, all results are
 *  logged, and a single \RuntimeException is re-thrown at the end so
 *  the queue worker triggers a retry via #[Backoff(120)].
 *
 * Idempotency:
 *  - synced_to_calendar and synced_to_sheets are set to true immediately
 *    on success, before any other service is attempted.
 *  - On retry, already-successful services are skipped, so a partial
 *    failure on attempt N only re-runs the services that actually failed.
 *  - For Google Calendar: appointments.notes stores the google_event_id
 *    in a structured prefix ("geid:{id}|{rest}") to support updateEvent
 *    and deleteEvent on subsequent attempts without a separate column.
 *
 * Intent routing:
 *  - 'confirmed'  → createEvent / appendRow  (on first sync)
 *  - 'confirmed'  → updateEvent / updateRow  (on re-sync when already synced)
 *  - 'cancelled'  → deleteEvent / updateRow
 *  - 'rescheduled' is handled by dispatching two jobs from the orchestrator:
 *    one for the cancelled old appointment and one for the new confirmed one.
 */
#[Tries(5)]
#[Backoff(120)]
class SyncAppointmentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use InteractsWithQueueAttributes;

    /**
     * @param  Appointment  $appointment  The appointment to sync (Eloquent model — serialised safely)
     */
    public function __construct(
        private readonly Appointment $appointment,
    ) {
        $this->initQueueAttributes();
        app(QueueRouteResolver::class)->apply($this, 'integrations');
    }

    /**
     * Execute the sync job.
     *
     * Loads the business and evaluates each enabled integration independently.
     * Failures are collected and a single exception is re-thrown at the end
     * to trigger the #[Backoff(120)] retry cycle without preventing other
     * integrations from running in the same attempt.
     */
    public function handle(
        AppointmentSyncService $appointmentSyncService,
        WorkerHeartbeatService $workerHeartbeatService,
    ): void {
        $workerHeartbeatService->beat(
            queueConnection: $this->connection ?: (string) config('queue.default'),
            queueName: $this->queue ?: 'default',
            meta: ['job' => self::class, 'appointment_id' => $this->appointment->id],
        );

        // Reload the appointment with fresh data in case it was updated
        // after the job was dispatched (e.g. status change on reschedule).
        $appointment = $this->appointment->fresh(['business', 'patient', 'provider']);

        if ($appointment === null) {
            Log::warning('SyncAppointmentJob: appointment no longer exists, skipping.', [
                'appointment_id' => $this->appointment->id,
            ]);

            return;
        }

        $appointmentSyncService->sync($appointment);

        Log::info('SyncAppointmentJob: sync completed.', [
            'appointment_id' => $appointment->id,
        ]);
    }
}
