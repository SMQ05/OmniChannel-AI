<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Data\InboundMessageData;
use App\Models\Business;
use App\Models\InboundWebhook;

class InboundWebhookRecorder
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        Business $business,
        string $channel,
        string $businessSlug,
        string $correlationId,
        array $payload,
        InboundMessageData $messageData,
        bool $signatureValid,
    ): InboundWebhook {
        $attributes = [
            'business_id' => $business->id,
            'channel' => $channel,
            'business_slug' => $businessSlug,
            'correlation_id' => $correlationId,
            'idempotency_key' => $messageData->idempotencyKey($business->id),
            'external_message_id' => $messageData->externalMessageId ?: null,
            'sender_platform_id' => $messageData->senderId !== '' ? $messageData->senderId : null,
            'sender_name' => $messageData->senderName !== '' ? $messageData->senderName : null,
            'message_text' => $messageData->text !== '' ? $messageData->text : null,
            'message_type' => $messageData->messageType,
            'payload' => $payload,
            'normalized_payload' => $messageData->normalizedPayload,
            'signature_valid' => $signatureValid,
            'status' => $messageData->isMessage() ? 'received' : 'ignored',
            'received_at' => now(),
        ];

        return InboundWebhook::query()->firstOrCreate(
            [
                'business_id' => $business->id,
                'idempotency_key' => $attributes['idempotency_key'],
            ],
            $attributes,
        );
    }
}
