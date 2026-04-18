<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Models\Business;
use App\Voice\Contracts\LlmStreamInterface;
use App\Voice\Contracts\SpeechToTextInterface;
use App\Voice\Contracts\TelephonyTransportInterface;
use App\Voice\Contracts\TextToSpeechInterface;
use App\Voice\Null\NullLlmStream;
use App\Voice\Null\NullSpeechToText;
use App\Voice\Null\NullTelephonyTransport;
use App\Voice\Null\NullTextToSpeech;
use App\Voice\Providers\DeepgramSpeechToText;
use App\Voice\Providers\ElevenLabsTextToSpeech;
use App\Voice\Providers\OpenRouterLlmStream;
use App\Voice\Providers\SipTransport;
use App\Voice\Providers\TelnyxTransport;
use Illuminate\Contracts\Container\Container;

class VoiceProviderResolver
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function transport(?Business $business = null): TelephonyTransportInterface
    {
        return match ($this->businessProvider($business, 'transport_provider', config('voice.default_transport'))) {
            'telnyx' => $this->container->make(TelnyxTransport::class),
            'sip' => $this->container->make(SipTransport::class),
            default => $this->container->make(NullTelephonyTransport::class),
        };
    }

    public function stt(?Business $business = null): SpeechToTextInterface
    {
        return match ($this->businessProvider($business, 'stt_provider', config('voice.default_stt'))) {
            'deepgram' => $this->container->make(DeepgramSpeechToText::class),
            default => $this->container->make(NullSpeechToText::class),
        };
    }

    public function llm(?Business $business = null): LlmStreamInterface
    {
        return match ($this->businessProvider($business, 'llm_provider', config('voice.default_llm'))) {
            'openrouter' => $this->container->make(OpenRouterLlmStream::class),
            default => $this->container->make(NullLlmStream::class),
        };
    }

    public function tts(?Business $business = null): TextToSpeechInterface
    {
        return match ($this->businessProvider($business, 'tts_provider', config('voice.default_tts'))) {
            'elevenlabs' => $this->container->make(ElevenLabsTextToSpeech::class),
            default => $this->container->make(NullTextToSpeech::class),
        };
    }

    private function businessProvider(?Business $business, string $key, mixed $fallback): ?string
    {
        $provider = $business?->channel_config['voice'][$key] ?? $fallback;

        return is_string($provider) && $provider !== '' && $provider !== 'null'
            ? $provider
            : null;
    }
}
