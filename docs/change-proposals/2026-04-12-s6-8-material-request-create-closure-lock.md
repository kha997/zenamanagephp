# S6.8 Material Request Create Closure Lock

Date: 2026-04-12
Status: accepted closure lock

## Bound

- Canonical owner anchor: `POST /api/zena/material-requests`
- Owner controller: `App\Http\Controllers\Api\MaterialRequestController`
- Owner model: `App\Models\MaterialRequest`
- Persistence truth: `zena_material_requests`

## Approved Create Contract

Client-writable fields only:

- `project_id`
- `description`
- `estimated_cost` (optional)
- `required_date` (optional)

Server-managed fields:

- `request_number` is server-generated with an `MR-` prefix
- `status` is forced to `draft`
- `requested_by` is forced to the authenticated user id
- `approved_by` is `null`
- `approved_at` is `null`
- timestamps remain framework-managed

Authorization and scoping:

- Route guard is `rbac:material.request`
- Tenant-owned project is sufficient for this bounded slice
- Cross-tenant project lookup must fail safely

Anti-spoof rule:

- Client-supplied `request_number`, `status`, `requested_by`, `approved_by`, and `approved_at` are not persisted

## Non-Claims

This closure lock does not claim:

- update/delete semantics
- submit/approve/reject/fulfill workflow
- line items, quantities, or units
- purchase orders, receipts, vendors, or notifications
- any `/api/v1/*` compatibility owner
- any proof derived from `/api/zena/site-engineer/material-requests`

## Verification

- `php artisan route:list --except-vendor -v --path=api/zena/material-requests`
- `php ./vendor/bin/phpunit tests/Feature/Api/MaterialRequestApiTest.php`
- `php ./vendor/bin/phpunit tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`

## Closure Verdict

`POST /api/zena/material-requests` is closure-locked for the bounded create slice above only.
