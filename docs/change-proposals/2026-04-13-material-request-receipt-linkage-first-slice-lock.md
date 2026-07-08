# Material Request To Receipt Linkage First Slice Lock

Date: 2026-04-13
Status: accepted and implemented

## Scope

This lock closes the first bounded runtime slice for the approved request-to-receipt linkage primitive:

- nullable `material_receipts.material_request_id`
- additive linkage only on canonical `POST /api/zena/material-receipts`

## Contract

- Owner anchor stays on the existing canonical receipt header family:
  - `POST /api/zena/material-receipts`
- The linkage primitive stays header-only.
- `material_request_id` is optional.
- If omitted, existing receipt create behavior stays unchanged.
- If present:
  - it must reference an existing `zena_material_requests.id`
  - it must resolve through the linked request project inside the current tenant boundary
  - it must belong to the same `project_id` as the receipt header being created

## Payload contract

- Canonical receipt payload now includes additive `material_request_id`
- No existing receipt payload field is removed or renamed

## Failure rules

- Invalid same-tenant or same-project request linkage returns safe `422`
- Existing receipt owner lookup and anti-enumeration behavior remain unchanged

## Guardrails

- No receipt list filter by `material_request_id`
- No request-anchored receipt route
- No line linkage
- No request status side effect
- No quantity reconciliation
- No PO, vendor, inventory, notification, or contract side effect
- No `/api/v1/*`

## Evidence lane

- `php artisan route:list --except-vendor -v --path=api/zena/material-receipts`
- `php ./vendor/bin/phpunit tests/Feature/Api/MaterialReceiptApiTest.php tests/Feature/Api/MaterialRequestApiTest.php tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
