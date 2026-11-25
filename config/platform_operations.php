<?php

declare(strict_types=1);

/*
 * OWN-2026-024 (O-17h) H2b — safeguards for destructive host maintenance and full database export.
 * The kill switch defaults to OFF: such operations are refused unless an operator deliberately enables them for a maintenance
 * window (PLATFORM_DESTRUCTIVE_OPS_ENABLED=true) — in addition to the platform permission, the operator ledger and an explicit
 * per-operation confirmation.
 */
return [
    'destructive_operations_enabled' => (bool) env('PLATFORM_DESTRUCTIVE_OPS_ENABLED', false),
];
