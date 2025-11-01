<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Observability Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for observability features including
    | logging, metrics collection, and monitoring.
    |
    */

    'enabled' => env('OBSERVABILITY_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Correlation ID Configuration
    |--------------------------------------------------------------------------
    |
    | Configure correlation ID generation and handling.
    |
    */

    'correlation_id' => [
        'enabled' => env('CORRELATION_ID_ENABLED', true),
        'header_name' => env('CORRELATION_ID_HEADER', 'X-Correlation-ID'),
        'generate_if_missing' => env('CORRELATION_ID_GENERATE_IF_MISSING', true),
        'include_in_response' => env('CORRELATION_ID_INCLUDE_IN_RESPONSE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Structured Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure structured logging settings.
    |
    */

    'structured_logging' => [
        'enabled' => env('STRUCTURED_LOGGING_ENABLED', true),
        'include_user_context' => env('STRUCTURED_LOGGING_INCLUDE_USER', true),
        'include_request_context' => env('STRUCTURED_LOGGING_INCLUDE_REQUEST', true),
        'log_level' => env('STRUCTURED_LOGGING_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Collection Configuration
    |--------------------------------------------------------------------------
    |
    | Configure metrics collection settings.
    |
    */

    'metrics' => [
        'enabled' => env('METRICS_COLLECTION_ENABLED', true),
        'collection_interval' => env('METRICS_COLLECTION_INTERVAL', 60), // seconds
        'retention_hours' => env('METRICS_RETENTION_HOURS', 168), // 1 week
        'store_in_cache' => env('METRICS_STORE_IN_CACHE', true),
        'cache_prefix' => env('METRICS_CACHE_PREFIX', 'metrics_'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configure performance monitoring settings.
    |
    */

    'performance' => [
        'enabled' => env('PERFORMANCE_MONITORING_ENABLED', true),
        'slow_request_threshold' => env('PERFORMANCE_SLOW_REQUEST_THRESHOLD', 1.0), // seconds
        'large_response_threshold' => env('PERFORMANCE_LARGE_RESPONSE_THRESHOLD', 1048576), // bytes
        'log_database_queries' => env('PERFORMANCE_LOG_DATABASE_QUERIES', false),
        'log_cache_operations' => env('PERFORMANCE_LOG_CACHE_OPERATIONS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | Configure health check settings.
    |
    */

    'health_checks' => [
        'enabled' => env('HEALTH_CHECKS_ENABLED', true),
        'timeout' => env('HEALTH_CHECKS_TIMEOUT', 30), // seconds
        'checks' => [
            'database' => env('HEALTH_CHECK_DATABASE', true),
            'cache' => env('HEALTH_CHECK_CACHE', true),
            'queue' => env('HEALTH_CHECK_QUEUE', true),
            'storage' => env('HEALTH_CHECK_STORAGE', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Dashboard Configuration
    |--------------------------------------------------------------------------
    |
    | Configure monitoring dashboard settings.
    |
    */

    'dashboard' => [
        'enabled' => env('MONITORING_DASHBOARD_ENABLED', true),
        'refresh_interval' => env('MONITORING_DASHBOARD_REFRESH_INTERVAL', 30), // seconds
        'max_log_entries' => env('MONITORING_DASHBOARD_MAX_LOG_ENTRIES', 1000),
        'max_historical_hours' => env('MONITORING_DASHBOARD_MAX_HISTORICAL_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure alerting settings.
    |
    */

    'alerting' => [
        'enabled' => env('ALERTING_ENABLED', false),
        'thresholds' => [
            'error_rate_percent' => env('ALERT_ERROR_RATE_THRESHOLD', 5.0),
            'response_time_ms' => env('ALERT_RESPONSE_TIME_THRESHOLD', 2000),
            'memory_usage_percent' => env('ALERT_MEMORY_USAGE_THRESHOLD', 80.0),
            'cpu_usage_percent' => env('ALERT_CPU_USAGE_THRESHOLD', 80.0),
            'disk_usage_percent' => env('ALERT_DISK_USAGE_THRESHOLD', 90.0),
        ],
        'channels' => [
            'log' => env('ALERT_CHANNEL_LOG', true),
            'email' => env('ALERT_CHANNEL_EMAIL', false),
            'slack' => env('ALERT_CHANNEL_SLACK', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | External Monitoring Integration
    |--------------------------------------------------------------------------
    |
    | Configure external monitoring service integration.
    |
    */

    'external' => [
        'enabled' => env('EXTERNAL_MONITORING_ENABLED', false),
        'services' => [
            'datadog' => [
                'enabled' => env('DATADOG_ENABLED', false),
                'api_key' => env('DATADOG_API_KEY'),
                'app_key' => env('DATADOG_APP_KEY'),
            ],
            'newrelic' => [
                'enabled' => env('NEWRELIC_ENABLED', false),
                'license_key' => env('NEWRELIC_LICENSE_KEY'),
            ],
            'sentry' => [
                'enabled' => env('SENTRY_ENABLED', false),
                'dsn' => env('SENTRY_DSN'),
            ],
        ],
    ],
];
