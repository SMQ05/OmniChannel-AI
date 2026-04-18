<?php

declare(strict_types=1);

namespace App\Voice\Providers;

use App\Exceptions\VoiceProviderException;
use App\Voice\Contracts\TextToSpeechInterface;
use Illuminate\Support\Facades\Http;

class ElevenLabsTextToSpeech implements TextToSpeechInterface
{
    public function synthesize(string $text): string
    {
        $apiKey = (string) config('voice.providers.tts.elevenlabs.api_key');
        $voiceId = (string) config('voice.providers.tts.elevenlabs.voice_id');

        if ($apiKey === '' || $voiceId === '') {
            throw VoiceProviderException::missingConfiguration('ElevenLabs', 'ELEVENLABS_API_KEY and ELEVENLABS_VOICE_ID are required');
        }

        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
            'Accept' => 'audio/mpeg',
        ])->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", [
            'text' => $text,
            'model_id' => 'eleven_flash_v2_5',
        ]);

        if ($response->failed()) {
            throw VoiceProviderException::requestFailed('ElevenLabs', $response->json('detail.message') ?? $response->body());
        }

        return base64_encode($response->body());
    }
}
