# S6.1 Role Widget First Runtime Target Lock

Date: 2026-04-04
Story: `S6.1`
Story title: `Role-based widget sets for AEC`
Status: docs-only planning adjustment

## Why this round exists

`S6.1` is the exact next unresolved story in backlog order after proved `S5.4`.

This round narrows `S6.1` one step further so the story can either become runtime-ready for one exact slice or be honestly split.

The backlog wording was still too broad for one honest runtime round because it bundled multiple role families into one acceptance surface:

- Design Lead
- Site
- QC
- PM
- Finance

## Current evidence

### Backlog truth

- `docs/roadmap/backlog.yaml` marks `S5.4` as `done`
- `docs/roadmap/backlog.yaml` marks `S6.1` as the next unresolved story in `EPIC-6`
- current acceptance is still only:
  - `Widgets exist for Design Lead / Site / QC / PM / Finance.`
  - `Each widget links to traceable data sources.`

### Canonical route truth

From `routes/api_zena.php` and `php artisan route:list`:

- mounted canonical role dashboard routes currently exist only at:
  - `GET /api/zena/pm/dashboard`
  - `GET /api/zena/designer/dashboard`
  - `GET /api/zena/site-engineer/dashboard`
- mounted canonical QC or finance dashboard routes do not currently exist on `/api/zena/*`
- generic `/api/zena/dashboard/widgets|metrics|alerts` routes are still advertised residue only, not mounted proof
- broad `/api/v1/dashboard*`, `/api/v1/notifications*`, `/api/v1/notification-rules*`, `/api/v1/contracts/*/payments`, and `/api/v1/compensation*` surfaces exist, but they are compatibility-only and cannot prove forward ownership for `S6.1`

### Current proved role-widget truth

- `S5.4` already proves one read-only site widget slice on `GET /api/zena/site-engineer/dashboard`
- that proof is role-scoped and QC-specific
- it does not prove cross-role dashboard convergence
- it does not prove generic widget-set ownership for PM, Designer, QC, or Finance

### Role-controller boundary truth

Current mounted candidate controllers are not equally narrow:

- `app/Http/Controllers/Api/PmDashboardController.php` mixes real project/task/RFI queries with multiple explicit sample sections (`milestone_progress`, `budget_progress`, `recent_activities`, `upcoming_milestones`, `budget_risks`, `resource_conflicts`, `quality_issues`)
- `app/Http/Controllers/Api/DesignerDashboardController.php` still contains sample `recent_activities`, but its overview-adjacent live query surface is narrower and can be reduced to designer-assigned task, RFI, and submittal facts without pulling finance/payment semantics into scope
- `app/Http/Controllers/Api/SiteEngineerDashboardController.php` already carries the proved `S5.4` site-role widget slice, so reusing Site as the `S6.1` first slice would blur the boundary between the two stories instead of opening the next role family

## What blocks runtime today

`S6.1` still does not have one canonical owner anchor for the full multi-role story.

Reasons:

- PM, Designer, and Site currently have mounted role dashboard anchors on `/api/zena/*`
- QC and Finance do not
- the story acceptance still reads like one multi-role rollout instead of one narrow owner-backed slice
- broad role/widget engines currently live mostly outside the locked canonical owner family for this lane
- PM is not the smallest honest first slice because the current controller contract still leans on multiple sample sections and broader progress/risk/budget residue
- Site is not the right first slice because `S5.4` already consumed the first proved site-role widget surface

## Owner-anchor decision

There is still no single canonical owner anchor for the full `S6.1` story.

Currently available mounted role anchors are:

- `GET /api/zena/pm/dashboard`
- `GET /api/zena/designer/dashboard`
- `GET /api/zena/site-engineer/dashboard`

Currently missing canonical owner anchors:

- `QC` dashboard on `/api/zena/*`
- `Finance` dashboard on `/api/zena/*`

## Decision

Choose `single-role-first` and keep `S6.1` as one story.

Reason:

- another split is not necessary yet because one exact first runtime slice can now be locked honestly inside the existing `S6.1` story
- splitting into per-role stories right now would create planning overhead before the first runtime slice is even executed
- the only thing that must be fixed immediately is the exact first owner anchor and its allowed source boundaries

## First role decision

Choose `designer`.

