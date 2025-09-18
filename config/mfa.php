<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Multi-Factor Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for MFA/2FA functionality
    |
    */

    'enabled' => env('MFA_ENABLED', true),
    
    'recovery_codes_count' => env('MFA_RECOVERY_CODES_COUNT', 10),
    
    'issuer' => env('MFA_ISSUER', env('APP_NAME', 'ZENA Manage')),
    
    /*
    |--------------------------------------------------------------------------
    | TOTP Configuration
    |--------------------------------------------------------------------------
    |
    | Time-based One-Time Password settings
    |
    */
    'totp' => [
        'algorithm' => env('MFA_TOTP_ALGORITHM', 'sha1'),
        'digits' => env('MFA_TOTP_DIGITS', 6),
        'period' => env('MFA_TOTP_PERIOD', 30), // seconds
        'window' => env('MFA_TOTP_WINDOW', 1), // tolerance window
    ],
    
    /*
    |--------------------------------------------------------------------------
    | QR Code Configuration
    |--------------------------------------------------------------------------
    |
    | QR code generation settings
    |
    */
    'qr_code' => [
        'size' => env('MFA_QR_SIZE', 200),
        'margin' => env('MFA_QR_MARGIN', 1),
    ],
];
