<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Headers Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for security headers.
    | You can customize security policies, CSP rules, and other security
    | settings based on your application's needs.
    |
    */

    'enabled' => env('SECURITY_HEADERS_ENABLED', true),

    'csp' => [
        'enabled' => env('CSP_ENABLED', true),
        'report_only' => env('CSP_REPORT_ONLY', false),
        'report_uri' => env('CSP_REPORT_URI', '/_security/csp-report'),
        'nonce_enabled' => env('CSP_NONCE_ENABLED', true),
        'strict_mode' => env('CSP_STRICT_MODE', false),
    ],

    'hsts' => [
        'enabled' => env('HSTS_ENABLED', true),
        'max_age' => env('HSTS_MAX_AGE', 31536000), // 1 year
        'include_subdomains' => env('HSTS_INCLUDE_SUBDOMAINS', true),
        'preload' => env('HSTS_PRELOAD', true),
    ],

    'frame_options' => [
        'enabled' => env('FRAME_OPTIONS_ENABLED', true),
        'default' => env('FRAME_OPTIONS_DEFAULT', 'DENY'),
        'allowed_origins' => env('FRAME_OPTIONS_ALLOWED_ORIGINS', ''),
    ],

    'referrer_policy' => [
        'enabled' => env('REFERRER_POLICY_ENABLED', true),
        'default' => env('REFERRER_POLICY_DEFAULT', 'strict-origin-when-cross-origin'),
        'options' => [
            'no-referrer',
            'no-referrer-when-downgrade',
            'origin',
            'origin-when-cross-origin',
            'same-origin',
            'strict-origin',
            'strict-origin-when-cross-origin',
            'unsafe-url',
        ],
    ],

    'permissions_policy' => [
        'enabled' => env('PERMISSIONS_POLICY_ENABLED', true),
        'default_deny' => [
            'camera',
            'microphone',
            'geolocation',
            'payment',
            'usb',
            'magnetometer',
            'gyroscope',
            'accelerometer',
            'ambient-light-sensor',
            'autoplay',
            'battery',
            'bluetooth',
            'display-capture',
            'document-domain',
            'encrypted-media',
            'fullscreen',
            'gamepad',
            'midi',
            'notifications',
            'picture-in-picture',
            'publickey-credentials-get',
            'screen-wake-lock',
            'sync-xhr',
            'web-share',
            'xr-spatial-tracking',
        ],
        'allowed_features' => [
            'admin' => ['fullscreen', 'notifications'],
            'meetings' => ['camera', 'microphone'],
            'video' => ['camera', 'microphone'],
        ],
    ],

    'cross_origin' => [
        'embedder_policy' => [
            'enabled' => env('COEP_ENABLED', true),
            'default' => env('COEP_DEFAULT', 'require-corp'),
            'options' => ['require-corp', 'credentialless', 'unsafe-none'],
        ],
        'opener_policy' => [
            'enabled' => env('COOP_ENABLED', true),
            'default' => env('COOP_DEFAULT', 'same-origin'),
            'options' => ['unsafe-none', 'same-origin-allow-popups', 'same-origin'],
        ],
        'resource_policy' => [
            'enabled' => env('CORP_ENABLED', true),
            'default' => env('CORP_DEFAULT', 'same-origin'),
            'options' => ['same-site', 'same-origin', 'cross-origin'],
        ],
    ],

    'cache_control' => [
        'sensitive_pages' => [
            '/admin',
            '/app/profile',
            '/app/settings',
            '/app/team',
            '/app/dashboard',
            '/login',
            '/register',
            '/password',
            '/logout',
            '/app/account',
            '/app/billing',
            '/app/security',
        ],
        'sensitive_cache_headers' => [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ],
        'public_cache_headers' => [
            'Cache-Control' => 'public, max-age=3600',
        ],
    ],

    'csp_sources' => [
        'development' => [
            'script_src' => [
                "'self'",
                "'unsafe-inline'",
                "'unsafe-eval'",
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com',
                'localhost:*',
            ],
            'style_src' => [
                "'self'",
                "'unsafe-inline'",
                'https://fonts.googleapis.com',
                'https://cdn.jsdelivr.net',
            ],
            'connect_src' => [
                "'self'",
                'localhost:*',
                'ws://localhost:*',
                'wss://localhost:*',
            ],
        ],
        'production' => [
            'script_src' => [
                "'self'",
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com',
            ],
            'style_src' => [
                "'self'",
                'https://fonts.googleapis.com',
            ],
            'connect_src' => [
                "'self'",
            ],
        ],
    ],

    'monitoring' => [
        'log_violations' => env('SECURITY_LOG_VIOLATIONS', true),
        'log_headers' => env('SECURITY_LOG_HEADERS', false),
        'violation_threshold' => env('SECURITY_VIOLATION_THRESHOLD', 10),
        'alert_on_threshold' => env('SECURITY_ALERT_ON_THRESHOLD', true),
    ],

    'testing' => [
        'enabled' => env('SECURITY_TESTING_ENABLED', false),
        'test_endpoints' => [
            '/_security/test-headers',
            '/_security/validate-headers',
            '/_security/config',
        ],
    ],

    'environment_specific' => [
        'local' => [
            'csp_strict' => false,
            'hsts_max_age' => 86400, // 1 day
            'log_headers' => true,
        ],
        'testing' => [
            'csp_strict' => false,
            'hsts_max_age' => 86400, // 1 day
            'log_headers' => true,
        ],
        'staging' => [
            'csp_strict' => true,
            'hsts_max_age' => 31536000, // 1 year
            'log_headers' => false,
        ],
        'production' => [
            'csp_strict' => true,
            'hsts_max_age' => 31536000, // 1 year
            'log_headers' => false,
            'report_only' => false,
        ],
    ],
];
