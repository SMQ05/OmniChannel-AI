<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('FEATURE_VOICE_AGENT', false),

    'internal_api' => [
        'shared_secret' => env('VOICE_GATEWAY_SHARED_SECRET'),
        'clock_skew_seconds' => (int) env('VOICE_GATEWAY_CLOCK_SKEW_SECONDS', 300),
    ],

    'realtime' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_REALTIME_BASE_URL', 'wss://api.openai.com/v1/realtime'),
        'primary_model' => env('VOICE_GATEWAY_PRIMARY_MODEL', 'gpt-realtime'),
        'fallback_model' => env('VOICE_GATEWAY_FALLBACK_MODEL', 'gpt-realtime-mini'),
        'voice' => env('VOICE_GATEWAY_OPENAI_VOICE', 'alloy'),
        'temperature' => (float) env('VOICE_GATEWAY_TEMPERATURE', 0.6),
        'max_output_tokens' => (int) env('VOICE_GATEWAY_MAX_OUTPUT_TOKENS', 700),
    ],

    'turns' => [
        'silence_timeout_ms' => (int) env('VOICE_GATEWAY_SILENCE_TIMEOUT_MS', 6000),
        'reprompt_limit' => (int) env('VOICE_GATEWAY_REPROMPT_LIMIT', 2),
    ],

    'deepgram' => [
        'shadow_transcription' => (bool) env('VOICE_GATEWAY_DEEPGRAM_SHADOW', true),
    ],

    'fallback' => [
        'whatsapp_followup_enabled' => (bool) env('VOICE_GATEWAY_FOLLOWUP_WHATSAPP', true),
        'callback_enabled' => (bool) env('VOICE_GATEWAY_CALLBACK_ENABLED', true),
        'transfer_enabled' => (bool) env('VOICE_GATEWAY_TRANSFER_ENABLED', true),
    ],

    'prompts' => [
        'global' => env(
            'VOICE_GATEWAY_GLOBAL_PROMPT',
            'You are Kynex Voice, an AI front-desk agent for clinics and service businesses. Keep responses brief, warm, and operationally correct.'
        ),
        'policy' => env(
            'VOICE_GATEWAY_POLICY_PROMPT',
            'Always disclose you are an AI assistant if asked. Never claim a booking, cancellation, or reschedule succeeded unless the tool result confirms it. Do not provide medical diagnosis or unsupported medical advice. If emergency language is detected, instruct the caller to contact local emergency services immediately and trigger escalation.'
        ),
        'disclosure' => env(
            'VOICE_GATEWAY_DISCLOSURE',
            'Hi, this is the Kynex AI front desk assistant for the clinic.'
        ),
        'reprompt' => env(
            'VOICE_GATEWAY_REPROMPT_TEXT',
            'I’m still here. How can I help with your appointment today?'
        ),
        'callback_offer' => env(
            'VOICE_GATEWAY_CALLBACK_TEXT',
            'If you prefer, I can ask the clinic team to call you back.'
        ),
        'transfer_offer' => env(
            'VOICE_GATEWAY_TRANSFER_TEXT',
            'If needed, I can try to transfer you to the team or create a callback request.'
        ),
    ],

    'safety' => [
        'emergency_keywords' => array_values(array_filter(array_map(
            static fn (string $keyword): string => trim($keyword),
            explode(',', (string) env('VOICE_GATEWAY_EMERGENCY_KEYWORDS', 'emergency,chest pain,stroke,can’t breathe,cant breathe,bleeding heavily,suicidal'))
        ))),
    ],
];
