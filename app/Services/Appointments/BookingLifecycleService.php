<?php

declare(strict_types=1);

namespace App\Services\Appointments;

use App\Models\Appointment;
use App\Models\BookingAction;
use App\Models\Business;
use App\Models\BusinessService;
use App\Models\Patient;
use App\Models\Provider;
use App\Services\Audit\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookingLifecycleService
{
    public function __construct(
        private readonly AppointmentSyncService $appointmentSyncService,
        private readonly AppointmentNotificationService $appointmentNotificationService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function book(
        Business $business,
        Patient $patient,
        Provider $provider,
        ?BusinessService $service,
        Carbon $startUtc,
        Carbon $endUtc,
        string $sourceChannel,
        string $idempotencyKey,
        array $context = [],
    ): array {
        return $this->runAction(
            business: $business,
            patient: $patient,
            action: 'create',
            sourceChannel: $sourceChannel,
            idempotencyKey: $idempotencyKey,
            requestPayload: array_merge($context, [
                'provider_id' => $provider->id,
                'service_id' => $service?->id,
                'start_time' => $startUtc->toISOString(),
                'end_time' => $endUtc->toISOString(),
            ]),
            callback: function (BookingAction $bookingAction) use ($business, $patient, $provider, $service, $startUtc, $endUtc, $sourceChannel): array {
                $appointment = DB::transaction(function () use ($business, $patient, $provider, $service, $startUtc, $endUtc, $sourceChannel): Appointment {
                    $conflict = Appointment::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
                        ->where('business_id', $business->id)
                        ->where('provider_id', $provider->id)
                        ->whereIn('status', ['pending', 'confirmed'])
                        ->where('start_time', '<', $endUtc)
                        ->where('end_time', '>', $startUtc)
                        ->lockForUpdate()
                        ->exists();

                    if ($conflict) {
                        throw new \RuntimeException('slot_taken');
                    }

                    return Appointment::query()->create([
                        'business_id' => $business->id,
                        'provider_id' => $provider->id,
                        'patient_id' => $patient->id,
                        'service_id' => $service?->id,
                        'service_type' => $service?->name ?? 'Appointment',
                        'start_time' => $startUtc,
                        'end_time' => $endUtc,
                        'status' => 'confirmed',
                        'booked_via' => $sourceChannel,
                    ]);
                });

                $this->appointmentSyncService->sync($appointment);
                $this->appointmentNotificationService->sendLifecycleEmail($appointment, 'created');
                $this->auditLogger->log(
                    actor: null,
                    action: 'appointment.booked',
                    subjectType: Appointment::class,
                    subjectId: $appointment->id,
                    payload: [
                        'channel' => $sourceChannel,
                        'patient_id' => $patient->id,
                        'provider_id' => $provider->id,
                    ],
                    request: null,
                    businessId: $business->id,
                );

                $bookingAction->appointment_id = $appointment->id;
                $bookingAction->patient_id = $patient->id;
                $bookingAction->saveQuietly();

                return $this->resultPayload($appointment, 'created');
            },
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function cancel(
        Business $business,
        Appointment $appointment,
        string $sourceChannel,
        string $idempotencyKey,
        array $context = [],
    ): array {
        return $this->runAction(
            business: $business,
            patient: $appointment->patient,
            action: 'cancel',
            sourceChannel: $sourceChannel,
            idempotencyKey: $idempotencyKey,
            requestPayload: array_merge($context, [
                'appointment_id' => $appointment->id,
            ]),
            callback: function (BookingAction $bookingAction) use ($business, $appointment, $sourceChannel): array {
                $appointment->forceFill([
                    'status' => 'cancelled',
                    'reminder_sent_at' => null,
                ])->save();

                $this->appointmentSyncService->sync($appointment);
                $this->appointmentNotificationService->sendLifecycleEmail($appointment, 'cancelled');
                $this->auditLogger->log(
                    actor: null,
                    action: 'appointment.cancelled',
                    subjectType: Appointment::class,
                    subjectId: $appointment->id,
                    payload: ['channel' => $sourceChannel],
                    request: null,
                    businessId: $business->id,
                );

                $bookingAction->appointment_id = $appointment->id;
                $bookingAction->patient_id = $appointment->patient_id;
                $bookingAction->saveQuietly();

                return $this->resultPayload($appointment->fresh(), 'cancelled');
            },
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function reschedule(
        Business $business,
        Appointment $currentAppointment,
        Provider $provider,
        ?BusinessService $service,
        Carbon $newStartUtc,
        Carbon $newEndUtc,
        string $sourceChannel,
        string $idempotencyKey,
        array $context = [],
    ): array {
        return $this->runAction(
            business: $business,
            patient: $currentAppointment->patient,
            action: 'reschedule',
            sourceChannel: $sourceChannel,
            idempotencyKey: $idempotencyKey,
            requestPayload: array_merge($context, [
                'appointment_id' => $currentAppointment->id,
                'provider_id' => $provider->id,
                'service_id' => $service?->id,
                'start_time' => $newStartUtc->toISOString(),
                'end_time' => $newEndUtc->toISOString(),
            ]),
            callback: function (BookingAction $bookingAction) use ($business, $currentAppointment, $provider, $service, $newStartUtc, $newEndUtc, $sourceChannel): array {
                [$oldAppointment, $newAppointment] = DB::transaction(function () use ($business, $currentAppointment, $provider, $service, $newStartUtc, $newEndUtc, $sourceChannel): array {
                    $current = Appointment::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
                        ->where('business_id', $business->id)
                        ->where('id', $currentAppointment->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $conflict = Appointment::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
                        ->where('business_id', $business->id)
                        ->where('provider_id', $provider->id)
                        ->whereIn('status', ['pending', 'confirmed'])
                        ->where('id', '!=', $current->id)
                        ->where('start_time', '<', $newEndUtc)
                        ->where('end_time', '>', $newStartUtc)
                        ->lockForUpdate()
                        ->exists();

                    if ($conflict) {
                        throw new \RuntimeException('slot_taken');
                    }

                    $current->forceFill([
                        'status' => 'cancelled',
                        'reminder_sent_at' => null,
                    ])->save();

                    $replacement = Appointment::query()->create([
                        'business_id' => $business->id,
                        'provider_id' => $provider->id,
                        'patient_id' => $current->patient_id,
                        'service_id' => $service?->id,
                        'service_type' => $service?->name ?? $current->service_type,
                        'start_time' => $newStartUtc,
                        'end_time' => $newEndUtc,
                        'status' => 'confirmed',
                        'booked_via' => $sourceChannel,
                    ]);

                    return [$current, $replacement];
                });

                $this->appointmentSyncService->sync($oldAppointment);
                $this->appointmentSyncService->sync($newAppointment);
                $this->appointmentNotificationService->sendLifecycleEmail($newAppointment, 'rescheduled', $oldAppointment);
                $this->auditLogger->log(
                    actor: null,
                    action: 'appointment.rescheduled',
                    subjectType: Appointment::class,
                    subjectId: $newAppointment->id,
                    payload: [
                        'channel' => $sourceChannel,
                        'cancelled_appointment_id' => $oldAppointment->id,
                    ],
                    request: null,
                    businessId: $business->id,
                );

                $bookingAction->appointment_id = $newAppointment->id;
                $bookingAction->patient_id = $newAppointment->patient_id;
                $bookingAction->saveQuietly();

                return array_merge(
                    $this->resultPayload($newAppointment, 'rescheduled'),
                    ['cancelled_appointment_id' => $oldAppointment->id],
                );
            },
        );
    }

    /**
     * @param  array<string, mixed>  $requestPayload
     * @param  callable(BookingAction): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function runAction(
        Business $business,
        Patient $patient,
        string $action,
        string $sourceChannel,
        string $idempotencyKey,
        array $requestPayload,
        callable $callback,
    ): array {
        $bookingAction = BookingAction::query()->firstOrCreate(
            [
                'business_id' => $business->id,
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'patient_id' => $patient->id,
                'action' => $action,
                'source_channel' => $sourceChannel,
                'status' => 'processing',
                'request_payload' => $requestPayload,
            ],
        );

        if ($bookingAction->status === 'completed' && is_array($bookingAction->result_payload)) {
            return $bookingAction->result_payload;
        }

        $bookingAction->forceFill([
            'patient_id' => $patient->id,
            'action' => $action,
            'source_channel' => $sourceChannel,
            'status' => 'processing',
            'request_payload' => $requestPayload,
            'last_error' => null,
        ])->saveQuietly();

        try {
            $result = $callback($bookingAction);

            $bookingAction->forceFill([
                'status' => 'completed',
                'result_payload' => $result,
                'processed_at' => now(),
                'last_error' => null,
            ])->saveQuietly();

            return $result;
        } catch (\Throwable $exception) {
            $bookingAction->forceFill([
                'status' => 'failed',
                'last_error' => $exception->getMessage(),
            ])->saveQuietly();

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resultPayload(Appointment $appointment, string $action): array
    {
        $appointment->loadMissing(['business', 'provider', 'patient']);
        $localStart = $appointment->start_time->copy()->setTimezone($appointment->business->timezone);

        $confirmation = match ($action) {
            'created' => sprintf(
                'Your appointment is confirmed for %s at %s with %s.',
                $localStart->format('F j, Y'),
                $localStart->format('g:i A'),
                $appointment->provider->displayName(),
            ),
            'cancelled' => sprintf(
                'Your appointment for %s at %s has been cancelled.',
                $localStart->format('F j, Y'),
                $localStart->format('g:i A'),
            ),
            'rescheduled' => sprintf(
                'Your appointment has been rescheduled to %s at %s with %s.',
                $localStart->format('F j, Y'),
                $localStart->format('g:i A'),
                $appointment->provider->displayName(),
            ),
            default => 'Your appointment has been updated.',
        };

        return [
            'ok' => true,
            'action' => $action,
            'appointment_id' => $appointment->id,
            'status' => $appointment->status,
            'start_time' => $appointment->start_time?->toISOString(),
            'end_time' => $appointment->end_time?->toISOString(),
            'reply_text' => $confirmation,
        ];
    }
}
