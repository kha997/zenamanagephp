# Material Receipt Linked Request Projection Lock

Date: 2026-04-14
Status: accepted and implemented

## Scope

This lock closes one bounded receipt-owned read projection over the existing request-to-receipt linkage primitive:

- `GET /api/zena/material-receipts/{id}/material-request`

## Contract

- Owner anchor stays on the canonical material-receipt owner family:
  - `GET /api/zena/material-receipts/{id}/material-request`
- Receipt lookup reuses the same tenant-safe receipt lookup pattern as the existing canonical material-receipt family.
- Cross-tenant or out-of-scope receipt lookup returns safe `404`.
- If the receipt has no `material_request_id`, the projection returns safe `404`.
- If linked, the projection resolves the canonical material request inside the existing tenant-safe request boundary.

## Response contract

- The projection reuses the canonical material-request payload shape.
- No extra projection-only fields are added.
- Existing receipt payloads remain unchanged.

## Guardrails

- No request status transitions.
- No receipt-line linkage.
- No new filters.
- No schema change.
- No inventory, PO, vendor, or notification semantics.
- No `/api/v1/*`

## Evidence lane

- `php artisan route:list --except-vendor -v --path=api/zena/material-receipts`
- `php ./vendor/bin/phpunit tests/Feature/Api/MaterialReceiptApiTest.php tests/Feature/Api/MaterialRequestApiTest.php tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
