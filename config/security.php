<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Comprehensive security settings for production hardening
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | Configuration for security headers middleware
    |
    */
    'headers' => [
        'csp_enabled' => env('SECURITY_CSP_ENABLED', true),
        'hsts_enabled' => env('SECURITY_HSTS_ENABLED', true),
        'hsts_max_age' => env('SECURITY_HSTS_MAX_AGE', 31536000),
        'frame_options' => env('SECURITY_FRAME_OPTIONS', 'DENY'),
        'content_type_options' => env('SECURITY_CONTENT_TYPE_OPTIONS', true),
        'xss_protection' => env('SECURITY_XSS_PROTECTION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Advanced rate limiting configuration
    |
    */
    'rate_limiting' => [
        'enabled' => env('SECURITY_RATE_LIMITING_ENABLED', true),
        'default_limits' => [
            'ip' => ['requests' => 100, 'minutes' => 15],
            'user' => ['requests' => 200, 'minutes' => 15],
            'endpoint' => ['requests' => 50, 'minutes' => 15]
        ],
        'auth_limits' => [
            'ip' => ['requests' => 10, 'minutes' => 15],
            'user' => ['requests' => 5, 'minutes' => 15],
            'endpoint' => ['requests' => 5, 'minutes' => 15]
        ],
        'api_limits' => [
            'ip' => ['requests' => 1000, 'minutes' => 60],
            'user' => ['requests' => 2000, 'minutes' => 60],
            'endpoint' => ['requests' => 500, 'minutes' => 60]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Input Sanitization
    |--------------------------------------------------------------------------
    |
    | Input sanitization and validation settings
    |
    */
    'input_sanitization' => [
        'enabled' => env('SECURITY_INPUT_SANITIZATION_ENABLED', true),
        'html_encode' => env('SECURITY_HTML_ENCODE', true),
        'remove_null_bytes' => env('SECURITY_REMOVE_NULL_BYTES', true),
        'detect_suspicious_patterns' => env('SECURITY_DETECT_SUSPICIOUS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Security
    |--------------------------------------------------------------------------
    |
    | Secure file upload configuration
    |
    */
    'file_upload' => [
        'max_size' => env('UPLOAD_MAX_SIZE', 10 * 1024 * 1024), // 10MB
        'allowed_mime_types' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'text/csv', 'application/zip', 'application/x-rar-compressed'
        ],
        'allowed_extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 
            'xls', 'xlsx', 'txt', 'csv', 'zip', 'rar'
        ],
        'scan_viruses' => env('UPLOAD_SCAN_VIRUSES', false),
        'validate_content' => env('UPLOAD_VALIDATE_CONTENT', true),
        'quarantine_suspicious' => env('UPLOAD_QUARANTINE_SUSPICIOUS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    |
    | Cross-Origin Resource Sharing settings
    |
    */
    'cors' => [
        'allowed_origins' => explode(',', (string) (env('CORS_ALLOWED_ORIGINS') ?? env('APP_URL') ?? '')),
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
        'exposed_headers' => [],
        'max_age' => 86400, // 24 hours
        'supports_credentials' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Security
    |--------------------------------------------------------------------------
    |
    | Session security configuration
    |
    */
    'session' => [
        'secure' => env('SESSION_SECURE', true),
        'http_only' => env('SESSION_HTTP_ONLY', true),
        'same_site' => env('SESSION_SAME_SITE', 'strict'),
        'regenerate_on_login' => env('SESSION_REGENERATE_LOGIN', true),
        'regenerate_on_logout' => env('SESSION_REGENERATE_LOGOUT', true),
        'lifetime' => env('SESSION_LIFETIME', 120), // minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption
    |--------------------------------------------------------------------------
    |
    | Encryption and hashing settings
    |
    */
    'encryption' => [
        'algorithm' => env('ENCRYPTION_ALGORITHM', 'AES-256-CBC'),
        'key_rotation_enabled' => env('ENCRYPTION_KEY_ROTATION_ENABLED', false),
        'key_rotation_interval' => env('ENCRYPTION_KEY_ROTATION_INTERVAL', 90), // days
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Security
    |--------------------------------------------------------------------------
    |
    | Security logging configuration
    |
    */
    'logging' => [
        'log_failed_logins' => env('SECURITY_LOG_FAILED_LOGINS', true),
        'log_suspicious_activity' => env('SECURITY_LOG_SUSPICIOUS_ACTIVITY', true),
        'log_file_access' => env('SECURITY_LOG_FILE_ACCESS', true),
        'log_admin_actions' => env('SECURITY_LOG_ADMIN_ACTIONS', true),
        'retention_days' => env('SECURITY_LOG_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Production Hardening
    |--------------------------------------------------------------------------
    |
    | Production-specific security hardening
    |
    */
    'production' => [
        'hide_debug_info' => env('SECURITY_HIDE_DEBUG_INFO', true),
        'disable_error_details' => env('SECURITY_DISABLE_ERROR_DETAILS', true),
        'disable_stack_traces' => env('SECURITY_DISABLE_STACK_TRACES', true),
        'require_https' => env('SECURITY_REQUIRE_HTTPS', true),
        'disable_unused_features' => env('SECURITY_DISABLE_UNUSED_FEATURES', true),
    ],
];
