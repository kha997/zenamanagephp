# Receipt Header Correction Slice Lock

Date: 2026-04-13
Status: accepted and implemented

## Scope

This lock closes one bounded write slice inside the canonical receipt header owner family:

- `PUT /api/zena/material-receipts/{id}`

## Contract

- Owner anchor stays on the existing canonical receipt header family:
  - `GET /api/zena/material-receipts`
  - `POST /api/zena/material-receipts`
  - `GET /api/zena/material-receipts/{id}`
  - `PUT /api/zena/material-receipts/{id}`
- Scoped lookup stays on the existing tenant-safe receipt owner lookup.
- Route middleware stays on the existing create-side permission:
  - `rbac:material-receipt.create`

## Writable fields

- `vendor_id`
  - optional
  - may be set or cleared
  - if present must belong to the current tenant
- `contract_id`
  - optional
  - may be set or cleared
  - if present must belong to the current tenant
  - if present must belong to the same `project_id` as the receipt header
- `material_request_id`
  - optional
  - may be set or cleared
  - if present must reference an existing `zena_material_requests.id`
  - if present must resolve through the linked request project inside the current tenant boundary
  - if present must belong to the same `project_id` as the receipt header
- `receipt_date`

## Immutable fields

- `project_id`
- `receipt_number`

## Preconditions

- Header correction is allowed only when the receipt has no receipt lines yet.

## Failure rules

- Cross-tenant or out-of-scope lookup returns safe `404`.
- Invalid same-tenant relation checks return safe `422`.
- Any existing receipt lines block correction with safe `422`.

## Guardrails

- No new route family is introduced.
- No receipt line edits are added.
- No request status transitions, line linkage, or quantity reconciliation are added.
- No inventory, PO, or vendor side effects are added.
- No new writable header fields are introduced beyond bounded `material_request_id` correction.

## Evidence lane

- `php artisan route:list --except-vendor -v --path=api/zena/material-receipts`
- `php ./vendor/bin/phpunit tests/Feature/Api/MaterialReceiptApiTest.php tests/Feature/Api/MaterialReceiptLineApiTest.php tests/Feature/Api/MaterialRequestApiTest.php tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
