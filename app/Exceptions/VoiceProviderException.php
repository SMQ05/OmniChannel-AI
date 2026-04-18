<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class VoiceProviderException extends RuntimeException
{
    public static function missingConfiguration(string $provider, string $detail): self
    {
        return new self(sprintf('%s configuration incomplete: %s', $provider, $detail));
    }

    public static function requestFailed(string $provider, string $detail): self
    {
        return new self(sprintf('%s request failed: %s', $provider, $detail));
    }
}
