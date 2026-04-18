<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\VoiceSession;
use App\Services\Voice\VoiceSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceSessionController extends Controller
{
    public function __construct(
        private readonly VoiceSessionService $voiceSessionService,
    ) {}

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'voice_channel_id' => ['nullable', 'integer', 'exists:voice_channels,id'],
            'provider' => ['required', 'string', 'max:32'],
            'provider_call_id' => ['nullable', 'string', 'max:255'],
            'transport_stream_id' => ['nullable', 'string', 'max:255'],
            'direction' => ['required', 'in:inbound,outbound'],
            'from_number' => ['nullable', 'string', 'max:50'],
            'to_number' => ['nullable', 'string', 'max:50'],
        ]);

        $session = $this->voiceSessionService->startSession($validated);

        return response()->json([
            'accepted' => $session['accepted'],
            'reason' => $session['reason'],
            'voice_session' => [
                'id' => $session['voice_session']->id,
                'uuid' => $session['voice_session']->uuid,
                'business_id' => $session['voice_session']->business_id,
                'voice_channel_id' => $session['voice_session']->voice_channel_id,
                'patient_id' => $session['voice_session']->patient_id,
            ],
            'prompts' => $session['prompts'],
            'tools' => $session['tools'],
        ]);
    }

    public function storeEvent(Request $request, VoiceSession $voiceSession): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => ['required', 'string', 'max:80'],
            'source' => ['nullable', 'string', 'max:32'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'correlation_id' => ['nullable', 'string', 'max:255'],
            'severity' => ['nullable', 'string', 'max:16'],
            'payload' => ['nullable', 'array'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $event = $this->voiceSessionService->storeEvent($voiceSession, $validated);

        return response()->json(['id' => $event->id], 201);
    }

    public function storeTurn(Request $request, VoiceSession $voiceSession): JsonResponse
    {
        $validated = $request->validate([
            'sequence' => ['nullable', 'integer', 'min:1'],
            'role' => ['required', 'string', 'max:24'],
            'source' => ['nullable', 'string', 'max:32'],
            'text' => ['nullable', 'string'],
            'transcript' => ['nullable', 'string'],
            'tool_name' => ['nullable', 'string', 'max:64'],
            'tool_status' => ['nullable', 'string', 'max:32'],
            'interrupted' => ['nullable', 'boolean'],
            'latency_ms' => ['nullable', 'integer', 'min:0'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'meta' => ['nullable', 'array'],
        ]);

        $turn = $this->voiceSessionService->storeTurn($voiceSession, $validated);

        return response()->json(['id' => $turn->id], 201);
    }

    public function storeUsage(Request $request, VoiceSession $voiceSession): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['nullable', 'string', 'max:32'],
            'metric' => ['required', 'string', 'max:64'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:24'],
            'cost_estimate' => ['nullable', 'numeric', 'min:0'],
            'meta' => ['nullable', 'array'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $usage = $this->voiceSessionService->storeUsage($voiceSession, $validated);

        return response()->json(['id' => $usage->id], 201);
    }

    public function storeSummary(Request $request, VoiceSession $voiceSession): JsonResponse
    {
        $validated = $request->validate([
            'summary' => ['nullable', 'string'],
            'disposition' => ['nullable', 'string', 'max:32'],
            'action_items' => ['nullable', 'array'],
            'booking_outcome' => ['nullable', 'string', 'max:32'],
            'followup_required' => ['nullable', 'boolean'],
            'structured_data' => ['nullable', 'array'],
            'generated_by' => ['nullable', 'string', 'max:64'],
            'generated_at' => ['nullable', 'date'],
        ]);

        $summary = $this->voiceSessionService->storeSummary($voiceSession, $validated);

        return response()->json(['id' => $summary->id], 201);
    }

    public function complete(Request $request, VoiceSession $voiceSession): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:32'],
            'ended_at' => ['nullable', 'date'],
            'openai_session_id' => ['nullable', 'string', 'max:255'],
            'deepgram_session_id' => ['nullable', 'string', 'max:255'],
            'fallback_mode' => ['nullable', 'string', 'max:32'],
            'handoff_reason' => ['nullable', 'string', 'max:255'],
            'metrics' => ['nullable', 'array'],
            'meta' => ['nullable', 'array'],
        ]);

        $session = $this->voiceSessionService->completeSession($voiceSession, $validated);

        return response()->json([
            'id' => $session->id,
            'status' => $session->status,
            'summary_id' => $session->summary?->id,
        ]);
    }
}
