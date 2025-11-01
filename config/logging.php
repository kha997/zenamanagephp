<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for structured JSON logging with observability features
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'structured'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'structured' => [
            'driver' => 'single',
            'path' => storage_path('logs/structured.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'audit' => [
            'driver' => 'single',
            'path' => storage_path('logs/audit.log'),
            'level' => 'info',
        ],

        'performance' => [
            'driver' => 'single',
            'path' => storage_path('logs/performance.log'),
            'level' => 'info',
        ],

        'security' => [
            'driver' => 'daily',
            'path' => storage_path('logs/security.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 90, // Keep security logs for 90 days
        ],

        'admin' => [
            'driver' => 'daily',
            'path' => storage_path('logs/admin.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 365, // Keep admin logs for 1 year
        ],

        'data' => [
            'driver' => 'daily',
            'path' => storage_path('logs/data.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 180, // Keep data access logs for 6 months
        ],

        'api' => [
            'driver' => 'daily',
            'path' => storage_path('logs/api.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30, // Keep API logs for 30 days
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('SLACK_WEBHOOK'),
            'username' => 'ZenaManage',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => LOG_USER,
            'replace_placeholders' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Features
    |--------------------------------------------------------------------------
    */

    'features' => [
        'structured_logging' => env('LOG_STRUCTURED', true),
        'request_id_propagation' => env('LOG_REQUEST_ID', true),
        'pii_redaction' => env('LOG_PII_REDACTION', true),
        'performance_tracking' => env('LOG_PERFORMANCE', true),
        'audit_logging' => env('LOG_AUDIT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | PII Redaction Rules
    |--------------------------------------------------------------------------
    */

    'redaction' => [
        'patterns' => [
            '/password/i',
            '/token/i',
            '/secret/i',
            '/key/i',
            '/email/i',
            '/phone/i',
            '/ssn/i',
            '/credit_card/i',
        ],
        'replacement' => '[REDACTED]',
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Thresholds
    |--------------------------------------------------------------------------
    */

    'performance' => [
        'page_load_threshold' => 500, // ms
        'api_response_threshold' => 300, // ms
        'database_query_threshold' => 100, // ms
        'cache_hit_threshold' => 90, // percentage
    ],
];