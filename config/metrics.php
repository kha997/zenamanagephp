<?php declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Metrics Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for application metrics collection and monitoring
    |
    */

    'enabled' => env('METRICS_ENABLED', true),

    'prometheus' => [
        'namespace' => env('METRICS_NAMESPACE', 'zenamanage'),
        'redis_connection' => env('METRICS_REDIS_CONNECTION', 'default'),
        'storage_adapter' => env('METRICS_STORAGE_ADAPTER', 'redis'),
        
        'collectors' => [
            'http_requests' => [
                'enabled' => true,
                'name' => 'http_requests_total',
                'help' => 'Total number of HTTP requests',
                'labels' => ['method', 'route', 'status_code'],
            ],
            
            'http_duration' => [
                'enabled' => true,
                'name' => 'http_request_duration_seconds',
                'help' => 'HTTP request duration in seconds',
                'labels' => ['method', 'route'],
                'buckets' => [0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0],
            ],
            
            'database_queries' => [
                'enabled' => true,
                'name' => 'database_queries_total',
                'help' => 'Total number of database queries',
                'labels' => ['connection', 'type'],
            ],
            
            'websocket_connections' => [
                'enabled' => true,
                'name' => 'websocket_connections_active',
                'help' => 'Number of active WebSocket connections',
                'labels' => ['server'],
            ],
            
            'queue_jobs' => [
                'enabled' => true,
                'name' => 'queue_jobs_total',
                'help' => 'Total number of queue jobs processed',
                'labels' => ['queue', 'status'],
            ],
            
            'memory_usage' => [
                'enabled' => true,
                'name' => 'memory_usage_bytes',
                'help' => 'Memory usage in bytes',
                'labels' => ['type'],
            ],
        ],
    ],

    'health_checks' => [
        'database' => [
            'enabled' => true,
            'timeout' => 5,
        ],
        'redis' => [
            'enabled' => true,
            'timeout' => 3,
        ],
        'websocket' => [
            'enabled' => true,
            'url' => env('WEBSOCKET_HEALTH_URL', 'http://localhost:3000/health'),
            'timeout' => 5,
        ],
        'storage' => [
            'enabled' => true,
            'disks' => ['local', 'public'],
        ],
    ],

    'alerts' => [
        'thresholds' => [
            'response_time' => 2.0, // seconds
            'error_rate' => 0.05, // 5%
            'memory_usage' => 0.85, // 85%
            'disk_usage' => 0.90, // 90%
            'cpu_usage' => 0.80, // 80%
        ],
        
        'channels' => [
            'slack' => [
                'enabled' => env('ALERTS_SLACK_ENABLED', false),
                'webhook_url' => env('ALERTS_SLACK_WEBHOOK'),
                'channel' => env('ALERTS_SLACK_CHANNEL', '#alerts'),
            ],
            'email' => [
                'enabled' => env('ALERTS_EMAIL_ENABLED', true),
                'recipients' => explode(',', env('ALERTS_EMAIL_RECIPIENTS', '')),
            ],
        ],
    ],
];