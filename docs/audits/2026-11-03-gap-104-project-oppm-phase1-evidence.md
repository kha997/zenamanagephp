# GAP-104 — Project OPPM Phase 1 (read-only): discovery evidence

Date: 2026-11-03. Base: `main` at `052a5aa39813336ff716919138b8e59945ae8381` (after GAP-103).
Scope source: issue #248 (Owner intent plus three Owner amendments), SSOT OWN-2026-009
(`docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md`) §4, §5, §8, §11, §14 item 9.
Method: static reading of routes, models, services, views and tests in this repository. No production data was available,
so data coverage is stated from write paths, not from row counts.

## 1. Already available after GAP-103

`App\Services\ProjectHealth\ProjectHealthReadService` (merged in #393):

- `progress()`: root components weighted by planned cost, else the active-task average (limited reliability), else
  no_data.
- schedule: `ProjectDelayStatus` against the latest baseline, plus milestone counts.
- attention: blocked/overdue tasks, blocked design items, overdue milestones, schedule late, Treasury alert count.
- finance: separate measures, each with availability and no profit figure.

SSOT §5.2 requires OPPM to consume this and not recompute progress, delay or attention. The KPI part of OPPM therefore
needs no new formula.

## 2. Header data (issue §1)

| Field | Source | Notes |
|---|---|---|
| Name, code, status, start/end dates | `projects` | already on the project page |
| Client | `Project::client` | already on the project page |
| PM | `Project::manager` | already on the project page |
| Scope summary | `projects.description` | free text, may be empty |
| Service Lines | `ProjectServiceLine` (GAP-046) | `DESIGN` / `CONSTRUCTION` / `INSPECTION`, with provenance; no rows means UNKNOWN (§2) |
| Report date | render time | no snapshot in Phase 1 |

## 3. Work groups ("nhóm công việc", issue §F)

Candidates found:

| Option | Data | Write path today | Fit |
|---|---|---|---|
| Root components (`components`, `parent_component_id` null) | name, dates, status, `progress_percent`, `planned_cost`/`actual_cost`; tasks link by `tasks.component_id` | API only (`Api\ComponentController`, `ComponentService`); the web task form has a component picker, but there is no web screen to create components | Same source as the GAP-103 progress definition (P-A), so progress per group and in total agree. Coverage is limited: projects without components show nothing |
| Project phases (`project_phases`, `tasks.phase_id`) | phase per task | only `UpdateTaskRequest` and the export projection; template-based | Thin; read through the `Src\CoreProject` compatibility model in places — against SSOT ownership |
| Work instances / steps (WorkTemplate, GAP-015) | step status, approvals, attachments | web (`/app/projects/{project}/work-instances`, `work.*` permissions) | Structured workflow per template, not a project schedule; separate permission (`work.view`) |
| Task list, no grouping | tasks with status/dates/blocked | web | Grows to tens or hundreds of rows — issue #248 forbids "one task per row" |

Gate 2 must pick one, with a fallback rule and a row cap (issue: 8–12 groups).

## 4. Milestones

- `project_milestones` exists (`ProjectMilestone`, no `tenant_id`, scoped through the project).
- **No live write path.** The milestone API routes were removed on 22/07
  (`docs/superpowers/specs/2026-07-22-dead-milestone-template-api-removal-design.md`), and no web form creates milestones.
- So the milestone block will show "Chưa có dữ liệu" for every project until a milestone-authoring Work ID exists. OPPM
  Phase 1 must render that honestly and must not add milestone authoring (out of scope).

## 5. Risks and blockers (issue §4)

- Tasks: `blocked_at`, `blocker_note`, `blocked_by`.
- Design items: `blocked_at`.
- Overdue: tasks by `end_date`; milestones (no data, §4).
- All three are already counted in GAP-103's attention section. A list (top N rows with owner and date) needs a bounded
  query per source.
- "Decisions needed" have no source table. They belong to issue #248 Phase 2/3 (overlay) and are out of scope here.

## 6. Commercial & Finance (issue §E, SSOT §5.5, §8)

- GAP-103's finance section already separates budget, contract value, cash received, cost recorded (not cash paid) and
  Treasury held/expenses.
- SSOT §5.5 says the OPPM block is a compact one-project summary with drill-down links (contract page, Treasury page when
  permitted).
- There must be no profit or net figure and no company-wide tables.

### Performance note (carried from the GAP-103 Codex review)

`forProject()` counts Treasury alerts through `TreasuryDashboardService::dashboard()`. Its cost grows with the number of
posted advances (about 8 queries per advance).

An OPPM page that calls `forProject()` inherits that cost. Gate 2 must choose one of these:
- accept it (same cost as the Treasury page);
- skip the alert count on OPPM;
- batch the alert count. This changes Treasury code, so it needs an allowlist amendment.

## 7. Authorization and tenant isolation (issue §H)

- `/app/projects/{project}` (`Web\ProjectController::show`) has route middleware `auth` and `tenant.isolation`, and loads the
  project with `where('tenant_id', user tenant)`. There is no `rbac:project.view` on the show route. The document-render
  route on the same project uses `rbac:project.view`.
- `project.view` exists. SSOT §11 requires reusing existing permission names, so no new permission.
- The Treasury block must keep using the Treasury gate (`treasury.view-project`), which the project page already uses.
- `project_milestones` and `baselines` have no `tenant_id`; they are read through the tenant-checked project (as GAP-103
  does).

## 8. UI patterns and print (issue §G)

- Operator layout (`layouts.operator`) with `x-ui.*` components: `card`, `field-value`, `status-badge`, `data-table`,
  `page-header`, `button-link`.
- Schedule/Gantt page: `/app/schedule` (`Web\SchedulePageController`, `rbac:task.view`).
- The read-service + Blade pattern is already used by the Today workspace (`TodayWorkspaceReadService`).
- No `@media print` stylesheet exists in `resources/css`. A print layout is new CSS, in the Blade view or `operator.css`.
- PDF export: the work-instance export is the only existing one, and it is out of scope.

## 9. Tests and CI conventions (issue §J)

Existing patterns that Gate 2 can follow:
- Feature tests per page.
- Tenant-isolation tests.
- Query-count invariance (as in `ProjectHealthReadServiceTest`).
- Route guard (`scripts/ci/route-guard.php`), deptrac, PHPStan, SSOT test lint.

Browser/Dusk is not needed for a read-only page.

## 10. Out of scope (SSOT §5.3, issue #248 amendments)

- Overlay input (outcome, weight, next action, notes, decisions).
- Snapshots or period reports; PDF export.
- Milestone authoring; component authoring UI.
- Service-Line CRUD; Contract Control / Finance Control portfolios; Treasury ledger.
- Control Tower / portfolio pages; AI forecasting.
- Fixing `ProjectAnalyticsService` (separate Work ID, GAP-103 X-1).

## 11. UNKNOWNs

- Real data coverage of components, phases, work instances and Service Lines in production (no data access). Gate 2 must
  design for "none present" as a normal case.
- Whether the Owner wants OPPM as a separate page with a print layout (issue #248 Phase 1), or as a section of the project
  page.
