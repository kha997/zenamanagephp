<?php declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Advanced Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for advanced security features including threat detection,
    | intrusion prevention, security analytics, and compliance monitoring.
    |
    */

    'threat_detection' => [
        'enabled' => env('SECURITY_THREAT_DETECTION_ENABLED', true),
        'patterns' => [
            'sql_injection' => [
                'patterns' => ['union select', 'drop table', 'insert into', 'delete from', 'update set'],
                'severity' => 'high',
                'action' => 'block',
            ],
            'xss_attack' => [
                'patterns' => ['<script>', 'javascript:', 'onload=', 'onerror=', 'onclick='],
                'severity' => 'high',
                'action' => 'sanitize',
            ],
            'csrf_attack' => [
                'patterns' => ['csrf_token', 'authenticity_token'],
                'severity' => 'medium',
                'action' => 'validate',
            ],
            'brute_force' => [
                'patterns' => ['multiple_failed_logins'],
                'severity' => 'high',
                'action' => 'rate_limit',
            ],
            'directory_traversal' => [
                'patterns' => ['../', '..\\', '/etc/passwd', '\\windows\\system32'],
                'severity' => 'high',
                'action' => 'block',
            ],
            'command_injection' => [
                'patterns' => [';', '|', '&', '`', '$('],
                'severity' => 'high',
                'action' => 'block',
            ],
        ],
    ],

    'intrusion_detection' => [
        'enabled' => env('SECURITY_INTRUSION_DETECTION_ENABLED', true),
        'suspicious_patterns' => [
            'rapid_requests' => [
                'threshold' => 100, // requests per minute
                'action' => 'rate_limit',
            ],
            'unusual_user_agent' => [
                'patterns' => ['bot', 'crawler', 'scanner', 'hack'],
                'action' => 'monitor',
            ],
            'suspicious_referer' => [
                'domains' => ['malicious-site.com', 'phishing-site.com'],
                'action' => 'block',
            ],
        ],
        'unusual_behavior' => [
            'unusual_access_pattern' => [
                'threshold' => 5, // unusual patterns per hour
                'action' => 'alert',
            ],
            'data_exfiltration_attempt' => [
                'threshold' => 3, // attempts per hour
                'action' => 'block',
            ],
        ],
        'privilege_escalation' => [
            'patterns' => ['sudo', 'su -', 'admin', 'root'],
            'action' => 'block',
        ],
    ],

    'authentication_security' => [
        'enabled' => env('SECURITY_AUTH_ENHANCEMENT_ENABLED', true),
        'password_policy' => [
            'min_length' => env('SECURITY_PASSWORD_MIN_LENGTH', 8),
            'require_uppercase' => env('SECURITY_PASSWORD_REQUIRE_UPPERCASE', true),
            'require_lowercase' => env('SECURITY_PASSWORD_REQUIRE_LOWERCASE', true),
            'require_numbers' => env('SECURITY_PASSWORD_REQUIRE_NUMBERS', true),
            'require_symbols' => env('SECURITY_PASSWORD_REQUIRE_SYMBOLS', true),
            'max_age_days' => env('SECURITY_PASSWORD_MAX_AGE_DAYS', 90),
            'history_count' => env('SECURITY_PASSWORD_HISTORY_COUNT', 5),
        ],
        'credential_stuffing' => [
            'enabled' => env('SECURITY_CREDENTIAL_STUFFING_ENABLED', true),
            'max_failed_attempts' => env('SECURITY_MAX_FAILED_ATTEMPTS', 10),
            'lockout_duration' => env('SECURITY_LOCKOUT_DURATION', 3600), // seconds
        ],
        'account_takeover' => [
            'enabled' => env('SECURITY_ACCOUNT_TAKEOVER_ENABLED', true),
            'unusual_ip_threshold' => env('SECURITY_UNUSUAL_IP_THRESHOLD', 3),
            'unusual_time_threshold' => env('SECURITY_UNUSUAL_TIME_THRESHOLD', 2),
        ],
        'device_fingerprinting' => [
            'enabled' => env('SECURITY_DEVICE_FINGERPRINTING_ENABLED', true),
            'trust_threshold' => env('SECURITY_DEVICE_TRUST_THRESHOLD', 0.8),
        ],
        'geolocation' => [
            'enabled' => env('SECURITY_GEOLOCATION_ENABLED', true),
            'suspicious_countries' => env('SECURITY_SUSPICIOUS_COUNTRIES', 'CN,RU,KP,IR'),
            'alert_on_unusual_location' => env('SECURITY_ALERT_UNUSUAL_LOCATION', true),
        ],
    ],

    'data_protection' => [
        'enabled' => env('SECURITY_DATA_PROTECTION_ENABLED', true),
        'sensitive_fields' => [
            'password', 'token', 'secret', 'key', 'ssn', 'credit_card',
            'bank_account', 'social_security', 'driver_license', 'passport',
        ],
        'encryption' => [
            'algorithm' => env('SECURITY_ENCRYPTION_ALGORITHM', 'AES-256-CBC'),
            'key_length' => env('SECURITY_ENCRYPTION_KEY_LENGTH', 256),
        ],
        'pii_detection' => [
            'enabled' => env('SECURITY_PII_DETECTION_ENABLED', true),
            'patterns' => [
                'email' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
                'phone' => '/^\+?[\d\s\-\(\)]+$/',
                'ssn' => '/^\d{3}-\d{2}-\d{4}$/',
                'credit_card' => '/^\d{4}[\s\-]?\d{4}[\s\-]?\d{4}[\s\-]?\d{4}$/',
            ],
        ],
    ],

    'incident_response' => [
        'enabled' => env('SECURITY_INCIDENT_RESPONSE_ENABLED', true),
        'severity_levels' => [
            'critical' => [
                'actions' => ['immediate_block', 'alert_security_team', 'escalate_to_management'],
                'response_time' => 15, // minutes
            ],
            'high' => [
                'actions' => ['rate_limit', 'alert_security_team'],
                'response_time' => 30, // minutes
            ],
            'medium' => [
                'actions' => ['log_incident', 'monitor_activity'],
                'response_time' => 60, // minutes
            ],
            'low' => [
                'actions' => ['log_incident'],
                'response_time' => 120, // minutes
            ],
        ],
        'notification' => [
            'email' => env('SECURITY_INCIDENT_EMAIL', 'security@example.com'),
            'slack' => env('SECURITY_INCIDENT_SLACK_WEBHOOK'),
            'webhook' => env('SECURITY_INCIDENT_WEBHOOK_URL'),
        ],
    ],

    'vulnerability_assessment' => [
        'enabled' => env('SECURITY_VULNERABILITY_ASSESSMENT_ENABLED', true),
        'scan_frequency' => env('SECURITY_VULNERABILITY_SCAN_FREQUENCY', 'weekly'),
        'severity_thresholds' => [
            'critical' => 9.0,
            'high' => 7.0,
            'medium' => 4.0,
            'low' => 0.1,
        ],
        'common_vulnerabilities' => [
            'sql_injection' => [
                'severity' => 'high',
                'description' => 'Potential SQL injection vulnerability',
                'recommendation' => 'Use parameterized queries',
            ],
            'xss' => [
                'severity' => 'medium',
                'description' => 'Potential XSS vulnerability',
                'recommendation' => 'Implement output encoding',
            ],
            'csrf' => [
                'severity' => 'medium',
                'description' => 'Potential CSRF vulnerability',
                'recommendation' => 'Implement CSRF tokens',
            ],
            'weak_password_policy' => [
                'severity' => 'medium',
                'description' => 'Password policy may be too weak',
                'recommendation' => 'Strengthen password requirements',
            ],
            'debug_mode_enabled' => [
                'severity' => 'medium',
                'description' => 'Debug mode may be enabled in production',
                'recommendation' => 'Disable debug mode in production',
            ],
        ],
    ],

    'compliance_monitoring' => [
        'enabled' => env('SECURITY_COMPLIANCE_MONITORING_ENABLED', true),
        'standards' => [
            'gdpr' => [
                'enabled' => env('SECURITY_GDPR_COMPLIANCE_ENABLED', true),
                'data_retention_days' => env('SECURITY_GDPR_RETENTION_DAYS', 2555),
                'consent_required' => env('SECURITY_GDPR_CONSENT_REQUIRED', true),
                'right_to_forget' => env('SECURITY_GDPR_RIGHT_TO_FORGET', true),
                'data_portability' => env('SECURITY_GDPR_DATA_PORTABILITY', true),
                'breach_notification_hours' => env('SECURITY_GDPR_BREACH_NOTIFICATION_HOURS', 72),
            ],
            'sox' => [
                'enabled' => env('SECURITY_SOX_COMPLIANCE_ENABLED', true),
                'audit_trail_required' => env('SECURITY_SOX_AUDIT_TRAIL_REQUIRED', true),
                'data_integrity' => env('SECURITY_SOX_DATA_INTEGRITY', true),
                'access_controls' => env('SECURITY_SOX_ACCESS_CONTROLS', true),
                'retention_period_years' => env('SECURITY_SOX_RETENTION_YEARS', 7),
            ],
            'hipaa' => [
                'enabled' => env('SECURITY_HIPAA_COMPLIANCE_ENABLED', true),
                'encryption_required' => env('SECURITY_HIPAA_ENCRYPTION_REQUIRED', true),
                'access_logging' => env('SECURITY_HIPAA_ACCESS_LOGGING', true),
                'audit_trails' => env('SECURITY_HIPAA_AUDIT_TRAILS', true),
                'data_minimization' => env('SECURITY_HIPAA_DATA_MINIMIZATION', true),
            ],
            'pci_dss' => [
                'enabled' => env('SECURITY_PCI_DSS_COMPLIANCE_ENABLED', true),
                'card_data_encryption' => env('SECURITY_PCI_CARD_DATA_ENCRYPTION', true),
                'secure_networks' => env('SECURITY_PCI_SECURE_NETWORKS', true),
                'access_controls' => env('SECURITY_PCI_ACCESS_CONTROLS', true),
                'monitoring' => env('SECURITY_PCI_MONITORING', true),
            ],
        ],
    ],

    'monitoring' => [
        'enabled' => env('SECURITY_MONITORING_ENABLED', true),
        'log_requests' => env('SECURITY_LOG_REQUESTS', true),
        'log_responses' => env('SECURITY_LOG_RESPONSES', false),
        'track_usage' => env('SECURITY_TRACK_USAGE', true),
        'alert_on_errors' => env('SECURITY_ALERT_ON_ERRORS', true),
        'metrics' => [
            'threat_detection_rate' => true,
            'intrusion_detection_rate' => true,
            'authentication_failure_rate' => true,
            'compliance_score' => true,
            'vulnerability_count' => true,
        ],
    ],

    'rate_limiting' => [
        'enabled' => env('SECURITY_RATE_LIMITING_ENABLED', true),
        'requests_per_minute' => env('SECURITY_RATE_LIMIT_PER_MINUTE', 100),
        'requests_per_hour' => env('SECURITY_RATE_LIMIT_PER_HOUR', 1000),
        'requests_per_day' => env('SECURITY_RATE_LIMIT_PER_DAY', 10000),
        'burst_limit' => env('SECURITY_BURST_LIMIT', 200),
    ],

    'ip_management' => [
        'whitelist' => [
            'enabled' => env('SECURITY_IP_WHITELIST_ENABLED', false),
            'ips' => explode(',', env('SECURITY_IP_WHITELIST', '')),
        ],
        'blacklist' => [
            'enabled' => env('SECURITY_IP_BLACKLIST_ENABLED', true),
            'ips' => explode(',', env('SECURITY_IP_BLACKLIST', '')),
            'auto_block_duration' => env('SECURITY_AUTO_BLOCK_DURATION', 3600), // seconds
        ],
        'geolocation_blocking' => [
            'enabled' => env('SECURITY_GEOLOCATION_BLOCKING_ENABLED', false),
            'blocked_countries' => explode(',', env('SECURITY_BLOCKED_COUNTRIES', '')),
        ],
    ],

    'security_headers' => [
        'enabled' => env('SECURITY_HEADERS_ENABLED', true),
        'content_security_policy' => env('SECURITY_CSP_POLICY', "default-src 'self'"),
        'strict_transport_security' => env('SECURITY_HSTS_ENABLED', true),
        'x_frame_options' => env('SECURITY_X_FRAME_OPTIONS', 'DENY'),
        'x_content_type_options' => env('SECURITY_X_CONTENT_TYPE_OPTIONS', 'nosniff'),
        'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
    ],

    'backup_security' => [
        'enabled' => env('SECURITY_BACKUP_ENABLED', true),
        'encrypt_backups' => env('SECURITY_ENCRYPT_BACKUPS', true),
        'secure_storage' => env('SECURITY_SECURE_BACKUP_STORAGE', true),
        'access_logging' => env('SECURITY_BACKUP_ACCESS_LOGGING', true),
    ],

    'alerting' => [
        'enabled' => env('SECURITY_ALERTING_ENABLED', true),
        'channels' => [
            'email' => [
                'enabled' => env('SECURITY_EMAIL_ALERTS_ENABLED', true),
                'recipients' => explode(',', env('SECURITY_EMAIL_RECIPIENTS', '')),
            ],
            'slack' => [
                'enabled' => env('SECURITY_SLACK_ALERTS_ENABLED', false),
                'webhook_url' => env('SECURITY_SLACK_WEBHOOK_URL'),
            ],
            'webhook' => [
                'enabled' => env('SECURITY_WEBHOOK_ALERTS_ENABLED', false),
                'url' => env('SECURITY_WEBHOOK_URL'),
            ],
        ],
        'severity_thresholds' => [
            'critical' => true,
            'high' => true,
            'medium' => false,
            'low' => false,
        ],
    ],
];
