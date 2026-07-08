# S6.7 Material Request Show Closure Lock

Date: 2026-04-12
Status: implementation-bound closure lock

## Why this memo exists

The bounded canonical owner family for material requests is already established by `S6.6`.

This round extends that same owner family by one narrower read-only proof only:

- `GET /api/zena/material-requests/{id}`

This memo records the exact show boundary so later work does not widen it into workflow or projection semantics.

## Locked slice

The closed slice is exactly:

- canonical owner family: `/api/zena/material-requests`
- exact owner anchor: `GET /api/zena/material-requests/{id}`
- proof surface: read-only canonical show only

The response contract is limited to schema-backed fields only:

- `id`
- `project_id`
- `request_number`
- `description`
- `status`
- `estimated_cost`
- `required_date`
- `requested_by`
- `approved_by`
- `approved_at`
- `created_at`
- `updated_at`

## Runtime result locked

Current canonical runtime now proves:

- canonical route `GET /api/zena/material-requests/{id}` is mounted on `App\Http\Controllers\Api\MaterialRequestController@show`
- show lookup reuses the same tenant-safe linked project boundary established by `S6.6`
- cross-tenant or out-of-scope lookup returns a safe `404`
- the response serializes only the approved schema-backed fields
- no positive proof is taken from `/api/zena/site-engineer/material-requests`

## Evidence

- route truth: `routes/api_zena.php`; `php artisan route:list --except-vendor -v --path=api/zena/material-requests`
- canonical model/controller: `app/Models/MaterialRequest.php`; `app/Http/Controllers/Api/MaterialRequestController.php`
- canonical feature proof: `tests/Feature/Api/MaterialRequestApiTest.php`
- owner-mapping proof: `tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
- prior owner-family closure: `docs/change-proposals/2026-04-12-s6-6-material-request-list-closure-lock.md`

## Exact claims now proved

- canonical owner is `GET /api/zena/material-requests/{id}`
- the slice is read-only show only
- tenant-safe by-id lookup is proved
- cross-tenant lookup returns `404`
- response shape is limited to the approved schema-backed fields only
- site-engineer projection residue is not the canonical owner proof

## Explicit non-claims

This memo does not claim:

- `POST /api/zena/material-requests`
- update/delete
- submit/approve/reject/fulfill workflow
- line items, quantities, or units
- purchase orders
- receipts
- vendors
- notifications
- approvals
- any `/api/v1/*` dependency

## Verdict

`S6.7` is closure-ready and closed as a bounded canonical material-request read-only show slice only.
