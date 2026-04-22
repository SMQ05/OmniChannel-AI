<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Data\ChannelSendResult;
use App\Exceptions\ChannelConfigurationException;
use App\Exceptions\ChannelDeliveryException;
use App\Models\Business;
use App\Models\OutboundMessageAttempt;
use App\Services\Billing\BillingLifecycleService;
use App\Services\ChannelReadinessService;
use App\Services\Channel\Contracts\ChannelServiceInterface;
use App\Services\Channel\MessengerChannelService;
use App\Services\Channel\WhatsAppChannelService;
use App\Services\Usage\UsageMeteringService;

class OutboundMessageService
{
    public function __construct(
        private readonly WhatsAppChannelService $whatsApp,
        private readonly MessengerChannelService $messenger,
        private readonly UsageMeteringService $usageMetering,
        private readonly ChannelReadinessService $channelReadinessService,
        private readonly BillingLifecycleService $billingLifecycleService,
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function deliver(
        Business $business,
        string $channel,
        string $recipientPlatformId,
        string $message,
        string $idempotencyKey,
        string $correlationId,
        ?int $conversationLogId = null,
        ?int $inboundWebhookId = null,
        array $meta = [],
    ): OutboundMessageAttempt {
        $attempt = OutboundMessageAttempt::query()->firstOrCreate(
            [
                'business_id' => $business->id,
                'channel' => $channel,
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'conversation_log_id' => $conversationLogId,
                'inbound_webhook_id' => $inboundWebhookId,
                'recipient_platform_id' => $recipientPlatformId,
                'correlation_id' => $correlationId,
                'status' => 'pending',
                'message_text' => $message,
                'attempts' => 0,
                'meta' => $meta,
            ],
        );

        if ($attempt->status === 'sent') {
            return $attempt;
        }

        $attempt->forceFill([
            'conversation_log_id' => $conversationLogId,
            'inbound_webhook_id' => $inboundWebhookId,
            'recipient_platform_id' => $recipientPlatformId,
            'correlation_id' => $correlationId,
            'message_text' => $message,
            'attempts' => $attempt->attempts + 1,
            'last_attempted_at' => now(),
            'meta' => array_merge($attempt->meta ?? [], $meta),
        ])->save();

        try {
            if ($this->billingLifecycleService->blocksOutboundMessaging($business)) {
                throw new ChannelConfigurationException('Outbound messaging is suspended because billing lifecycle is suspended.');
            }

            $channelState = $this->channelReadinessService->forChannel($business, $channel);

            if (!$channelState['connected']) {
                throw new ChannelConfigurationException("{$channel} channel is not connected.");
            }

            if (!$channelState['enabled']) {
                throw new ChannelConfigurationException("{$channel} channel is not enabled.");
            }

            $result = $this->channelService($channel)->sendMessage(
                platformUserId: $recipientPlatformId,
                message: $message,
                channelConfig: $this->channelReadinessService->resolveOutboundConfig($business, $channel),
                context: [
                    'correlation_id' => $correlationId,
                    'business_id' => $business->id,
                ],
            );

            $attempt->forceFill([
                'status' => 'sent',
                'provider_message_id' => $result->providerMessageId,
                'http_status' => $result->statusCode,
                'response_body' => $result->rawBody,
                'failure_class' => null,
                'last_error' => null,
                'sent_at' => now(),
            ])->save();

            $this->usageMetering->record(
                business: $business,
                metric: 'messages_sent',
                channel: $channel,
                quantity: 1,
                status: 'sent',
                referenceType: OutboundMessageAttempt::class,
                referenceId: $attempt->id,
                meta: ['provider_message_id' => $result->providerMessageId],
            );

            return $attempt;
        } catch (ChannelDeliveryException|ChannelConfigurationException $exception) {
            $attempt->forceFill([
                'status' => 'failed',
                'http_status' => $exception instanceof ChannelDeliveryException ? $exception->statusCode : null,
                'response_body' => $exception instanceof ChannelDeliveryException ? $exception->responseBody : null,
                'failure_class' => $exception instanceof ChannelDeliveryException && $exception->transient ? 'transient' : 'permanent',
                'last_error' => $exception->getMessage(),
            ])->save();

            $this->usageMetering->record(
                business: $business,
                metric: 'failed_sends',
                channel: $channel,
                quantity: 1,
                status: $exception instanceof ChannelDeliveryException && $exception->transient ? 'transient' : 'permanent',
                referenceType: OutboundMessageAttempt::class,
                referenceId: $attempt->id,
                meta: ['error' => $exception->getMessage()],
            );

            throw $exception;
        }
    }

    private function channelService(string $channel): ChannelServiceInterface
    {
        return $channel === 'messenger' ? $this->messenger : $this->whatsApp;
    }
}
