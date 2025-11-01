<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CDN Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for CDN (Content Delivery Network)
    | integration. You can configure multiple CDN providers and settings.
    |
    */

    'enabled' => env('CDN_ENABLED', false),

    'url' => env('CDN_URL', ''),

    'provider' => env('CDN_PROVIDER', 'cloudflare'),

    'asset_types' => [
        'css',
        'js', 
        'images',
        'fonts',
        'videos'
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN Providers Configuration
    |--------------------------------------------------------------------------
    */

    'cloudflare' => [
        'api_key' => env('CLOUDFLARE_API_KEY'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'email' => env('CLOUDFLARE_EMAIL'),
    ],

    'aws' => [
        'access_key_id' => env('AWS_ACCESS_KEY_ID'),
        'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'distribution_id' => env('AWS_CLOUDFRONT_DISTRIBUTION_ID'),
    ],

    'fastly' => [
        'api_key' => env('FASTLY_API_KEY'),
        'service_id' => env('FASTLY_SERVICE_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN Settings
    |--------------------------------------------------------------------------
    */

    'settings' => [
        'cache_control' => 'public, max-age=31536000', // 1 year
        'compression' => true,
        'versioning' => true,
        'fallback_to_local' => true,
        'timeout' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Asset Optimization
    |--------------------------------------------------------------------------
    */

    'optimization' => [
        'minify_css' => true,
        'minify_js' => true,
        'compress_images' => true,
        'webp_conversion' => true,
        'lazy_loading' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Headers
    |--------------------------------------------------------------------------
    */

    'cache_headers' => [
        'css' => 'public, max-age=31536000', // 1 year
        'js' => 'public, max-age=31536000', // 1 year
        'images' => 'public, max-age=31536000', // 1 year
        'fonts' => 'public, max-age=31536000', // 1 year
        'videos' => 'public, max-age=2592000', // 30 days
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring
    |--------------------------------------------------------------------------
    */

    'monitoring' => [
        'enabled' => true,
        'health_check_interval' => 300, // 5 minutes
        'alert_on_failure' => true,
        'log_requests' => true,
    ],
];
