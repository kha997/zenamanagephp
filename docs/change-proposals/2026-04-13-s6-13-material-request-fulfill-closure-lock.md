# S6.13 - Canonical Material Request Fulfill Closure Lock

Date: 2026-04-13
Status: accepted and implemented

## Scope

This lock closes the smallest bounded fulfill slice for the canonical Material Requests owner family:

- `POST /api/zena/material-requests/{id}/fulfill`

## Contract

- Permission: `rbac:material.receive`
- Scoped lookup: reuse the same tenant-safe owner query already used by canonical list/show/update/submit/approve/reject
- Precondition: current `status` must be exactly `approved`
- Transition: `approved -> fulfilled`
- Persisted mutation only:
  - `status = fulfilled`

## Explicit non-mutations

The fulfill slice leaves these persisted fields unchanged:

- `project_id`
- `request_number`
- `description`
- `estimated_cost`
- `required_date`
- `requested_by`
- `approved_by`
- `approved_at`

## Explicit non-invention

This slice does not invent:

- `fulfilled_by`
- `fulfilled_at`
- receipt linkage fields
- purchase-order side effects
- inventory side effects
- vendor semantics
- notification semantics

## Evidence lane

- `php artisan route:list --except-vendor -v --path=api/zena/material-requests`
- `php ./vendor/bin/phpunit tests/Feature/Api/MaterialRequestApiTest.php`
- `php ./vendor/bin/phpunit tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
