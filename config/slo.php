<?php

/**
 * SLO Configuration
 * 
 * PR: SLO/SLA nội bộ
 * 
 * Configuration for SLO monitoring and alerting
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Alert Recipients
    |--------------------------------------------------------------------------
    |
    | Email addresses that will receive SLO violation alerts (critical only).
    |
    */
    'alert_recipients' => env('SLO_ALERT_RECIPIENTS', '')
        ? explode(',', env('SLO_ALERT_RECIPIENTS'))
        : [],

    /*
    |--------------------------------------------------------------------------
    | Slack Webhook URL
    |--------------------------------------------------------------------------
    |
    | Slack webhook URL for sending SLO violation alerts.
    | Leave empty to disable Slack notifications.
    |
    */
    'slack_webhook_url' => env('SLO_SLACK_WEBHOOK_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Slack Channel
    |--------------------------------------------------------------------------
    |
    | Slack channel name for SLO alerts (for display purposes).
    |
    */
    'slack_channel' => env('SLO_SLACK_CHANNEL', '#alerts-zenamanage'),

    /*
    |--------------------------------------------------------------------------
    | Alert Cooldown
    |--------------------------------------------------------------------------
    |
    | Cooldown periods (in seconds) for different alert severities.
    | Prevents alert spam.
    |
    */
    'cooldown' => [
        'critical' => 0,      // No cooldown for critical alerts
        'warning' => 900,    // 15 minutes
        'info' => 3600,      // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | SLO Targets
    |--------------------------------------------------------------------------
    |
    | SLO targets are defined in performance-budgets.json.
    | These are override values if needed (optional).
    |
    */
    'targets' => [
        'api' => [
            'p95' => 300, // milliseconds
        ],
        'pages' => [
            'p95' => 500, // milliseconds
        ],
        'websocket' => [
            'subscribe' => 200,
            'message_delivery' => 100,
            'connection_establishment' => 500,
        ],
        'cache' => [
            'hit_rate' => 80, // percentage
            'freshness' => 5000, // milliseconds
        ],
        'database' => [
            'query_time' => 100, // milliseconds
            'slow_queries' => 10, // per hour
        ],
        'error_rate' => [
            '4xx' => 1.0, // percentage
            '5xx' => 0.1, // percentage
        ],
        'availability' => [
            'uptime' => 99.9, // percentage
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Severity Thresholds
    |--------------------------------------------------------------------------
    |
    | Thresholds for determining alert severity based on SLO violation percentage.
    |
    */
    'severity_thresholds' => [
        'critical' => 1.0, // 100% of target
        'warning' => 0.8,  // 80% of target
        'info' => 0.6,     // 60% of target
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Freshness Target
    |--------------------------------------------------------------------------
    |
    | Target time (in milliseconds) for dashboard to update after mutation.
    |
    */
    'freshness_target' => 5000, // 5 seconds

];

