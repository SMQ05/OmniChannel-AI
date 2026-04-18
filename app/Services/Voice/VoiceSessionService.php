<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Jobs\SummarizeVoiceSessionJob;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\CallLog;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\VoiceChannel;
use App\Models\VoiceEvent;
use App\Models\VoiceSession;
use App\Models\VoiceSummary;
use App\Models\VoiceTurn;
use App\Models\VoiceUsageEvent;
use App\Services\Usage\UsageMeteringService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VoiceSessionService
{
    public function __construct(
        private readonly VoicePromptBuilder $voicePromptBuilder,
        private readonly VoiceToolCatalog $voiceToolCatalog,
        private readonly UsageMeteringService $usageMeteringService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{accepted: bool, reason: ?string, voice_session: VoiceSession, prompts: array<string, string>, tools: list<array<string, mixed>>}
     */
    public function startSession(array $attributes): array
    {
        $voiceChannel = $this->resolveVoiceChannel($attributes);
        $business = $voiceChannel->business()->with('subscription.plan')->firstOrFail();
        $patient = $this->resolvePatient($business, (string) ($attributes['from_number'] ?? ''));
        $accepted = $this->canHandleVoice($business);
        $reason = $accepted ? null : 'Voice is not enabled for this business or plan.';

        $callLog = CallLog::query()->create([
            'business_id' => $business->id,
            'voice_channel_id' => $voiceChannel->id,
            'patient_id' => $patient?->id,
            'provider_call_id' => (string) ($attributes['provider_call_id'] ?? ''),
            'status' => $accepted ? 'initiated' : 'rejected',
            'direction' => (string) ($attributes['direction'] ?? 'inbound'),
            'duration_seconds' => 0,
            'meta' => [
                'provider' => (string) ($attributes['provider'] ?? $voiceChannel->provider),
                'stream_id' => $attributes['transport_stream_id'] ?? null,
            ],
            'started_at' => now(),
        ]);

        $voiceSession = VoiceSession::query()->create([
            'uuid' => (string) Str::uuid(),
            'business_id' => $business->id,
            'voice_channel_id' => $voiceChannel->id,
            'patient_id' => $patient?->id,
            'legacy_call_log_id' => $callLog->id,
            'provider' => (string) ($attributes['provider'] ?? $voiceChannel->provider),
            'provider_call_id' => $attributes['provider_call_id'] ?? null,
            'transport_stream_id' => $attributes['transport_stream_id'] ?? null,
            'direction' => (string) ($attributes['direction'] ?? 'inbound'),
            'status' => $accepted ? 'initiated' : 'rejected',
            'from_number' => $attributes['from_number'] ?? null,
            'to_number' => $attributes['to_number'] ?? null,
            'initiated_at' => now(),
            'last_activity_at' => now(),
            'context' => [
                'business_slug' => $business->slug,
                'patient_name' => $patient?->name,
            ],
            'meta' => [
                'entrypoint' => 'voice_gateway',
                'rejection_reason' => $reason,
            ],
        ]);

        $this->storeEvent($voiceSession, [
            'event_type' => 'session.started',
            'source' => 'voice_gateway',
            'severity' => $accepted ? 'info' : 'warning',
            'payload' => [
                'accepted' => $accepted,
                'reason' => $reason,
            ],
        ]);

        $prompts = $this->voicePromptBuilder->build(
            $business,
            $voiceChannel,
            [
                'caller_number' => (string) ($attributes['from_number'] ?? ''),
                'called_number' => (string) ($attributes['to_number'] ?? ''),
                'patient_name' => (string) ($patient?->name ?? 'Guest'),
                'disclosure' => (string) config('voice_gateway.prompts.disclosure'),
                'callback_offer' => (string) config('voice_gateway.prompts.callback_offer'),
                'transfer_offer' => (string) config('voice_gateway.prompts.transfer_offer'),
            ],
        );

        Log::info('Voice session started.', [
            'voice_session_id' => $voiceSession->id,
            'business_id' => $business->id,
            'accepted' => $accepted,
            'provider_call_id' => $voiceSession->provider_call_id,
        ]);

        return [
            'accepted' => $accepted,
            'reason' => $reason,
            'voice_session' => $voiceSession->fresh(['business', 'patient', 'voiceChannel']),
            'prompts' => $prompts,
            'tools' => $this->voiceToolCatalog->definitions(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function storeEvent(VoiceSession $voiceSession, array $attributes): VoiceEvent
    {
        $event = VoiceEvent::query()->create([
            'business_id' => $voiceSession->business_id,
            'voice_session_id' => $voiceSession->id,
            'event_type' => (string) $attributes['event_type'],
            'source' => (string) ($attributes['source'] ?? 'voice_gateway'),
            'idempotency_key' => $attributes['idempotency_key'] ?? null,
            'correlation_id' => $attributes['correlation_id'] ?? null,
            'severity' => (string) ($attributes['severity'] ?? 'info'),
            'payload' => $attributes['payload'] ?? null,
            'occurred_at' => $attributes['occurred_at'] ?? now(),
        ]);

        $voiceSession->forceFill(['last_activity_at' => now()])->saveQuietly();

        return $event;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function storeTurn(VoiceSession $voiceSession, array $attributes): VoiceTurn
    {
        $sequence = (int) ($attributes['sequence'] ?? ($voiceSession->turns()->max('sequence') + 1));

        $turn = VoiceTurn::query()->create([
            'voice_session_id' => $voiceSession->id,
            'sequence' => $sequence,
            'role' => (string) $attributes['role'],
            'source' => $attributes['source'] ?? null,
            'text' => $attributes['text'] ?? null,
            'transcript' => $attributes['transcript'] ?? null,
            'tool_name' => $attributes['tool_name'] ?? null,
            'tool_status' => $attributes['tool_status'] ?? null,
            'interrupted' => (bool) ($attributes['interrupted'] ?? false),
            'latency_ms' => isset($attributes['latency_ms']) ? (int) $attributes['latency_ms'] : null,
            'started_at' => $attributes['started_at'] ?? null,
            'ended_at' => $attributes['ended_at'] ?? null,
            'meta' => $attributes['meta'] ?? null,
        ]);

        $voiceSession->forceFill(['last_activity_at' => now()])->saveQuietly();

        if ($voiceSession->legacyCallLog !== null && $turn->role === 'user' && $turn->transcript !== null) {
            $voiceSession->legacyCallLog->forceFill([
                'transcript_excerpt' => mb_substr($turn->transcript, 0, 500),
            ])->saveQuietly();
        }

        return $turn;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function storeUsage(VoiceSession $voiceSession, array $attributes): VoiceUsageEvent
    {
        $usage = VoiceUsageEvent::query()->create([
            'business_id' => $voiceSession->business_id,
            'voice_session_id' => $voiceSession->id,
            'provider' => $attributes['provider'] ?? null,
            'metric' => (string) $attributes['metric'],
            'quantity' => (float) $attributes['quantity'],
            'unit' => (string) ($attributes['unit'] ?? 'count'),
            'cost_estimate' => isset($attributes['cost_estimate']) ? (float) $attributes['cost_estimate'] : null,
            'meta' => $attributes['meta'] ?? null,
            'recorded_at' => $attributes['recorded_at'] ?? now(),
        ]);

        $metric = (string) $attributes['metric'];

        if (in_array($metric, ['voice_minutes', 'voice_call_attempts'], true)) {
            $this->usageMeteringService->record(
                business: $voiceSession->business,
                metric: $metric,
                channel: 'voice',
                quantity: (float) $attributes['quantity'],
                status: 'recorded',
                referenceType: VoiceSession::class,
                referenceId: $voiceSession->id,
                meta: $attributes['meta'] ?? [],
            );
        }

        return $usage;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function storeSummary(VoiceSession $voiceSession, array $attributes): VoiceSummary
    {
        return VoiceSummary::query()->updateOrCreate(
            ['voice_session_id' => $voiceSession->id],
            [
                'summary' => $attributes['summary'] ?? null,
                'disposition' => $attributes['disposition'] ?? null,
                'action_items' => $attributes['action_items'] ?? null,
                'booking_outcome' => $attributes['booking_outcome'] ?? null,
                'followup_required' => (bool) ($attributes['followup_required'] ?? false),
                'structured_data' => $attributes['structured_data'] ?? null,
                'generated_by' => $attributes['generated_by'] ?? 'voice_gateway',
                'generated_at' => $attributes['generated_at'] ?? now(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function completeSession(VoiceSession $voiceSession, array $attributes): VoiceSession
    {
        $endedAt = $attributes['ended_at'] ?? now();

        $voiceSession->forceFill([
            'status' => (string) ($attributes['status'] ?? 'completed'),
            'ended_at' => $endedAt,
            'last_activity_at' => $endedAt,
            'openai_session_id' => $attributes['openai_session_id'] ?? $voiceSession->openai_session_id,
            'deepgram_session_id' => $attributes['deepgram_session_id'] ?? $voiceSession->deepgram_session_id,
            'fallback_mode' => $attributes['fallback_mode'] ?? $voiceSession->fallback_mode,
            'handoff_reason' => $attributes['handoff_reason'] ?? $voiceSession->handoff_reason,
            'metrics' => array_merge($voiceSession->metrics ?? [], $attributes['metrics'] ?? []),
            'meta' => array_merge($voiceSession->meta ?? [], $attributes['meta'] ?? []),
        ])->save();

        if ($voiceSession->legacyCallLog !== null) {
            $durationSeconds = max(
                0,
                (int) ($voiceSession->initiated_at?->diffInSeconds($endedAt) ?? 0),
            );

            $voiceSession->legacyCallLog->forceFill([
                'status' => $voiceSession->status,
                'duration_seconds' => $durationSeconds,
                'ended_at' => $endedAt,
                'meta' => array_merge($voiceSession->legacyCallLog->meta ?? [], [
                    'voice_session_id' => $voiceSession->id,
                    'fallback_mode' => $voiceSession->fallback_mode,
                ]),
            ])->save();
        }

        if ($voiceSession->summary()->doesntExist()) {
            SummarizeVoiceSessionJob::dispatch($voiceSession->id);
        }

        return $voiceSession->fresh(['summary', 'usageEvents']);
    }

    private function resolveVoiceChannel(array $attributes): VoiceChannel
    {
        if (!empty($attributes['voice_channel_id'])) {
            return VoiceChannel::query()
                ->with('business')
                ->findOrFail((int) $attributes['voice_channel_id']);
        }

        $target = $this->normalizePhone((string) ($attributes['to_number'] ?? ''));

        return VoiceChannel::query()
            ->with('business')
            ->get()
            ->firstOrFail(fn (VoiceChannel $channel): bool => $this->normalizePhone((string) ($channel->phone_number ?? '')) === $target);
    }

    private function canHandleVoice(Business $business): bool
    {
        if (!$business->is_active || !config('voice_gateway.enabled')) {
            return false;
        }

        /** @var BusinessSubscription|null $subscription */
        $subscription = $business->subscription;
        /** @var Plan|null $plan */
        $plan = $subscription?->plan;

        $featureFlags = array_merge(
            $plan?->feature_flags ?? [],
            $subscription?->feature_flags ?? [],
        );

        return (bool) ($featureFlags['voice_agent'] ?? false) || (bool) ($subscription?->admin_override ?? false);
    }

    private function resolvePatient(Business $business, string $fromNumber): ?Patient
    {
        $normalized = $this->normalizePhone($fromNumber);

        if ($normalized === '') {
            return null;
        }

        $patient = Patient::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)
            ->where('business_id', $business->id)
            ->where(function ($query) use ($normalized): void {
                $query->where('phone', $normalized)
                    ->orWhere('platform_user_id', $normalized);
            })
            ->first();

        if ($patient !== null) {
            return $patient;
        }

        return Patient::query()->create([
            'business_id' => $business->id,
            'name' => 'Voice Caller',
            'phone' => $normalized,
            'platform_user_id' => $normalized,
            'platform' => 'voice',
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        $trimmed = trim($phone);

        if ($trimmed === '') {
            return '';
        }

        $digits = preg_replace('/[^\d+]/', '', $trimmed);

        return is_string($digits) ? $digits : '';
    }
}
