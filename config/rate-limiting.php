<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the rate limiting configuration for different API groups
    | and endpoints. Rate limits are defined as requests per minute.
    |
    */

    'limits' => [
        // Public API - No authentication required
        'public' => [
            'requests_per_minute' => 30,
            'burst_limit' => 50, // Allow burst up to 50 requests
            'ban_duration' => 300, // 5 minutes ban after exceeding burst
        ],

        // App API - Tenant-scoped authenticated users
        'app' => [
            'requests_per_minute' => 120,
            'burst_limit' => 200, // Allow burst up to 200 requests
            'ban_duration' => 600, // 10 minutes ban after exceeding burst
        ],

        // Admin API - Super admin only
        'admin' => [
            'requests_per_minute' => 60,
            'burst_limit' => 100, // Allow burst up to 100 requests
            'ban_duration' => 900, // 15 minutes ban after exceeding burst
        ],

        // Auth API - Authentication endpoints
        'auth' => [
            'requests_per_minute' => 20,
            'burst_limit' => 30, // Allow burst up to 30 requests
            'ban_duration' => 1800, // 30 minutes ban after exceeding burst
        ],

        // Invitation API - Invitation endpoints
        'invitations' => [
            'requests_per_minute' => 10,
            'burst_limit' => 15, // Allow burst up to 15 requests
            'ban_duration' => 3600, // 1 hour ban after exceeding burst
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Keys
    |--------------------------------------------------------------------------
    |
    | Define how rate limiting keys are generated for different contexts.
    |
    */

    'key_generators' => [
        'public' => 'ip', // Rate limit by IP address
        'app' => 'user', // Rate limit by authenticated user
        'admin' => 'user', // Rate limit by authenticated user
        'auth' => 'ip', // Rate limit by IP address for auth endpoints
        'invitations' => 'ip', // Rate limit by IP address for invitations
    ],

    /*
    |--------------------------------------------------------------------------
    | Exemptions
    |--------------------------------------------------------------------------
    |
    | Define IP addresses or user IDs that are exempt from rate limiting.
    |
    */

    'exemptions' => [
        'ips' => [
            '127.0.0.1',
            '::1',
            'localhost',
        ],
        'user_ids' => [
            // Add super admin user IDs here if needed
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Logging
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting monitoring and logging.
    |
    */

    'monitoring' => [
        'enabled' => true,
        'log_violations' => true,
        'log_successful_requests' => false, // Set to true for debugging
        'alert_threshold' => 0.8, // Alert when 80% of limit is reached
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure cache settings for rate limiting.
    |
    */

    'cache' => [
        'store' => 'redis', // Use Redis for better performance
        'prefix' => 'rate_limit:',
        'ttl' => 3600, // 1 hour TTL for rate limit counters
    ],
];
