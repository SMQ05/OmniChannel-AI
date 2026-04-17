<?php

declare(strict_types=1);

namespace App\Data;

final class ChannelSendResult
{
    /**
     * @param  array<string, mixed>|null  $decodedBody
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly ?string $providerMessageId,
        public readonly string $rawBody,
        public readonly ?array $decodedBody = null,
    ) {}
}
