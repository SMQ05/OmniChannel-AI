<?php

declare(strict_types=1);

return [
    'queues' => [
        'named' => [
            'webhooks' => [
                'connection' => env('QUEUE_WEBHOOK_CONNECTION', env('QUEUE_CONNECTION', 'database')),
                'queue' => env('QUEUE_WEBHOOK_NAME', 'webhooks'),
            ],
            'integrations' => [
                'connection' => env('QUEUE_INTEGRATIONS_CONNECTION', env('QUEUE_CONNECTION', 'database')),
                'queue' => env('QUEUE_INTEGRATIONS_NAME', 'integrations'),
            ],
            'reminders' => [
                'connection' => env('QUEUE_REMINDERS_CONNECTION', env('QUEUE_CONNECTION', 'database')),
                'queue' => env('QUEUE_REMINDERS_NAME', 'reminders'),
            ],
        ],
        'worker_heartbeat_ttl' => (int) env('QUEUE_WORKER_HEARTBEAT_TTL', 300),
    ],
    'usage' => [
        'warning_threshold' => (float) env('USAGE_WARNING_THRESHOLD', 0.8),
    ],
    'features' => [
        'voice_agent' => (bool) env('FEATURE_VOICE_AGENT', false),
        'usage_enforcement' => (bool) env('FEATURE_USAGE_ENFORCEMENT', true),
    ],
    'app_version' => env('APP_VERSION'),
    'git_commit' => env('GIT_COMMIT_SHA', env('RENDER_GIT_COMMIT')),
];
