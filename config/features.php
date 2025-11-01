<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains all feature flags for the ZenaManage application.
    | Feature flags allow us to enable/disable features dynamically without
    | deploying new code.
    |
    */

    'ui' => [
        'enable_focus_mode' => env('UI_ENABLE_FOCUS_MODE', true),
        'enable_rewards' => env('UI_ENABLE_REWARDS', true),
        'enable_dark_mode' => env('UI_ENABLE_DARK_MODE', true),
        'enable_advanced_search' => env('UI_ENABLE_ADVANCED_SEARCH', true),
        'enable_bulk_operations' => env('UI_ENABLE_BULK_OPERATIONS', true),
    ],

    'projects' => [
        'view_mode' => env('PROJECTS_VIEW_MODE', 'table'),
        'enable_gantt_chart' => env('PROJECTS_ENABLE_GANTT_CHART', true),
        'enable_time_tracking' => env('PROJECTS_ENABLE_TIME_TRACKING', true),
        'enable_budget_tracking' => env('PROJECTS_ENABLE_BUDGET_TRACKING', true),
        'enable_milestones' => env('PROJECTS_ENABLE_MILESTONES', true),
        'enable_collaboration' => env('PROJECTS_ENABLE_COLLABORATION', true),
        'enable_file_sharing' => env('PROJECTS_ENABLE_FILE_SHARING', true),
        'enable_version_control' => env('PROJECTS_ENABLE_VERSION_CONTROL', true),
        'enable_approval_workflow' => env('PROJECTS_ENABLE_APPROVAL_WORKFLOW', true),
    ],

    'tasks' => [
        'view_mode' => env('TASKS_VIEW_MODE', 'table'),
        'enable_subtasks' => env('TASKS_ENABLE_SUBTASKS', true),
        'enable_dependencies' => env('TASKS_ENABLE_DEPENDENCIES', true),
        'enable_time_estimation' => env('TASKS_ENABLE_TIME_ESTIMATION', true),
        'enable_priority_levels' => env('TASKS_ENABLE_PRIORITY_LEVELS', true),
        'enable_task_templates' => env('TASKS_ENABLE_TASK_TEMPLATES', true),
        'enable_bulk_assignment' => env('TASKS_ENABLE_BULK_ASSIGNMENT', true),
        'enable_automated_notifications' => env('TASKS_ENABLE_AUTOMATED_NOTIFICATIONS', true),
    ],

    'dashboard' => [
        'enable_kpi_widgets' => env('DASHBOARD_ENABLE_KPI_WIDGETS', true),
        'enable_custom_widgets' => env('DASHBOARD_ENABLE_CUSTOM_WIDGETS', true),
        'enable_real_time_updates' => env('DASHBOARD_ENABLE_REAL_TIME_UPDATES', true),
        'enable_export_functionality' => env('DASHBOARD_ENABLE_EXPORT_FUNCTIONALITY', true),
        'enable_scheduled_reports' => env('DASHBOARD_ENABLE_SCHEDULED_REPORTS', true),
    ],

    'team' => [
        'enable_role_management' => env('TEAM_ENABLE_ROLE_MANAGEMENT', true),
        'enable_permission_system' => env('TEAM_ENABLE_PERMISSION_SYSTEM', true),
        'enable_team_collaboration' => env('TEAM_ENABLE_TEAM_COLLABORATION', true),
        'enable_performance_tracking' => env('TEAM_ENABLE_PERFORMANCE_TRACKING', true),
        'enable_skill_management' => env('TEAM_ENABLE_SKILL_MANAGEMENT', true),
    ],

    'clients' => [
        'enable_client_portal' => env('CLIENTS_ENABLE_CLIENT_PORTAL', true),
        'enable_communication_log' => env('CLIENTS_ENABLE_COMMUNICATION_LOG', true),
        'enable_document_sharing' => env('CLIENTS_ENABLE_DOCUMENT_SHARING', true),
        'enable_feedback_system' => env('CLIENTS_ENABLE_FEEDBACK_SYSTEM', true),
        'enable_project_visibility' => env('CLIENTS_ENABLE_PROJECT_VISIBILITY', true),
    ],

    'quotes' => [
        'enable_quote_templates' => env('QUOTES_ENABLE_QUOTE_TEMPLATES', true),
        'enable_automated_pricing' => env('QUOTES_ENABLE_AUTOMATED_PRICING', true),
        'enable_approval_workflow' => env('QUOTES_ENABLE_APPROVAL_WORKFLOW', true),
        'enable_electronic_signatures' => env('QUOTES_ENABLE_ELECTRONIC_SIGNATURES', true),
        'enable_integration_crm' => env('QUOTES_ENABLE_INTEGRATION_CRM', true),
    ],

    'documents' => [
        'enable_version_control' => env('DOCUMENTS_ENABLE_VERSION_CONTROL', true),
        'enable_collaborative_editing' => env('DOCUMENTS_ENABLE_COLLABORATIVE_EDITING', true),
        'enable_cloud_storage' => env('DOCUMENTS_ENABLE_CLOUD_STORAGE', true),
        'enable_document_templates' => env('DOCUMENTS_ENABLE_DOCUMENT_TEMPLATES', true),
        'enable_automated_backup' => env('DOCUMENTS_ENABLE_AUTOMATED_BACKUP', true),
    ],

    'reports' => [
        'enable_custom_reports' => env('REPORTS_ENABLE_CUSTOM_REPORTS', true),
        'enable_scheduled_reports' => env('REPORTS_ENABLE_SCHEDULED_REPORTS', true),
        'enable_data_export' => env('REPORTS_ENABLE_DATA_EXPORT', true),
        'enable_visualization' => env('REPORTS_ENABLE_VISUALIZATION', true),
        'enable_analytics' => env('REPORTS_ENABLE_ANALYTICS', true),
    ],

    'integrations' => [
        'enable_calendar_sync' => env('INTEGRATIONS_ENABLE_CALENDAR_SYNC', true),
        'enable_email_integration' => env('INTEGRATIONS_ENABLE_EMAIL_INTEGRATION', true),
        'enable_slack_integration' => env('INTEGRATIONS_ENABLE_SLACK_INTEGRATION', true),
        'enable_api_access' => env('INTEGRATIONS_ENABLE_API_ACCESS', true),
        'enable_webhook_support' => env('INTEGRATIONS_ENABLE_WEBHOOK_SUPPORT', true),
    ],

    'security' => [
        'enable_two_factor_auth' => env('SECURITY_ENABLE_TWO_FACTOR_AUTH', true),
        'enable_sso' => env('SECURITY_ENABLE_SSO', true),
        'enable_audit_logging' => env('SECURITY_ENABLE_AUDIT_LOGGING', true),
        'enable_ip_whitelisting' => env('SECURITY_ENABLE_IP_WHITELISTING', true),
        'enable_session_management' => env('SECURITY_ENABLE_SESSION_MANAGEMENT', true),
    ],

    'notifications' => [
        'enable_email_notifications' => env('NOTIFICATIONS_ENABLE_EMAIL_NOTIFICATIONS', true),
        'enable_push_notifications' => env('NOTIFICATIONS_ENABLE_PUSH_NOTIFICATIONS', true),
        'enable_sms_notifications' => env('NOTIFICATIONS_ENABLE_SMS_NOTIFICATIONS', false),
        'enable_desktop_notifications' => env('NOTIFICATIONS_ENABLE_DESKTOP_NOTIFICATIONS', true),
        'enable_custom_notification_rules' => env('NOTIFICATIONS_ENABLE_CUSTOM_RULES', true),
    ],

    'performance' => [
        'enable_caching' => env('PERFORMANCE_ENABLE_CACHING', true),
        'enable_query_optimization' => env('PERFORMANCE_ENABLE_QUERY_OPTIMIZATION', true),
        'enable_cdn' => env('PERFORMANCE_ENABLE_CDN', false),
        'enable_compression' => env('PERFORMANCE_ENABLE_COMPRESSION', true),
        'enable_monitoring' => env('PERFORMANCE_ENABLE_MONITORING', true),
    ],

    'experimental' => [
        'enable_ai_assistant' => env('EXPERIMENTAL_ENABLE_AI_ASSISTANT', false),
        'enable_machine_learning' => env('EXPERIMENTAL_ENABLE_MACHINE_LEARNING', false),
        'enable_blockchain_integration' => env('EXPERIMENTAL_ENABLE_BLOCKCHAIN_INTEGRATION', false),
        'enable_voice_commands' => env('EXPERIMENTAL_ENABLE_VOICE_COMMANDS', false),
        'enable_ar_visualization' => env('EXPERIMENTAL_ENABLE_AR_VISUALIZATION', false),
    ],
];