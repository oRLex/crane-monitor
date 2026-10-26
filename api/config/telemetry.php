<?php

return [
    // rabbitmq: telemetry:simulate publishes, telemetry:consume processes (separate processes).
    // sync: telemetry:simulate processes in-process — no broker needed.
    'bus' => env('TELEMETRY_BUS', 'sync'),
    'tick_ms' => (int) env('TELEMETRY_TICK_MS', 1000),
    'history_points' => 120,
    // Cache store for the live window (latest point + short history per crane).
    'store' => env('TELEMETRY_STORE', env('CACHE_STORE', 'redis')),
    'rabbitmq' => [
        'url' => env('RABBITMQ_URL', 'amqp://guest:guest@127.0.0.1:5672/%2f'),
        'exchange' => 'telemetry',
        'queue' => 'telemetry.processor',
    ],
];
