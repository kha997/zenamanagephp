<?php

return [
    // Max number of backups to keep (fallback matches BackupCommand default)
    'max_backups' => (int) env('BACKUP_MAX_BACKUPS', 10),

    // Max age (days) to keep backups (fallback matches BackupCommand default)
    'max_age_days' => (int) env('BACKUP_MAX_AGE_DAYS', 30),

    // Storage disk to store backups on
    'disk' => env('BACKUP_DISK', config('filesystems.default', 'local')),

    // Base path on the disk
    'path' => env('BACKUP_PATH', 'backups'),
];
