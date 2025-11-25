<?php

return [
    'project_health' => [
        'monitoring_enabled' => env('PROJECT_HEALTH_MONITORING_ENABLED', true),
        'log_channel' => env('PROJECT_HEALTH_LOG_CHANNEL', null),
        'sample_rate' => (float) env('PROJECT_HEALTH_MONITORING_SAMPLE_RATE', 1.0),
        'log_when_empty' => (bool) env('PROJECT_HEALTH_MONITORING_LOG_WHEN_EMPTY', false),
        'cache_enabled' => env('PROJECT_HEALTH_CACHE_ENABLED', false),
        'cache_ttl_seconds' => (int) env('PROJECT_HEALTH_CACHE_TTL_SECONDS', 60),
        'snapshot_schedule_enabled' => (bool) env('PROJECT_HEALTH_SNAPSHOT_SCHEDULE_ENABLED', false),
        'snapshot_schedule_cron' => env('PROJECT_HEALTH_SNAPSHOT_SCHEDULE_CRON', '0 2 * * *'),
    ],
];

