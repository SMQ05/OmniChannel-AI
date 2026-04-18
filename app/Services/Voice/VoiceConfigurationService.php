<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Models\Business;
use App\Models\CallLog;
use App\Models\VoiceChannel;
use App\Services\Usage\UsageSummaryService;
use Illuminate\Support\Collection;

class VoiceConfigurationService
{
    public function __construct(
        private readonly UsageSummaryService $usageSummaryService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forBusiness(Business $business): array
    {
        $settings = $business->channel_config['voice'] ?? [];
        $platform = $this->platformSummary();
        $featureFlags = $this->usageSummaryService->mergedFeatureFlags($business);
        $selectedProviders = [
            'transport' => $this->normalizeProvider($settings['transport_provider'] ?? config('voice.default_transport')),
            'stt' => $this->normalizeProvider($settings['stt_provider'] ?? config('voice.default_stt')),
            'llm' => $this->normalizeProvider($settings['llm_provider'] ?? config('voice.default_llm')),
            'tts' => $this->normalizeProvider($settings['tts_provider'] ?? config('voice.default_tts')),
        ];

        /** @var Collection<int, VoiceChannel> $channels */
        $channels = $business->voiceChannels()->latest()->get();
        $enabledChannels = $channels->where('is_enabled', true);
        $issues = [];

        if (!config('kynex.features.voice_agent')) {
            $issues[] = 'Voice is globally disabled. Turn on FEATURE_VOICE_AGENT to expose live call handling.';
        }

        if (!(bool) ($featureFlags['voice_agent'] ?? false)) {
            $issues[] = 'The current plan does not include the voice agent feature.';
        }

        if (($settings['enabled'] ?? false) && $enabledChannels->isEmpty()) {
            $issues[] = 'Voice is enabled for this business but there is no active voice channel.';
        }

        foreach ($selectedProviders as $kind => $provider) {
            if ($provider === null) {
                $issues[] = sprintf('Select a %s provider before enabling live voice.', strtoupper($kind));

                continue;
            }

            if (!(bool) data_get($platform, "providers.$kind.$provider.ready")) {
                $issues[] = sprintf('%s provider "%s" is selected but not configured at platform level.', strtoupper($kind), $provider);
            }
        }

        $recentCalls = CallLog::query()
            ->where('business_id', $business->id)
            ->latest('started_at')
            ->limit(10)
            ->get();

        $usageSummary = $this->usageSummaryService->forBusiness($business);
        $voiceMinutesMetric = collect($usageSummary['metrics'])->firstWhere('metric', 'voice_minutes');
        $voiceMinutesUsed = (float) ($voiceMinutesMetric['used'] ?? 0);

        return [
            'settings' => array_merge([
                'enabled' => false,
                'transport_provider' => $selectedProviders['transport'],
                'stt_provider' => $selectedProviders['stt'],
                'llm_provider' => $selectedProviders['llm'],
                'tts_provider' => $selectedProviders['tts'],
                'greeting_message' => null,
                'handoff_message' => null,
                'notes' => null,
            ], $settings),
            'platform' => $platform,
            'feature_flags' => $featureFlags,
            'plan_supports_voice' => (bool) ($featureFlags['voice_agent'] ?? false),
            'ready' => empty($issues),
            'issues' => $issues,
            'channels' => $channels,
            'recent_calls' => $recentCalls,
            'summary' => [
                'total_channels' => $channels->count(),
                'active_channels' => $enabledChannels->count(),
                'recent_calls' => $recentCalls->count(),
                'voice_minutes_used' => $voiceMinutesUsed,
            ],
            'options' => $this->providerOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function platformSummary(): array
    {
        $providers = [];

        foreach ($this->providerOptions() as $kind => $options) {
            foreach (array_keys($options) as $provider) {
                $providers[$kind][$provider] = [
                    'label' => $options[$provider],
                    'ready' => $this->providerConfigured($kind, $provider),
                ];
            }
        }

        return [
            'feature_enabled' => (bool) config('kynex.features.voice_agent'),
            'defaults' => [
                'transport' => $this->normalizeProvider(config('voice.default_transport')),
                'stt' => $this->normalizeProvider(config('voice.default_stt')),
                'llm' => $this->normalizeProvider(config('voice.default_llm')),
                'tts' => $this->normalizeProvider(config('voice.default_tts')),
            ],
            'providers' => $providers,
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function providerOptions(): array
    {
        return [
            'transport' => [
                'telnyx' => 'Telnyx',
                'sip' => 'SIP',
            ],
            'stt' => [
                'deepgram' => 'Deepgram',
            ],
            'llm' => [
                'openrouter' => 'OpenRouter',
            ],
            'tts' => [
                'elevenlabs' => 'ElevenLabs',
            ],
        ];
    }

    private function normalizeProvider(mixed $provider): ?string
    {
        if (!is_string($provider) || $provider === '' || $provider === 'null') {
            return null;
        }

        return $provider;
    }

    private function providerConfigured(string $kind, string $provider): bool
    {
        return match ("{$kind}:{$provider}") {
            'transport:telnyx' => filled(config('voice.providers.transport.telnyx.api_key'))
                && filled(config('voice.providers.transport.telnyx.connection_id')),
            'transport:sip' => filled(config('voice.providers.transport.sip.server'))
                && filled(config('voice.providers.transport.sip.username'))
                && filled(config('voice.providers.transport.sip.password')),
            'stt:deepgram' => filled(config('voice.providers.stt.deepgram.api_key')),
            'llm:openrouter' => filled(config('voice.providers.llm.openrouter.api_key')),
            'tts:elevenlabs' => filled(config('voice.providers.tts.elevenlabs.api_key'))
                && filled(config('voice.providers.tts.elevenlabs.voice_id')),
            default => false,
        };
    }
}
