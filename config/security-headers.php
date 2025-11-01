<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Headers Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for security headers middleware.
    | You can customize security policies based on your application needs.
    |
    */

    'enabled' => env('SECURITY_HEADERS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security (HSTS)
    |--------------------------------------------------------------------------
    */
    'hsts' => [
        'enabled' => env('HSTS_ENABLED', true),
        'max_age' => env('HSTS_MAX_AGE', 31536000), // 1 year
        'include_subdomains' => env('HSTS_INCLUDE_SUBDOMAINS', true),
        'preload' => env('HSTS_PRELOAD', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy (CSP)
    |--------------------------------------------------------------------------
    */
    'csp' => [
        'enabled' => env('CSP_ENABLED', true),
        'report_only' => env('CSP_REPORT_ONLY', false),
        'report_uri' => env('CSP_REPORT_URI', null),
        
        'directives' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'"],
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],
            'font-src' => ["'self'", 'https://fonts.gstatic.com'],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'connect-src' => ["'self'"],
            'media-src' => ["'self'"],
            'object-src' => ["'none'"],
            'child-src' => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'form-action' => ["'self'"],
            'base-uri' => ["'self'"],
            'manifest-src' => ["'self'"],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | X-Frame-Options
    |--------------------------------------------------------------------------
    */
    'frame_options' => [
        'enabled' => env('FRAME_OPTIONS_ENABLED', true),
        'policy' => env('FRAME_OPTIONS_POLICY', 'DENY'), // DENY, SAMEORIGIN, ALLOW-FROM
    ],

    /*
    |--------------------------------------------------------------------------
    | X-Content-Type-Options
    |--------------------------------------------------------------------------
    */
    'content_type_options' => [
        'enabled' => env('CONTENT_TYPE_OPTIONS_ENABLED', true),
        'value' => 'nosniff',
    ],

    /*
    |--------------------------------------------------------------------------
    | X-XSS-Protection
    |--------------------------------------------------------------------------
    */
    'xss_protection' => [
        'enabled' => env('XSS_PROTECTION_ENABLED', true),
        'value' => '1; mode=block',
    ],

    /*
    |--------------------------------------------------------------------------
    | Referrer Policy
    |--------------------------------------------------------------------------
    */
    'referrer_policy' => [
        'enabled' => env('REFERRER_POLICY_ENABLED', true),
        'policy' => env('REFERRER_POLICY', 'strict-origin-when-cross-origin'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions Policy (formerly Feature Policy)
    |--------------------------------------------------------------------------
    */
    'permissions_policy' => [
        'enabled' => env('PERMISSIONS_POLICY_ENABLED', true),
        'features' => [
            'accelerometer' => '()',
            'ambient-light-sensor' => '()',
            'autoplay' => '()',
            'battery' => '()',
            'camera' => '()',
            'cross-origin-isolated' => '()',
            'display-capture' => '()',
            'document-domain' => '()',
            'encrypted-media' => '()',
            'execution-while-not-rendered' => '()',
            'execution-while-out-of-viewport' => '()',
            'fullscreen' => '(self)',
            'geolocation' => '()',
            'gyroscope' => '()',
            'keyboard-map' => '()',
            'magnetometer' => '()',
            'microphone' => '()',
            'midi' => '()',
            'navigation-override' => '()',
            'payment' => '()',
            'picture-in-picture' => '()',
            'publickey-credentials-get' => '()',
            'screen-wake-lock' => '()',
            'sync-xhr' => '()',
            'usb' => '()',
            'web-share' => '()',
            'xr-spatial-tracking' => '()',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Policies
    |--------------------------------------------------------------------------
    */
    'cross_origin' => [
        'embedder_policy' => [
            'enabled' => env('COEP_ENABLED', false),
            'policy' => env('COEP_POLICY', 'require-corp'),
        ],
        'opener_policy' => [
            'enabled' => env('COOP_ENABLED', true),
            'policy' => env('COOP_POLICY', 'same-origin-allow-popups'),
        ],
        'resource_policy' => [
            'enabled' => env('CORP_ENABLED', false),
            'policy' => env('CORP_POLICY', 'same-origin'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Control
    |--------------------------------------------------------------------------
    */
    'cache_control' => [
        'api' => 'no-cache, no-store, must-revalidate, private',
        'admin' => 'no-cache, no-store, must-revalidate, private',
        'app' => 'no-cache, no-store, must-revalidate, private',
        'public' => 'public, max-age=3600',
    ],

    /*
    |--------------------------------------------------------------------------
    | Additional Security Headers
    |--------------------------------------------------------------------------
    */
    'additional' => [
        'x_permitted_cross_domain_policies' => 'none',
        'x_download_options' => 'noopen',
        'x_dns_prefetch_control' => 'off',
    ],

    /*
    |--------------------------------------------------------------------------
    | Clear-Site-Data
    |--------------------------------------------------------------------------
    */
    'clear_site_data' => [
        'enabled' => env('CLEAR_SITE_DATA_ENABLED', true),
        'logout_routes' => ['logout', 'api/v1/auth/logout'],
        'data_types' => ['cache', 'cookies', 'storage', 'executionContexts'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment-specific Settings
    |--------------------------------------------------------------------------
    */
    'environments' => [
        'local' => [
            'hsts' => ['max_age' => 300], // 5 minutes for local development
            'csp' => ['report_only' => true],
        ],
        'production' => [
            'hsts' => ['max_age' => 31536000, 'preload' => true], // 1 year with preload
            'csp' => ['report_only' => false],
        ],
    ],
];
