# ZENA Adjunct Safe Ceiling Consolidation Memo

## Context snapshot
The current adjunct stop-line is now explicit. The following bounded adjunct slices are already closure-ready: `contract-payment` bounded family, `site-engineer/inspections`, `notifications` bounded canonical family, `pm/progress`, and `designer/rfis`. This round is docs-only consolidation of the current safe ceiling under mounted adjunct surfaces; there is no runtime patch, no test patch, and no new frontier implementation in this memo.

## Closure-ready adjunct slices
- `contract-payment` bounded family
  - Route family / owner: `/api/zena/projects/{project}/contracts/{contract}/payments*` owned by `App\Http\Controllers\Api\ContractPaymentController`.
  - Nature of closure: bounded family hardening.
  - Key truth that made it safe: canonical nested route ownership is mounted on `routes/api_zena.php`; payment reads and writes are project-scoped and contract-scoped, and direct feature/hardening tests already prove tenant-safe and wrong-project rejection behavior.
- `site-engineer/inspections`
  - Route family / owner: `GET /api/zena/site-engineer/inspections` owned by `App\Http\Controllers\Api\SiteEngineerDashboardController`.
  - Nature of closure: bounded read-model projection.
  - Key truth that made it safe: the projection reads from real QC runtime truth rooted in canonical inspection lineage, and direct route tests already prove tenant safety, status filtering, ordering, and `inspection_date -> scheduled_date` mapping.
- `notifications` bounded canonical family
  - Route family / owner: `/api/zena/notifications*` owned by `App\Http\Controllers\Api\NotificationController`.
  - Nature of closure: bounded canonical read-model family.
  - Key truth that made it safe: canonical app-owned notification routes are mounted, the `notifications` table is real, and the mounted family is already constrained as delivery/read-model ownership rather than rule-definition ownership.
- `pm/progress`
  - Route family / owner: `GET /api/zena/pm/progress` owned by `App\Http\Controllers\Api\PmDashboardController`.
  - Nature of closure: bounded read-model projection hardening.
  - Key truth that made it safe: the route is mounted with tenant/RBAC guardrails, and direct feature tests already prove project-membership boundary, same-tenant denial without membership, and fact-backed progress payload behavior for the safe slice.
- `designer/rfis`
  - Route family / owner: `GET /api/zena/designer/rfis` owned by `App\Http\Controllers\Api\DesignerDashboardController`.
  - Nature of closure: projection hardening.
  - Key truth that made it safe: the route stays mounted under `/api/zena/designer/*`, its payload comes from canonical `App\Models\Rfi` fields and real relations, and direct feature plus invariant coverage now proves project-membership boundary, assignee scoping, tenant safety, RBAC, and route guardrails.

## Remaining blocked adjunct frontiers
- `pm/dashboard`
  - Route / owner: `GET /api/zena/pm/dashboard` on `PmDashboardController@getOverview`.
  - Status: `HARD-BLOCKED`
  - Exact blocker(s): payload still includes sample/static `recent_activities` and `upcoming_milestones`.
  - Minimum truth to reopen safely: real fact source for those sections, or a narrower proved contract that excludes them.
- `pm/weekly-report`
  - Route / owner: `GET /api/zena/pm/weekly-report` on `PmDashboardController@getWeeklyReport`.
  - Status: `HARD-BLOCKED`
  - Exact blocker(s): controller queries `Rfi.status = resolved` even though `rfis.status` schema does not prove `resolved`; controller also reads `projects.progress_percentage` while current project truth proves `progress`, not `progress_percentage`.
  - Minimum truth to reopen safely: schema/model/runtime/test proof that `resolved` and `progress_percentage` are real runtime semantics in this repo.
- `pm/risks`
  - Route / owner: `GET /api/zena/pm/risks` on `PmDashboardController@getRiskAssessment`.
  - Status: `HARD-BLOCKED`
  - Exact blocker(s): controller queries `tasks.due_date` while current task schema proves `end_date`; `budget_risks`, `resource_conflicts`, and `quality_issues` remain sample/static helpers rather than fact-backed sections.
  - Minimum truth to reopen safely: schema/model proof for the actual due-date field used by this route and real persisted fact sources for each non-task risk section.
