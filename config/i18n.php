<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Translation Namespaces
    |--------------------------------------------------------------------------
    |
    | These namespaces will be loaded by default when no namespaces parameter
    | is provided in the API request. Order matters for consistency.
    |
    */
    'default_namespaces' => [
        'app',
        'settings',
        'tasks',
        'projects',
        'dashboard',
        'auth',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (Time To Live)
    |--------------------------------------------------------------------------
    |
    | Cache duration in seconds for translations. Translations are static files
    | that rarely change, so a longer cache time is recommended.
    |
    */
    'cache_ttl' => 3600, // 1 hour

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | List of supported locales. If not set, will fallback to app.locales.
    |
    */
    'supported_locales' => config('app.locales', ['en']),

    /*
    |--------------------------------------------------------------------------
    | Enable HTTP Cache
    |--------------------------------------------------------------------------
    |
    | Whether to send HTTP cache headers (ETag, Cache-Control) for translations.
    | Set to false in development if you want to always get fresh translations.
    |
    */
    'enable_http_cache' => true,
];

