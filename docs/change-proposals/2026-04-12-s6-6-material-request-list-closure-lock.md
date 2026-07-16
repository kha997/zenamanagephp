# S6.6 Material Request List Closure Lock

Date: 2026-04-12
Status: implementation-bound closure lock

## Why this memo exists

The bounded canonical owner slice for `GET /api/zena/material-requests` is already implemented and accepted on this branch.

This round does not widen that runtime scope.

This memo exists to closure-lock the accepted slice into SSOT so it no longer depends only on operating-decision truth.

## Locked slice

The closed slice is exactly:

- canonical owner family: `/api/zena/material-requests`
- exact owner anchor: `GET /api/zena/material-requests`
- proof surface: read-only canonical list only

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

Allowed query filters inside this same bounded slice:

- `project_id`
- `status`

## Runtime result locked

Current canonical runtime now proves:

- `MaterialRequest` explicitly binds to `zena_material_requests`
- canonical route `GET /api/zena/material-requests` is mounted on `App\Http\Controllers\Api\MaterialRequestController@index`
- tenant-safe list scoping is enforced through the linked canonical project tenant boundary
- the response serializes only the approved schema-backed list fields
- no positive proof is taken from `/api/zena/site-engineer/material-requests`

## Evidence

- route truth: `routes/api_zena.php`; `php artisan route:list --except-vendor -v --path=api/zena/material-requests`
- migration truth: `database/migrations/2025_09_14_110000_create_zena_system_tables.php`
- canonical model/controller: `app/Models/MaterialRequest.php`; `app/Http/Controllers/Api/MaterialRequestController.php`
- canonical feature proof: `tests/Feature/Api/MaterialRequestApiTest.php`
- owner-mapping proof: `tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
- accepted unlock boundary: `docs/change-proposals/2026-04-12-post-s6-5-material-request-owner-unlock-proposal.md`
- normalized SSOT state: `docs/roadmap/backlog.yaml`; `docs/progress.md`; `docs/architecture/module-ownership-ssot.md`

## Exact claims now proved

- canonical owner is `GET /api/zena/material-requests`
- the slice is read-only list only
- tenant-safe list isolation is proved
- optional `project_id` and `status` filters are proved
- response shape is limited to the approved schema-backed fields only
- site-engineer projection residue is not the canonical owner proof

## Explicit non-claims

This memo does not claim:

- `GET /api/zena/material-requests/{id}`
- `POST /api/zena/material-requests`
- update/delete
- submit/approve/reject/fulfill workflow
- purchase orders
- receipts
- vendor workflow
- approval workflow
- notifications
- any `/api/v1/*` dependency
- any broader material-request roadmap state beyond this bounded list slice

## Verdict

`S6.6` is closure-ready and closed as a bounded canonical material-request read-only list slice only.
