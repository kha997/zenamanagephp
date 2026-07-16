<?php declare(strict_types=1);

return [
    // The Tenant.name value this integration is restricted to (see docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md, Phase 2).
    // Resolved at runtime via Tenant::where('name', ...) — never a hardcoded/copied tenant ID.
    'integration_tenant_name' => env('ZENA_BOQ_INTEGRATION_TENANT_NAME', 'Z.E.N.A'),

    // Bearer secret for the outbound read-only call to zena-boq-core's (not-yet-existing) read API.
    'read_api_secret' => env('ZENA_BOQ_READ_API_SECRET'),

    // Base URL of the zena-boq-core deployment, e.g. https://zena-boq.vercel.app
    'base_url' => env('ZENA_BOQ_BASE_URL'),
];
