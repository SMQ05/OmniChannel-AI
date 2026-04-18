<?php

declare(strict_types=1);

namespace App\Voice\Providers;

use App\Exceptions\VoiceProviderException;
use App\Voice\Contracts\LlmStreamInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OpenRouterLlmStream implements LlmStreamInterface
{
    public function stream(array $messages): iterable
    {
        $apiKey = (string) config('voice.providers.llm.openrouter.api_key');
        $model = (string) config('voice.providers.llm.openrouter.model', 'openai/gpt-4.1-mini');

        if ($apiKey === '') {
            throw VoiceProviderException::missingConfiguration('OpenRouter', 'OPENROUTER_API_KEY is required');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'stream' => false,
            ]);

        if ($response->failed()) {
            throw VoiceProviderException::requestFailed('OpenRouter', $response->json('error.message') ?? $response->body());
        }

        $content = (string) data_get($response->json(), 'choices.0.message.content', '');

        foreach (Str::of($content)->split('/(?<=\S)\s+/') as $chunk) {
            yield (string) $chunk;
        }
    }
}
