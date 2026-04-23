<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\BusinessService;
use App\Models\ConversationLog;
use App\Models\Patient;
use App\Models\Provider;
use App\Services\Appointments\BookingLifecycleService;
use App\Services\Conversations\HumanHandoffService;
use App\Services\Operations\BusinessServiceCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AppointmentOrchestrator — executes the intent returned by AppointmentAgent.
 *
 * Handles five intent types:
 *  - book        → verify slot, create Appointment, dispatch SyncAppointmentJob
 *  - cancel      → find upcoming appointment, cancel it, dispatch sync
 *  - reschedule  → cancel old, book new, dispatch sync for both
 *  - faq         → no DB write, pass reply_text through
 *  - handoff     → set human_mode on ConversationLog + Redis, alert staff
 *
 * Timezone contract:
 *  The AgentResponse date/time fields are in the BUSINESS's local timezone.
 *  This class converts them to UTC before every DB write, satisfying the
 *  UTC-only storage rule.
 *
 * Race-condition protection:
 *  Before confirming a booking the slot is re-verified inside a DB
 *  transaction with a row-level lock on the provider's appointments to
 *  prevent double-booking under concurrent requests.
 *
 * @phpstan-type AgentResponse array{
 *     intent:       string,
 *     provider_id:  int|null,
 *     service_id:   int|null,
 *     date:         string|null,
 *     time:         string|null,
 *     service_type: string|null,
 *     reply_text:   string,
 *     needs_human:  bool,
 * }
 */
class AppointmentOrchestrator
{
    public function __construct(
        private readonly ?BusinessServiceCatalog $businessServiceCatalog = null,
        private readonly ?HumanHandoffService $humanHandoffService = null,
        private readonly ?BookingLifecycleService $bookingLifecycleService = null,
    ) {
    }

    /**
     * Execute the AI agent's intent and return the reply text to send
     * to the patient.
     *
     * @param  Business          $business        The active tenant
     * @param  Patient           $patient         The messaging patient
     * @param  ConversationLog   $conversationLog The current session (mutated in-place for handoff)
     * @param  AgentResponse     $agentResponse   Parsed AI response
     * @param  string            $channel         'whatsapp' or 'messenger'
     * @param  array<string, mixed> $context
     * @return string            The reply text to dispatch to the patient
     */
    public function execute(
        Business $business,
        Patient $patient,
        ConversationLog $conversationLog,
        array $agentResponse,
        string $channel,
        array $context = [],
    ): string {
        // Escalate to human immediately if the agent flagged needs_human,
        // regardless of the intent value.
        if ($agentResponse['needs_human']) {
            $agentResponse['intent'] = 'handoff';
        }

        return match ($agentResponse['intent']) {
            'book'        => $this->handleBook($business, $patient, $agentResponse, $channel, $context),
            'cancel'      => $this->handleCancel($business, $patient, $agentResponse, $channel, $context),
            'reschedule'  => $this->handleReschedule($business, $patient, $agentResponse, $channel, $context),
            'handoff'     => $this->handleHandoff($business, $patient, $conversationLog, $agentResponse),
            default       => $agentResponse['reply_text'], // 'faq' and any unknown intent
        };
    }

    // -------------------------------------------------------------------------
    // Intent Handlers
    // -------------------------------------------------------------------------

    /**
     * Handle the 'book' intent.
     *
     * 1. Validate required fields are present in the agent response.
     * 2. Re-verify the slot is still free inside a DB transaction.
     * 3. Create the Appointment with UTC timestamps.
     * 4. Dispatch SyncAppointmentJob.
     * 5. Return a confirmation reply.
     *
     * @param  Business       $business
     * @param  Patient        $patient
     * @param  AgentResponse  $agentResponse
     */
    private function handleBook(
        Business $business,
        Patient $patient,
        array $agentResponse,
        string $channel,
        array $context = [],
    ): string {
        if (!$this->hasBookingFields($agentResponse)) {
            Log::warning('AppointmentOrchestrator: book intent missing required fields.', [
                'business_id'   => $business->id,
                'agent_response' => $agentResponse,
            ]);

            return $agentResponse['reply_text'];
        }

        $provider = $this->resolveProvider($business, (int) $agentResponse['provider_id']);

        if ($provider === null) {
            return "I couldn't find that provider. Could you let me know your preferred provider or would you like me to suggest one?";
        }

        $service = $this->resolveService($business, $provider, $agentResponse);

        if (($agentResponse['service_id'] ?? null) !== null && $service === null) {
            return "I couldn't match that service to this provider. Could you choose a different provider or service?";
        }

        // Convert local business time → UTC for DB storage
        [$startUtc, $endUtc] = $this->toUtcWindow(
            date: (string) $agentResponse['date'],
            time: (string) $agentResponse['time'],
            timezone: $business->timezone,
            durationMinutes: $service?->duration_minutes ?? $provider->slot_duration_minutes,
        );

        try {
            $result = ($this->bookingLifecycleService ?? app(BookingLifecycleService::class))->book(
                business: $business,
                patient: $patient,
                provider: $provider,
                service: $service,
                startUtc: $startUtc,
                endUtc: $endUtc,
                sourceChannel: $channel,
                idempotencyKey: (string) ($context['idempotency_key'] ?? sprintf('booking:%s:%d', $channel, $patient->id)),
                context: $context,
            );
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'slot_taken') {
                Log::info('AppointmentOrchestrator: slot was taken by a concurrent booking.', [
                    'business_id' => $business->id,
                    'provider_id' => $provider->id,
                    'start_utc'   => $startUtc,
                ]);

                return "I'm sorry, that slot was just taken! Could you choose another time? Here are the latest available options.";
            }

            throw $e;
        }

