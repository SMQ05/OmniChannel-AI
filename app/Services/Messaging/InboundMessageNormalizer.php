<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Data\InboundMessageData;

class InboundMessageNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function normalize(array $payload, string $channel, string $businessSlug): InboundMessageData
    {
        return match ($channel) {
            'messenger' => $this->normalizeMessenger($payload, $businessSlug),
            default => $this->normalizeWhatsApp($payload, $businessSlug),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalizeWhatsApp(array $payload, string $businessSlug): InboundMessageData
    {
        $value = $payload['entry'][0]['changes'][0]['value'] ?? [];
        $message = $value['messages'][0] ?? [];
        $contact = $value['contacts'][0] ?? [];
        $senderName = (string) ($contact['profile']['name'] ?? '');
        $senderId = (string) ($message['from_user_id'] ?? $contact['user_id'] ?? $message['from'] ?? $contact['wa_id'] ?? '');
        $text = (string) ($message['text']['body'] ?? '');

        return new InboundMessageData(
            channel: 'whatsapp',
            businessSlug: $businessSlug,
            externalMessageId: (string) ($message['id'] ?? ''),
            senderId: $senderId,
            senderName: $senderName,
            text: $text,
            messageType: (string) ($message['type'] ?? 'text'),
            providerTimestamp: isset($message['timestamp']) ? (string) $message['timestamp'] : null,
            normalizedPayload: [
                'message_id' => (string) ($message['id'] ?? ''),
                'sender_id' => $senderId,
                'sender_name' => $senderName,
                'text' => $text,
                'message_type' => (string) ($message['type'] ?? 'text'),
                'timestamp' => isset($message['timestamp']) ? (string) $message['timestamp'] : null,
                'recipient_id' => (string) ($value['metadata']['phone_number_id'] ?? ''),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalizeMessenger(array $payload, string $businessSlug): InboundMessageData
    {
        $messaging = $payload['entry'][0]['messaging'][0] ?? [];
        $message = $messaging['message'] ?? [];
        $senderId = (string) ($messaging['sender']['id'] ?? '');
        $text = (string) ($message['text'] ?? '');

        return new InboundMessageData(
            channel: 'messenger',
            businessSlug: $businessSlug,
            externalMessageId: (string) ($message['mid'] ?? ''),
            senderId: $senderId,
            senderName: '',
            text: $text,
            messageType: isset($message['attachments']) ? 'attachment' : 'text',
            providerTimestamp: isset($messaging['timestamp']) ? (string) $messaging['timestamp'] : null,
            normalizedPayload: [
                'message_id' => (string) ($message['mid'] ?? ''),
                'sender_id' => $senderId,
                'text' => $text,
                'message_type' => isset($message['attachments']) ? 'attachment' : 'text',
                'timestamp' => isset($messaging['timestamp']) ? (string) $messaging['timestamp'] : null,
            ],
        );
    }
}
