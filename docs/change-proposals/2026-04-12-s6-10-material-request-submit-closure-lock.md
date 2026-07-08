# S6.10 Material Request Submit Closure Lock

Date: 2026-04-12
Status: accepted closure lock

## Bound

- Canonical owner anchor: `POST /api/zena/material-requests/{id}/submit`
- Owner controller: `App\Http\Controllers\Api\MaterialRequestController`
- Owner model: `App\Models\MaterialRequest`
- Persistence truth: `zena_material_requests`

## Approved Submit Contract

Submit constraints:

- Route guard is `rbac:material.request`
- Scoped lookup reuses the same tenant-safe owner query as list/show/update
- Submit is allowed only when current `status = draft`
- The only persisted transition is `draft -> submitted`

Fields that must remain unchanged:

- `request_number`
- `requested_by`
- `approved_by`
- `approved_at`
- all other persisted fields except `status`

Approval field contract:

- `approved_by` remains `null`
- `approved_at` remains `null`

Audit-column contract:

- Do not invent `submitted_by`
- Do not invent `submitted_at`

Failure shape:

- Cross-tenant or out-of-scope lookup returns `404`
- Non-draft submit returns `422`

Notification contract:

- Explicitly out of scope
- No notification side effects are created

## Non-Claims

This closure lock does not claim:

- approve/reject/fulfill semantics
- vendors, receipts, or purchase orders
- notifications
- any `/api/v1/*` compatibility owner
- any proof derived from `/api/zena/site-engineer/material-requests`

## Verification

- `php artisan route:list --except-vendor -v --path=api/zena/material-requests`
- `php ./vendor/bin/phpunit tests/Feature/Api/MaterialRequestApiTest.php`
- `php ./vendor/bin/phpunit tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`

## Closure Verdict

`POST /api/zena/material-requests/{id}/submit` is closure-locked for the bounded submit slice above only.
