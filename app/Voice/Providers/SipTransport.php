<?php

declare(strict_types=1);

namespace App\Voice\Providers;

use App\Exceptions\VoiceProviderException;
use App\Voice\Contracts\TelephonyTransportInterface;

class SipTransport implements TelephonyTransportInterface
{
    public function initiateCall(string $to, string $from, array $payload = []): array
    {
        $server = (string) config('voice.providers.transport.sip.server');
        $username = (string) config('voice.providers.transport.sip.username');
        $password = (string) config('voice.providers.transport.sip.password');

        if ($server === '' || $username === '' || $password === '') {
            throw VoiceProviderException::missingConfiguration('SIP', 'VOICE_SIP_SERVER, VOICE_SIP_USERNAME, and VOICE_SIP_PASSWORD are required');
        }

        return [
            'provider' => 'sip',
            'status' => 'prepared',
            'dial_string' => sprintf('sip:%s@%s', ltrim($to, '+'), $server),
            'from' => $from,
            'meta' => $payload,
        ];
    }
}
