<?php

declare(strict_types=1);

namespace App\Voice\Providers;

use App\Exceptions\VoiceProviderException;
use App\Voice\Contracts\TelephonyTransportInterface;
use Illuminate\Support\Facades\Http;

class TelnyxTransport implements TelephonyTransportInterface
{
    public function initiateCall(string $to, string $from, array $payload = []): array
    {
        $apiKey = (string) config('voice.providers.transport.telnyx.api_key');
        $connectionId = (string) config('voice.providers.transport.telnyx.connection_id');

        if ($apiKey === '' || $connectionId === '') {
            throw VoiceProviderException::missingConfiguration('Telnyx', 'TELNYX_API_KEY and TELNYX_CONNECTION_ID are required');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->post('https://api.telnyx.com/v2/calls', array_filter([
                'connection_id' => $connectionId,
                'to' => $to,
                'from' => $from,
                'answering_machine_detection' => $payload['answering_machine_detection'] ?? null,
                'stream_url' => $payload['stream_url'] ?? null,
                'webhook_url' => $payload['webhook_url'] ?? null,
                'client_state' => $payload['client_state'] ?? null,
                'record' => $payload['record'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        if ($response->failed()) {
            throw VoiceProviderException::requestFailed('Telnyx', $response->json('errors.0.detail') ?? $response->body());
        }

        return [
            'provider' => 'telnyx',
            'status' => 'initiated',
            'response' => $response->json('data') ?? $response->json(),
        ];
    }
}
