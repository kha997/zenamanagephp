# Material Request Receipt Linkage Filter Slice Lock

Date: 2026-04-14
Status: accepted and implemented

## Scope

This lock closes one bounded read-side refinement on the existing canonical receipt header owner family:

- `GET /api/zena/material-receipts`
- additive optional `material_request_id` filter only

## Contract

- Owner anchor stays on the existing canonical receipt header list route:
  - `GET /api/zena/material-receipts`
- Existing list behavior remains unchanged when `material_request_id` is absent.
- When `material_request_id` is present:
  - the existing tenant-safe receipt header list narrows to receipts whose `material_request_id` matches the provided value

## Payload contract

- Canonical receipt payload keeps additive `material_request_id`
- No existing receipt payload field is removed or renamed

## Failure and safety rules

- The filter stays inside the existing tenant-safe receipt owner query
- Cross-tenant receipts remain excluded by the normal owner boundary
- No special request workflow semantics are introduced

## Guardrails

- No new route family
- No request-anchored receipt route
- No create, update, or delete semantic change
- No line-level linkage
- No request status transition
- No inventory, PO, vendor, or notification side effect
- No `/api/v1/*`

## Evidence lane

- `php artisan route:list --except-vendor -v --path=api/zena/material-receipts`
- `php ./vendor/bin/phpunit tests/Feature/Api/MaterialReceiptApiTest.php tests/Feature/Api/MaterialRequestApiTest.php tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
