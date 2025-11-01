<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the rate limiting system.
    | You can customize rate limits for different endpoints, user roles,
    | and strategies.
    |
    */

    'default_strategy' => 'sliding_window',

    'strategies' => [
        'sliding_window' => [
            'description' => 'Sliding window with burst allowance',
            'best_for' => ['api', 'web', 'general'],
        ],
        'token_bucket' => [
            'description' => 'Token bucket for smooth rate limiting',
            'best_for' => ['upload', 'download', 'streaming'],
        ],
        'fixed_window' => [
            'description' => 'Fixed time window rate limiting',
            'best_for' => ['auth', 'simple_operations'],
        ],
    ],

    'endpoints' => [
        'auth' => [
            'requests_per_minute' => 10,
            'burst_limit' => 20,
            'window_size' => 60,
            'strategy' => 'sliding_window',
            'penalty_multiplier' => 2.0,
            'success_reduction' => 0.1,
            'description' => 'Authentication endpoints - strict limits',
        ],
        'api' => [
            'requests_per_minute' => 100,
            'burst_limit' => 200,
            'window_size' => 60,
            'strategy' => 'sliding_window',
            'penalty_multiplier' => 1.5,
            'success_reduction' => 0.05,
            'description' => 'General API endpoints',
        ],
        'upload' => [
            'requests_per_minute' => 5,
            'burst_limit' => 10,
            'window_size' => 60,
            'strategy' => 'token_bucket',
            'penalty_multiplier' => 3.0,
            'success_reduction' => 0.2,
            'description' => 'File upload endpoints - very strict',
        ],
        'admin' => [
            'requests_per_minute' => 500,
            'burst_limit' => 1000,
            'window_size' => 60,
            'strategy' => 'sliding_window',
            'penalty_multiplier' => 1.2,
            'success_reduction' => 0.02,
            'description' => 'Admin endpoints - higher limits',
        ],
        'public' => [
            'requests_per_minute' => 30,
            'burst_limit' => 50,
            'window_size' => 60,
            'strategy' => 'sliding_window',
            'penalty_multiplier' => 2.5,
            'success_reduction' => 0.15,
            'description' => 'Public endpoints - moderate limits',
        ],
        'default' => [
            'requests_per_minute' => 60,
            'burst_limit' => 100,
            'window_size' => 60,
            'strategy' => 'sliding_window',
            'penalty_multiplier' => 1.8,
            'success_reduction' => 0.08,
            'description' => 'Default rate limiting configuration',
        ],
    ],

    'user_roles' => [
        'super_admin' => [
            'multiplier' => 2.0,
            'description' => 'Super administrators - highest limits',
        ],
        'admin' => [
            'multiplier' => 1.5,
            'description' => 'Administrators - elevated limits',
        ],
        'pm' => [
            'multiplier' => 1.2,
            'description' => 'Project managers - slightly elevated limits',
        ],
        'member' => [
            'multiplier' => 1.0,
            'description' => 'Regular members - standard limits',
        ],
        'client' => [
            'multiplier' => 0.8,
            'description' => 'Clients - reduced limits',
        ],
        'guest' => [
            'multiplier' => 0.5,
            'description' => 'Unauthenticated users - lowest limits',
        ],
    ],

    'system_load_adjustments' => [
        'low_load' => [
            'threshold' => 0.5,
            'multiplier' => 1.2,
            'description' => 'System is idle - increase limits',
        ],
        'normal_load' => [
            'threshold' => 1.0,
            'multiplier' => 1.0,
            'description' => 'Normal system load - standard limits',
        ],
        'moderate_load' => [
            'threshold' => 1.5,
            'multiplier' => 0.8,
            'description' => 'Moderate load - reduce limits',
        ],
        'high_load' => [
            'threshold' => 2.0,
            'multiplier' => 0.6,
            'description' => 'High load - significantly reduce limits',
        ],
    ],

    'time_based_adjustments' => [
        'peak_hours' => [
            'start' => 9,
            'end' => 17,
            'multiplier' => 0.9,
            'description' => 'Business hours - slightly reduced limits',
        ],
        'off_peak_hours' => [
            'multiplier' => 1.1,
            'description' => 'Off-peak hours - slightly increased limits',
        ],
    ],

    'penalty_system' => [
        'violation_threshold' => 3,
        'escalation_factor' => 1.5,
        'penalty_duration' => 300, // 5 minutes
        'max_penalty_multiplier' => 5.0,
        'description' => 'Penalty system for repeated violations',
    ],

    'success_reduction' => [
        'success_threshold' => 10,
        'reduction_factor' => 0.05,
        'reduction_duration' => 60, // 1 minute
        'max_reduction' => 0.5,
        'description' => 'Reward system for good behavior',
    ],

    'monitoring' => [
        'log_all_requests' => false,
        'log_warning_threshold' => 10, // Log when remaining requests < 10
        'log_violations' => true,
        'log_performance' => true,
        'cache_stats_duration' => 300, // 5 minutes
    ],

    'cache' => [
        'driver' => 'redis',
        'prefix' => 'rate_limit:',
        'default_ttl' => 3600, // 1 hour
        'cleanup_interval' => 300, // 5 minutes
    ],

    'headers' => [
        'enabled' => true,
        'include_strategy' => true,
        'include_burst_info' => true,
        'include_reset_time' => true,
    ],

    'exemptions' => [
        'ips' => [
            // '127.0.0.1',
            // '192.168.1.0/24',
        ],
        'user_agents' => [
            // 'HealthCheckBot/1.0',
        ],
        'endpoints' => [
            // '/health',
            // '/ping',
        ],
    ],

    'emergency_mode' => [
        'enabled' => false,
        'global_limit' => 10,
        'description' => 'Emergency mode - severely restrict all requests',
    ],
];