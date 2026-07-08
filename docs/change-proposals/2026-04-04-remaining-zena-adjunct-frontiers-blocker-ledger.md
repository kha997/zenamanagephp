# Remaining ZENA Adjunct Frontiers Blocker Ledger

## Context snapshot
The following clusters are already closure-ready and out of scope for this round: `contract-payment`, `site-engineer/inspections`, `notifications`, and `pm/progress`. This round only inventories the remaining mounted adjunct frontiers under `pm`, `designer`, and `site-engineer`, using runtime/schema/model/controller/test truth only.

## Operating rules
- Mounted route != roadmap anchor.
- A mounted route can still be a valid implementation slice if runtime truth is sufficient.
- This round does not invent new roadmap/story/owner semantics.
- This round does not patch runtime.

## Candidate inventory matrix
| Route / owner | Owner clarity | Schema truth | Runtime truth | Test truth | Risk of scope drift | Status | Exact blocker(s) | Minimum truth needed to unblock |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `GET /api/zena/pm/dashboard` / `PmDashboardController@getOverview` | Clear | `projects`, `tasks`, `rfis` exist; no blocker on core widget schema | Core widget uses fact queries, but `recent_activities` and `upcoming_milestones` are sample payloads in controller | Direct feature coverage exists for PM widget in [`tests/Feature/Api/PmDashboardApiTest.php#L40`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/tests/Feature/Api/PmDashboardApiTest.php#L40) | High | `HARD-BLOCKED` | Route payload still includes sample/static sections at [`app/Http/Controllers/Api/PmDashboardController.php#L74`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/PmDashboardController.php#L74), [`app/Http/Controllers/Api/PmDashboardController.php#L461`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/PmDashboardController.php#L461), [`app/Http/Controllers/Api/PmDashboardController.php#L487`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/PmDashboardController.php#L487) | Real fact source for activities/milestones or a narrower route contract explicitly excluding those sections |
| `GET /api/zena/pm/weekly-report` / `PmDashboardController@getWeeklyReport` | Clear | `rfis.status` enum does not include `resolved`; `projects` stores `progress`, not `progress_percentage` | Controller reads `progress_percentage` and queries `Rfi.status = resolved` at [`app/Http/Controllers/Api/PmDashboardController.php#L298`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/PmDashboardController.php#L298), [`app/Http/Controllers/Api/PmDashboardController.php#L308`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/PmDashboardController.php#L308) | No direct route test | High | `HARD-BLOCKED` | Missing truth for `resolved`; missing truth for `progress_percentage` | Prove `resolved` as real RFI runtime semantic and prove `progress_percentage` as real project runtime field |
| `GET /api/zena/pm/risks` / `PmDashboardController@getRiskAssessment` | Clear | `tasks` table has `end_date`, not `due_date`; no separate budget/resource conflict schema cited | `high_risk_tasks` and `overdue_items` query `due_date`; `budget_risks`, `resource_conflicts`, `quality_issues` are sample/static helpers at [`app/Http/Controllers/Api/PmDashboardController.php#L249`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/PmDashboardController.php#L249), [`app/Http/Controllers/Api/PmDashboardController.php#L511`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/PmDashboardController.php#L511), [`app/Http/Controllers/Api/PmDashboardController.php#L555`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/PmDashboardController.php#L555) | No direct route test | High | `HARD-BLOCKED` | Missing `tasks.due_date` truth; 3/5 sections are sample payloads | Prove real due-date field semantics for risk tasks and real fact sources for budget/resource/quality sections |
| `GET /api/zena/designer/dashboard` / `DesignerDashboardController@getOverview` | Clear | `projects.progress_percentage` not proved; `submittals` has only sqlite test shim, no migration proof; no `drawings` migration found | Overview reads `progress_percentage`, counts drawings by `pending_review`, and returns sample `recent_activities` at [`app/Http/Controllers/Api/DesignerDashboardController.php#L59`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/DesignerDashboardController.php#L59), [`app/Http/Controllers/Api/DesignerDashboardController.php#L74`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/DesignerDashboardController.php#L74), [`app/Http/Controllers/Api/DesignerDashboardController.php#L452`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/DesignerDashboardController.php#L452) | Feature tests cover only `designer_widget` counts in [`tests/Feature/Api/DesignerDashboardApiTest.php#L39`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/tests/Feature/Api/DesignerDashboardApiTest.php#L39) | High | `HARD-BLOCKED` | Unproven `progress_percentage`; no real drawings table proof; submittals rely on test-only shim; sample activity payload | Prove project progress field, prove drawings schema, prove real submittals table, and replace sample activity source with fact source |
| `GET /api/zena/designer/tasks` / `DesignerDashboardController@getDesignTasks` | Clear | `tasks` migration proves `end_date`, `assignee_id`; does not prove `type` or `due_date` at [`database/migrations/2025_09_15_042450_create_tasks_table.php#L20`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/database/migrations/2025_09_15_042450_create_tasks_table.php#L20) | Controller filters `type = design`, orders by `due_date`, and eager-loads `assignedUser` relation that does not exist on `Task` (`assignee()` exists) at [`app/Http/Controllers/Api/DesignerDashboardController.php#L198`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/DesignerDashboardController.php#L198), [`app/Models/Task.php#L198`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Models/Task.php#L198) | No direct route test | Medium | `HARD-BLOCKED` | Missing `tasks.type`; missing `tasks.due_date`; missing `assignedUser` relation truth | Prove route-backed task typing, prove due-date field, and prove relation name used by controller |
| `GET /api/zena/designer/drawings` / `DesignerDashboardController@getDrawingsStatus` | Clear | No `drawings` migration found; model fields are `code`, `name`, `version`, status set includes `review` not `pending_review` at [`app/Models/Drawing.php#L12`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Models/Drawing.php#L12) | Controller projects `title`, `drawing_number`, `revision`, and counts `pending_review` at [`app/Http/Controllers/Api/DesignerDashboardController.php#L262`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/DesignerDashboardController.php#L262) | No direct route test; no drawing factory found | Medium | `HARD-BLOCKED` | Missing table/schema proof; controller/model field mismatch; status mismatch (`pending_review` vs model `review`) | Prove real drawings table and exact field/status contract consumed by this route |
| `GET /api/zena/designer/rfis` / `DesignerDashboardController@getRfisToAnswer` | Clear | `rfis` schema backs `assigned_to`, `due_date`, `status`, `created_by` | Controller only uses proven RFI fields and proven relations `project`/`createdBy` at [`app/Http/Controllers/Api/DesignerDashboardController.php#L295`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/DesignerDashboardController.php#L295) | No direct route test; adjacent RFI workflow tests prove statuses and timestamps | Low | `UNKNOWN` | No direct route-level contract proving response shape, scoping, and status filtering | A targeted route test covering tenant/project/status filtering and serialized shape |
| `GET /api/zena/designer/submittals` / `DesignerDashboardController@getSubmittalsStatus` | Clear but legacy/test-shim leaning | `Submittal` model exists, but no real `submittals` migration found; sqlite test shim creates table in [`tests/TestCase.php#L152`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/tests/TestCase.php#L152) | Controller query shape is internally consistent, but runtime depends on table not proved outside test bootstrap at [`app/Http/Controllers/Api/DesignerDashboardController.php#L352`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/DesignerDashboardController.php#L352) | No direct route test; only dashboard widget indirectly touches submittals | Medium | `HARD-BLOCKED` | No production migration/schema proof for `submittals`; current truth is test-only shim | Real migration/schema proof for `submittals` plus direct route contract test |
| `GET /api/zena/designer/workload` / `DesignerDashboardController@getDesignWorkload` | Clear | `tasks.type` and `tasks.due_date` not proved | `calculateCurrentWorkload()` and `getUpcomingDeadlines()` depend on `type = design` and `due_date`; route also aggregates across projects using those helpers at [`app/Http/Controllers/Api/DesignerDashboardController.php#L421`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/DesignerDashboardController.php#L421), [`app/Http/Controllers/Api/DesignerDashboardController.php#L503`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/DesignerDashboardController.php#L503), [`app/Http/Controllers/Api/DesignerDashboardController.php#L533`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/DesignerDashboardController.php#L533) | No direct route test | Medium | `HARD-BLOCKED` | Missing task-type truth; missing due-date truth | Prove route-backed task typing and due-date field semantics, then add direct route contract coverage |
| `GET /api/zena/site-engineer/dashboard` / `SiteEngineerDashboardController@getOverview` | Clear | `projects.progress_percentage` not proved; `tasks.type = site` not proved; no `material_requests` migration found | Overview reads `progress_percentage`, counts `type = site`, conditionally hits `material_requests`, and returns sample `recent_activities`/`site_conditions` at [`app/Http/Controllers/Api/SiteEngineerDashboardController.php#L59`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/SiteEngineerDashboardController.php#L59), [`app/Http/Controllers/Api/SiteEngineerDashboardController.php#L68`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/SiteEngineerDashboardController.php#L68), [`app/Http/Controllers/Api/SiteEngineerDashboardController.php#L505`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/SiteEngineerDashboardController.php#L505) | Feature tests cover only `qc_widget` in [`tests/Feature/Api/SiteEngineerDashboardApiTest.php#L43`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/tests/Feature/Api/SiteEngineerDashboardApiTest.php#L43) | High | `HARD-BLOCKED` | Unproven `progress_percentage`; unproven site-task typing; no material request schema proof; sample activity/site-condition payloads | Prove progress field, prove site task type, prove material request schema, and replace sample sections with real fact source |
| `GET /api/zena/site-engineer/tasks` / `SiteEngineerDashboardController@getSiteTasks` | Clear | `tasks.type` and `tasks.due_date` not proved | Controller filters `type = site`, orders by `due_date`, and eager-loads missing `assignedUser` relation at [`app/Http/Controllers/Api/SiteEngineerDashboardController.php#L206`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/SiteEngineerDashboardController.php#L206), [`app/Http/Controllers/Api/SiteEngineerDashboardController.php#L221`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/SiteEngineerDashboardController.php#L221), [`app/Models/Task.php#L198`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Models/Task.php#L198) | No direct route test | Medium | `HARD-BLOCKED` | Missing `tasks.type`; missing `tasks.due_date`; missing `assignedUser` relation truth | Prove site-task typing, due-date field, and relation name used by controller |
| `GET /api/zena/site-engineer/material-requests` / `SiteEngineerDashboardController@getMaterialRequests` | Clear but schema-poor | No `material_requests` migration found; model only proves `request_number`, `description`, `status`, `estimated_cost`, `required_date` at [`app/Models/MaterialRequest.php#L12`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Models/MaterialRequest.php#L12) | Controller projects `title`, `material_type`, `quantity`, `unit`, `priority`, `requested_date` that model does not prove at [`app/Http/Controllers/Api/SiteEngineerDashboardController.php#L275`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/SiteEngineerDashboardController.php#L275) | No direct route test; no factory found | Medium | `HARD-BLOCKED` | Missing table/schema proof and controller/model field mismatch | Prove real material request table and exact field contract consumed by this route |
| `GET /api/zena/site-engineer/rfis` / `SiteEngineerDashboardController@getSiteRfis` | Clear | `rfis` schema proves core fields; relation name proved as `assignedTo`, not `assignedUser` | Controller eager-loads and serializes missing `assignedUser` relation at [`app/Http/Controllers/Api/SiteEngineerDashboardController.php#L333`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/SiteEngineerDashboardController.php#L333), while model exposes `assignedTo()` at [`app/Models/Rfi.php#L101`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Models/Rfi.php#L101) | No direct route test | Low | `HARD-BLOCKED` | Missing relation alias/name used by controller | Prove `assignedUser` as real alias/relation or prove a route contract using existing relation name |
| `GET /api/zena/site-engineer/safety` / `SiteEngineerDashboardController@getSiteSafetyStatus` | Clear but domain surface is shell-only | No `safety_incidents`, safety checklist, or training schema proof found | Entire payload is controller-local sample data/calculation at [`app/Http/Controllers/Api/SiteEngineerDashboardController.php#L440`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/SiteEngineerDashboardController.php#L440), [`app/Http/Controllers/Api/SiteEngineerDashboardController.php#L553`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/SiteEngineerDashboardController.php#L553) | No direct route test | High | `HARD-BLOCKED` | No fact source; no schema/model proof for any safety payload section | Real persisted safety domain truth for incidents/checklists/training/score and direct route contract test |
| `GET /api/zena/site-engineer/daily-report` / `SiteEngineerDashboardController@getDailySiteReport` | Clear but domain surface is shell-only | No `daily_reports` schema; no persisted weather/material-delivery/observation tables proved | Entire payload is controller-local sample data and pseudo weather integration at [`app/Http/Controllers/Api/SiteEngineerDashboardController.php#L478`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/SiteEngineerDashboardController.php#L478), [`app/Http/Controllers/Api/SiteEngineerDashboardController.php#L618`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/SiteEngineerDashboardController.php#L618) | No direct route test | High | `HARD-BLOCKED` | No fact source for any section; no daily-report schema proof | Real persisted/reporting truth for weather/work/issues/deliveries/observations plus direct route contract test |

## Hard blockers by category
- Missing column / missing enum truth:
  `pm.weekly-report` (`rfis.status = resolved`, `projects.progress_percentage`), `pm.risks` (`tasks.due_date`), `designer.dashboard` (`projects.progress_percentage`), `designer.tasks` (`tasks.type`, `tasks.due_date`), `designer.workload` (`tasks.type`, `tasks.due_date`), `site-engineer.dashboard` (`projects.progress_percentage`, `tasks.type`), `site-engineer.tasks` (`tasks.type`, `tasks.due_date`).
- Sample/static payload instead of fact source:
  `pm.dashboard`, `pm.risks`, `designer.dashboard`, `site-engineer.dashboard`, `site-engineer.safety`, `site-engineer.daily-report`.
- Missing table/schema proof:
  `designer.drawings`, `designer.submittals`, `site-engineer.material-requests`, `site-engineer.safety`, `site-engineer.daily-report`.
- Ambiguous derived field semantics:
  `pm.weekly-report`, `designer.dashboard`, `site-engineer.dashboard` via `progress_percentage`.
- Runtime relation/field mismatch:
  `designer.drawings` (`title`/`drawing_number`/`revision` vs model `name`/`code`/`version`), `site-engineer.material-requests` (controller fields not in model), `designer.tasks` and `site-engineer.tasks` (`assignedUser`), `site-engineer.rfis` (`assignedUser`).
- No direct route test contract / only partial adjacent coverage:
  `pm.weekly-report`, `pm.risks`, `designer.tasks`, `designer.drawings`, `designer.rfis`, `designer.submittals`, `designer.workload`, `site-engineer.tasks`, `site-engineer.material-requests`, `site-engineer.rfis`, `site-engineer.safety`, `site-engineer.daily-report`.
- Test-only shim instead of production truth:
  `designer.submittals` because `submittals` table is created in [`tests/TestCase.php#L152`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/tests/TestCase.php#L152), not by a production migration.

## Per-candidate unblock conditions
### `GET /api/zena/pm/dashboard`
What is missing:
Real fact source for `recent_activities` and `upcoming_milestones`.

What would be enough to unblock safely:
Concrete runtime source proving those sections, or a proven route contract that excludes them from the safe slice.

### `GET /api/zena/pm/weekly-report`
What is missing:
Truth for RFI `resolved` status and truth for project `progress_percentage`.

What would be enough to unblock safely:
Runtime/schema/model/test evidence that both semantics are real in this repo.

### `GET /api/zena/pm/risks`
What is missing:
Truth for `tasks.due_date` and real persisted sources for budget/resource/quality risk sections.

What would be enough to unblock safely:
Schema/model proof of the due-date field used here and fact-backed runtime sources for every non-task risk section.

### `GET /api/zena/designer/dashboard`
What is missing:
Truth for `progress_percentage`, real drawings schema, real submittals runtime table, and non-sample activity source.

What would be enough to unblock safely:
Production schema/runtime proof for all four areas, not just widget-level indirect tests.

### `GET /api/zena/designer/tasks`
What is missing:
Truth for `tasks.type`, `tasks.due_date`, and `assignedUser` relation naming.

What would be enough to unblock safely:
Schema/model proof for the task typing and due-date semantics plus a real relation alias or matching route contract.

### `GET /api/zena/designer/drawings`
What is missing:
Any production drawings table proof and field/status contract matching the controller.

What would be enough to unblock safely:
Migration/model/test evidence for `drawings` with fields and statuses exactly consumed by this route.

### `GET /api/zena/designer/rfis`
What is missing:
Direct route contract proof for filtering, tenant safety, and serialized shape.

What would be enough to unblock safely:
A targeted route-level test proving this exact surface without changing semantics.

### `GET /api/zena/designer/submittals`
What is missing:
Production `submittals` schema truth outside the sqlite test shim.

What would be enough to unblock safely:
Real migration/schema proof plus a direct route-level contract test.

### `GET /api/zena/designer/workload`
What is missing:
Truth for design-task typing and due-date semantics used by both workload helpers.

What would be enough to unblock safely:
Schema/model/test evidence that the task fields queried by the route are real and stable.

### `GET /api/zena/site-engineer/dashboard`
What is missing:
Truth for `progress_percentage`, site-task typing, material request schema, and non-sample activity/site-condition sources.

What would be enough to unblock safely:
Production runtime/schema proof for each of those sections, not only the existing `qc_widget`.

### `GET /api/zena/site-engineer/tasks`
What is missing:
Truth for `tasks.type`, `tasks.due_date`, and `assignedUser`.

What would be enough to unblock safely:
Schema/model proof for those exact fields/relations plus a direct route contract.

### `GET /api/zena/site-engineer/material-requests`
What is missing:
Production table proof and field contract matching `title/material_type/quantity/unit/priority/requested_date`.

What would be enough to unblock safely:
Real schema/model/test evidence for the exact material request shape used by the controller.

### `GET /api/zena/site-engineer/rfis`
What is missing:
Truth for relation name `assignedUser` on `Rfi`.

What would be enough to unblock safely:
Model/runtime evidence that `assignedUser` is a real alias, or a tested route contract using the proven relation shape.

### `GET /api/zena/site-engineer/safety`
What is missing:
Any persisted safety domain truth for incidents, checklists, training, and score.

What would be enough to unblock safely:
Schema/model/runtime/test proof for those sections as fact-backed data, not controller-local samples.

### `GET /api/zena/site-engineer/daily-report`
What is missing:
Any persisted/reporting truth for weather, work completed/planned, issues, deliveries, and observations.

What would be enough to unblock safely:
Real runtime sources and direct route contract proof for every section in the response.

## Recommended stop-line
Do not touch `pm/weekly-report`, `pm/risks`, `designer/dashboard`, `designer/tasks`, `designer/drawings`, `designer/submittals`, `designer/workload`, `site-engineer/dashboard`, `site-engineer/tasks`, `site-engineer/material-requests`, `site-engineer/rfis`, `site-engineer/safety`, or `site-engineer/daily-report` without new truth. `designer/rfis` is the closest candidate to re-check later, but it still needs direct route-contract proof before being treated as safe. Under current truth, there is no remaining adjunct frontier that is clearly runtime-safe to reopen for implementation work.

## Final verdict
Under current repo truth, the bounded adjunct surfaces have reached the present runtime-safe ceiling. The remaining mounted frontiers are either hard-blocked by missing schema/runtime truth or still lack enough route-level proof to be treated as safe implementation slices right now.
