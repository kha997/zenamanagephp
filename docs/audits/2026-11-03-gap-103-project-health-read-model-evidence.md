# GAP-103 — Shared Project Health Read Model (OPPM slice 2): Gate-1 evidence

**Date:** 2026-11-03 (+07:00)

**Canonical base:** `aefd55fd2dca9750d2eba51a2390ddb4a163df7a`

**Branch:** `docs/GAP-103-project-health-read-model`

**Scope:** Read-only investigation. No code change.

**Authority:** `docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md` (OWN-2026-009, Gate 3
approved) §4 requires *one* shared read model for Project Health / attention / delay / progress reliability, consumed
identically by OPPM, portfolio pages and Control Tower, and forbids rendering missing data as 0 / 0% / on-track. §14 lists
this as slice 2, before Project OPPM (slice 9, issue #248). Owner chose (2026-11-03, AskUserQuestion, verbatim: "Slice 2
trước, rồi OPPM P1 (Recommended)") to build this slice first.

## 1. Progress — four competing definitions, no shared one

| Source | Definition | Notes |
|---|---|---|
| `projects.progress` (stored) | Cost-weighted average of root components' `progress_percent` × `planned_cost` (`app/Models/Project.php:377-415`) | Only recalculated when a component changes or on the manual "recalculate" action (`Component.php:320`, `Web\ProjectController.php:341`, `Api\ProjectController.php:444`). **Stores `0.0` when there are no components or total planned cost is 0** — indistinguishable from "0% done" (violates §4.2). Shown as "Tiến độ" on the project page (`projects/show.blade.php:31`) and project lists. |
| `Project::calculateProgress()` | % of milestones with `status = completed` (`Project.php:420-433`) | Returns `0` when there are no milestones (same §4.2 problem). |
| `ProjectPhase` | Average of visible tasks' `progress_percent` (`ProjectPhase.php:124`) | Per phase only. |
| Task `progress_percent` | Per task (`Task.php`), per project task (`ProjectTask.php`) | Raw inputs. |

## 2. Delay / schedule — one good source

`App\Services\ProjectDelayStatus::evaluate()` (pure, date-only; states `completed`, `no_baseline`, `late`,
`forecast_late`, `on_track`) compares today and the current end date with the latest baseline. It already has an explicit
`no_baseline` state. Used by the project page (`_delay-badge`) and the app project list (`Web\AppController.php:66`).

## 3. Attention — computed ad hoc

- Blocked items: built inline in `Web\ProjectController::show` (`:172-186`) from design items and tasks with `blocked_at`.
- Overdue tasks / team exceptions: `TeamExceptionQuery`, `UpcomingMilestoneQuery` serve the Today Workspace (actor-scoped,
  out of scope per SSOT §1.7).
- No project-level attention flags exist as a reusable read.

## 4. Cost / finance — several distinct measures, none combined

- `projects.budget_total`, `projects.actual_cost` / `budget_actual` (stored); component `planned_cost` / `actual_cost`
  (`Project::recalculateActualCost()`).
- Contracts: value and paid total (`ContractPayment` with `status = paid`) shown per project in `Web\ProjectController::show`
  (`:188-200`).
- `ContractExpense.amount` is accrual (cost incurred), not cash paid (SSOT §6.3, §12 item 7).
- Project Treasury S1–S6 is now released (GAP-037 … GAP-097, `app/Services/Treasury/*`, `TreasuryDashboardService`), so
  SSOT §7.3's "mark Treasury measures unavailable" no longer has to apply; the SSOT's evidence matrix (§15) still says
  Treasury is not implemented.

## 5. Existing analytics service is broken

`App\Services\ProjectAnalyticsService::getProjectOverview()` (served by `GET /api/.../analytics/projects/{project}`,
`routes/api.php:284`) calls `$project->isOverdue()`, `getDaysRemaining()` and `getBudgetUtilization()`; none exists on
`App\Models\Project` (only `ProjectMilestone::isOverdue()`), so the endpoint would raise a `BadMethodCallException`. It is
not a usable base for the shared read model.

## 6. What does not exist

- A service that returns one project's health (progress + reliability, schedule state, attention flags, cost/cash measures
  with explicit availability) for every consumer.
- Availability / reliability metadata on progress or cost figures.
