# Procurement Receiving + Contract Cost Pilot Preflight Automation Lock

Date: 2026-04-15
Type: bounded implementation lock
Status: accepted

## Purpose

Codify the canonical pilot preflight lane for the already locked procurement receiving + contract cost read-side vertical into one deterministic, fail-fast script.

This round does not change runtime business behavior. It only automates the already accepted pilot verification lane.

## Canonical Preflight Script

The canonical preflight entrypoint for this pilot is:

- `scripts/pilot/procurement_receiving_contract_cost_preflight.sh`

The script:

- starts with `set -euo pipefail`
- resolves repo root safely before execution
- prints each verification step before running it
- runs the pilot route check plus each targeted PHPUnit suite as a separate command
- fails fast on the first failing step
- ends with a clear pilot-only success summary

## Exact Commands Codified

The script codifies exactly these commands in order:

1. `php artisan route:list --path=api/zena`
2. `php ./vendor/bin/phpunit tests/Feature/Api/ProcurementReceivingContractCostPilotSmokeTest.php`
3. `php ./vendor/bin/phpunit tests/Feature/Api/MaterialRequestApiTest.php`
4. `php ./vendor/bin/phpunit tests/Feature/Api/MaterialReceiptApiTest.php`
5. `php ./vendor/bin/phpunit tests/Feature/Api/MaterialReceiptLineApiTest.php`
6. `php ./vendor/bin/phpunit tests/Feature/Api/MaterialReceiptChecklistApiTest.php`
7. `php ./vendor/bin/phpunit tests/Feature/Api/ContractApiTest.php`
8. `php ./vendor/bin/phpunit tests/Feature/Api/ContractApiHardeningTest.php`
9. `php ./vendor/bin/phpunit tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`

## Why Multi-File Positional PHPUnit Is Not Used

This repo currently gives false confidence when multiple positional test file paths are passed to one PHPUnit command: only the first positional file is actually executed in the observed pilot lane.

Because of that repo-specific behavior, this preflight script deliberately runs each suite as its own explicit command. That keeps pilot verification honest and deterministic.

## Scope Boundary

This preflight verifies only the locked pilot vertical:

- materials
- vendors
- material requests
- material receipts
- receipt checklist
- receipt lines
- project-scoped contracts
- contract mapped receipts
- contract cost summary

It is not a full-suite repo proof, not a CI redesign, and not a new business-semantic contract.
