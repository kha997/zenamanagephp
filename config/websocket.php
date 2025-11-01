<?php declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | WebSocket Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for WebSocket server used for real-time dashboard updates
    |
    */

    'host' => env('WEBSOCKET_HOST', '0.0.0.0'),
    'port' => env('WEBSOCKET_PORT', 8080),
    'workers' => env('WEBSOCKET_WORKERS', 1),

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | WebSocket authentication settings
    |
    */

    'auth' => [
        'guard' => 'sanctum',
        'token_header' => 'Authorization',
        'token_prefix' => 'Bearer ',
    ],

    /*
    |--------------------------------------------------------------------------
    | Channels
    |--------------------------------------------------------------------------
    |
    | Available WebSocket channels for subscription
    |
    */

    'channels' => [
        'dashboard' => 'dashboard.{user_id}',
        'alerts' => 'alerts.{user_id}',
        'metrics' => 'metrics.{tenant_id}',
        'notifications' => 'notifications.{user_id}',
        'project' => 'project.{project_id}',
        'system' => 'system.{tenant_id}',
    ],

    /*
    |--------------------------------------------------------------------------
    | Heartbeat
    |--------------------------------------------------------------------------
    |
    | Heartbeat settings for connection monitoring
    |
    */

    'heartbeat' => [
        'interval' => 30, // seconds
        'timeout' => 60, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting
    |--------------------------------------------------------------------------
    |
    | Broadcasting settings for real-time updates
    |
    */

    'broadcasting' => [
        'driver' => env('WEBSOCKET_BROADCAST_DRIVER', 'redis'),
        'redis' => [
            'connection' => 'default',
            'channel' => 'websocket_broadcast',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting for WebSocket connections
    |
    */

    'rate_limiting' => [
        'enabled' => true,
        'max_connections_per_user' => 5,
        'max_connections_per_ip' => 10,
        'connection_timeout' => 300, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Logging settings for WebSocket server
    |
    */

    'logging' => [
        'enabled' => true,
        'level' => env('WEBSOCKET_LOG_LEVEL', 'info'),
        'channel' => env('WEBSOCKET_LOG_CHANNEL', 'daily'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SSL/TLS
    |--------------------------------------------------------------------------
    |
    | SSL/TLS settings for secure WebSocket connections
    |
    */

    'ssl' => [
        'enabled' => env('WEBSOCKET_SSL_ENABLED', false),
        'cert' => env('WEBSOCKET_SSL_CERT'),
        'key' => env('WEBSOCKET_SSL_KEY'),
        'verify_peer' => env('WEBSOCKET_SSL_VERIFY_PEER', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS
    |--------------------------------------------------------------------------
    |
    | CORS settings for WebSocket connections
    |
    */

    'cors' => [
        'enabled' => true,
        'origins' => [
            env('APP_URL', 'http://localhost:3000'),
            env('FRONTEND_URL', 'http://localhost:3000'),
        ],
        'methods' => ['GET', 'POST'],
        'headers' => ['Authorization', 'Content-Type'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    |
    | Performance optimization settings
    |
    */

    'performance' => [
        'max_message_size' => 1024 * 1024, // 1MB
        'max_connections' => 1000,
        'buffer_size' => 8192,
        'keep_alive' => true,
        'keep_alive_interval' => 30, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring
    |--------------------------------------------------------------------------
    |
    | Monitoring and metrics settings
    |
    */

    'monitoring' => [
        'enabled' => true,
        'metrics_interval' => 60, // seconds
        'health_check_endpoint' => '/websocket/health',
        'stats_endpoint' => '/websocket/stats',
    ],
];
