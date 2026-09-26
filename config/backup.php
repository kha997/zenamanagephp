<?php

return [
    // Legacy keys kept for callers outside backup:run (unchanged meaning).
    'max_backups' => (int) env('BACKUP_MAX_BACKUPS', 10),
    'max_age_days' => (int) env('BACKUP_MAX_AGE_DAYS', 30),

    // null = keep archives in storage/backups exactly as before GAP-054.
    // Set to a Laravel filesystem disk name to store finished archives there.
    'disk' => env('BACKUP_DISK'),

    // Directory on BACKUP_DISK (ignored when disk is null).
    'path' => env('BACKUP_PATH', 'backups'),

    // Per-type retention (GAP-054 Gate 2, Q3). files/config fall back to full.
    'retention' => [
        'full' => [
            'max_backups' => (int) env('BACKUP_FULL_MAX_BACKUPS', 30),
            'max_age_days' => (int) env('BACKUP_FULL_MAX_AGE_DAYS', 30),
        ],
        'database' => [
            'max_backups' => (int) env('BACKUP_DATABASE_MAX_BACKUPS', 28),
            'max_age_days' => (int) env('BACKUP_DATABASE_MAX_AGE_DAYS', 7),
        ],
    ],
];
