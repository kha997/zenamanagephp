<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Media Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for media file processing including virus scanning,
    | EXIF stripping, image resizing, and CDN integration.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Virus Scanning
    |--------------------------------------------------------------------------
    */

    'virus_scan_enabled' => env('MEDIA_VIRUS_SCAN_ENABLED', true),

    'virus_scan_driver' => env('MEDIA_VIRUS_SCAN_DRIVER', 'clamav'), // 'clamav' or 'cloud'

    'clamav_host' => env('CLAMAV_HOST', 'localhost'),
    'clamav_port' => env('CLAMAV_PORT', 3310),

    /*
    |--------------------------------------------------------------------------
    | EXIF Stripping
    |--------------------------------------------------------------------------
    */

    'strip_exif' => env('MEDIA_STRIP_EXIF', true),

    /*
    |--------------------------------------------------------------------------
    | Image Processing
    |--------------------------------------------------------------------------
    */

    'image_processing_enabled' => env('MEDIA_IMAGE_PROCESSING_ENABLED', true),

    'image_variants' => [
        'thumbnail' => [150, 150],
        'small' => [400, 300],
        'medium' => [800, 600],
        'large' => [1200, 900],
    ],

    'image_quality' => env('MEDIA_IMAGE_QUALITY', 85), // 0-100

    'generate_webp' => env('MEDIA_GENERATE_WEBP', true), // Generate WebP variants

    /*
    |--------------------------------------------------------------------------
    | Signed URLs
    |--------------------------------------------------------------------------
    */

    'signed_url_ttl' => env('MEDIA_SIGNED_URL_TTL', 3600), // seconds

    /*
    |--------------------------------------------------------------------------
    | CDN Configuration
    |--------------------------------------------------------------------------
    */

    'cdn_enabled' => env('MEDIA_CDN_ENABLED', false),
    'cdn_url' => env('MEDIA_CDN_URL', ''),
    'cdn_domain' => env('MEDIA_CDN_DOMAIN', ''),

    /*
    |--------------------------------------------------------------------------
    | Storage Quota
    |--------------------------------------------------------------------------
    */

    'default_quota_mb' => env('MEDIA_DEFAULT_QUOTA_MB', 10240), // 10GB default

];

