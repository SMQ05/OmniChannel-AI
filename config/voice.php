<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('FEATURE_VOICE_AGENT', false),
    'default_transport' => env('VOICE_TRANSPORT', 'null'),
    'default_stt' => env('VOICE_STT_PROVIDER', 'null'),
    'default_llm' => env('VOICE_LLM_PROVIDER', 'null'),
    'default_tts' => env('VOICE_TTS_PROVIDER', 'null'),
    'providers' => [
        'transport' => [
            'telnyx' => [
                'api_key' => env('TELNYX_API_KEY'),
                'connection_id' => env('TELNYX_CONNECTION_ID'),
            ],
            'sip' => [
                'server' => env('VOICE_SIP_SERVER'),
                'username' => env('VOICE_SIP_USERNAME'),
                'password' => env('VOICE_SIP_PASSWORD'),
            ],
        ],
        'stt' => [
            'deepgram' => [
                'api_key' => env('DEEPGRAM_API_KEY'),
                'model' => env('DEEPGRAM_MODEL', 'nova-2'),
            ],
        ],
        'llm' => [
            'openrouter' => [
                'api_key' => env('OPENROUTER_API_KEY'),
                'model' => env('VOICE_LLM_MODEL', 'openai/gpt-4.1-mini'),
            ],
        ],
        'tts' => [
            'elevenlabs' => [
                'api_key' => env('ELEVENLABS_API_KEY'),
                'voice_id' => env('ELEVENLABS_VOICE_ID'),
            ],
        ],
    ],
];