        return (string) ($result['reply_text'] ?? $agentResponse['reply_text']);
    }

    /**
     * Handle the 'cancel' intent.
     *
     * Finds the patient's next upcoming confirmed appointment matching
     * the date/provider hint from the agent response (if provided), or
     * the nearest upcoming appointment if no specific details were given.
     *
     * @param  Business       $business
     * @param  Patient        $patient
     * @param  AgentResponse  $agentResponse
     */
    private function handleCancel(
        Business $business,
        Patient $patient,
        array $agentResponse,
        string $channel,
        array $context = [],
    ): string {
        $query = Appointment::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
            ->where('business_id', $business->id)
            ->where('patient_id', $patient->id)
            ->where('status', 'confirmed')
            ->where('start_time', '>', Carbon::now()->utc());

        // Narrow by provider if the agent identified one
        if ($agentResponse['provider_id'] !== null) {
            $query->where('provider_id', $agentResponse['provider_id']);
        }

        // Narrow by date if the agent identified one
        if ($agentResponse['date'] !== null) {
            $localDate = Carbon::parse($agentResponse['date'], $business->timezone);
            $query->whereDate('start_time', $localDate->utc()->toDateString());
        }

        $appointment = $query->orderBy('start_time')->first();

        if ($appointment === null) {
            return "I couldn't find an upcoming appointment to cancel. Could you give me more details about which appointment you'd like to cancel?";
        }

        $result = ($this->bookingLifecycleService ?? app(BookingLifecycleService::class))->cancel(
            business: $business,
            appointment: $appointment,
            sourceChannel: $channel,
            idempotencyKey: (string) ($context['idempotency_key'] ?? sprintf('cancel:%s:%d:%d', $channel, $patient->id, $appointment->id)),
            context: $context,
        );

        return (string) ($result['reply_text'] ?? $agentResponse['reply_text']);
    }

    /**
     * Handle the 'reschedule' intent.
     *
     * Cancels the patient's current appointment and books a new one.
     * Both operations are wrapped in a single DB transaction.
     * A SyncAppointmentJob is dispatched for each affected appointment.
     *
     * @param  Business       $business
     * @param  Patient        $patient
     * @param  AgentResponse  $agentResponse
     */
    private function handleReschedule(
        Business $business,
        Patient $patient,
        array $agentResponse,
        string $channel,
        array $context = [],
    ): string {
        if (!$this->hasBookingFields($agentResponse)) {
            return $agentResponse['reply_text'];
        }

        $provider = $this->resolveProvider($business, (int) $agentResponse['provider_id']);

        if ($provider === null) {
            return "I couldn't find that provider. Could you let me know which provider you'd like to reschedule with?";
        }

        $service = $this->resolveService($business, $provider, $agentResponse);

        if (($agentResponse['service_id'] ?? null) !== null && $service === null) {
            return "I couldn't match that service to this provider. Could you choose a different provider or service?";
        }

        [$newStartUtc, $newEndUtc] = $this->toUtcWindow(
            date: (string) $agentResponse['date'],
            time: (string) $agentResponse['time'],
            timezone: $business->timezone,
            durationMinutes: $service?->duration_minutes ?? $provider->slot_duration_minutes,
        );

        $currentAppointment = Appointment::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
            ->where('business_id', $business->id)
            ->where('patient_id', $patient->id)
            ->where('status', 'confirmed')
            ->where('start_time', '>', Carbon::now()->utc())
            ->orderBy('start_time')
            ->first();

        if ($currentAppointment === null) {
            return "I couldn't find an existing appointment to reschedule. Could you give me more details?";
        }

        try {
            $result = ($this->bookingLifecycleService ?? app(BookingLifecycleService::class))->reschedule(
                business: $business,
                currentAppointment: $currentAppointment,
                provider: $provider,
                service: $service,
                newStartUtc: $newStartUtc,
                newEndUtc: $newEndUtc,
                sourceChannel: $channel,
                idempotencyKey: (string) ($context['idempotency_key'] ?? sprintf('reschedule:%s:%d:%d', $channel, $patient->id, $currentAppointment->id)),
                context: $context,
            );
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'slot_taken'              => "I'm sorry, that new slot was just taken! Could you choose another time?",
                default                   => throw $e,
            };
        }

        return (string) ($result['reply_text'] ?? $agentResponse['reply_text']);
    }

    /**
     * Handle the 'handoff' intent.
     *
     * 1. Sets human_mode = true on the ConversationLog (mutated in-place).
     * 2. Writes the human-mode flag to Redis so subsequent messages in
     *    this session are silently discarded by ProcessIncomingMessage.
     * 3. Dispatches a staff alert via the dashboard notification system.
     *
     * @param  Business          $business
     * @param  Patient           $patient
     * @param  ConversationLog   $conversationLog  Mutated in-place
     * @param  AgentResponse     $agentResponse
     */
    private function handleHandoff(
        Business $business,
        Patient $patient,
        ConversationLog $conversationLog,
        array $agentResponse,
    ): string {
        ($this->humanHandoffService ?? app(HumanHandoffService::class))->activate(
            business: $business,
            patient: $patient,
            conversationLog: $conversationLog,
            source: 'ai_agent',
        );

        Log::info('AppointmentOrchestrator: human handoff triggered.', [
            'business_id' => $business->id,
            'patient_id'  => $patient->id,
        ]);

        return $agentResponse['reply_text'];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Determine whether the agent response contains all fields required
     * to create a booking.
     *
     * @param  AgentResponse  $agentResponse
     */
    private function hasBookingFields(array $agentResponse): bool
    {
        return $agentResponse['provider_id'] !== null
            && $agentResponse['date'] !== null
            && $agentResponse['time'] !== null;
    }

    /**
     * Resolve a Provider that belongs to the given business.
     *
     * Returns null if the provider does not exist or does not belong
     * to this tenant (prevents cross-tenant data access).
     *
     * @param  Business  $business
     * @param  int       $providerId
     */
    private function resolveProvider(Business $business, int $providerId): ?Provider
    {
        return Provider::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
            ->where('id', $providerId)
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->with('services:id')
            ->first();
    }

    /**
     * @param  AgentResponse  $agentResponse
     */
    private function resolveService(Business $business, Provider $provider, array $agentResponse): ?BusinessService
    {
        $service = ($this->businessServiceCatalog ?? app(BusinessServiceCatalog::class))->resolveForBusiness(
            business: $business,
            serviceId: isset($agentResponse['service_id']) ? (int) $agentResponse['service_id'] : null,
            serviceType: $agentResponse['service_type'] ?? null,
        );

        if ($service === null) {
            return null;
        }

        return (($this->businessServiceCatalog ?? app(BusinessServiceCatalog::class))->providerCanDeliver($provider, $service))
            ? $service
            : null;
    }

    /**
     * Convert a local date + time string pair to a UTC [start, end] window.
     *
     * The AgentResponse carries local business-timezone times; all DB writes
     * must be UTC. This method is the single UTC conversion point for the
     * orchestrator, enforcing the project-wide UTC storage rule.
     *
     * @param  string  $date             'YYYY-MM-DD' in business timezone
     * @param  string  $time             'HH:MM' in business timezone
     * @param  string  $timezone         Business timezone identifier
     * @param  int     $durationMinutes  Slot length in minutes
     * @return array{0: Carbon, 1: Carbon}  [startUtc, endUtc]
     */
    private function toUtcWindow(
        string $date,
        string $time,
        string $timezone,
        int $durationMinutes,
    ): array {
        $startLocal = Carbon::parse("{$date} {$time}", $timezone);
        $startUtc   = $startLocal->utc();
        $endUtc     = $startUtc->copy()->addMinutes($durationMinutes);

        return [$startUtc, $endUtc];
    }
}
