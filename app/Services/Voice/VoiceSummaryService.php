<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Models\VoiceSession;

class VoiceSummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function build(VoiceSession $voiceSession): array
    {
        $voiceSession->loadMissing(['turns', 'events']);

        $latestUserTurns = $voiceSession->turns
            ->where('role', 'user')
            ->pluck('transcript')
            ->filter()
            ->take(3)
            ->implode(' | ');

        $latestAssistantTurns = $voiceSession->turns
            ->where('role', 'assistant')
            ->pluck('text')
            ->filter()
            ->take(3)
            ->implode(' | ');

        $callbackRequested = $voiceSession->events->contains(
            fn ($event): bool => $event->event_type === 'tool.create_callback_request.result'
                && (bool) ($event->payload['result']['ok'] ?? false)
        );

        $transferRequested = $voiceSession->events->contains(
            fn ($event): bool => $event->event_type === 'session.transfer_requested'
        );

        $summary = trim(implode(' ', array_filter([
            $latestUserTurns !== '' ? 'Caller said: ' . $latestUserTurns . '.' : null,
            $latestAssistantTurns !== '' ? 'Agent handled: ' . $latestAssistantTurns . '.' : null,
            $callbackRequested ? 'A callback request was created.' : null,
            $transferRequested ? 'A transfer or escalation path was requested.' : null,
        ])));

        return [
            'summary' => $summary !== '' ? $summary : 'Voice session ended without enough transcript for a richer summary.',
            'disposition' => $callbackRequested ? 'callback_requested' : ($transferRequested ? 'transferred' : 'completed'),
            'action_items' => array_values(array_filter([
                $callbackRequested ? 'Review callback request and contact the caller.' : null,
                $transferRequested ? 'Review transfer/escalation notes.' : null,
            ])),
            'booking_outcome' => $this->detectBookingOutcome($voiceSession),
            'followup_required' => $callbackRequested || $transferRequested,
            'structured_data' => [
                'last_user_turns' => $latestUserTurns,
                'last_assistant_turns' => $latestAssistantTurns,
            ],
            'generated_by' => 'laravel-summary-fallback',
            'generated_at' => now(),
        ];
    }

    private function detectBookingOutcome(VoiceSession $voiceSession): string
    {
        foreach ($voiceSession->events as $event) {
            if (!str_starts_with($event->event_type, 'tool.')) {
                continue;
            }

            if (!(bool) ($event->payload['result']['ok'] ?? false)) {
                continue;
            }

            if (str_contains($event->event_type, 'book_appointment')) {
                return 'booked';
            }

            if (str_contains($event->event_type, 'reschedule_appointment')) {
                return 'rescheduled';
            }

            if (str_contains($event->event_type, 'cancel_appointment')) {
                return 'cancelled';
            }
        }

        return 'none';
    }
}
