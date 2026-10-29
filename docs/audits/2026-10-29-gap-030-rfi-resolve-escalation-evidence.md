# GAP-030 — RFI escalation resolve is gated by the escalate permission: Gate-1 evidence

**Date:** 2026-10-29 (+07:00)

**Canonical base:** `23a909459b8161c613bdceabc6d52029b33d440c` (origin/main)

**Branch:** `docs/GAP-030-rfi-resolve-escalation`

**Scope:** Read-only investigation and Gate-1 documentation. A scratch SQLite database (outside the repository) was
seeded with `php artisan migrate:fresh --seed` to read the default role grants; no code, schema or repository data
changed.

## Candidate-ID audit

GAP-030 is the existing register row (`OPERATIONAL_GAP_REGISTER.md:65`, "OPEN (verified) — intentionally deferred").
`docs/owner-decisions/GAP-030/` does not exist on any `origin/*` ref.

## Finding 1 — the route gate (unchanged since the register row)

`routes/api_zena.php:287-288`: both `POST /api/zena/rfis/{id}/escalate` and `POST /api/zena/rfis/{id}/resolve-escalation`
use `rbac:rfi.escalate`. `RfiController::resolveEscalation()` (`app/Http/Controllers/Api/RfiController.php:508-561`)
already allows exactly: the escalation target (`escalated_to`), the project's PM, or an admin; anyone else 403. The
route middleware runs first, so a target without `rfi.escalate` never reaches that check.

`RfiController::escalate()` accepts any active user of the same tenant as `escalated_to` (no permission check on the
target). `tests/Feature/Api/RfiApiTest.php:496` (`test_escalation_target_can_resolve_their_own_escalation`) has to
grant `rfi.escalate` to the target to pass — the register row's evidence.

The register row records the agreed direction: keep a static `rbac:` gate (route-security invariant tests require one),
but a separate `rfi.resolve-escalation` permission checked on the actor; keep the controller's record-level check;
**do not guess which roles are valid actors — the role matrix needs a business decision first.**

## Finding 2 — default seeded grants (new)

Seeded with `DatabaseSeeder` on a scratch database:

| Role | RFI permissions |
|---|---|
| System Admin | `rfi.view, create, edit, delete, assign, respond, close, escalate, cancel` |
| Project Manager | **none** |
| Project Member | **none** |

`ZenaProjectManagerRolePermissionSeeder` grants `rfi.escalate` / `rfi.cancel` (and others) only to a role named
`project_manager` (`LOWER(name) = 'project_manager'`); the seeded role is `Project Manager`, so it matches nothing. With
default data, only System Admin can escalate or resolve an RFI escalation through the API, even though the controller
treats the project PM as allowed. (Environments that created a `project_manager` role by hand would get the grants.)

## Finding 3 — no web UI

No web route or view calls escalate / resolve-escalation; this is API-only.

## Decisions Gate 2 needs from the Owner

1. Which roles may act as **resolver** (hold `rfi.resolve-escalation`): e.g. all internal roles (the controller still
   limits to target / PM / admin), only Admin + PM, or also restrict escalation targets to users who hold it.
2. Whether to fix the PM seeder name mismatch (Finding 2) in the same Work ID, so seeded PMs get their RFI grants.
3. New environments only (seeder) or also existing data (the seeder is additive and can be re-run).