- `designer/dashboard`
  - Route / owner: `GET /api/zena/designer/dashboard` on `DesignerDashboardController@getOverview`.
  - Status: `HARD-BLOCKED`
  - Exact blocker(s): unproven `projects.progress_percentage`, no real `drawings` migration proof, `submittals` still lean on test-only shim truth, and `recent_activities` remains sample/static.
  - Minimum truth to reopen safely: production schema/runtime proof for project progress, drawings, submittals, and activity facts.
- `designer/tasks`
  - Route / owner: `GET /api/zena/designer/tasks` on `DesignerDashboardController@getDesignTasks`.
  - Status: `HARD-BLOCKED`
  - Exact blocker(s): missing truth for `tasks.type`, missing truth for `tasks.due_date`, and controller relies on `assignedUser` relation naming that current `Task` model truth does not prove.
  - Minimum truth to reopen safely: schema/model proof for task typing, due-date semantics, and the exact relation alias consumed by the route.
- `designer/drawings`
  - Route / owner: `GET /api/zena/designer/drawings` on `DesignerDashboardController@getDrawingsStatus`.
  - Status: `HARD-BLOCKED`
  - Exact blocker(s): no production `drawings` migration proof, controller/model field mismatch (`title`/`drawing_number`/`revision` vs `name`/`code`/`version`), and status mismatch (`pending_review` vs model `review`).
  - Minimum truth to reopen safely: migration/model/test proof for a real drawings table with fields and statuses exactly matching the route contract.
- `designer/submittals`
  - Route / owner: `GET /api/zena/designer/submittals` on `DesignerDashboardController@getSubmittalsStatus`.
  - Status: `HARD-BLOCKED`
  - Exact blocker(s): no production migration/schema proof for `submittals`; current table truth depends on the sqlite test shim.
  - Minimum truth to reopen safely: real production migration/schema proof for `submittals` plus direct route contract proof.
- `designer/workload`
  - Route / owner: `GET /api/zena/designer/workload` on `DesignerDashboardController@getDesignWorkload`.
  - Status: `HARD-BLOCKED`
  - Exact blocker(s): workload helpers depend on unproven `tasks.type = design` and unproven `tasks.due_date`.
  - Minimum truth to reopen safely: schema/model/test proof that the task fields queried by the route are real and stable.
- `site-engineer/dashboard`
  - Route / owner: `GET /api/zena/site-engineer/dashboard` on `SiteEngineerDashboardController@getOverview`.
  - Status: `HARD-BLOCKED`
  - Exact blocker(s): unproven `progress_percentage`, unproven site-task typing, material-request schema gaps, and sample/static activity and site-condition sections outside the already-safe QC widget.
  - Minimum truth to reopen safely: production runtime/schema proof for each non-QC section.
- `site-engineer/tasks`
  - Route / owner: `GET /api/zena/site-engineer/tasks` on `SiteEngineerDashboardController@getSiteTasks`.
  - Status: `HARD-BLOCKED`
  - Exact blocker(s): missing truth for `tasks.type`, missing truth for `tasks.due_date`, and controller depends on `assignedUser` relation naming not proved by current `Task` model truth.
  - Minimum truth to reopen safely: schema/model proof for site-task typing, due-date semantics, and the exact relation alias used by the route.
- `site-engineer/material-requests`
  - Route / owner: `GET /api/zena/site-engineer/material-requests` on `SiteEngineerDashboardController@getMaterialRequests`.
  - Status: `HARD-BLOCKED`
  - Exact blocker(s): no `material_requests` migration proof, and controller projects `title`, `material_type`, `quantity`, `unit`, `priority`, `requested_date` that current model truth does not prove.
  - Minimum truth to reopen safely: production schema/model/test proof for the exact material-request shape used by the route.
