<?php declare(strict_types=1);

return [
    // Anthropic API key. Leave unset in any environment where AI suggestions
    // should be disabled — AiAssistService fails closed (returns null) when empty.
    'anthropic_api_key' => env('ANTHROPIC_API_KEY'),

    // Model used for lead-conversion suggestions (spec: docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md, Phase 7).
    'model' => env('AI_MODEL', 'claude-haiku-4-5-20251001'),
];
