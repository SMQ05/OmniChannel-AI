<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Appointment;
use App\Queue\Attributes\Backoff;
use App\Queue\Attributes\Tries;
use App\Queue\Concerns\InteractsWithQueueAttributes;
use App\Services\Appointments\AppointmentNotificationService;
use App\Services\Messaging\OutboundMessageService;
use App\Services\Billing\BillingLifecycleService;
use App\Services\Queue\QueueRouteResolver;
use App\Services\Queue\WorkerHeartbeatService;
use App\Services\Usage\UsageMeteringService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

#[Tries(3)]
#[Backoff(60)]
class SendReminderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use InteractsWithQueueAttributes;

    public function __construct(
        private readonly Appointment $appointment,
        private readonly int $offsetHours,
    ) {
        $this->initQueueAttributes();
        app(QueueRouteResolver::class)->apply($this, 'reminders');
    }

    public function handle(
        OutboundMessageService $outboundMessageService,
        AppointmentNotificationService $appointmentNotificationService,
        WorkerHeartbeatService $workerHeartbeatService,
        UsageMeteringService $usageMetering,
        BillingLifecycleService $billingLifecycleService,
    ): void {
        $workerHeartbeatService->beat(
            queueConnection: $this->connection ?: (string) config('queue.default'),
            queueName: $this->queue ?: 'default',
            meta: ['job' => self::class, 'appointment_id' => $this->appointment->id],
        );

        $appointment = $this->appointment->fresh(['business', 'patient', 'provider']);

        if ($appointment === null) {
            Log::warning('SendReminderJob: appointment no longer exists, skipping.', [
                'appointment_id' => $this->appointment->id,
                'offset_hours' => $this->offsetHours,
            ]);

            return;
        }

        $key = (string) $this->offsetHours;
        $reminderSentAt = $appointment->reminder_sent_at ?? [];

        if (!empty($reminderSentAt[$key])) {
            Log::info('SendReminderJob: reminder already sent, skipping.', [
                'appointment_id' => $appointment->id,
                'offset_hours' => $this->offsetHours,
            ]);

            return;
        }

        if ($appointment->status !== 'confirmed') {
            Log::info('SendReminderJob: appointment no longer confirmed, skipping reminder.', [
                'appointment_id' => $appointment->id,
                'status' => $appointment->status,
                'offset_hours' => $this->offsetHours,
            ]);

            return;
        }

        $business = $appointment->business;

        if ($billingLifecycleService->blocksReminders($business)) {
            Log::warning('SendReminderJob: billing lifecycle suspended, skipping reminder.', [
                'appointment_id' => $appointment->id,
                'business_id' => $business->id,
            ]);

            return;
        }

        $rules = $business->reminder_settings['reminders'] ?? [];
        $rule = $this->findRule($rules, $this->offsetHours);

        if ($rule === null) {
            Log::warning('SendReminderJob: no reminder rule found for offset, skipping.', [
                'appointment_id' => $appointment->id,
                'offset_hours' => $this->offsetHours,
            ]);

            return;
        }

        $message = $this->renderTemplate(
            template: (string) ($rule['message_template'] ?? ''),
            appointment: $appointment,
        );

        if ($message === '') {
            Log::warning('SendReminderJob: rendered message is empty, skipping.', [
                'appointment_id' => $appointment->id,
                'offset_hours' => $this->offsetHours,
            ]);

            return;
        }

        $usageChannel = $appointment->booked_via;

        if (in_array($appointment->booked_via, ['whatsapp', 'messenger'], true)) {
            $platformUserId = $appointment->patient->platform_user_id;

            if ($platformUserId === null || $platformUserId === '') {
                Log::warning('SendReminderJob: patient is missing platform_user_id, skipping.', [
                    'appointment_id' => $appointment->id,
                    'patient_id' => $appointment->patient->id,
                ]);

                return;
            }

            $outboundMessageService->deliver(
                business: $business,
                channel: $appointment->booked_via,
                recipientPlatformId: $platformUserId,
                message: $message,
                idempotencyKey: sprintf('reminder:%d:%d', $appointment->id, $this->offsetHours),
                correlationId: (string) Str::uuid(),
                meta: [
                    'type' => 'reminder',
                    'appointment_id' => $appointment->id,
                    'offset_hours' => $this->offsetHours,
                ],
            );
        } else {
            $email = trim((string) ($appointment->patient->email ?? ''));

            if ($email === '') {
                Log::info('SendReminderJob: appointment has no reminder-capable destination, skipping.', [
                    'appointment_id' => $appointment->id,
                    'channel' => $appointment->booked_via,
                ]);

                return;
            }

            $appointmentNotificationService->sendLifecycleEmail($appointment, 'reminder');
            $usageChannel = 'email';
        }

        $appointment->reminder_sent_at = array_merge(
            $reminderSentAt,
            [$key => Carbon::now()->utc()->toISOString()],
        );
        $appointment->saveQuietly();

        $usageMetering->record(
            business: $business,
            metric: 'reminders_sent',
            channel: $usageChannel,
            quantity: 1,
            status: 'sent',
            referenceType: Appointment::class,
            referenceId: $appointment->id,
            meta: ['offset_hours' => $this->offsetHours],
        );

        Log::info('SendReminderJob: reminder sent and recorded.', [
            'appointment_id' => $appointment->id,
            'offset_hours' => $this->offsetHours,
            'channel' => $usageChannel,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     */
    private function findRule(array $rules, int $offsetHours): ?array
    {
        foreach ($rules as $rule) {
            if ((int) ($rule['offset_hours'] ?? 0) === $offsetHours) {
                return $rule;
            }
        }

        return null;
    }

    private function renderTemplate(string $template, Appointment $appointment): string
    {
        $business = $appointment->business;
        $patient  = $appointment->patient;
        $provider = $appointment->provider;
        $timezone = $business->timezone;

        $start = Carbon::parse($appointment->start_time)->setTimezone($timezone);

        $placeholders = [
            '{patient_name}' => $patient->name ?? 'Valued Patient',
            '{provider_name}' => $provider->name ?? 'Your Provider',
            '{provider_title}' => $provider->title ?? '',
            '{date}' => $start->format('l, F j, Y'),
            '{time}' => $start->format('g:i A'),
            '{service_type}' => $appointment->service_type,
            '{business_name}' => $business->name,
            '{business_phone}' => (string) ($business->ai_config['business_phone'] ?? ''),
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }
}
