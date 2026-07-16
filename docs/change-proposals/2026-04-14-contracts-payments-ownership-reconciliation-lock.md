# Contracts / Payments Ownership Reconciliation Lock

Date: 2026-04-14

## Scope

One bounded docs-only round to reconcile SSOT ownership docs with the current mounted canonical runtime for contracts and payments.

## Runtime Facts

Current canonical runtime mounts the finance-side owner family on the project-scoped `/api/zena/*` surface:

- `GET|POST /api/zena/projects/{project}/contracts`
- `GET|PUT|DELETE /api/zena/projects/{project}/contracts/{contract}`
- `GET /api/zena/projects/{project}/contracts/{contract}/material-receipts`
- `GET /api/zena/projects/{project}/contracts/{contract}/cost-summary`
- `GET|POST /api/zena/projects/{project}/contracts/{contract}/payments`
- `GET|PUT|DELETE /api/zena/projects/{project}/contracts/{contract}/payments/{payment}`

Compatibility routes remain mounted on `/api/v1/*`, but they are no longer the canonical owner truth.

## Drift Found

`docs/architecture/module-ownership-ssot.md` still described:

- Contracts as canonically owned at `/api/v1/projects/{project}/contracts`
- Payments as canonically owned at `/api/v1/contracts/{contract}/payments`

That drift no longer matched:

- `routes/api_zena.php`
- `php artisan route:list --path=api/zena/projects`
- contract/payment runtime tests and route invariants

## Reconciliation Decisions

- Canonical Contracts owner family is `/api/zena/projects/{project}/contracts`
- Canonical Payments owner family is `/api/zena/projects/{project}/contracts/{contract}/payments`
- Canonical Contract mapped receipts surface is `/api/zena/projects/{project}/contracts/{contract}/material-receipts`
- Canonical Contract cost summary surface is `/api/zena/projects/{project}/contracts/{contract}/cost-summary`
- `/api/v1/projects/{project}/contracts` and `/api/v1/contracts/{contract}/payments` remain documented only as compatibility aliases still mounted

## Non-Claims

- No runtime code change
- No route-surface change
- No controller-behavior change
- No auth/policy change
- No payload or business-semantic change

## Verify

- `php artisan route:list --path=api/zena/projects`
- `php ./vendor/bin/phpunit tests/Feature/Api/ContractApiTest.php tests/Feature/Api/ContractApiHardeningTest.php tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`

## Result

SSOT docs now match the mounted canonical runtime for contracts, payments, contract mapped receipts, and contract cost summary, while preserving `/api/v1/*` as compatibility-only documentation.
