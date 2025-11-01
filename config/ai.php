<?php declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | AI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AI-powered features including provider settings,
    | API keys, models, and feature toggles.
    |
    */

    'default_provider' => env('AI_DEFAULT_PROVIDER', 'openai'),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-3.5-turbo'),
            'max_tokens' => env('OPENAI_MAX_TOKENS', 1000),
            'temperature' => env('OPENAI_TEMPERATURE', 0.7),
            'timeout' => env('OPENAI_TIMEOUT', 30),
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-sonnet-20240229'),
            'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 1000),
            'temperature' => env('ANTHROPIC_TEMPERATURE', 0.7),
            'timeout' => env('ANTHROPIC_TIMEOUT', 30),
        ],

        'local' => [
            'api_key' => null,
            'base_url' => env('LOCAL_AI_BASE_URL', 'http://localhost:8000'),
            'model' => env('LOCAL_AI_MODEL', 'local-model'),
            'max_tokens' => env('LOCAL_AI_MAX_TOKENS', 1000),
            'temperature' => env('LOCAL_AI_TEMPERATURE', 0.7),
            'timeout' => env('LOCAL_AI_TIMEOUT', 30),
        ],
    ],

    'features' => [
        'project_analysis' => [
            'enabled' => env('AI_PROJECT_ANALYSIS_ENABLED', true),
            'cache_ttl' => env('AI_PROJECT_ANALYSIS_CACHE_TTL', 3600),
            'max_description_length' => env('AI_PROJECT_ANALYSIS_MAX_LENGTH', 2000),
        ],

        'task_assignment' => [
            'enabled' => env('AI_TASK_ASSIGNMENT_ENABLED', true),
            'cache_ttl' => env('AI_TASK_ASSIGNMENT_CACHE_TTL', 1800),
            'min_confidence_score' => env('AI_TASK_ASSIGNMENT_MIN_CONFIDENCE', 0.7),
        ],

        'predictive_analytics' => [
            'enabled' => env('AI_PREDICTIVE_ANALYTICS_ENABLED', true),
            'cache_ttl' => env('AI_PREDICTIVE_ANALYTICS_CACHE_TTL', 7200),
            'min_confidence_score' => env('AI_PREDICTIVE_ANALYTICS_MIN_CONFIDENCE', 0.6),
        ],

        'natural_language_processing' => [
            'enabled' => env('AI_NLP_ENABLED', true),
            'cache_ttl' => env('AI_NLP_CACHE_TTL', 1800),
            'max_query_length' => env('AI_NLP_MAX_QUERY_LENGTH', 500),
        ],

        'content_generation' => [
            'enabled' => env('AI_CONTENT_GENERATION_ENABLED', true),
            'cache_ttl' => env('AI_CONTENT_GENERATION_CACHE_TTL', 3600),
            'max_context_length' => env('AI_CONTENT_GENERATION_MAX_CONTEXT', 1000),
        ],

        'sentiment_analysis' => [
            'enabled' => env('AI_SENTIMENT_ANALYSIS_ENABLED', true),
            'cache_ttl' => env('AI_SENTIMENT_ANALYSIS_CACHE_TTL', 1800),
            'max_text_length' => env('AI_SENTIMENT_ANALYSIS_MAX_LENGTH', 1000),
        ],

        'risk_assessment' => [
            'enabled' => env('AI_RISK_ASSESSMENT_ENABLED', true),
            'cache_ttl' => env('AI_RISK_ASSESSMENT_CACHE_TTL', 3600),
            'min_confidence_score' => env('AI_RISK_ASSESSMENT_MIN_CONFIDENCE', 0.7),
        ],

        'recommendations' => [
            'enabled' => env('AI_RECOMMENDATIONS_ENABLED', true),
            'cache_ttl' => env('AI_RECOMMENDATIONS_CACHE_TTL', 3600),
            'min_confidence_score' => env('AI_RECOMMENDATIONS_MIN_CONFIDENCE', 0.7),
        ],
    ],

    'rate_limiting' => [
        'enabled' => env('AI_RATE_LIMITING_ENABLED', true),
        'requests_per_minute' => env('AI_RATE_LIMIT_PER_MINUTE', 60),
        'requests_per_hour' => env('AI_RATE_LIMIT_PER_HOUR', 1000),
        'requests_per_day' => env('AI_RATE_LIMIT_PER_DAY', 10000),
    ],

    'monitoring' => [
        'enabled' => env('AI_MONITORING_ENABLED', true),
        'log_requests' => env('AI_LOG_REQUESTS', true),
        'log_responses' => env('AI_LOG_RESPONSES', false),
        'track_usage' => env('AI_TRACK_USAGE', true),
        'alert_on_errors' => env('AI_ALERT_ON_ERRORS', true),
    ],

    'security' => [
        'enabled' => env('AI_SECURITY_ENABLED', true),
        'sanitize_input' => env('AI_SANITIZE_INPUT', true),
        'validate_output' => env('AI_VALIDATE_OUTPUT', true),
        'encrypt_sensitive_data' => env('AI_ENCRYPT_SENSITIVE_DATA', true),
        'audit_log' => env('AI_AUDIT_LOG', true),
    ],

    'fallback' => [
        'enabled' => env('AI_FALLBACK_ENABLED', true),
        'providers' => ['anthropic', 'local'],
        'mock_responses' => env('AI_MOCK_RESPONSES', true),
        'graceful_degradation' => env('AI_GRACEFUL_DEGRADATION', true),
    ],

    'cache' => [
        'enabled' => env('AI_CACHE_ENABLED', true),
        'default_ttl' => env('AI_CACHE_DEFAULT_TTL', 3600),
        'store' => env('AI_CACHE_STORE', 'redis'),
        'prefix' => env('AI_CACHE_PREFIX', 'ai:'),
    ],

    'webhooks' => [
        'enabled' => env('AI_WEBHOOKS_ENABLED', false),
        'url' => env('AI_WEBHOOK_URL'),
        'secret' => env('AI_WEBHOOK_SECRET'),
        'events' => [
            'request_completed',
            'request_failed',
            'rate_limit_exceeded',
            'provider_switched',
        ],
    ],
];
