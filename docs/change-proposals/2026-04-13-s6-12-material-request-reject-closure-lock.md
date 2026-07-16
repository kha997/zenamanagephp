# S6.12 — Material Request Reject Closure Lock

Date: 2026-04-13
Status: CLOSED

## Scope

Bounded canonical reject slice only:

- `POST /api/zena/material-requests/{id}/reject`

## Contract Locked

- Route stays on the canonical Material Requests owner family only.
- Route uses `rbac:material.approve`.
- Tenant-safe lookup reuses the existing canonical scoped owner query.
- Reject is allowed only when current `status = submitted`.
- The only persisted mutation is:
  - `status = rejected`
- The following fields remain unchanged:
  - `project_id`
  - `request_number`
  - `description`
  - `estimated_cost`
  - `required_date`
  - `requested_by`
  - `approved_by`
  - `approved_at`
- Reject does not invent:
  - `rejected_by`
  - `rejected_at`
  - `rejection_reason`
- Cross-tenant or out-of-scope lookup returns `404`.
- Non-submitted reject returns `422`.
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
