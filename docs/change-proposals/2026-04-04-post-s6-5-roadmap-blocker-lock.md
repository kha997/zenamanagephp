# Post-S6.5 Roadmap Blocker Lock

Date: 2026-04-04
Status: docs-only blocker lock

## Why this round exists

`S6.5` is now runtime-proved.

The next roadmap story after `S6.5` was re-checked directly from SSOT and runtime truth. This round exists to lock one factual blocker state into SSOT so later threads do not keep re-running the same discovery loop.

This round does not define `S6.6`.

This round does not open `EPIC-7`.

This round does not patch runtime.

## Current SSOT truth

From `docs/roadmap/backlog.yaml` and `docs/progress.md`:

- `EPIC-6` currently ends at `S6.5`
- no `S6.6` exists
- no `EPIC-7` exists
- no exact post-`S6.5` story is currently locked in SSOT

This is a real repo state, not a reading gap.

## Canonical inventory truth already reviewed

From `routes/api_zena.php`, `php artisan route:list`, `docs/architecture/module-ownership-ssot.md`, and the latest post-`S6.5` inventory round:

- multiple canonical `/api/zena/*` surfaces are mounted beyond the already-proved `S6.1` to `S6.5` slices
- mounted adjacent dashboard-family surfaces include:
  - `GET /api/zena/pm/progress`
  - `GET /api/zena/pm/risks`
  - `GET /api/zena/pm/weekly-report`
  - `GET /api/zena/designer/drawings`
  - `GET /api/zena/designer/workload`
  - `GET /api/zena/site-engineer/material-requests`
  - `GET /api/zena/site-engineer/safety`
- broader canonical business families also remain mounted, including `projects`, `documents`, `submittals`, `inspections`, `work-templates`, `work-instances`, `deliverable-templates`, `materials`, and `contracts`

## Candidate rejection lock

No mounted canonical candidate currently clears the bar for "next smallest honest slice" after `S6.5`.

Rejected candidate groups:

### Generic or absent dashboard / alert / event / rule candidates

- reject generic dashboard residue because no canonical `/api/zena/dashboard/widgets`, `/api/zena/dashboard/metrics`, or `/api/zena/dashboard/alerts` route is mounted
- reject notification-rule convergence because no canonical `/api/zena/notification-rules*` family exists and module ownership still marks `/api/v1/notification-rules` as the active compatibility owner
- reject public event-record read/replay candidates because no canonical `/api/zena/event-records*` family exists

### PM / designer / site adjunct dashboard candidates

- reject `GET /api/zena/pm/progress`, `GET /api/zena/pm/risks`, and `GET /api/zena/pm/weekly-report` as next-story anchors because they are adjacent PM overview surfaces without backlog or deferred-readiness evidence naming them as the next slice
- reject `GET /api/zena/designer/drawings` and `GET /api/zena/designer/workload` because the repo has no post-`S6.5` readiness lock identifying them as the next roadmap owner
- reject `GET /api/zena/site-engineer/material-requests` and `GET /api/zena/site-engineer/safety` for the same reason; mounted runtime alone is not enough to create a new story key

### QC / Finance candidates

- reject QC dashboard continuation because no canonical `/api/zena/*` QC dashboard owner anchor exists
- reject Finance dashboard continuation because no canonical `/api/zena/*` Finance dashboard owner anchor exists

### Broad canonical business families

- reject large canonical families such as `projects`, `documents`, `submittals`, `inspections`, `work-templates`, `work-instances`, `materials`, and `contracts` as post-`S6.5` next-story candidates because they are either already story-backed in earlier lanes or too broad to become a new story without a fresh narrowing round

## Why `S6.6` cannot be locked yet

`S6.6` cannot be created honestly yet because no candidate simultaneously satisfies all of the following:

- canonical `/api/zena/*` owner anchor is mounted and real
- the candidate is not already consumed or deferred as residue by prior proved slices
- the acceptance boundary is narrow enough to avoid platform creep
- current backlog/progress/change-proposal evidence actually supports it as the next story

Mounted runtime alone is not sufficient to mint a new story key.

## Why `EPIC-7` cannot be opened yet

`EPIC-7` cannot be opened honestly yet because:

- no SSOT document currently defines a new epic
- no post-`S6.5` candidate is more evidence-backed than the still-deferred but owner-blocked concerns already known inside the current roadmap
- opening a new epic now would be roadmap invention rather than evidence-backed planning

## Evidence required to unblock roadmap later

One of the following must exist before the roadmap can unlock honestly:

- a new change proposal that names one exact post-`S6.5` owner anchor and explicitly narrows its first proof surface
- a new canonical `/api/zena/*` owner family for one of the currently blocked areas such as dashboard alerts, notification rules, event records, QC dashboard, or Finance dashboard
- new backlog/progress evidence that explicitly promotes one mounted PM/designer/site-engineer adjunct route from residue into the next roadmap owner

Until then, next-story status remains `UNKNOWN`.

## Explicit non-goals

This blocker lock does not:

- create `S6.6`
- open `EPIC-7`
- treat generic dashboard residue as a story
- treat `/api/v1/*` compatibility routes as forward proof
- promote PM/designer/site-engineer adjunct routes into a story without readiness evidence
- authorize runtime work after `S6.5`

## Verdict

Post-`S6.5` roadmap state is currently blocked in a factual way: SSOT really stops at `S6.5`, canonical route inventory has already been checked, and no candidate yet has enough owner, scope, and planning evidence to become the next honest story.
