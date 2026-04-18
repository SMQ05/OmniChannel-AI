<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Exceptions\VoiceProviderException;
use App\Ai\Agents\AppointmentAgent;
use App\Http\Controllers\Controller;
use App\Models\VoiceChannel;
use App\Services\Usage\UsageMeteringService;
use App\Services\Voice\VoiceConfigurationService;
use App\Services\Voice\VoiceProviderResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VoiceSettingsController extends Controller
{
    public function edit(Request $request, VoiceConfigurationService $voiceConfigurationService): View
    {
        $business = $request->user()->business;
        $voiceState = $voiceConfigurationService->forBusiness($business);

        return view('settings.voice', [
            'business' => $business,
            'voiceState' => $voiceState,
            'managedByAdmin' => $this->managedByAdmin($request),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureManagedByAdmin($request);

        $validated = $request->validate([
            'voice.enabled' => ['boolean'],
            'voice.transport_provider' => ['nullable', Rule::in(['telnyx', 'sip'])],
            'voice.stt_provider' => ['nullable', Rule::in(['deepgram'])],
            'voice.llm_provider' => ['nullable', Rule::in(['openrouter'])],
            'voice.tts_provider' => ['nullable', Rule::in(['elevenlabs'])],
            'voice.greeting_message' => ['nullable', 'string', 'max:1000'],
            'voice.handoff_message' => ['nullable', 'string', 'max:1000'],
            'voice.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $business = $request->user()->business;
        $existing = $business->channel_config ?? [];
        $voice = array_merge($existing['voice'] ?? [], $validated['voice'] ?? []);
        $voice['enabled'] = $request->boolean('voice.enabled');

        $business->channel_config = array_merge($existing, ['voice' => $voice]);
        $business->save();

        return redirect()->route('settings.voice')->with('success', 'Voice settings saved.');
    }

    public function storeChannel(Request $request): RedirectResponse
    {
        $this->ensureManagedByAdmin($request);

        $validated = $request->validate([
            'channel_id' => ['nullable', 'integer'],
            'provider' => ['required', Rule::in(['telnyx', 'sip'])],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'is_enabled' => ['boolean'],
            'config.label' => ['nullable', 'string', 'max:100'],
            'config.inbound_flow' => ['nullable', 'string', 'max:100'],
        ]);

        $business = $request->user()->business;
        $channel = isset($validated['channel_id'])
            ? VoiceChannel::query()->where('business_id', $business->id)->findOrFail($validated['channel_id'])
            : new VoiceChannel(['business_id' => $business->id]);

        $channel->fill([
            'provider' => $validated['provider'],
            'phone_number' => $validated['phone_number'] ?? null,
            'is_enabled' => $request->boolean('is_enabled'),
            'config' => array_filter($validated['config'] ?? [], static fn ($value): bool => filled($value)),
        ]);
        $channel->save();

        return redirect()->route('settings.voice')->with('success', 'Voice channel saved.');
    }

    public function toggleChannel(Request $request, VoiceChannel $voiceChannel): RedirectResponse
    {
        $this->ensureManagedByAdmin($request);

        $business = $request->user()->business;
        abort_unless($voiceChannel->business_id === $business->id, 404);

        $voiceChannel->update([
            'is_enabled' => !$voiceChannel->is_enabled,
        ]);

        return redirect()->route('settings.voice')
            ->with('success', 'Voice channel ' . ($voiceChannel->is_enabled ? 'enabled.' : 'disabled.'));
    }

    public function testProvider(
        Request $request,
        string $component,
        VoiceProviderResolver $voiceProviderResolver,
        UsageMeteringService $usageMeteringService,
        AppointmentAgent $appointmentAgent,
    ): RedirectResponse {
        $this->ensureManagedByAdmin($request);

        $business = $request->user()->business;
        abort_unless(in_array($component, ['transport', 'stt', 'llm', 'tts'], true), 404);

        try {
            $result = match ($component) {
                'transport' => $this->testTransport($request, $business, $voiceProviderResolver, $usageMeteringService),
                'stt' => $this->testSpeechToText($request, $business, $voiceProviderResolver, $usageMeteringService),
                'llm' => $this->testLlm($request, $business, $usageMeteringService, $appointmentAgent),
                'tts' => $this->testTextToSpeech($request, $business, $voiceProviderResolver, $usageMeteringService),
            };
        } catch (VoiceProviderException $exception) {
            return redirect()->route('settings.voice')->withErrors(['voice_test' => $exception->getMessage()]);
        }

        return redirect()->route('settings.voice')->with('success', $result);
    }

    private function testTransport(
        Request $request,
        \App\Models\Business $business,
        VoiceProviderResolver $resolver,
        UsageMeteringService $usageMeteringService,
    ): string {
        $validated = $request->validate([
            'to' => ['required', 'string', 'max:64'],
            'from' => ['required', 'string', 'max:64'],
        ]);

        $result = $resolver->transport($business)->initiateCall($validated['to'], $validated['from'], [
            'client_state' => base64_encode('kynex-voice-test'),
        ]);

        $usageMeteringService->record(
            business: $business,
            metric: 'voice_call_attempts',
            channel: 'voice',
            quantity: 1,
            status: 'test',
            meta: ['provider' => $result['provider'] ?? 'voice'],
        );

        return sprintf('Voice transport test accepted via %s.', ucfirst((string) ($result['provider'] ?? 'provider')));
    }

    private function testSpeechToText(
        Request $request,
        \App\Models\Business $business,
        VoiceProviderResolver $resolver,
        UsageMeteringService $usageMeteringService,
    ): string {
        $validated = $request->validate([
            'audio_reference' => ['required', 'url', 'max:2000'],
        ]);

        $transcript = $resolver->stt($business)->transcribe($validated['audio_reference']);

        $usageMeteringService->record(
            business: $business,
            metric: 'voice_minutes',
            channel: 'voice',
            quantity: 0,
            status: 'test',
            meta: ['transcript_excerpt' => mb_substr($transcript, 0, 120)],
        );

        return 'STT test transcript: ' . ($transcript !== '' ? mb_substr($transcript, 0, 160) : 'empty transcript returned.');
    }

    private function testLlm(
        Request $request,
        \App\Models\Business $business,
        UsageMeteringService $usageMeteringService,
        AppointmentAgent $appointmentAgent,
    ): string {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:1000'],
        ]);

        $response = $appointmentAgent->respond(
            business: $business,
            messages: [
            ['role' => 'user', 'content' => $validated['prompt']],
            ],
            availableSlots: [],
            providerOverride: $business->channel_config['voice']['llm_provider'] ?? config('voice.default_llm'),
        );
        $reply = $response['reply_text'];

        $usageMeteringService->record(
            business: $business,
            metric: 'llm_tokens_estimated',
            channel: 'voice',
            quantity: max(1, (int) ceil(strlen($reply) / 4)),
            status: 'test',
            meta: ['provider' => $business->channel_config['voice']['llm_provider'] ?? config('voice.default_llm')],
        );

        return 'LLM voice test reply: ' . ($reply !== '' ? mb_substr($reply, 0, 160) : 'empty response returned.');
    }

    private function testTextToSpeech(
        Request $request,
        \App\Models\Business $business,
        VoiceProviderResolver $resolver,
        UsageMeteringService $usageMeteringService,
    ): string {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:1000'],
        ]);

        $audio = $resolver->tts($business)->synthesize($validated['text']);

        $usageMeteringService->record(
            business: $business,
            metric: 'voice_minutes',
            channel: 'voice',
            quantity: 0,
            status: 'test',
            meta: ['audio_bytes_base64' => strlen($audio)],
        );

        return 'TTS voice test generated audio payload successfully.';
    }

    private function managedByAdmin(Request $request): bool
    {
        return $request->session()->has('impersonating_as') || $request->user()?->role === 'super_admin';
    }

    private function ensureManagedByAdmin(Request $request): void
    {
        if (!$this->managedByAdmin($request)) {
            throw new AuthorizationException('Voice setup is managed by Kynex Solutions.');
        }
    }
}
