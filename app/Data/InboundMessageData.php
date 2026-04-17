<?php

declare(strict_types=1);

namespace App\Data;

final class InboundMessageData
{
    /**
     * @param  array<string, mixed>  $normalizedPayload
     */
    public function __construct(
        public readonly string $channel,
        public readonly string $businessSlug,
        public readonly string $externalMessageId,
        public readonly string $senderId,
        public readonly string $senderName,
        public readonly string $text,
        public readonly string $messageType,
        public readonly ?string $providerTimestamp,
        public readonly array $normalizedPayload,
    ) {}

    public function isMessage(): bool
    {
        return $this->senderId !== '' && $this->text !== '';
    }

    public function idempotencyKey(int $businessId): string
    {
        return sha1(implode('|', [
            $businessId,
            $this->channel,
            $this->externalMessageId !== '' ? $this->externalMessageId : md5($this->text),
            $this->senderId,
            $this->providerTimestamp ?? '',
        ]));
    }
}
