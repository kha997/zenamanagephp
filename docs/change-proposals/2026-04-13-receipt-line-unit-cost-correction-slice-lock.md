# Receipt Line Unit Cost And Notes Correction Slice Lock

Date: 2026-04-13
Status: accepted and implemented

## Scope

This lock closes one bounded corrective slice inside the canonical receipt-line owner family:

- `PUT /api/zena/material-receipts/{receipt}/lines/{line}`

## Contract

- Owner anchor stays on the existing canonical receipt-line family:
  - `GET /api/zena/material-receipts/{receipt}/lines`
  - `POST /api/zena/material-receipts/{receipt}/lines`
  - `GET /api/zena/material-receipts/{receipt}/lines/{line}`
  - `PUT /api/zena/material-receipts/{receipt}/lines/{line}`
- Scoped lookup stays on the existing tenant-safe receipt-plus-line child lookup.
- Route middleware stays on the existing create-side permission:
  - `rbac:material-receipt-line.create`

## Writable fields

- `unit_cost`
  - optional in the request
  - may be set or cleared
  - when present must be numeric and non-negative
- `notes`
  - optional in the request
  - may be set to a string or cleared to `null`

## Immutable fields

- `material_id`
- `quantity_received`
- `project_id`
- `material_receipt_id`

## Failure rules

- Cross-tenant or out-of-scope lookup returns safe `404`.
- Wrong-parent line lookup returns safe `404`.
- Invalid `unit_cost` validation returns safe `422`.
- Invalid `notes` validation returns safe `422`.

## Guardrails

- No new route family is introduced.
- No receipt-header changes are added.
- No request-receipt linkage is implied.
- No inventory, PO, vendor, or contract side effects are added.
- No derived amount, total, or summary fields are added.

## Evidence lane

- `php artisan route:list --except-vendor -v --path=api/zena/material-receipts`
- `php ./vendor/bin/phpunit tests/Feature/Api/MaterialReceiptLineApiTest.php tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
