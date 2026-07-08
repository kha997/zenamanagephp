# S6.11 — Material Request Approve Closure Lock

Date: 2026-04-12
Status: CLOSED

## Scope

Bounded canonical approve slice only:

- `POST /api/zena/material-requests/{id}/approve`

## Contract Locked

- Route stays on the canonical Material Requests owner family only.
- Route uses `rbac:material.approve`.
- Tenant-safe lookup reuses the existing canonical scoped owner query.
- Approve is allowed only when current `status = submitted`.
- The only persisted mutations are:
  - `status = approved`
  - `approved_by = auth()->id()`
  - `approved_at = now()`
- The following fields remain unchanged:
  - `project_id`
  - `request_number`
  - `description`
  - `estimated_cost`
  - `required_date`
  - `requested_by`
- Cross-tenant or out-of-scope lookup returns `404`.
- Non-submitted approve returns `422`.
- Response fields stay limited to:
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

## Explicit Non-Scope

- reject
- fulfill
- vendors
- receipts
- purchase orders
- notifications
- `/api/v1/*`
- site-engineer projection semantics
- any broader workflow semantics

## Proof Anchors

- `routes/api_zena.php`
- `app/Http/Controllers/Api/MaterialRequestController.php`
- `tests/Feature/Api/MaterialRequestApiTest.php`
- `tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
