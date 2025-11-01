<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for email verification and notifications
    |
    */

    'verification_token_expiry' => env('EMAIL_VERIFICATION_TOKEN_EXPIRY', 24), // hours
    
    'frontend_url' => env('FRONTEND_URL', env('APP_URL')),
    
    /*
    |--------------------------------------------------------------------------
    | Email Templates
    |--------------------------------------------------------------------------
    |
    | Paths to email templates
    |
    */
    'templates' => [
        'verify_email' => 'emails.verify-email',
        'verify_email_change' => 'emails.verify-email-change',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Email sending rate limits
    |
    */
    'rate_limits' => [
        'verification_resend' => env('EMAIL_VERIFICATION_RESEND_LIMIT', 3), // per hour
        'email_change' => env('EMAIL_CHANGE_LIMIT', 1), // per hour
    ],
];
