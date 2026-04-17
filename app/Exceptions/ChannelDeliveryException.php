<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ChannelDeliveryException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $transient,
        public readonly ?int $statusCode = null,
        public readonly ?string $responseBody = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
