<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Models\VoiceEvent;
use App\Models\VoiceSession;

class VoiceIdempotencyService
{
    /**
     * @param  callable(): array<string, mixed>  $callback
     * @return array{result: array<string, mixed>, replayed: bool}
     */
    public function execute(VoiceSession $voiceSession, string $tool, string $idempotencyKey, callable $callback): array
    {
        $eventType = sprintf('tool.%s.result', $tool);

        $existing = VoiceEvent::query()
            ->where('voice_session_id', $voiceSession->id)
            ->where('event_type', $eventType)
            ->where('idempotency_key', $idempotencyKey)
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return [
                'result' => (array) ($existing->payload['result'] ?? []),
                'replayed' => true,
            ];
        }

        $result = $callback();

        VoiceEvent::query()->create([
            'business_id' => $voiceSession->business_id,
            'voice_session_id' => $voiceSession->id,
            'event_type' => $eventType,
            'source' => 'laravel',
            'idempotency_key' => $idempotencyKey,
            'severity' => ($result['ok'] ?? false) ? 'info' : 'warning',
            'payload' => ['result' => $result],
            'occurred_at' => now(),
        ]);

        return [
            'result' => $result,
            'replayed' => false,
        ];
    }
}
