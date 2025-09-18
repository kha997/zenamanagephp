<?php declare(strict_types=1);

return [
    'default' => env('CACHE_DRIVER', 'redis'),

    'stores' => [
        // Array cache for testing
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],

        // Thêm database cache store
        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => null,
            'lock_connection' => null,
        ],

        // Dedicated cache store for sessions
        'session' => [
            'driver' => 'redis',
            'connection' => 'session',
        ],

        // Dedicated cache store for user data
        'users' => [
            'driver' => 'redis',
            'connection' => 'users',
            'prefix' => 'zena_users',
        ],

        // Dedicated cache store for projects
        'projects' => [
            'driver' => 'redis',
            'connection' => 'projects',
            'prefix' => 'zena_projects',
        ],
    ],

    'prefix' => env('CACHE_PREFIX', 'zena_cache'),
];