<?php

declare(strict_types=1);

namespace App\Voice\Null;

use App\Voice\Contracts\TelephonyTransportInterface;

class NullTelephonyTransport implements TelephonyTransportInterface
{
    public function initiateCall(string $to, string $from, array $payload = []): array
    {
        return [
            'status' => 'disabled',
            'to' => $to,
            'from' => $from,
            'payload' => $payload,
        ];
    }
}
