<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\AppointmentAgent;
use App\Models\ConversationLog;
use App\Models\InboundWebhook;
use App\Models\Patient;
use App\Models\Provider;
use App\Queue\Attributes\Backoff;
use App\Queue\Attributes\FailOnTimeout;
use App\Queue\Attributes\Timeout;
use App\Queue\Attributes\Tries;
use App\Queue\Concerns\InteractsWithQueueAttributes;
use App\Services\AppointmentOrchestrator;
use App\Services\Billing\BillingLifecycleService;
use App\Services\Messaging\OutboundMessageService;
use App\Services\Queue\QueueRouteResolver;
use App\Services\Queue\WorkerHeartbeatService;
use App\Services\SlotCalculatorService;
use App\Services\Usage\PlanEnforcementService;
use App\Services\Usage\UsageMeteringService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Tries(3)]
#[Backoff(60)]
#[Timeout(30)]
#[FailOnTimeout]
class ProcessIncomingMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use InteractsWithQueueAttributes;

    private const SESSION_TTL = 1800;
    private const SESSION_PREFIX = 'session';
    private const HUMAN_MODE_PREFIX = 'human_mode';

    public function __construct(
        private readonly int $inboundWebhookId,
    ) {
        $this->initQueueAttributes();
        app(QueueRouteResolver::class)->apply($this, 'webhooks');
    }

    public function handle(
        SlotCalculatorService $slotCalculator,
        AppointmentAgent $agent,
        AppointmentOrchestrator $orchestrator,
        OutboundMessageService $outboundMessageService,
        WorkerHeartbeatService $workerHeartbeatService,
        UsageMeteringService $usageMetering,
        PlanEnforcementService $planEnforcementService,
        BillingLifecycleService $billingLifecycleService,
    ): void {
        $webhook = InboundWebhook::query()->with('business')->findOrFail($this->inboundWebhookId);
        $business = $webhook->business;
        $channel = $webhook->channel;
        $platformUserId = (string) ($webhook->sender_platform_id ?? '');
        $inboundText = (string) ($webhook->message_text ?? '');

        Log::withContext([
            'correlation_id' => $webhook->correlation_id,
            'business_id' => $business->id,
            'channel' => $channel,
            'inbound_webhook_id' => $webhook->id,
        ]);

        $workerHeartbeatService->beat(
            queueConnection: $this->connection ?: (string) config('queue.default'),
            queueName: $this->queue ?: 'default',
            meta: ['job' => self::class, 'inbound_webhook_id' => $webhook->id],
        );

        $webhook->forceFill([
            'attempt_count' => $this->attempts(),
            'status' => 'processing',
        ])->save();

        try {
            if ($platformUserId === '' || $inboundText === '') {
                $webhook->forceFill([
                    'status' => 'ignored',
                    'processed_at' => now(),
                ])->save();

                return;
            }

            $quota = $planEnforcementService->assess($business, 'messages_sent');

            if (!$quota['allowed']) {
                $webhook->forceFill([
                    'status' => 'blocked',
                    'last_error' => 'Plan limit reached for outbound messaging.',
                ])->save();

                Log::warning('ProcessIncomingMessage: outbound blocked by plan limit.', $quota);

                return;
            }

            $patient = Patient::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
                ->firstOrCreate(
                    [
                        'business_id' => $business->id,
                        'platform_user_id' => $platformUserId,
                        'platform' => $channel,
                    ],
                    [
                        'name' => $webhook->sender_name ?: 'Guest',
                    ],
                );

            if (($patient->name === 'Guest' || $patient->name === '') && !empty($webhook->sender_name)) {
                $patient->forceFill(['name' => $webhook->sender_name])->saveQuietly();
            }

            $conversationLog = ConversationLog::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
                ->where('business_id', $business->id)
                ->where('patient_id', $patient->id)
                ->whereNull('session_ended_at')
                ->latest()
                ->first();

            if ($conversationLog === null) {
                $conversationLog = new ConversationLog([
                    'business_id' => $business->id,
                    'patient_id' => $patient->id,
                    'channel' => $channel,
                    'messages' => [],
                    'ai_model_used' => $business->llmProvider(),
                    'session_started_at' => Carbon::now()->utc(),
                ]);
            }

            if (Cache::get($this->humanModeKey($business->id, $channel, $platformUserId), false) || $conversationLog->human_mode) {
                $webhook->forceFill([
                    'status' => 'human_takeover',
                    'processed_at' => now(),
                ])->save();

                return;
            }

            $conversationLog->appendMessage('user', $inboundText);
            $conversationLog->ai_model_used = $business->llmProvider();
            $conversationLog->save();

            if ($billingLifecycleService->blocksOutboundMessaging($business)) {
                $webhook->forceFill([
                    'status' => 'blocked',
                    'processed_at' => now(),
                    'last_error' => 'Outbound reply suppressed because billing lifecycle is suspended.',
                ])->save();

                Log::warning('ProcessIncomingMessage: outbound reply blocked by suspended billing lifecycle.');

                return;
            }

            $providers = Provider::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
                ->where('business_id', $business->id)
                ->where('is_active', true)
                ->with([
                    'blockedDates',
                    'appointments' => function ($query): void {
                        $query->withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
                            ->whereIn('status', ['confirmed', 'pending'])
                            ->where('start_time', '>=', Carbon::now()->utc())
                            ->where('start_time', '<=', Carbon::now()->utc()->addDays(7));
                    },
                ])
                ->get();

            $availableSlots = $slotCalculator->compute(
                providers: $providers,
                timezone: $business->timezone,
                days: 7,
            );

            $agentResponse = $agent->handle(
                business: $business,
                conversationLog: $conversationLog,
                availableSlots: $availableSlots,
                inboundText: $inboundText,
            );

            $replyText = $orchestrator->execute(
                business: $business,
                patient: $patient,
                conversationLog: $conversationLog,
                agentResponse: $agentResponse,
                channel: $channel,
            );

            $conversationLog->appendMessage('assistant', $replyText);
            $conversationLog->save();

            Cache::put(
                key: $this->sessionKey($business->id, $channel, $platformUserId),
                value: $conversationLog->messages,
                ttl: self::SESSION_TTL,
            );

            $outboundMessageService->deliver(
                business: $business,
                channel: $channel,
                recipientPlatformId: $platformUserId,
                message: $replyText,
                idempotencyKey: 'reply:' . $webhook->id,
                correlationId: $webhook->correlation_id,
                conversationLogId: $conversationLog->id,
                inboundWebhookId: $webhook->id,
                meta: ['type' => 'assistant_reply'],
            );

            $usageMetering->record(
                business: $business,
                metric: 'llm_tokens_estimated',
                channel: $channel,
                quantity: (int) ceil((strlen($inboundText) + strlen($replyText)) / 4),
                status: 'estimated',
                referenceType: ConversationLog::class,
                referenceId: $conversationLog->id,
            );

            $webhook->forceFill([
                'status' => 'processed',
                'processed_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $webhook->forceFill([
                'status' => 'failed',
                'last_error' => $exception->getMessage(),
            ])->save();

            Log::error('ProcessIncomingMessage: unhandled exception.', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        try {
            $webhook = InboundWebhook::query()->with('business')->find($this->inboundWebhookId);

            if ($webhook === null || empty($webhook->sender_platform_id)) {
                return;
            }

            $phone = $webhook->business->ai_config['business_phone'] ?? '';
            $message = $phone !== ''
                ? "Sorry, I'm having a moment! Please call us directly at {$phone} to book."
                : "Sorry, I'm having a moment! Please contact us directly to book.";

            app(OutboundMessageService::class)->deliver(
                business: $webhook->business,
                channel: $webhook->channel,
                recipientPlatformId: (string) $webhook->sender_platform_id,
                message: $message,
                idempotencyKey: 'fallback:' . $webhook->id,
                correlationId: $webhook->correlation_id,
                inboundWebhookId: $webhook->id,
                meta: ['type' => 'fallback', 'job_error' => $exception->getMessage()],
            );
        } catch (Throwable $fallbackException) {
            Log::error('ProcessIncomingMessage: fallback message delivery also failed.', [
                'error' => $fallbackException->getMessage(),
            ]);
        }
    }

    private function sessionKey(int $businessId, string $channel, string $platformUserId): string
    {
        return implode(':', [self::SESSION_PREFIX, $businessId, $channel, $platformUserId]);
    }

    private function humanModeKey(int $businessId, string $channel, string $platformUserId): string
    {
        return implode(':', [self::HUMAN_MODE_PREFIX, $businessId, $channel, $platformUserId]);
    }
}
