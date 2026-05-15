<?php

declare(strict_types=1);

return [
    'idempotency' => [
        'store' => env('IDEMPOTENCY_CACHE_STORE', 'redis'),
        'ttl_seconds' => (int) env('IDEMPOTENCY_TTL_SECONDS', 86400),
        'lock_ttl_seconds' => (int) env('IDEMPOTENCY_LOCK_TTL_SECONDS', 300),
        'key_prefix' => env('IDEMPOTENCY_KEY_PREFIX', 'idem:'),
    ],

    'mock' => [
        'failure_rate' => (float) env('MOCK_PROVIDER_FAILURE_RATE', 0),
        'latency_ms' => (int) env('MOCK_PROVIDER_LATENCY_MS', 0),
        'email_use_mailpit' => filter_var(env('MOCK_EMAIL_USE_MAILPIT', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'queues' => [
        'high' => env('NOTIFICATIONS_QUEUE_HIGH', 'notifications.high'),
        'low' => env('NOTIFICATIONS_QUEUE_LOW', 'notifications.low'),
    ],

    'recipients' => [
        'max_per_batch' => (int) env('NOTIFICATIONS_MAX_RECIPIENTS', 10000),
    ],

    'message' => [
        'max_length' => (int) env('NOTIFICATIONS_MESSAGE_MAX_LENGTH', 4096),
    ],
];
