# S5.4 QC Dashboard Widget Readiness Lock

Date: 2026-04-04
Story: `S5.4`
Story title: `QC dashboard widgets`
Status: runtime proved

## Why this round exists

`S5.4` is the exact next unresolved story in backlog order after `S5.3`, but the current backlog wording is too broad to support an honest runtime round.

The repo contains a large amount of dashboard residue, and the active canonical `/api/zena/*` surface does not yet prove a dedicated QC widget contract.

## Current evidence

### Backlog truth

- `docs/roadmap/backlog.yaml` marks `S5.1`, `S5.2`, and `S5.3` as `done`
- `docs/roadmap/backlog.yaml` marks `S5.4` as the next unresolved story in `EPIC-5`
- current acceptance before this lock was only:
  - `QC metrics available role-based and traceable`

### Canonical route truth

From `routes/api_zena.php` and `php artisan route:list`:

- mounted canonical role route exists:
  - `GET /api/zena/site-engineer/dashboard`
- mounted canonical QC-adjacent child route exists:
  - `GET /api/zena/site-engineer/inspections`
- mounted generic dashboard widget routes do not exist on `/api/zena/*`
- `routes/api_zena.php` API-info payload advertises:
  - `GET /api/zena/dashboard`
  - `GET /api/zena/dashboard/widgets`
  - `GET /api/zena/dashboard/metrics`
  - `GET /api/zena/dashboard/alerts`
- those advertised entries are not mounted canonical routes today, so they cannot be used as owner proof

### QMS data-source truth

Currently proved canonical QMS facts:

- inspections are canonically owned on `/api/zena/inspections`
- NCRs are canonically owned only as inspection children on `/api/zena/inspections/{inspection}/ncrs`
- overdue CAPA escalation is canonically task-owned on `/api/zena/tasks/{id}/escalate-overdue`
- the proved overdue basis is `tasks.end_date < now()`
- the proved CAPA marker is `inspection-ncr-capa`

### Residue / ambiguity that blocks runtime

- `app/Http/Controllers/Api/SiteEngineerDashboardController.php` mixes some real queries with sample/mock dashboard payload sections
- `app/Http/Controllers/Api/DashboardRoleBasedController.php` and broader dashboard services expose generic role/widget concepts outside the locked `/api/zena/*` owner family for this lane
- `/api/v1/dashboard*` compatibility surfaces are extensive, but they are not valid forward owner proof

## Owner-anchor decision

The first honest canonical owner anchor for `S5.4` is:

- `GET /api/zena/site-engineer/dashboard`

Why this is the best current anchor:

- it is already mounted on `/api/zena/*`
- it already has dedicated RBAC via `site-engineer.dashboard`
- it is already adjacent to the canonical QC-facing route `GET /api/zena/site-engineer/inspections`
- it avoids inventing ownership on unmounted generic `/api/zena/dashboard/*` routes

## First future runtime slice

The first future runtime slice is now locked to:

- a read-only QC summary widget embedded on `GET /api/zena/site-engineer/dashboard`

Minimal traceable sources allowed in that slice:

- inspection counts derived from canonical inspection facts
- NCR counts derived from canonical inspection-child NCR facts
- overdue CAPA task counts derived from canonical task facts using:
  - `inspection-ncr-capa`
  - `end_date < now()`
  - non-terminal task status

## Explicitly out of scope

- generic widget CRUD
- dashboard layout persistence
- cross-role dashboard convergence
- generic `/api/zena/dashboard/widgets`
- alert inbox or alert-taxonomy semantics
- stakeholder fan-out
- `/api/v1/*` dashboard proof

## Runtime outcome

The locked first runtime slice is now proved on the same date.

Exact proved owner surface:

- `GET /api/zena/site-engineer/dashboard`

Exact proved read-only projection:

- top-level `data.qc_widget`
- `data.qc_widget.widget_key = qc_summary`
- `data.qc_widget.inspections.{total,scheduled,in_progress,completed,failed}`
- `data.qc_widget.ncrs.{total,open,in_progress,resolved,closed}`
- `data.qc_widget.overdue_capa_tasks.total`

Exact proved data-source basis:

- inspection counts are derived from canonical inspection facts on tenant-safe, project-scoped QC plans
- NCR counts are derived from canonical inspection-child NCR facts only
- overdue CAPA counts are derived from canonical task facts only, limited to `inspection-ncr-capa`, `end_date < now()`, and non-terminal task statuses

Exact exclusions that remain true after runtime proof:

- no generic `/api/zena/dashboard/widgets|metrics|alerts` route family was mounted
- no widget CRUD or layout persistence was added
- no alert taxonomy or alert inbox semantics were added
- no cross-role dashboard convergence was added
- no `/api/v1/*` dashboard surface was used as proof

Verification used for the proved slice:

- `php artisan optimize:clear`
- `composer ssot:lint`
- `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Api/SiteEngineerDashboardApiTest.php tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
