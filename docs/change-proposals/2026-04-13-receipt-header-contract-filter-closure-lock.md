# Receipt Header Contract Filter Closure Lock

Date: 2026-04-13
Status: accepted and implemented

## Scope

This lock closes one bounded read-only frontier slice inside the canonical receipt header owner family:

- `GET /api/zena/material-receipts?contract_id=...`

## Contract

- Owner anchor stays on the existing canonical receipt header list route:
  - `GET /api/zena/material-receipts`
- New behavior is limited to an optional same-surface read-only filter:
  - `contract_id`
- The filter applies only to the already-proved schema-backed receipt header field:
  - `material_receipts.contract_id`

## Guardrails

- No new route family is introduced.
- No write behavior is added.
- No receipt-line changes are added.
- No request-receipt linkage is implied.
- No vendor, PO, inventory, or workflow semantics are added.
- Cross-tenant enumeration remains blocked by the existing tenant-scoped receipt owner query.

## Evidence lane

- `php artisan route:list --except-vendor -v --path=api/zena/material-receipts`
- `php ./vendor/bin/phpunit tests/Feature/Api/MaterialReceiptApiTest.php`
