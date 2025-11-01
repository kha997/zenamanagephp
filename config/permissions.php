<?php declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Role-Based Access Control (RBAC) Configuration
    |--------------------------------------------------------------------------
    |
    | This file defines the permissions for each role in the system.
    | Permissions are used to control access to various features and actions.
    |
    */

    'roles' => [
        'super_admin' => [
            '*', // All permissions
        ],

        'admin' => [
            // User Management
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.restore',
            'users.manage_roles',
            'users.manage_permissions',

            // Tenant Management
            'tenants.view',
            'tenants.update',
            'tenants.manage_settings',
            'tenants.manage_users',
            'tenants.manage_billing',
            'tenants.view_analytics',

            // Project Management
            'projects.view',
            'projects.create',
            'projects.update',
            'projects.delete',
            'projects.restore',
            'projects.manage_members',
            'projects.manage_settings',

            // Task Management
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.delete',
            'tasks.restore',
            'tasks.assign',
            'tasks.manage_assignees',

            // Document Management
            'documents.view',
            'documents.create',
            'documents.update',
            'documents.delete',
            'documents.restore',
            'documents.share',
            'documents.manage_permissions',

            // Client Management
            'clients.view',
            'clients.create',
            'clients.update',
            'clients.delete',
            'clients.restore',
            'clients.manage_lifecycle',

            // Quote Management
            'quotes.view',
            'quotes.create',
            'quotes.update',
            'quotes.delete',
            'quotes.restore',
            'quotes.send',
            'quotes.approve',
            'quotes.reject',

            // Template Management
            'templates.view',
            'templates.create',
            'templates.update',
            'templates.delete',
            'templates.restore',
            'templates.share',

            // Dashboard & Analytics
            'dashboard.view',
            'analytics.view',
            'reports.generate',
            'reports.export',

            // Notifications
            'notifications.view',
            'notifications.create',
            'notifications.update',
            'notifications.delete',

            // Settings
            'settings.view',
            'settings.update',
            'settings.manage_integrations',
        ],

        'pm' => [
            // User Management (limited)
            'users.view',

            // Project Management
            'projects.view',
            'projects.create',
            'projects.update',
            'projects.manage_members',
            'projects.manage_settings',

            // Task Management
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.assign',
            'tasks.manage_assignees',

            // Document Management
            'documents.view',
            'documents.create',
            'documents.update',
            'documents.share',

            // Client Management
            'clients.view',
            'clients.create',
            'clients.update',
            'clients.manage_lifecycle',

            // Quote Management
            'quotes.view',
            'quotes.create',
            'quotes.update',
            'quotes.send',

            // Template Management
            'templates.view',
            'templates.create',
            'templates.update',

            // Dashboard & Analytics
            'dashboard.view',
            'analytics.view',
            'reports.generate',

            // Notifications
            'notifications.view',
            'notifications.create',
            'notifications.update',

            // Settings (limited)
            'settings.view',
        ],

        'member' => [
            // User Management (self only)
            'users.view', // Can only view own profile

            // Project Management (limited)
            'projects.view',
            'projects.update', // Only assigned projects

            // Task Management
            'tasks.view',
            'tasks.create',
            'tasks.update', // Only assigned tasks
            'tasks.assign', // Only to other members

            // Document Management
            'documents.view',
            'documents.create',
            'documents.update', // Only own documents

            // Client Management (read-only)
            'clients.view',

            // Quote Management (read-only)
            'quotes.view',

            // Template Management (read-only)
            'templates.view',

            // Dashboard & Analytics
            'dashboard.view',
            'analytics.view', // Limited analytics

            // Notifications
            'notifications.view',
            'notifications.create',

            // Settings (own only)
            'settings.view',
        ],

        'client' => [
            // User Management (self only)
            'users.view', // Can only view own profile

            // Project Management (read-only)
            'projects.view', // Only assigned projects

            // Task Management (read-only)
            'tasks.view', // Only assigned tasks

            // Document Management (read-only)
            'documents.view', // Only shared documents

            // Client Management (own only)
            'clients.view', // Only own client record
            'clients.update', // Only own client record

            // Quote Management
            'quotes.view', // Only own quotes
            'quotes.update', // Only own quotes (limited fields)

            // Dashboard & Analytics (limited)
            'dashboard.view',

            // Notifications
            'notifications.view',

            // Settings (own only)
            'settings.view',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Groups
    |--------------------------------------------------------------------------
    |
    | Group related permissions for easier management and display.
    |
    */

    'groups' => [
        'user_management' => [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.restore',
            'users.manage_roles',
            'users.manage_permissions',
        ],

        'project_management' => [
            'projects.view',
            'projects.create',
            'projects.update',
            'projects.delete',
            'projects.restore',
            'projects.manage_members',
            'projects.manage_settings',
        ],

        'task_management' => [
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.delete',
            'tasks.restore',
            'tasks.assign',
            'tasks.manage_assignees',
        ],

        'document_management' => [
            'documents.view',
            'documents.create',
            'documents.update',
            'documents.delete',
            'documents.restore',
            'documents.share',
            'documents.manage_permissions',
        ],

        'client_management' => [
            'clients.view',
            'clients.create',
            'clients.update',
            'clients.delete',
            'clients.restore',
            'clients.manage_lifecycle',
        ],

        'quote_management' => [
            'quotes.view',
            'quotes.create',
            'quotes.update',
            'quotes.delete',
            'quotes.restore',
            'quotes.send',
            'quotes.approve',
            'quotes.reject',
        ],

        'template_management' => [
            'templates.view',
            'templates.create',
            'templates.update',
            'templates.delete',
            'templates.restore',
            'templates.share',
        ],

        'analytics' => [
            'dashboard.view',
            'analytics.view',
            'reports.generate',
            'reports.export',
        ],

        'notifications' => [
            'notifications.view',
            'notifications.create',
            'notifications.update',
            'notifications.delete',
        ],

        'settings' => [
            'settings.view',
            'settings.update',
            'settings.manage_integrations',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Permissions
    |--------------------------------------------------------------------------
    |
    | Default permissions granted to all authenticated users.
    |
    */

    'defaults' => [
        'users.view', // Users can always view their own profile
        'dashboard.view', // Users can always view dashboard
        'notifications.view', // Users can always view notifications
        'settings.view', // Users can always view their own settings
    ],
];