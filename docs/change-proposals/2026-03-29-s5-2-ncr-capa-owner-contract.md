# S5.2 NCR + CAPA Owner Contract

Date: 2026-03-29
Status: docs-only planning lock
Story: `S5.2`
Story title: `NCR + CAPA tasks lifecycle`

## Why this exists

`S5.2` backlog wording is still too broad for the locked snapshot.

Current repo evidence already proves:

- NCR domain residue exists in model/schema/policy/tests
- inspections already have a canonical owner surface on `/api/zena/inspections`
- tasks already have a canonical owner surface on `/api/zena/tasks`

Current repo evidence does not yet prove:

- an active canonical NCR route/controller family
- a dedicated top-level NCR owner surface
- a canonical persistent NCR-to-task link contract
- escalation, notification, dashboard, or broad QMS cleanup semantics

Without a planning lock, the next runtime round would have to invent NCR ownership and CAPA linkage semantics.

## Current NCR/CAPA Evidence

### NCR model/schema/policy/tests

What exists now:

- `App\Models\Ncr` with fields for `inspection_id`, `status`, `severity`, `root_cause`, `corrective_action`, `preventive_action`, `resolution`, `resolved_at`, and `closed_at`
- `database/migrations/2025_09_20_142033_create_ncrs_table.php` creating `ncrs`
- `App\Policies\NcrPolicy`
- `Database\Factories\NcrFactory`
- `tests/Feature/InspectionNcrWorkflowTest.php`

What that evidence proves:

- the repo already has a real NCR persistence model
- the schema already supports an inspection-linked NCR lifecycle
- the policy registry already knows `App\Models\Ncr`
- there is test coverage for model-level lifecycle and inspection linkage

What it does not prove:

- any canonical `/api/zena/ncr*` runtime surface
- any active NCR controller owner
- any canonical request/response contract for NCR APIs

### Inspection linkage

Strongest current linkage:

- `App\Models\QcInspection::ncrs()` owns the only explicit NCR relation already present in active app models
- runtime truth already proves canonical inspection ownership through `/api/zena/inspections`

Implication:

- inspection-owned nesting is grounded in existing evidence

### Task linkage

Strongest current task evidence:

- `/api/zena/tasks` is already an active canonical owner family
- `App\Http\Controllers\Api\TaskController` already owns create/show/update/delete/status/dependencies
- route invariants already lock the current canonical task surface

What is still missing:

- no current canonical NCR-to-task link field or table
- no active NCR route/controller creates or reads CAPA tasks
- no canonical reverse-query contract from task back to NCR

Implication:

- CAPA execution can safely reuse the existing task owner surface
- broader NCR↔task linkage semantics remain `UNKNOWN`

## Owner Surface Options

### Option A

Nested NCR owner under inspections:

- `/api/zena/inspections/{inspection}/ncrs`

Pros:

- grounded by `QcInspection::ncrs()`
- continues the already-proved canonical inspection owner path
- avoids inventing a new top-level owner before runtime evidence exists

Cons:

- does not cover standalone non-inspection NCRs
- schema allows nullable `inspection_id`, but current runtime evidence does not justify using that as the first owner contract

### Option B

Dedicated NCR owner family:

- `/api/zena/ncrs`

Pros:

- broader long-term flexibility if NCRs later need standalone ownership

Cons:

- not grounded by current runtime truth
- no active controller/route/test evidence supports it today
- would force the next runtime round to invent a new top-level owner contract

## Recommended Owner Contract

Choose Option A for the first canonical slice:

- canonical NCR owner surface is nested under `/api/zena/inspections/{inspection}/ncrs`
- first slice assumes inspection-backed NCRs only
- do not claim `/api/zena/ncrs/*` yet

Reason:

- Option A is the only surface currently supported by both explicit model linkage and existing canonical owner routes

## Minimal NCR State Vocabulary

First canonical proof should be limited to:

- `open`
- `in_progress`
- `resolved`
- `closed`

Explicitly not claimed in the first slice:

- `under_review` as a required canonical workflow step
- any approval, escalation, reviewer, or stakeholder semantics

Clarification:

- `under_review` already exists in schema/model residue, but that is not enough to make it part of the first canonical proof contract

## Minimal CAPA Contract

The first CAPA contract should be:

- no new `/api/zena/capa*` route family
- CAPA execution reuses canonical `/api/zena/tasks`
- any CAPA task created from NCR must become a normal task owned by the existing task lifecycle:
  - `POST /api/zena/tasks`
  - `GET /api/zena/tasks/{id}`
  - `PUT /api/zena/tasks/{id}`
  - `PATCH /api/zena/tasks/{id}/status`

What this planning lock intentionally does not claim:

- a canonical persistent NCR↔task reverse-link model
- broad CAPA reporting semantics
- dashboard rollups
- notification or escalation behavior

## Explicitly Deferred / UNKNOWN

Deferred:

- escalation rules
- notifications
- dashboards
- broad QMS cleanup
- standalone `/api/zena/ncrs/*` ownership

`UNKNOWN` until runtime evidence exists:

- canonical persistent NCR↔task reverse-link storage
- whether standalone non-inspection NCRs should become canonical later
- whether `under_review` deserves first-slice workflow proof

## Verify Target For The Next Runtime Round

- `php artisan route:list | grep -E "inspection|ncr|task" || true`
- `rg -n "Ncr|NCR|capa|corrective|inspection" app tests database docs src`
- targeted canonical tests proving the chosen nested NCR owner path only
- targeted canonical tests proving CAPA handoff stays on `/api/zena/tasks`
- no proof depending on `/api/v1/*`

## Verdict

The first safe `S5.2` runtime slice is now narrow enough to execute without inventing a new top-level NCR owner family, overclaiming CAPA linkage, or pulling escalation/notification/dashboard scope into the round.
