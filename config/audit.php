<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for audit logging and retention policies.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Retention Policy
    |--------------------------------------------------------------------------
    |
    | Default retention period for audit logs (in years).
    | Can be configured per tenant in the future.
    |
    */
    'retention_years' => env('AUDIT_RETENTION_YEARS', 2),

    /*
    |--------------------------------------------------------------------------
    | Cleanup Schedule
    |--------------------------------------------------------------------------
    |
    | How often to run audit log cleanup (daily recommended).
    |
    */
    'cleanup_schedule' => env('AUDIT_CLEANUP_SCHEDULE', 'daily'),

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Enable/disable audit logging globally.
    |
    */
    'enabled' => env('AUDIT_ENABLED', true),
];

