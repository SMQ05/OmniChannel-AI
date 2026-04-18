<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Patient;
use App\Models\Provider;
use App\Models\VoiceEvent;
use App\Models\VoiceSession;
use App\Services\Messaging\OutboundMessageService;
use App\Services\SlotCalculatorService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VoiceToolService
{
    public function __construct(
        private readonly SlotCalculatorService $slotCalculatorService,
        private readonly OutboundMessageService $outboundMessageService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function execute(VoiceSession $voiceSession, string $tool, array $payload): array
    {
        return match ($tool) {
            'check_availability' => $this->checkAvailability($voiceSession, $payload),
            'book_appointment' => $this->bookAppointment($voiceSession, $payload),
            'reschedule_appointment' => $this->rescheduleAppointment($voiceSession, $payload),
            'cancel_appointment' => $this->cancelAppointment($voiceSession, $payload),
            'lookup_patient' => $this->lookupPatient($voiceSession, $payload),
            'get_business_faq' => $this->getBusinessFaq($voiceSession, $payload),
            'create_callback_request' => $this->createCallbackRequest($voiceSession, $payload),
            'notify_staff' => $this->notifyStaff($voiceSession, $payload),
            'send_followup_whatsapp' => $this->sendFollowupWhatsapp($voiceSession, $payload),
            default => [
                'ok' => false,
                'error' => 'Unsupported tool.',
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function checkAvailability(VoiceSession $voiceSession, array $payload): array
    {
        $business = $voiceSession->business;
        $days = max(1, min(7, (int) ($payload['days'] ?? 3)));
        $providerId = isset($payload['provider_id']) ? (int) $payload['provider_id'] : null;

        $query = Provider::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->with([
                'blockedDates',
                'appointments' => function ($appointments): void {
                    $appointments
                        ->withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
                        ->whereIn('status', ['confirmed', 'pending'])
                        ->where('start_time', '>=', now()->utc())
                        ->where('start_time', '<=', now()->utc()->addDays(7));
                },
            ]);

        if ($providerId !== null) {
            $query->where('id', $providerId);
        }

        /** @var Collection<int, Provider> $providers */
        $providers = $query->get();

        $slots = $this->slotCalculatorService->compute(
            providers: $providers,
            timezone: $business->timezone,
            days: $days,
        );

        return [
            'ok' => true,
            'slots' => array_slice($slots, 0, 12),
            'count' => count($slots),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function bookAppointment(VoiceSession $voiceSession, array $payload): array
    {
        $business = $voiceSession->business;
        $provider = $this->resolveProvider($business, (int) ($payload['provider_id'] ?? 0));
        $patient = $this->resolvePatient($voiceSession, $payload);

        if ($provider === null || $patient === null) {
            return ['ok' => false, 'error' => 'Provider or patient could not be resolved.'];
        }

        [$startUtc, $endUtc] = $this->buildUtcWindow(
            $business,
            $provider,
            (string) ($payload['date'] ?? ''),
            (string) ($payload['time'] ?? ''),
        );

        if ($startUtc === null || $endUtc === null) {
            return ['ok' => false, 'error' => 'Booking date or time is invalid.'];
        }

        try {
            $appointment = DB::transaction(function () use ($business, $provider, $patient, $payload, $startUtc, $endUtc): Appointment {
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
                    'service_type' => (string) ($payload['service_type'] ?? 'Appointment'),
                    'start_time' => $startUtc,
                    'end_time' => $endUtc,
                    'status' => 'confirmed',
                    'booked_via' => 'voice',
                    'notes' => $payload['notes'] ?? null,
                ]);
            });
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'slot_taken') {
                return ['ok' => false, 'error' => 'That time slot is no longer available.'];
            }

            throw $exception;
        }

        return [
            'ok' => true,
            'appointment_id' => $appointment->id,
            'status' => $appointment->status,
            'start_time' => $appointment->start_time?->toISOString(),
            'end_time' => $appointment->end_time?->toISOString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function rescheduleAppointment(VoiceSession $voiceSession, array $payload): array
    {
        $current = $this->findAppointment($voiceSession, $payload);

        if ($current === null) {
            return ['ok' => false, 'error' => 'No appointment could be found to reschedule.'];
        }

        $booked = $this->bookAppointment($voiceSession, array_merge($payload, [
            'patient_id' => $current->patient_id,
            'provider_id' => $payload['provider_id'] ?? $current->provider_id,
            'service_type' => $payload['service_type'] ?? $current->service_type,
        ]));

        if (!($booked['ok'] ?? false)) {
            return $booked;
        }

        $current->forceFill(['status' => 'cancelled'])->save();

        return [
            'ok' => true,
            'cancelled_appointment_id' => $current->id,
            'replacement_appointment_id' => $booked['appointment_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cancelAppointment(VoiceSession $voiceSession, array $payload): array
    {
        $appointment = $this->findAppointment($voiceSession, $payload);

        if ($appointment === null) {
            return ['ok' => false, 'error' => 'No appointment could be found to cancel.'];
        }

        $appointment->forceFill(['status' => 'cancelled'])->save();

        return [
            'ok' => true,
            'appointment_id' => $appointment->id,
            'status' => $appointment->status,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function lookupPatient(VoiceSession $voiceSession, array $payload): array
    {
        $business = $voiceSession->business;

        $query = Patient::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
            ->where('business_id', $business->id);

        if (!empty($payload['patient_id'])) {
            $query->where('id', (int) $payload['patient_id']);
        } elseif (!empty($payload['phone'])) {
            $phone = $this->normalizePhone((string) $payload['phone']);

            $query->where(function ($subQuery) use ($phone): void {
                $subQuery->where('phone', $phone)->orWhere('platform_user_id', $phone);
            });
        } else {
            return ['ok' => false, 'error' => 'patient_id or phone is required.'];
        }

        $patient = $query->first();

        if ($patient === null) {
            return ['ok' => true, 'found' => false];
        }

        $upcoming = Appointment::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
            ->where('business_id', $business->id)
            ->where('patient_id', $patient->id)
            ->where('start_time', '>=', now()->utc())
            ->orderBy('start_time')
            ->first();

        return [
            'ok' => true,
            'found' => true,
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->name,
                'phone' => $patient->phone,
                'email' => $patient->email,
            ],
            'upcoming_appointment' => $upcoming?->only(['id', 'service_type', 'status']),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function getBusinessFaq(VoiceSession $voiceSession, array $payload): array
    {
        $faqs = $voiceSession->business->ai_config['faqs'] ?? [];
        $query = mb_strtolower(trim((string) ($payload['query'] ?? '')));

        if ($query === '') {
            return ['ok' => true, 'faqs' => $faqs];
        }

        $filtered = array_values(array_filter(
            is_array($faqs) ? $faqs : [],
            static function (array $faq) use ($query): bool {
                return str_contains(mb_strtolower((string) ($faq['q'] ?? '')), $query)
                    || str_contains(mb_strtolower((string) ($faq['a'] ?? '')), $query);
            },
        ));

        return ['ok' => true, 'faqs' => $filtered];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function createCallbackRequest(VoiceSession $voiceSession, array $payload): array
    {
        $event = VoiceEvent::query()->create([
            'business_id' => $voiceSession->business_id,
            'voice_session_id' => $voiceSession->id,
            'event_type' => 'callback.requested',
            'source' => 'laravel',
            'severity' => 'info',
            'payload' => [
                'phone' => $this->normalizePhone((string) ($payload['phone'] ?? $voiceSession->from_number ?? '')),
                'reason' => (string) ($payload['reason'] ?? ''),
                'urgency' => (string) ($payload['urgency'] ?? 'normal'),
                'preferred_time' => $payload['preferred_time'] ?? null,
            ],
            'occurred_at' => now(),
        ]);

        $voiceSession->forceFill(['callback_requested_at' => now()])->saveQuietly();

        return [
            'ok' => true,
            'callback_event_id' => $event->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function notifyStaff(VoiceSession $voiceSession, array $payload): array
    {
        $event = VoiceEvent::query()->create([
            'business_id' => $voiceSession->business_id,
            'voice_session_id' => $voiceSession->id,
            'event_type' => 'staff.notified',
            'source' => 'laravel',
            'severity' => 'info',
            'payload' => [
                'message' => (string) ($payload['message'] ?? ''),
                'department' => $payload['department'] ?? null,
                'urgency' => $payload['urgency'] ?? 'normal',
            ],
            'occurred_at' => now(),
        ]);

        return [
            'ok' => true,
            'notification_event_id' => $event->id,
            'status' => 'queued',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sendFollowupWhatsapp(VoiceSession $voiceSession, array $payload): array
    {
        $business = $voiceSession->business;

        if (!$business->isChannelEnabled('whatsapp')) {
            return ['ok' => false, 'error' => 'WhatsApp is not enabled for this business.'];
        }

        $recipientPlatformId = trim((string) ($payload['recipient_platform_id'] ?? ''));

        if ($recipientPlatformId === '' && !empty($payload['phone'])) {
            $phone = $this->normalizePhone((string) $payload['phone']);

            $matchedPatient = Patient::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
                ->where('business_id', $business->id)
                ->where('platform', 'whatsapp')
                ->where(function ($query) use ($phone): void {
                    $query->where('phone', $phone)->orWhere('platform_user_id', $phone);
                })
                ->first();

            $recipientPlatformId = (string) ($matchedPatient?->platform_user_id ?? '');
        }

        if ($recipientPlatformId === '') {
            return ['ok' => false, 'error' => 'No valid WhatsApp recipient was found.'];
        }

        $attempt = $this->outboundMessageService->deliver(
            business: $business,
            channel: 'whatsapp',
            recipientPlatformId: $recipientPlatformId,
            message: (string) ($payload['message'] ?? ''),
            idempotencyKey: 'voice-followup:' . $voiceSession->id,
            correlationId: $voiceSession->uuid,
            meta: ['voice_session_id' => $voiceSession->id],
        );

        return [
            'ok' => true,
            'attempt_id' => $attempt->id,
            'status' => $attempt->status,
        ];
    }

    private function resolveProvider(Business $business, int $providerId): ?Provider
    {
        if ($providerId <= 0) {
            return null;
        }

        return Provider::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
            ->where('business_id', $business->id)
            ->where('id', $providerId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolvePatient(VoiceSession $voiceSession, array $payload): ?Patient
    {
        $business = $voiceSession->business;

        if (!empty($payload['patient_id'])) {
            return Patient::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
                ->where('business_id', $business->id)
                ->find((int) $payload['patient_id']);
        }

        $phone = $this->normalizePhone((string) ($payload['phone'] ?? $voiceSession->from_number ?? ''));

        if ($phone === '') {
            return $voiceSession->patient;
        }

        return Patient::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
            ->where('business_id', $business->id)
            ->where(function ($query) use ($phone): void {
                $query->where('phone', $phone)->orWhere('platform_user_id', $phone);
            })
            ->first();
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function buildUtcWindow(Business $business, Provider $provider, string $date, string $time): array
    {
        if ($date === '' || $time === '') {
            return [null, null];
        }

        $start = Carbon::parse(sprintf('%s %s', $date, $time), $business->timezone)->utc();
        $end = $start->copy()->addMinutes($provider->slot_duration_minutes);

        return [$start, $end];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findAppointment(VoiceSession $voiceSession, array $payload): ?Appointment
    {
        $business = $voiceSession->business;

        $query = Appointment::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
            ->where('business_id', $business->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('start_time');

        if (!empty($payload['appointment_id'])) {
            return $query->where('id', (int) $payload['appointment_id'])->first();
        }

        $patient = $this->resolvePatient($voiceSession, $payload);

        if ($patient !== null) {
            $query->where('patient_id', $patient->id);
        }

        if (!empty($payload['provider_id'])) {
            $query->where('provider_id', (int) $payload['provider_id']);
        }

        return $query->first();
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^\d+]/', '', trim($phone));

        return is_string($digits) ? $digits : '';
    }
}
