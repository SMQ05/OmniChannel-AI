<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Appointment;
use App\Queue\Attributes\Backoff;
use App\Queue\Attributes\Tries;
use App\Queue\Concerns\InteractsWithQueueAttributes;
use App\Services\Google\GoogleCalendarService;
use App\Services\Google\GoogleSheetsService;
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
     * Prefix used to embed the Google Calendar event ID in appointment notes.
     * Format: "geid:{google_event_id}|{any other notes}"
     */
    private const GEID_PREFIX = 'geid:';

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
        GoogleCalendarService $calendar,
        GoogleSheetsService $sheets,
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

        $business = $appointment->business;

        $failures = [];

        // ------------------------------------------------------------------
        // Google Calendar sync
        // ------------------------------------------------------------------
        if ($business->isCalendarEnabled() && !$appointment->synced_to_calendar) {
            try {
                $this->syncToCalendar($calendar, $business, $appointment);

                $appointment->synced_to_calendar = true;
                $appointment->saveQuietly();

                Log::info('SyncAppointmentJob: calendar sync succeeded.', [
                    'appointment_id' => $appointment->id,
                ]);
            } catch (\Throwable $e) {
                Log::error('SyncAppointmentJob: calendar sync failed.', [
                    'appointment_id' => $appointment->id,
                    'error'          => $e->getMessage(),
                ]);

                $failures[] = 'google_calendar';
            }
        } elseif ($business->isCalendarEnabled() && $appointment->synced_to_calendar) {
            // Already synced — run an update to reflect any status/time changes
            try {
                $this->updateCalendar($calendar, $business, $appointment);

                Log::info('SyncAppointmentJob: calendar update succeeded.', [
                    'appointment_id' => $appointment->id,
                ]);
            } catch (\Throwable $e) {
                Log::error('SyncAppointmentJob: calendar update failed.', [
                    'appointment_id' => $appointment->id,
                    'error'          => $e->getMessage(),
                ]);

                $failures[] = 'google_calendar_update';
            }
        }

        // ------------------------------------------------------------------
        // Google Sheets sync
        // ------------------------------------------------------------------
        if ($business->isSheetsEnabled() && !$appointment->synced_to_sheets) {
            try {
                $sheets->appendRow($business, $appointment);

                $appointment->synced_to_sheets = true;
                $appointment->saveQuietly();

                Log::info('SyncAppointmentJob: sheets sync succeeded.', [
                    'appointment_id' => $appointment->id,
                ]);
            } catch (\Throwable $e) {
                Log::error('SyncAppointmentJob: sheets sync failed.', [
                    'appointment_id' => $appointment->id,
                    'error'          => $e->getMessage(),
                ]);

                $failures[] = 'google_sheets';
            }
        } elseif ($business->isSheetsEnabled() && $appointment->synced_to_sheets) {
            // Already synced — update the row to reflect current status
            try {
                $sheets->updateRow($business, $appointment);

                Log::info('SyncAppointmentJob: sheets update succeeded.', [
                    'appointment_id' => $appointment->id,
                ]);
            } catch (\Throwable $e) {
                Log::error('SyncAppointmentJob: sheets update failed.', [
                    'appointment_id' => $appointment->id,
                    'error'          => $e->getMessage(),
                ]);

                $failures[] = 'google_sheets_update';
            }
        }

        // ------------------------------------------------------------------
        // Re-throw if any service failed so the queue worker retries
        // ------------------------------------------------------------------
        if (!empty($failures)) {
            throw new \RuntimeException(
                'SyncAppointmentJob: the following integrations failed and will be retried: '
                . implode(', ', $failures)
                . ' — appointment #' . $appointment->id,
            );
        }
    }

    // -------------------------------------------------------------------------
    // Calendar routing helpers
    // -------------------------------------------------------------------------

    /**
     * Route a first-time calendar sync: createEvent for confirmed,
     * deleteEvent for cancelled.
     *
     * The Google event ID is embedded in appointment.notes using the
     * "geid:{id}|" prefix so we can retrieve it for updates/deletes
     * without adding a dedicated database column.
     *
     * @param  GoogleCalendarService               $calendar
     * @param  \App\Models\Business                $business
     * @param  Appointment                         $appointment
     *
     * @throws \RuntimeException Propagated from GoogleCalendarService
     */
    private function syncToCalendar(
        GoogleCalendarService $calendar,
        \App\Models\Business $business,
        Appointment $appointment,
    ): void {
        if ($appointment->status === 'cancelled') {
            // Nothing to create for a new cancelled appointment
            return;
        }

        $googleEventId = $calendar->createEvent($business, $appointment);

        // Embed the event ID in notes using our prefix convention
        $existingNotes = $this->stripGeid($appointment->notes ?? '');
        $appointment->notes = self::GEID_PREFIX . $googleEventId
            . ($existingNotes !== '' ? '|' . $existingNotes : '');

        $appointment->saveQuietly();
    }

    /**
     * Route a re-sync: updateEvent for active, deleteEvent for cancelled.
     *
     * @param  GoogleCalendarService               $calendar
     * @param  \App\Models\Business                $business
     * @param  Appointment                         $appointment
     *
     * @throws \RuntimeException Propagated from GoogleCalendarService
     */
    private function updateCalendar(
        GoogleCalendarService $calendar,
        \App\Models\Business $business,
        Appointment $appointment,
    ): void {
        $googleEventId = $this->extractGeid($appointment->notes ?? '');

        if ($googleEventId === '') {
            Log::warning('SyncAppointmentJob: no google_event_id found in notes; skipping calendar update.', [
                'appointment_id' => $appointment->id,
            ]);

            return;
        }

        if ($appointment->status === 'cancelled') {
            $calendar->deleteEvent($business, $googleEventId);
        } else {
            $calendar->updateEvent($business, $appointment, $googleEventId);
        }
    }

    // -------------------------------------------------------------------------
    // Notes helpers for event ID embedding
    // -------------------------------------------------------------------------

    /**
     * Extract the Google event ID from the notes field.
     *
     * Returns an empty string when no event ID prefix is found.
     *
     * @param  string  $notes
     */
    private function extractGeid(string $notes): string
    {
        if (!str_starts_with($notes, self::GEID_PREFIX)) {
            return '';
        }

        $withoutPrefix = substr($notes, strlen(self::GEID_PREFIX));
        $pipePos       = strpos($withoutPrefix, '|');

        return $pipePos !== false
            ? substr($withoutPrefix, 0, $pipePos)
            : $withoutPrefix;
    }

    /**
     * Return the notes string with the "geid:{id}|" prefix stripped,
     * leaving only the human-readable notes portion.
     *
     * @param  string  $notes
     */
    private function stripGeid(string $notes): string
    {
        if (!str_starts_with($notes, self::GEID_PREFIX)) {
            return $notes;
        }

        $pipePos = strpos($notes, '|');

        return $pipePos !== false
            ? substr($notes, $pipePos + 1)
            : '';
    }
}
