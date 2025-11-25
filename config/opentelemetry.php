<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenTelemetry Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for OpenTelemetry distributed tracing and metrics.
    | When enabled, traces will be exported to configured exporters.
    |
    */

    'enabled' => env('OPENTELEMETRY_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Service Information
    |--------------------------------------------------------------------------
    |
    | Service name and version for trace identification.
    |
    */

    'service_name' => env('OPENTELEMETRY_SERVICE_NAME', 'zenamanage'),
    'service_version' => env('OPENTELEMETRY_SERVICE_VERSION', '1.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Trace Exporter Configuration
    |--------------------------------------------------------------------------
    |
    | Configure trace exporters (Jaeger, Zipkin, OTLP, etc.)
    |
    */

    'trace' => [
        'enabled' => env('OPENTELEMETRY_TRACE_ENABLED', true),
        'exporter' => env('OPENTELEMETRY_TRACE_EXPORTER', 'jaeger'), // jaeger, zipkin, otlp, console
        
        'jaeger' => [
            'endpoint' => env('OPENTELEMETRY_JAEGER_ENDPOINT', 'http://localhost:14268/api/traces'),
        ],
        
        'zipkin' => [
            'endpoint' => env('OPENTELEMETRY_ZIPKIN_ENDPOINT', 'http://localhost:9411/api/v2/spans'),
        ],
        
        'otlp' => [
            'endpoint' => env('OPENTELEMETRY_OTLP_ENDPOINT', 'http://localhost:4318/v1/traces'),
            'protocol' => env('OPENTELEMETRY_OTLP_PROTOCOL', 'http/protobuf'), // http/protobuf, http/json
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Configuration
    |--------------------------------------------------------------------------
    |
    | Configure metrics collection and export.
    |
    */

    'metrics' => [
        'enabled' => env('OPENTELEMETRY_METRICS_ENABLED', true),
        'exporter' => env('OPENTELEMETRY_METRICS_EXPORTER', 'prometheus'), // prometheus, otlp
        
        'prometheus' => [
            'endpoint' => env('OPENTELEMETRY_PROMETHEUS_ENDPOINT', '/metrics'),
        ],
        
        'otlp' => [
            'endpoint' => env('OPENTELEMETRY_METRICS_OTLP_ENDPOINT', 'http://localhost:4318/v1/metrics'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sampling Configuration
    |--------------------------------------------------------------------------
    |
    | Configure trace sampling rate (0.0 to 1.0).
    | 1.0 = sample all traces, 0.1 = sample 10% of traces
    |
    */

    'sampling_rate' => env('OPENTELEMETRY_SAMPLING_RATE', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Resource Attributes
    |--------------------------------------------------------------------------
    |
    | Additional attributes to attach to all traces and metrics.
    |
    */

    'resource_attributes' => [
        'environment' => env('APP_ENV', 'local'),
        'deployment.environment' => env('APP_ENV', 'local'),
    ],
];
