# GAP-057 — Web requests run production migrations and cache compilation: Gate-1 evidence

**Date:** 2026-09-29 (+07:00)

**Canonical base:** `18cc0f796abd6735bd2abe1ebf318b10aec58d7d`

**Branch:** `docs/GAP-057-http-launch-actions`

**Scope:** Read-only investigation and Gate-1 documentation. No code, route,
config, test, CI, deployment or environment change. The runtime probe used a
disposable, never-committed PHPUnit file with `Artisan` mocked, so no command
actually ran; it was deleted before any commit.

## Origin

Discovered during GAP-055 Gate 1 (evidence
`docs/audits/2026-09-29-gap-055-http-cache-flush-evidence.md`, "Also
discovered on B1/B2") and deliberately left out of GAP-055's scope. GAP-055
removed only `cache:clear` from the same method.

## Defect

`App\Services\LaunchChecklistService::executePreLaunchActions()`
(`app/Services/LaunchChecklistService.php:219-252`) runs, from inside a web
request:

1. `config:clear`, `route:clear` (lines 226-227)
2. `optimize`, `config:cache`, `route:cache` (lines 235-237)
3. **`migrate --force`** (line 245)

Each step's exception is caught and reported as a string in the JSON body; the
request still returns 200.

## Reachability (runtime route table, canonical base, 1171 routes)

| Route | Handler | Calls `executePreLaunchActions()` |
|---|---|---|
| `POST api/v1/final-integration/pre-launch-actions` | `FinalIntegrationController::executePreLaunchActions()` | yes |
| **`GET api/v1/final-integration/launch-report`** | `FinalIntegrationController::generateLaunchReport()` (`app/Http/Controllers/FinalIntegrationController.php:193`) | **yes — a read-looking GET** |

Middleware (both): `web`, `Authenticate`, `TenantIsolationMiddleware`,
`RoleBasedAccessControlMiddleware:admin`, `InputSanitizationMiddleware`,
`ErrorEnvelopeMiddleware` (`routes/web.php:167-180`). `rbac:admin` admits
super-admins only (GAP-055 evidence).

No UI calls `final-integration/*` (`git grep` over `resources`, `public/js`:
no match); the routes are reachable by direct HTTP only.

### Runtime probe (disposable, `Artisan::call` mocked)

| Actor | Request | Status | Artisan commands invoked |
|---|---|---|---|
| `super_admin` | `GET /api/v1/final-integration/launch-report` | 200 | `config:clear`, `route:clear`, `optimize`, `config:cache`, `route:cache`, `migrate {"--force":true}` |
| `super_admin` | `POST /api/v1/final-integration/pre-launch-actions` | 200 | same six |
| tenant `admin` | `GET …/launch-report` | 403 | — |

## Why it matters

- **Bypasses the GAP-049 migration safety contract.** The sanctioned path
  (`.github/workflows/production.yml:230`) runs `php artisan deploy:migrate`,
  which refuses unclassified migrations and requires maintenance mode before
  any classified breaking migration (`app/Console/Commands/DeployMigrateCommand.php:16-55`,
  `docs/runbooks/gap-049-migration-safety.md`). The web path calls raw
  `migrate --force`: no classification check, no maintenance mode, no backup.
- **Runs against the live release while it serves traffic.** The deploy
  step builds `config:cache`/`route:cache`/`view:cache` in the new release
  directory before the atomic switch (`production.yml:230-236`). The web path
  rewrites the *current* release's `bootstrap/cache` mid-traffic, under the web
  server's user and PHP request time limit — a timeout can leave migrations or
  compiled caches half-applied.
- **A GET has side effects.** `launch-report` looks like a report; opening it
  (or a crawler/prefetch with a super-admin session) mutates schema and caches.
- **Failures are hidden.** Every step's exception becomes a string inside a
  200 response.

## Other methods on the same controller (checked, not defects of this gap)

`executeLaunchActions()`, `runLaunchPreparationTasks()` (except
`setupBackupSystem()` creating `storage/backups`), `validateIntegration`,
`runProductionCheck`, `completeLaunchTask`, `toggleChecklistItem`,
`executeAction` return simulated/static results or read-only checks. They are
misleading (always "success") but have no destructive side effect.

## Related, out of scope

- `Admin\MaintenanceController::optimize()` (`config:cache`/`route:cache`/
  `view:cache`) has **no route** in the runtime table.
- Dead controllers listed in GAP-055 evidence that call `config:clear` etc.
  have no route.

## Out of scope for this Gate 1

Any fix; the simulated "always success" launch-checklist endpoints; deleting
the final-integration feature; deployment.
