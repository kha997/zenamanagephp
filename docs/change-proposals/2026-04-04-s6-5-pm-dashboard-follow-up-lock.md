# S6.5 PM Dashboard Summary Follow-up Lock

Date: 2026-04-04
Status: docs-only roadmap extension
Story: `S6.5`
Story title: `PM dashboard summary widget follow-up`

## Why this round exists

`S6.4` is now proved on the canonical project-template walkthrough cluster.

Current SSOT stops there: no `S6.5` and no `EPIC-7` were defined yet.

This round exists only to lock the next smallest honest story into SSOT without inventing a runtime slice.

## Current evidence

### Current SSOT stop point

From `docs/roadmap/backlog.yaml` and `docs/progress.md`:

- `EPIC-6` currently ends at `S6.4`
- no `S6.5` exists yet
- no `EPIC-7` exists yet

### Deferreds after the proved `S6.x` slices

From `docs/progress.md` and the `S6.1` to `S6.4` change proposals:

- `S6.1` explicitly defers:
  - `PM-first widget-set runtime`
  - `QC dashboard ownership until a canonical /api/zena/* anchor exists`
  - `Finance dashboard ownership until a canonical /api/zena/* anchor exists`
  - `cross-role dashboard convergence`
- `S6.2` still defers dashboard alerts and canonical rule CRUD
- `S6.3` still defers any public event-record read/replay surface
- `S6.4` still defers any dashboard/notification/event-record documentation coverage beyond the proved template walkthrough cluster

### Owner-anchor maturity comparison

From `routes/api_zena.php`, `docs/architecture/module-ownership-ssot.md`, and `tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`:

- `GET /api/zena/pm/dashboard` is already mounted on the canonical `/api/zena/*` business surface
- `GET /api/zena/designer/dashboard` is already consumed by the proved `S6.1` first slice
- `GET /api/zena/site-engineer/dashboard` is already consumed by the proved `S5.4` QC widget slice
- no canonical `/api/zena/dashboard/alerts` route is mounted
- no canonical `/api/zena/notification-rules` route family is mounted
- no canonical `/api/zena/event-records` route family is mounted
- QC and Finance still do not have their own canonical `/api/zena/*` dashboard anchors

Implication:

- a PM dashboard follow-up is the smallest remaining deferred with an already-mounted canonical owner anchor
- dashboard alerts, notification-rule convergence, event-record read APIs, and QC/Finance owners are broader or still owner-blocked

### PM controller boundary

From `app/Http/Controllers/Api/PmDashboardController.php` and the prior `S6.1` readiness lock:

- the PM overview owner already exists at `GET /api/zena/pm/dashboard`
- current PM controller still mixes live summary facts with broader milestone, budget, risk, resource, and quality-adjacent sections

Implication:

- the next story must stay narrower than generic PM dashboard completion
- the smallest honest target is one read-only top-level summary widget on the existing owner path only

## Decision

Continue `EPIC-6`.

Why not open `EPIC-7` yet:

- the smallest evidence-backed unresolved slice still lives inside `EPIC-6`'s role-widget/dashboard lane
- opening `EPIC-7` now would skip a narrower already-deferred story with a cleaner mounted owner anchor
- the strongest alternative candidates are still blocked by absent canonical owners or would force platform/documentation sweep semantics

## Locked next story

- story key: `S6.5`
- title: `PM dashboard summary widget follow-up`
- dependency: `S6.1`

## Proposed owner anchor / proof surface

Use exactly one owner anchor:

- `GET /api/zena/pm/dashboard`

First proof surface stays limited to one read-only top-level PM summary projection on that existing route.

Allowed summary basis for the first future slice:

- PM-scoped project counts
- PM-scoped task counts
- PM-scoped pending-RFI counts
- PM-scoped overdue-task counts

The first future slice must not rely on:

- generic `/api/zena/dashboard/*`
- `/api/v1/dashboard*`
- `/api/v1/notification-rules*`
- `/api/zena/event-records*`
- payment, invoice, compensation, or finance-specific owner claims

## Minimal acceptance for the future runtime slice

- stays on exactly one existing canonical owner path: `GET /api/zena/pm/dashboard`
- proves one read-only top-level `pm_widget` summary projection only
- keeps source boundaries limited to PM-scoped project/task/RFI summary facts already derivable from canonical owners
- does not promote milestone, budget, risk, resource-conflict, quality-issue, alert, notification, or event-record sections into the first proof
- does not claim cross-role convergence, widget CRUD, layout persistence, dashboard-alert ownership, or finance dashboard ownership

## Explicit non-goals

This roadmap lock does not claim:

- a complete PM dashboard contract
- QC dashboard ownership
- Finance dashboard ownership
- generic `/api/zena/dashboard/alerts` ownership
- canonical `/api/zena/notification-rules` ownership
- canonical `/api/zena/event-records` read ownership
- repo-wide documentation continuation after `S6.4`
- cross-role dashboard convergence

## Deferred / UNKNOWN

Deferred:

- any PM slice beyond one top-level read-only summary widget
- dashboard alerts and alert bundles
- notification-rule convergence into `/api/zena/*`
- public event-record read or replay APIs
- QC and Finance role-dashboard ownership

UNKNOWN:

- the exact final minimal `pm_widget` field names for the future runtime slice
- whether a later roadmap round should open `EPIC-7` immediately after a narrow PM follow-up or after another in-epic dashboard/alert convergence decision

## Verdict

The next smallest honest story after proved `S6.4` is still inside `EPIC-6`, not a new epic. `S6.5` should be locked as a narrow PM dashboard summary follow-up anchored on `GET /api/zena/pm/dashboard`, while alert/rule/event-record platform work and repo-wide documentation continuation remain deferred.
