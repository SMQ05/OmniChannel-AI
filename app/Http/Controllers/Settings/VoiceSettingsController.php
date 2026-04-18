<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\VoiceChannel;
use App\Services\Voice\VoiceConfigurationService;
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
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
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
        $business = $request->user()->business;
        abort_unless($voiceChannel->business_id === $business->id, 404);

        $voiceChannel->update([
            'is_enabled' => !$voiceChannel->is_enabled,
        ]);

        return redirect()->route('settings.voice')
            ->with('success', 'Voice channel ' . ($voiceChannel->is_enabled ? 'enabled.' : 'disabled.'));
    }
}
