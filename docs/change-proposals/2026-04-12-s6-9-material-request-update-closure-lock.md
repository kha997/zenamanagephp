# S6.9 Material Request Update Closure Lock

Date: 2026-04-12
Status: accepted closure lock

## Bound

- Canonical owner anchor: `PUT /api/zena/material-requests/{id}`
- Owner controller: `App\Http\Controllers\Api\MaterialRequestController`
- Owner model: `App\Models\MaterialRequest`
- Persistence truth: `zena_material_requests`

## Approved Update Contract

Client-writable fields only:

- `description`
- `estimated_cost` (optional)
- `required_date` (optional)

Protected fields:

- `project_id`
- `request_number`
- `status`
- `requested_by`
- `approved_by`
- `approved_at`

Update constraints:

- Update is allowed only when the record is in tenant-safe scope
- Update is allowed only when `status = draft`
- Route guard is `rbac:material.request`

Failure shape:

- Cross-tenant or out-of-scope lookup returns `404`
- Non-draft update returns `422`
- Spoofed protected fields are not persisted

## Non-Claims

This closure lock does not claim:

- delete semantics
- submit/approve/reject/fulfill workflow
- line items, quantities, or units
- vendors, receipts, purchase orders, or notifications
- any `/api/v1/*` compatibility owner
- any proof derived from `/api/zena/site-engineer/material-requests`

## Verification

- `php artisan route:list --except-vendor -v --path=api/zena/material-requests`
- `php ./vendor/bin/phpunit tests/Feature/Api/MaterialRequestApiTest.php`
- `php ./vendor/bin/phpunit tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`

## Closure Verdict

`PUT /api/zena/material-requests/{id}` is closure-locked for the bounded draft-only update slice above only.
