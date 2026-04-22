<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Monitoring Snapshots (Derived Aggregates Only)
    |--------------------------------------------------------------------------
    |
    | Snapshots are summaries for control-plane visibility. They must never
    | become runtime truth for messaging, voice, webhook dispatch, or billing.
    |
    */
    'monitoring' => [
        'platform_snapshot_ttl_hours' => (int) env('DATA_CONTROLS_PLATFORM_SNAPSHOT_TTL_HOURS', 24),
        'tenant_snapshot_ttl_hours' => (int) env('DATA_CONTROLS_TENANT_SNAPSHOT_TTL_HOURS', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Governance Exports
    |--------------------------------------------------------------------------
    */
    'exports' => [
        'disk' => env('DATA_CONTROLS_EXPORT_DISK', 'local'),
        'prefix' => env('DATA_CONTROLS_EXPORT_PREFIX', 'private/governance/exports'),
        'expires_minutes' => (int) env('DATA_CONTROLS_EXPORT_EXPIRES_MINUTES', 240),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention Defaults and Guardrails
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'defaults' => [
            'conversation_logs_days' => (int) env('RETENTION_CONVERSATION_LOGS_DAYS', 365),
            'inbound_webhooks_days' => (int) env('RETENTION_INBOUND_WEBHOOKS_DAYS', 120),
            'outbound_attempts_days' => (int) env('RETENTION_OUTBOUND_ATTEMPTS_DAYS', 120),
            'voice_events_days' => (int) env('RETENTION_VOICE_EVENTS_DAYS', 180),
        ],
        'bounds' => [
            'min_days' => (int) env('RETENTION_MIN_DAYS', 30),
            'max_days' => (int) env('RETENTION_MAX_DAYS', 1460),
        ],
    ],
];