Why `designer` is the best mounted canonical owner now:

- `GET /api/zena/designer/dashboard` is already mounted on the canonical `/api/zena/*` surface
- it has dedicated RBAC via `designer.dashboard`
- unlike `site-engineer`, it has not already been used to prove a role-scoped widget slice in `S5.4`
- unlike `pm`, it can be narrowed to a smaller read-only source boundary without leaning on budget, milestone, quality, or finance-adjacent sample sections

Why not `site-engineer`:

- `S5.4` already proved a site-role widget slice on `GET /api/zena/site-engineer/dashboard`
- using Site again as the first `S6.1` runtime slice would mostly relabel an already-proved role-widget pattern instead of advancing the next unresolved role family

Why not `pm`:

- the current PM controller mixes live queries with explicit sample-only sections in more places than the Designer controller
- a PM-first slice would need one more narrowing pass to avoid silently inheriting milestone/budget/risk residue into `S6.1`

## Locked minimal future runtime target

The first future runtime round under `S6.1` is now locked to:

- role: `designer`
- owner anchor: `GET /api/zena/designer/dashboard`
- permission boundary: `designer.dashboard`
- response nature: read-only only
- projection nature: one designer-only widget-set projection embedded on the existing dashboard response

Exact minimal source boundaries for that future slice:

- designer-assigned task facts only
- designer-assigned RFI facts only
- designer-facing submittal status facts only

Forward-safe source surfaces for traceability:

- `GET /api/zena/designer/tasks`
- `GET /api/zena/designer/rfis`
- `GET /api/zena/designer/submittals`

Explicitly not part of the first slice:

- drawing-centric widgets
- PM progress/risk/budget style projections
- site/QC reuse of the proved `S5.4` slice
- generic `/api/zena/dashboard/*`
- any `/api/v1/dashboard*`
- `/api/v1/notifications*`
- `/api/v1/notification-rules*`
- `/api/v1/contracts/*/payments`
- `/api/v1/compensation*`

Read-only shape locked for the future runtime round:

- one top-level designer widget-set projection on the existing dashboard payload
- grouped/summary widget content only
- no widget CRUD
- no layout persistence
- no alert inbox or alert taxonomy semantics
- no cross-role convergence
- no writes to task, RFI, submittal, notification, or finance state

## Ready or blocked after this round

Before runtime, `S6.1` is still blocked as a full multi-role story.

After runtime, the first exact slice is now proved:

- designer only
- existing canonical owner anchor only
- read-only widget-set projection only
- task/RFI/submittal source boundary only

## Runtime outcome

The locked first runtime slice is now proved on the same date.

Exact proved owner surface:

- `GET /api/zena/designer/dashboard`

Exact proved read-only projection:

- top-level `data.designer_widget`
- `data.designer_widget.widget_key = designer_summary`
- `data.designer_widget.tasks.{total,pending,in_progress,completed}`
- `data.designer_widget.rfis.{total,pending,in_progress,answered,closed}`
- `data.designer_widget.submittals.{total,draft,submitted,pending_review,approved,rejected}`

Exact proved data-source basis:

- task counts are derived only from designer-assigned tasks on assigned designer projects
- RFI counts are derived only from assigned RFIs on assigned designer projects
- submittal counts are derived only from submittals on assigned designer projects

Exact runtime behaviors proved:

- zero-safe behavior for an empty scoped project
- project-scoped rollup on the existing owner path
- no generic `/api/zena/dashboard/widgets|metrics|alerts` route family was mounted
- no widget CRUD or layout persistence was added
- no drawing-based widget projection was added
- no alert taxonomy or cross-role convergence was added
- no `/api/v1/*` dashboard surface was used as proof

Verification used for the proved slice:

- `php artisan optimize:clear`
- `composer ssot:lint`
- `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Api/DesignerDashboardApiTest.php tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`

## Explicitly deferred

- PM-first widget-set runtime
- cross-role dashboard convergence
- QC dashboard ownership until a canonical `/api/zena/*` owner anchor exists
- Finance dashboard ownership until a canonical `/api/zena/*` owner anchor exists
- alert taxonomy and rule semantics under `S6.2`
- payment, invoice, and compensation semantics
- any claim that all AEC roles already share one converged widget-set contract
