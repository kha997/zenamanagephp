<?php declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Enterprise Features Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for enterprise features including SAML SSO, LDAP integration,
    | audit trails, compliance reporting, and advanced enterprise management.
    |
    */

    'saml' => [
        'enabled' => env('ENTERPRISE_SAML_ENABLED', false),
        'entity_id' => env('ENTERPRISE_SAML_ENTITY_ID'),
        'sso_url' => env('ENTERPRISE_SAML_SSO_URL'),
        'slo_url' => env('ENTERPRISE_SAML_SLO_URL'),
        'certificate' => env('ENTERPRISE_SAML_CERTIFICATE'),
        'private_key' => env('ENTERPRISE_SAML_PRIVATE_KEY'),
        'attribute_mapping' => [
            'email' => env('ENTERPRISE_SAML_EMAIL_ATTRIBUTE', 'email'),
            'first_name' => env('ENTERPRISE_SAML_FIRST_NAME_ATTRIBUTE', 'givenName'),
            'last_name' => env('ENTERPRISE_SAML_LAST_NAME_ATTRIBUTE', 'sn'),
            'department' => env('ENTERPRISE_SAML_DEPARTMENT_ATTRIBUTE', 'department'),
            'role' => env('ENTERPRISE_SAML_ROLE_ATTRIBUTE', 'role'),
        ],
        'providers' => [
            'azure_ad' => [
                'name' => 'Azure Active Directory',
                'entity_id' => 'https://sts.windows.net/{tenant-id}/',
                'sso_url' => 'https://login.microsoftonline.com/{tenant-id}/saml2',
                'slo_url' => 'https://login.microsoftonline.com/{tenant-id}/saml2',
            ],
            'okta' => [
                'name' => 'Okta',
                'entity_id' => 'https://{domain}.okta.com',
                'sso_url' => 'https://{domain}.okta.com/app/{app-id}/sso/saml',
                'slo_url' => 'https://{domain}.okta.com/app/{app-id}/slo/saml',
            ],
            'onelogin' => [
                'name' => 'OneLogin',
                'entity_id' => 'https://{subdomain}.onelogin.com/saml/metadata/{app-id}',
                'sso_url' => 'https://{subdomain}.onelogin.com/trust/saml2/http-post/sso/{app-id}',
                'slo_url' => 'https://{subdomain}.onelogin.com/trust/saml2/http-redirect/slo/{app-id}',
            ],
        ],
    ],

    'ldap' => [
        'enabled' => env('ENTERPRISE_LDAP_ENABLED', false),
        'host' => env('ENTERPRISE_LDAP_HOST'),
        'port' => env('ENTERPRISE_LDAP_PORT', 389),
        'base_dn' => env('ENTERPRISE_LDAP_BASE_DN'),
        'bind_dn' => env('ENTERPRISE_LDAP_BIND_DN'),
        'bind_password' => env('ENTERPRISE_LDAP_BIND_PASSWORD'),
        'user_filter' => env('ENTERPRISE_LDAP_USER_FILTER', '(uid=%s)'),
        'group_filter' => env('ENTERPRISE_LDAP_GROUP_FILTER', '(member=%s)'),
        'ssl' => env('ENTERPRISE_LDAP_SSL', false),
        'tls' => env('ENTERPRISE_LDAP_TLS', false),
        'timeout' => env('ENTERPRISE_LDAP_TIMEOUT', 30),
        'servers' => [
            'active_directory' => [
                'name' => 'Active Directory',
                'port' => 389,
                'ssl_port' => 636,
                'user_filter' => '(sAMAccountName=%s)',
                'group_filter' => '(member=%s)',
            ],
            'openldap' => [
                'name' => 'OpenLDAP',
                'port' => 389,
                'ssl_port' => 636,
                'user_filter' => '(uid=%s)',
                'group_filter' => '(member=%s)',
            ],
            'freeipa' => [
                'name' => 'FreeIPA',
                'port' => 389,
                'ssl_port' => 636,
                'user_filter' => '(uid=%s)',
                'group_filter' => '(member=%s)',
            ],
        ],
    ],

    'multi_tenant' => [
        'enabled' => env('ENTERPRISE_MULTI_TENANT_ENABLED', true),
        'tenant_isolation' => env('ENTERPRISE_TENANT_ISOLATION', true),
        'resource_management' => env('ENTERPRISE_RESOURCE_MANAGEMENT', true),
        'billing_integration' => env('ENTERPRISE_BILLING_INTEGRATION', false),
        'tenant_limits' => [
            'max_users' => env('ENTERPRISE_MAX_USERS_PER_TENANT', 10000),
            'max_projects' => env('ENTERPRISE_MAX_PROJECTS_PER_TENANT', 1000),
            'max_storage_gb' => env('ENTERPRISE_MAX_STORAGE_GB_PER_TENANT', 1000),
            'max_api_calls_per_month' => env('ENTERPRISE_MAX_API_CALLS_PER_MONTH', 1000000),
        ],
        'plans' => [
            'basic' => [
                'name' => 'Basic',
                'max_users' => 10,
                'max_projects' => 50,
                'max_storage_gb' => 10,
                'features' => ['basic_analytics', 'standard_support'],
            ],
            'professional' => [
                'name' => 'Professional',
                'max_users' => 100,
                'max_projects' => 500,
                'max_storage_gb' => 100,
                'features' => ['advanced_analytics', 'priority_support', 'custom_integrations'],
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'max_users' => 10000,
                'max_projects' => 1000,
                'max_storage_gb' => 1000,
                'features' => ['enterprise_analytics', 'dedicated_support', 'saml_sso', 'ldap_integration', 'audit_trails', 'compliance_reporting'],
            ],
        ],
    ],

    'audit_trails' => [
        'enabled' => env('ENTERPRISE_AUDIT_TRAILS_ENABLED', true),
        'retention_days' => env('ENTERPRISE_AUDIT_RETENTION_DAYS', 2555),
        'log_levels' => ['info', 'warning', 'error', 'critical'],
        'events' => [
            'user_login' => true,
            'user_logout' => true,
            'user_creation' => true,
            'user_modification' => true,
            'user_deletion' => true,
            'project_creation' => true,
            'project_modification' => true,
            'project_deletion' => true,
            'task_creation' => true,
            'task_modification' => true,
            'task_deletion' => true,
            'file_upload' => true,
            'file_download' => true,
            'file_deletion' => true,
            'settings_change' => true,
            'permission_change' => true,
            'data_export' => true,
            'data_import' => true,
        ],
        'sensitive_fields' => [
            'password', 'token', 'secret', 'key', 'ssn', 'credit_card',
            'bank_account', 'social_security', 'driver_license', 'passport',
        ],
        'real_time_monitoring' => env('ENTERPRISE_AUDIT_REAL_TIME_MONITORING', true),
        'alert_thresholds' => [
            'failed_login_attempts' => 5,
            'suspicious_activities' => 3,
            'data_access_violations' => 1,
            'permission_escalations' => 1,
        ],
    ],

    'compliance_reporting' => [
        'enabled' => env('ENTERPRISE_COMPLIANCE_REPORTING_ENABLED', true),
        'standards' => [
            'gdpr' => [
                'enabled' => env('ENTERPRISE_GDPR_COMPLIANCE_ENABLED', true),
                'data_retention_days' => env('ENTERPRISE_GDPR_RETENTION_DAYS', 2555),
                'consent_required' => env('ENTERPRISE_GDPR_CONSENT_REQUIRED', true),
                'right_to_forget' => env('ENTERPRISE_GDPR_RIGHT_TO_FORGET', true),
                'data_portability' => env('ENTERPRISE_GDPR_DATA_PORTABILITY', true),
                'breach_notification_hours' => env('ENTERPRISE_GDPR_BREACH_NOTIFICATION_HOURS', 72),
                'privacy_impact_assessments' => env('ENTERPRISE_GDPR_PRIVACY_IMPACT_ASSESSMENTS', true),
            ],
            'sox' => [
                'enabled' => env('ENTERPRISE_SOX_COMPLIANCE_ENABLED', true),
                'audit_trail_required' => env('ENTERPRISE_SOX_AUDIT_TRAIL_REQUIRED', true),
                'data_integrity' => env('ENTERPRISE_SOX_DATA_INTEGRITY', true),
                'access_controls' => env('ENTERPRISE_SOX_ACCESS_CONTROLS', true),
                'retention_period_years' => env('ENTERPRISE_SOX_RETENTION_YEARS', 7),
                'financial_reporting' => env('ENTERPRISE_SOX_FINANCIAL_REPORTING', true),
            ],
            'hipaa' => [
                'enabled' => env('ENTERPRISE_HIPAA_COMPLIANCE_ENABLED', true),
                'encryption_required' => env('ENTERPRISE_HIPAA_ENCRYPTION_REQUIRED', true),
                'access_logging' => env('ENTERPRISE_HIPAA_ACCESS_LOGGING', true),
                'audit_trails' => env('ENTERPRISE_HIPAA_AUDIT_TRAILS', true),
                'data_minimization' => env('ENTERPRISE_HIPAA_DATA_MINIMIZATION', true),
                'breach_notification' => env('ENTERPRISE_HIPAA_BREACH_NOTIFICATION', true),
            ],
            'pci_dss' => [
                'enabled' => env('ENTERPRISE_PCI_DSS_COMPLIANCE_ENABLED', true),
                'card_data_encryption' => env('ENTERPRISE_PCI_CARD_DATA_ENCRYPTION', true),
                'secure_networks' => env('ENTERPRISE_PCI_SECURE_NETWORKS', true),
                'access_controls' => env('ENTERPRISE_PCI_ACCESS_CONTROLS', true),
                'monitoring' => env('ENTERPRISE_PCI_MONITORING', true),
                'regular_testing' => env('ENTERPRISE_PCI_REGULAR_TESTING', true),
            ],
        ],
        'report_formats' => ['pdf', 'excel', 'csv', 'json'],
        'schedule_reports' => env('ENTERPRISE_SCHEDULE_COMPLIANCE_REPORTS', true),
        'automated_alerts' => env('ENTERPRISE_COMPLIANCE_AUTOMATED_ALERTS', true),
    ],

    'advanced_analytics' => [
        'enabled' => env('ENTERPRISE_ADVANCED_ANALYTICS_ENABLED', true),
        'real_time_analytics' => env('ENTERPRISE_REAL_TIME_ANALYTICS', true),
        'predictive_analytics' => env('ENTERPRISE_PREDICTIVE_ANALYTICS', true),
        'business_intelligence' => env('ENTERPRISE_BUSINESS_INTELLIGENCE', true),
        'metrics' => [
            'user_activity' => true,
            'system_performance' => true,
            'security_metrics' => true,
            'compliance_status' => true,
            'business_metrics' => true,
            'cost_analysis' => true,
            'productivity_metrics' => true,
            'satisfaction_scores' => true,
        ],
        'dashboards' => [
            'executive' => true,
            'operational' => true,
            'technical' => true,
            'compliance' => true,
            'security' => true,
        ],
        'data_retention_days' => env('ENTERPRISE_ANALYTICS_RETENTION_DAYS', 365),
        'export_formats' => ['pdf', 'excel', 'csv', 'json'],
    ],

    'enterprise_security' => [
        'enabled' => env('ENTERPRISE_SECURITY_ENABLED', true),
        'threat_detection' => env('ENTERPRISE_THREAT_DETECTION', true),
        'intrusion_prevention' => env('ENTERPRISE_INTRUSION_PREVENTION', true),
        'compliance_monitoring' => env('ENTERPRISE_COMPLIANCE_MONITORING', true),
        'security_analytics' => env('ENTERPRISE_SECURITY_ANALYTICS', true),
        'incident_response' => env('ENTERPRISE_INCIDENT_RESPONSE', true),
        'vulnerability_management' => env('ENTERPRISE_VULNERABILITY_MANAGEMENT', true),
        'security_training' => env('ENTERPRISE_SECURITY_TRAINING', true),
        'penetration_testing' => env('ENTERPRISE_PENETRATION_TESTING', true),
    ],

    'data_retention' => [
        'enabled' => env('ENTERPRISE_DATA_RETENTION_ENABLED', true),
        'default_days' => env('ENTERPRISE_DATA_RETENTION_DAYS', 2555),
        'policies' => [
            'user_data' => env('ENTERPRISE_USER_DATA_RETENTION_DAYS', 2555),
            'project_data' => env('ENTERPRISE_PROJECT_DATA_RETENTION_DAYS', 2555),
            'audit_logs' => env('ENTERPRISE_AUDIT_LOGS_RETENTION_DAYS', 2555),
            'compliance_reports' => env('ENTERPRISE_COMPLIANCE_REPORTS_RETENTION_DAYS', 2555),
            'analytics_data' => env('ENTERPRISE_ANALYTICS_DATA_RETENTION_DAYS', 365),
            'backup_data' => env('ENTERPRISE_BACKUP_DATA_RETENTION_DAYS', 90),
        ],
        'automated_cleanup' => env('ENTERPRISE_AUTOMATED_DATA_CLEANUP', true),
        'compliance_mode' => env('ENTERPRISE_COMPLIANCE_MODE', true),
    ],

    'backup' => [
        'enabled' => env('ENTERPRISE_BACKUP_ENABLED', true),
        'strategy' => env('ENTERPRISE_BACKUP_STRATEGY', 'daily'),
        'retention_days' => env('ENTERPRISE_BACKUP_RETENTION_DAYS', 90),
        'encryption' => env('ENTERPRISE_BACKUP_ENCRYPTION', true),
        'compression' => env('ENTERPRISE_BACKUP_COMPRESSION', true),
        'storage' => [
            'local' => env('ENTERPRISE_BACKUP_LOCAL_STORAGE', true),
            'cloud' => env('ENTERPRISE_BACKUP_CLOUD_STORAGE', false),
            'cloud_provider' => env('ENTERPRISE_BACKUP_CLOUD_PROVIDER', 'aws'),
        ],
        'testing' => env('ENTERPRISE_BACKUP_TESTING', true),
        'monitoring' => env('ENTERPRISE_BACKUP_MONITORING', true),
    ],

    'reporting' => [
        'enabled' => env('ENTERPRISE_REPORTING_ENABLED', true),
        'report_types' => [
            'executive_summary' => true,
            'financial_analysis' => true,
            'operational_metrics' => true,
            'security_assessment' => true,
            'compliance_audit' => true,
            'user_activity' => true,
            'system_performance' => true,
            'business_intelligence' => true,
        ],
        'formats' => ['pdf', 'excel', 'csv', 'json', 'xml'],
        'scheduling' => env('ENTERPRISE_REPORT_SCHEDULING', true),
        'automation' => env('ENTERPRISE_REPORT_AUTOMATION', true),
        'distribution' => env('ENTERPRISE_REPORT_DISTRIBUTION', true),
        'custom_reports' => env('ENTERPRISE_CUSTOM_REPORTS', true),
    ],

    'integrations' => [
        'enabled' => env('ENTERPRISE_INTEGRATIONS_ENABLED', true),
        'api_rate_limiting' => env('ENTERPRISE_API_RATE_LIMITING', true),
        'webhook_support' => env('ENTERPRISE_WEBHOOK_SUPPORT', true),
        'sso_providers' => [
            'saml' => env('ENTERPRISE_SAML_INTEGRATION', false),
            'oauth2' => env('ENTERPRISE_OAUTH2_INTEGRATION', false),
            'ldap' => env('ENTERPRISE_LDAP_INTEGRATION', false),
        ],
        'third_party_services' => [
            'slack' => env('ENTERPRISE_SLACK_INTEGRATION', false),
            'microsoft_teams' => env('ENTERPRISE_TEAMS_INTEGRATION', false),
            'salesforce' => env('ENTERPRISE_SALESFORCE_INTEGRATION', false),
            'jira' => env('ENTERPRISE_JIRA_INTEGRATION', false),
            'confluence' => env('ENTERPRISE_CONFLUENCE_INTEGRATION', false),
        ],
        'data_sources' => [
            'databases' => ['mysql', 'postgresql', 'sqlserver', 'oracle'],
            'apis' => ['rest', 'graphql', 'soap'],
            'files' => ['csv', 'excel', 'json', 'xml'],
        ],
    ],

    'monitoring' => [
        'enabled' => env('ENTERPRISE_MONITORING_ENABLED', true),
        'real_time_monitoring' => env('ENTERPRISE_REAL_TIME_MONITORING', true),
        'alerting' => env('ENTERPRISE_ALERTING', true),
        'metrics' => [
            'system_performance' => true,
            'user_activity' => true,
            'security_events' => true,
            'compliance_status' => true,
            'business_metrics' => true,
            'cost_metrics' => true,
        ],
        'dashboards' => [
            'executive' => true,
            'operational' => true,
            'technical' => true,
            'compliance' => true,
            'security' => true,
        ],
        'notifications' => [
            'email' => env('ENTERPRISE_EMAIL_NOTIFICATIONS', true),
            'slack' => env('ENTERPRISE_SLACK_NOTIFICATIONS', false),
            'webhook' => env('ENTERPRISE_WEBHOOK_NOTIFICATIONS', false),
        ],
    ],

    'support' => [
        'enabled' => env('ENTERPRISE_SUPPORT_ENABLED', true),
        'tiers' => [
            'basic' => [
                'name' => 'Basic Support',
                'response_time' => '24 hours',
                'channels' => ['email'],
                'hours' => 'Business hours',
            ],
            'professional' => [
                'name' => 'Professional Support',
                'response_time' => '8 hours',
                'channels' => ['email', 'phone'],
                'hours' => 'Business hours',
            ],
            'enterprise' => [
                'name' => 'Enterprise Support',
                'response_time' => '2 hours',
                'channels' => ['email', 'phone', 'chat'],
                'hours' => '24/7',
            ],
        ],
        'sla' => [
            'uptime' => 99.9,
            'response_time' => 2, // hours
            'resolution_time' => 24, // hours
        ],
    ],
];