- `site-engineer/rfis`
  - Route / owner: `GET /api/zena/site-engineer/rfis` on `SiteEngineerDashboardController@getSiteRfis`.
  - Status: `HARD-BLOCKED`
  - Exact blocker(s): controller depends on `assignedUser`, while current `Rfi` model truth proves `assignedTo`.
  - Minimum truth to reopen safely: model/runtime proof that `assignedUser` is a real alias or a direct route contract using the proved relation name.
- `site-engineer/safety`
  - Route / owner: `GET /api/zena/site-engineer/safety` on `SiteEngineerDashboardController@getSiteSafetyStatus`.
  - Status: `HARD-BLOCKED`
  - Exact blocker(s): no fact source and no schema/model proof for incidents, checklists, training, or safety score sections.
  - Minimum truth to reopen safely: persisted safety-domain schema/model/runtime/test proof for those sections.
- `site-engineer/daily-report`
  - Route / owner: `GET /api/zena/site-engineer/daily-report` on `SiteEngineerDashboardController@getDailySiteReport`.
  - Status: `HARD-BLOCKED`
  - Exact blocker(s): no daily-report schema proof and no fact source for weather, work completed/planned, issues, deliveries, or observations.
  - Minimum truth to reopen safely: real persisted/reporting truth plus direct route contract proof for every section returned by the route.

## Blocker categories
- Missing enum / missing column truth
  - Affects: `pm/weekly-report`, `pm/risks`, `designer/dashboard`, `designer/tasks`, `designer/workload`, `site-engineer/dashboard`, `site-engineer/tasks`.
- Sample/static payload instead of fact source
  - Affects: `pm/dashboard`, `pm/risks`, `designer/dashboard`, `site-engineer/dashboard`, `site-engineer/safety`, `site-engineer/daily-report`.
- Missing table/schema proof
  - Affects: `designer/drawings`, `designer/submittals`, `site-engineer/material-requests`, `site-engineer/safety`, `site-engineer/daily-report`.
- Ambiguous derived field semantics
  - Affects: `pm/weekly-report`, `designer/dashboard`, `site-engineer/dashboard` via `progress_percentage`.
- Runtime relation/field mismatch
  - Affects: `designer/tasks`, `designer/drawings`, `site-engineer/tasks`, `site-engineer/material-requests`, `site-engineer/rfis`.
- Missing route/test contract
  - Affects: every remaining blocked frontier above still lacks the direct route-contract proof needed to turn unproven semantics into a safe adjunct slice.
- Test-only shim instead of production truth
  - Affects: `designer/submittals`.

## Safe ceiling statement
Under current repo truth, there is no remaining adjunct runtime-safe frontier to implement immediately. The repo has reached the current safe implementation ceiling for bounded adjunct surfaces: the already closure-ready slices above can stay closed, and every other mounted adjunct frontier remains blocked until new schema/model/runtime/test truth appears.

## Re-entry conditions
- Reopen only when the exact missing truth is present in repo runtime evidence, not inferred from adjacent controllers or planning docs.
- Acceptable truth to reopen a blocked adjunct surface is limited to:
  - production migration/schema proof for every field, enum, and table the route reads;
  - model/accessor/relation truth for every relation or derived field the route serializes;
  - controller/runtime truth showing the payload is fact-backed rather than sample/static;
  - direct route/test contract proving tenant safety, project-membership or scope boundary, RBAC, and response shape for that exact mounted route.
- Do not reopen on the basis of:
  - nearby mounted routes,
  - compatibility-only `Src/*` surfaces,
  - test-only shims standing in for production schema,
  - roadmap inference, owner invention, or semantic guessing.

## Final verdict
The adjunct surface map is now at a stable stop-line. `contract-payment`, `site-engineer/inspections`, canonical `notifications`, `pm/progress`, and `designer/rfis` are the only mounted adjunct slices currently safe to treat as closed. All remaining mounted adjunct frontiers are still blocked by missing runtime/schema/model/test truth, so future runtime implementation in this area should stop here unless new concrete truth lands in the repo first.
