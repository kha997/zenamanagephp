<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bulk Operations Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for bulk operations and import/export functionality
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Batch Processing
    |--------------------------------------------------------------------------
    |
    | Configuration for batch processing of bulk operations
    |
    */
    'batch_size' => env('BULK_BATCH_SIZE', 100),
    'max_operations' => env('BULK_MAX_OPERATIONS', 1000),
    'timeout' => env('BULK_TIMEOUT', 300), // 5 minutes

    /*
    |--------------------------------------------------------------------------
    | Import Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for data import operations
    |
    */
    'import' => [
        'max_rows' => env('IMPORT_MAX_ROWS', 10000),
        'allowed_file_types' => ['xlsx', 'xls', 'csv'],
        'max_file_size' => env('IMPORT_MAX_FILE_SIZE', 10240), // 10MB in KB
        'temp_directory' => 'imports',
        'cleanup_after_days' => env('IMPORT_CLEANUP_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for data export operations
    |
    */
    'export' => [
        'directory' => 'exports',
        'cleanup_after_days' => env('EXPORT_CLEANUP_DAYS', 7),
        'default_format' => env('EXPORT_DEFAULT_FORMAT', 'xlsx'),
        'allowed_formats' => ['xlsx', 'xls', 'csv'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for queued bulk operations
    |
    */
    'queue' => [
        'enabled' => env('BULK_QUEUE_ENABLED', true),
        'connection' => env('BULK_QUEUE_CONNECTION', 'default'),
        'retry_attempts' => env('BULK_QUEUE_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('BULK_QUEUE_RETRY_DELAY', 60), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Default validation rules for bulk operations
    |
    */
    'validation' => [
        'users' => [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string|max:20',
        ],
        'projects' => [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'status' => 'nullable|string|in:planning,active,completed,cancelled',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ],
        'tasks' => [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'status' => 'nullable|string|in:pending,in_progress,completed,cancelled',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'due_date' => 'nullable|date',
            'assignee_id' => 'nullable|string|exists:users,id',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Mapping
    |--------------------------------------------------------------------------
    |
    | Field mapping for import/export operations
    |
    */
    'field_mapping' => [
        'users' => [
            'id' => 'ID',
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'is_active' => 'Status',
            'created_at' => 'Created At',
            'last_login_at' => 'Last Login',
        ],
        'projects' => [
            'id' => 'ID',
            'name' => 'Project Name',
            'description' => 'Description',
            'status' => 'Status',
            'start_date' => 'Start Date',
            'end_date' => 'End Date',
            'created_at' => 'Created At',
        ],
        'tasks' => [
            'id' => 'ID',
            'title' => 'Task Title',
            'description' => 'Description',
            'status' => 'Status',
            'priority' => 'Priority',
            'due_date' => 'Due Date',
            'assignee_id' => 'Assignee ID',
            'created_at' => 'Created At',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security configuration for bulk operations
    |
    */
    'security' => [
        'require_authentication' => env('BULK_REQUIRE_AUTH', true),
        'require_permissions' => env('BULK_REQUIRE_PERMISSIONS', true),
        'audit_all_operations' => env('BULK_AUDIT_ALL', true),
        'rate_limit' => [
            'enabled' => env('BULK_RATE_LIMIT_ENABLED', true),
            'max_requests_per_minute' => env('BULK_RATE_LIMIT_MAX', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Performance optimization settings
    |
    */
    'performance' => [
        'use_transactions' => env('BULK_USE_TRANSACTIONS', true),
        'disable_model_events' => env('BULK_DISABLE_MODEL_EVENTS', false),
        'chunk_size' => env('BULK_CHUNK_SIZE', 1000),
        'memory_limit' => env('BULK_MEMORY_LIMIT', '512M'),
        'execution_time_limit' => env('BULK_EXECUTION_TIME_LIMIT', 300), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Notification configuration for bulk operations
    |
    */
    'notifications' => [
        'enabled' => env('BULK_NOTIFICATIONS_ENABLED', true),
        'channels' => ['mail', 'database'],
        'notify_on_completion' => env('BULK_NOTIFY_COMPLETION', true),
        'notify_on_failure' => env('BULK_NOTIFY_FAILURE', true),
        'notify_on_progress' => env('BULK_NOTIFY_PROGRESS', false),
    ],
];
