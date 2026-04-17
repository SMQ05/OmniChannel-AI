<?php

declare(strict_types=1);

namespace App\Voice\Contracts;

interface TelephonyTransportInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function initiateCall(string $to, string $from, array $payload = []): array;
}
