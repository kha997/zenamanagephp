# Material Request Linked Receipts Projection Lock

Date: 2026-04-14
Status: accepted and implemented

## Scope

This lock closes one bounded request-owned read projection over the existing request-to-receipt linkage primitive:

- `GET /api/zena/material-requests/{id}/receipts`

## Contract

- Owner anchor stays on the canonical material-request owner family:
  - `GET /api/zena/material-requests/{id}/receipts`
- Request lookup reuses the same tenant-safe request lookup pattern as the existing canonical material-request family.
- Cross-tenant or out-of-scope request lookup returns safe `404`.
- Linked receipt headers are read from canonical `material_receipts` where:
  - `tenant_id = current tenant`
  - `material_request_id = {id}`

## Response contract

- The projection reuses the canonical receipt header payload shape
- `material_request_id` remains the additive linkage field already proved on receipts
- No extra projection-only fields are added

## Guardrails

- No new query params
- No request status side effects
- No receipt-line linkage
- No vendor, PO, inventory, or notification semantics
- No `/api/v1/*`

## Evidence lane

- `php artisan route:list --except-vendor -v --path=api/zena/material-requests`
- `php ./vendor/bin/phpunit tests/Feature/Api/MaterialRequestApiTest.php tests/Feature/Api/MaterialReceiptApiTest.php tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
