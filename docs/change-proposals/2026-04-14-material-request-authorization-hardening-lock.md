# Material Request Authorization Hardening Lock

Date: 2026-04-14

## Scope

Lock one bounded authorization-hardening slice on the existing canonical material-request owner family:

- `GET /api/zena/material-requests`
- `GET /api/zena/material-requests/{id}`
- `GET /api/zena/material-requests/{id}/receipts`
- `POST /api/zena/material-requests`
- `PUT /api/zena/material-requests/{id}`
- `POST /api/zena/material-requests/{id}/submit`
- `POST /api/zena/material-requests/{id}/approve`
- `POST /api/zena/material-requests/{id}/reject`
- `POST /api/zena/material-requests/{id}/fulfill`

## Hardening Gap

The owner family already had route-level RBAC and tenant-scoped lookup, but controller actions did not yet apply explicit policy authorization. That left the family short of the canonical owner-discipline already used by other hardened owner families such as contracts and contract payments.

## Locked Decisions

- `MaterialRequestPolicy` is the canonical authorization owner for `App\Models\MaterialRequest`.
- Class-level decisions:
  - `viewAny` => `material.read`
  - `create` => `material.request`
- Instance-level decisions require both canonical permission and tenant parity through the linked request project tenant:
  - `view` => `material.read`
  - `update` => `material.request`
  - `submit` => `material.request`
  - `approve` => `material.approve`
  - `reject` => `material.approve`
  - `fulfill` => `material.receive`
- `receipts()` reuses the same explicit `view` authorization as `show()`.
- Tenant-safe `404` anti-enumeration remains anchored in the pre-existing tenant-scoped request lookup before authorization.

## Explicit Non-Claims

- No route-surface change.
- No response-payload change.
- No status-machine change.
- No request↔receipt linkage behavior change.
- No receipt line, quantity, contract, payment, dashboard, or UI semantics change.

## Verify

- `php artisan route:list --path=api/zena/material-requests`
- `php ./vendor/bin/phpunit tests/Feature/Api/MaterialRequestApiTest.php tests/Feature/Api/MaterialReceiptApiTest.php tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`

## Result

This slice is locked as authorization hardening only. It raises the material-request owner family to explicit canonical policy discipline without widening business semantics.
