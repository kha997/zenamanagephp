<?php

return [
    'docs_url' => env('API_MIGRATION_DOCS_URL', null),
    'sunset' => env('API_MIGRATION_SUNSET', null),
    'canonical_projects' => env('API_CANONICAL_PROJECTS', false),
    'canonical_documents' => env('API_CANONICAL_DOCUMENTS', false),
    'canonical_inspections' => env('API_CANONICAL_INSPECTIONS', false),
    'log_legacy_traffic' => env('API_MIGRATION_LOG_LEGACY_TRAFFIC', false),
    'log_sample_rate' => (float) env('API_MIGRATION_LOG_SAMPLE_RATE', 1.0),
];
