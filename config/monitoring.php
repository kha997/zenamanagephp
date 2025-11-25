<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for observability and metrics collection.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Metrics Collection
    |--------------------------------------------------------------------------
    |
    | Enable/disable metrics collection for different components.
    |
    */
    'metrics' => [
        'enabled' => env('METRICS_ENABLED', true),
        'api' => [
            'enabled' => env('METRICS_API_ENABLED', true),
            'collect_latency' => env('METRICS_COLLECT_LATENCY', true),
            'collect_error_rate' => env('METRICS_COLLECT_ERROR_RATE', true),
        ],
        'queue' => [
            'enabled' => env('METRICS_QUEUE_ENABLED', true),
        ],
        'database' => [
            'enabled' => env('METRICS_DATABASE_ENABLED', true),
            'slow_query_threshold_ms' => env('METRICS_SLOW_QUERY_THRESHOLD_MS', 300),
        ],
        'cache' => [
            'enabled' => env('METRICS_CACHE_ENABLED', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Budgets
    |--------------------------------------------------------------------------
    |
    | SLO targets for performance monitoring.
    |
    */
    'budgets' => [
        'api' => [
            'p95_latency_ms' => env('API_P95_LATENCY_BUDGET_MS', 300),
            'error_rate_threshold' => env('API_ERROR_RATE_THRESHOLD', 0.01), // 1%
        ],
        'page' => [
            'p95_latency_ms' => env('PAGE_P95_LATENCY_BUDGET_MS', 500),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerting
    |--------------------------------------------------------------------------
    |
    | Configuration for alerts and notifications.
    |
    */
    'alerts' => [
        'enabled' => env('ALERTS_ENABLED', true),
        'cache_failure' => [
            'enabled' => env('ALERT_CACHE_FAILURE', true),
            'immediate' => true,
        ],
        'db_slowdown' => [
            'enabled' => env('ALERT_DB_SLOWDOWN', true),
            'threshold_ms' => env('ALERT_DB_SLOWDOWN_THRESHOLD_MS', 300),
        ],
        'error_spike' => [
            'enabled' => env('ALERT_ERROR_SPIKE', true),
            'threshold_rate' => env('ALERT_ERROR_SPIKE_THRESHOLD', 0.01), // 1%
        ],
        'queue_backlog' => [
            'enabled' => env('ALERT_QUEUE_BACKLOG', true),
            'threshold' => env('ALERT_QUEUE_BACKLOG_THRESHOLD', 1000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboards
    |--------------------------------------------------------------------------
    |
    | Configuration for metrics dashboards.
    |
    */
    'dashboards' => [
        'enabled' => env('DASHBOARDS_ENABLED', true),
        'prometheus' => [
            'enabled' => env('PROMETHEUS_ENABLED', false),
            'endpoint' => env('PROMETHEUS_ENDPOINT', '/metrics'),
        ],
    ],
];

