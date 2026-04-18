<?php

declare(strict_types=1);

namespace App\Voice\Providers;

use App\Exceptions\VoiceProviderException;
use App\Voice\Contracts\SpeechToTextInterface;
use Illuminate\Support\Facades\Http;

class DeepgramSpeechToText implements SpeechToTextInterface
{
    public function transcribe(string $audioReference): string
    {
        $apiKey = (string) config('voice.providers.stt.deepgram.api_key');
        $model = (string) config('voice.providers.stt.deepgram.model', 'nova-2');

        if ($apiKey === '') {
            throw VoiceProviderException::missingConfiguration('Deepgram', 'DEEPGRAM_API_KEY is required');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Token ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post(
            sprintf('https://api.deepgram.com/v1/listen?model=%s&smart_format=true', urlencode($model)),
            ['url' => $audioReference],
        );

        if ($response->failed()) {
            throw VoiceProviderException::requestFailed('Deepgram', $response->json('err_msg') ?? $response->body());
        }

        return (string) data_get($response->json(), 'results.channels.0.alternatives.0.transcript', '');
    }
}
