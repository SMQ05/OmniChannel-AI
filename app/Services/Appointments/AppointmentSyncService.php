<?php

declare(strict_types=1);

namespace App\Services\Appointments;

use App\Models\Appointment;
use App\Services\Google\GoogleCalendarService;
use App\Services\Google\GoogleSheetsService;
use Illuminate\Support\Facades\Log;

class AppointmentSyncService
{
    private const GEID_PREFIX = 'geid:';

    public function __construct(
        private readonly GoogleCalendarService $calendar,
        private readonly GoogleSheetsService $sheets,
    ) {
    }

    public function sync(Appointment $appointment): void
    {
        $appointment = $appointment->fresh(['business', 'patient', 'provider', 'service']);

        if ($appointment === null) {
            return;
        }

        $business = $appointment->business;
        $failures = [];

        if ($business->isCalendarEnabled() && !$appointment->synced_to_calendar) {
            try {
                $this->syncToCalendar($appointment);
                $appointment->synced_to_calendar = true;
                $appointment->saveQuietly();
            } catch (\Throwable $exception) {
                Log::error('AppointmentSyncService: calendar sync failed.', [
                    'appointment_id' => $appointment->id,
                    'error' => $exception->getMessage(),
                ]);

                $failures[] = 'google_calendar';
            }
        } elseif ($business->isCalendarEnabled() && $appointment->synced_to_calendar) {
            try {
                $this->updateCalendar($appointment);
            } catch (\Throwable $exception) {
                Log::error('AppointmentSyncService: calendar update failed.', [
                    'appointment_id' => $appointment->id,
                    'error' => $exception->getMessage(),
                ]);

                $failures[] = 'google_calendar_update';
            }
        }

        if ($business->isSheetsEnabled() && !$appointment->synced_to_sheets) {
            try {
                $this->sheets->appendRow($business, $appointment);
                $appointment->synced_to_sheets = true;
                $appointment->saveQuietly();
            } catch (\Throwable $exception) {
                Log::error('AppointmentSyncService: sheets sync failed.', [
                    'appointment_id' => $appointment->id,
                    'error' => $exception->getMessage(),
                ]);

                $failures[] = 'google_sheets';
            }
        } elseif ($business->isSheetsEnabled() && $appointment->synced_to_sheets) {
            try {
                $this->sheets->updateRow($business, $appointment);
            } catch (\Throwable $exception) {
                Log::error('AppointmentSyncService: sheets update failed.', [
                    'appointment_id' => $appointment->id,
                    'error' => $exception->getMessage(),
                ]);

                $failures[] = 'google_sheets_update';
            }
        }

        if ($failures !== []) {
            throw new \RuntimeException(
                'AppointmentSyncService: integration sync failed for ' . implode(', ', $failures),
            );
        }
    }

    private function syncToCalendar(Appointment $appointment): void
    {
        if ($appointment->status === 'cancelled') {
            return;
        }

        $eventId = $this->calendar->createEvent($appointment->business, $appointment);
        $existingNotes = $this->stripGeid($appointment->notes ?? '');
        $appointment->notes = self::GEID_PREFIX . $eventId . ($existingNotes !== '' ? '|' . $existingNotes : '');
        $appointment->saveQuietly();
    }

    private function updateCalendar(Appointment $appointment): void
    {
        $eventId = $this->extractGeid($appointment->notes ?? '');

        if ($eventId === '') {
            Log::warning('AppointmentSyncService: missing Google event id, skipping calendar update.', [
                'appointment_id' => $appointment->id,
            ]);

            return;
        }

        if ($appointment->status === 'cancelled') {
            $this->calendar->deleteEvent($appointment->business, $eventId);

            return;
        }

        $this->calendar->updateEvent($appointment->business, $appointment, $eventId);
    }

    private function extractGeid(string $notes): string
    {
        if (!str_starts_with($notes, self::GEID_PREFIX)) {
            return '';
        }

        $withoutPrefix = substr($notes, strlen(self::GEID_PREFIX));
        $pipePos = strpos($withoutPrefix, '|');

        return $pipePos !== false
            ? substr($withoutPrefix, 0, $pipePos)
            : $withoutPrefix;
    }

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
