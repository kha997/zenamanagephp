# GAP-014c — Durable NCR ↔ CAPA task link: Gate-1 evidence

**Date:** 2026-10-29 (+07:00)

**Canonical base:** `170b7eea44a35e9d2be5fe15e50159f1fc3f14d1` (origin/main)

**Branch:** `docs/GAP-014c-ncr-capa-link`

**Scope:** Read-only investigation and Gate-1 documentation. No code, schema or data change.

## Candidate-ID audit

GAP-014c is the existing register row (`OPERATIONAL_GAP_REGISTER.md:100`, OPEN, verified 2026-08-06), split from GAP-014
by OWN-2026-003. `docs/owner-decisions/GAP-014c/` does not exist on any `origin/*` ref.

## Finding 1 — the NCR record has no task link

`database/migrations/2025_09_20_142033_create_ncrs_table.php`: no `task_id` / `capa_task_id` column. `app/Models/Ncr.php`:
no task relation. `tasks` (`2025_09_15_042450_create_tasks_table.php` and later migrations): no `ncr_id`, no generic
`source_type` / `source_id`. Unchanged since the 2026-08-06 verification.

## Finding 2 — how a CAPA task is made today (API only, client-side)

`InspectionController::transformNcr()` (`app/Http/Controllers/Api/InspectionController.php`) returns a `task_handoff`
block on every NCR response: `owner_route` `/api/zena/tasks`, `POST`, and a ready payload (`project_id`, title
`CAPA <ncr_number> <title>`, description with the corrective/preventive action, `status: pending`, priority mapped from
severity, `tags: ['inspection-ncr-capa']`). The server does not create the task; an API client must POST the payload
itself. `tests/Feature/InspectionNcrWorkflowTest.php::test_can_prove_inspection_owned_ncr_slice_and_task_handoff` proves
exactly that flow.

The web NCR page (`resources/views/inspections/ncr-show.blade.php`, routes `inspections.ncrs.*` in `routes/web.php`)
has no CAPA-task action and shows no task.

## Finding 3 — the only marker is a generic tag

The created task carries `tags: ['inspection-ncr-capa']`, with no NCR id. Consumers:

- `TaskController` (`/api/zena/tasks/{id}/escalate-overdue`): a task is CAPA iff it has the tag.
- `SiteEngineerDashboardController`: counts overdue CAPA tasks by the tag.

Consequences:

- From an NCR, nothing lists its CAPA task(s) or their progress.
- From a CAPA task, nothing identifies its NCR (only the NCR number inside the free-text title, which a user can edit).
- An NCR can be resolved/closed while its CAPA task is still open, or with no CAPA task at all; nothing records which
  task delivered the corrective action.
- Any task tagged `inspection-ncr-capa` by hand counts as CAPA.

## Finding 4 — existing data

Existing CAPA tasks can only be matched to NCRs heuristically (NCR number in the title, same tenant and project). No
environment data was inspected (no environment access in scope).

## Options for Gate 2 (not chosen here)

1. Nullable `ncr_id` on `tasks` (tenant-checked), set when the CAPA task is created; NCR exposes its CAPA tasks
   (one NCR → many tasks).
2. Nullable `capa_task_id` on `ncrs` (one NCR → one task).
3. Link table `ncr_tasks` (many ↔ many).

Each needs: how the link is created (keep the client handoff and accept `ncr_id`, or a server-side "create CAPA task"
action, API and/or web), tenant/project integrity, what the NCR shows, whether close/resolve should check the CAPA task,
and whether to backfill existing tagged tasks. Gate 2 must include a migration-rollback plan
(`docs/superpowers/specs/2026-08-06-operational-gap-remediation-program-design.md:205`).
