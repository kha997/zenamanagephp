# S3.3 Apply Boundary Delta Contract

Date: 2026-03-29
Status: proposal-only
Story: `S3.3`
Story title: `Approved CR creates delta tasks + baseline delta`

## Why this exists

`S3.3` backlog wording is currently broader than the canonical runtime proof.

Current canonical runtime on `/api/zena/change-requests` proves:

- `approve()` only allows `submitted -> approved`
- `approve()` mutates project budget/schedule directly
- `apply()` only allows `approved -> implemented`
- `apply()` calls `createBaselineSnapshot()`, but that hook is log-only today

Current runtime does not prove:

- canonical task delta persistence
- canonical baseline delta persistence
- canonical change-request link-back for either artifact

This proposal narrows the execution contract so the next runtime round can implement a minimal, evidence-backed slice without inventing extra semantics.

## Canonical boundary

Recommended implementation boundary: `apply()`, not `approve()`.

Why:

- `approve()` is already shipping as the approval decision boundary on the canonical controller.
- `apply()` is already the canonical downstream implementation hook and already owns the `approved -> implemented` transition.
- Moving delta generation into `approve()` would conflict with current runtime truth and would blur decision-time effects with implementation-time effects.

Contract:

- `approve()` remains approval-only for `S3.3`.
- `apply()` owns creation of downstream task delta evidence and baseline delta evidence.
- No `/api/v1/*` route or compatibility behavior is used as proof for this story.

## Minimal delta task contract

Minimal contract for `delta task` in this story:

- It is a canonical `tasks` table write caused by `POST /api/zena/change-requests/{id}/apply`.
- The affected task row must belong to the same `tenant_id` and `project_id` as the applied change request.
- The delta can be either:
  - create at least one new task, or
  - mutate at least one existing canonical task
- The runtime round must choose one of those two shapes and prove it with tests. This proposal does not force both.

Minimal canonical fields that must be evidenced on the task side:

- `tasks.project_id`
- task identity in canonical `tasks`
- at least one material task-level delta owned by the apply flow

What counts as a material task-level delta is intentionally narrow:

- create: new canonical task row exists after apply
- update: at least one persisted task attribute changes because of apply

What is still `UNKNOWN` in this round:

- whether task delta should always create new tasks, always mutate existing tasks, or support both
- any required task naming convention
- any dependency, assignment, due-date, watcher, or checklist semantics
- whether multiple tasks must be generated from one change request

## Minimal baseline delta contract

Minimal contract for `baseline delta` in this story:

- It is a canonical artifact written during `POST /api/zena/change-requests/{id}/apply`.
- The minimum proof anchor is a canonical `baselines` row.
- That baseline row must be linked back to the applied change request via existing canonical linkage already exposed by the model: `baselines.linked_contract_id`.
- `baseline_history` may be written as supporting evidence, but it is not sufficient by itself for the minimal `S3.3` proof contract.

Minimal acceptable shape for the runtime round:

1. baseline-row delta
   - create a new `baselines` row version or snapshot for the same project
   - link it back to the change request through `linked_contract_id`
   - optionally add `baseline_history` if the chosen write path already versions/re-baselines

This proposal intentionally anchors the minimum proof to `baselines` because current evidence does not show a canonical direct change-request link on `baseline_history`.

What is still `UNKNOWN` in this round:

- whether `baseline_history` should later become a co-equal proof artifact rather than supporting evidence only
- whether the delta should target `contract`, `execution`, or another baseline type
- any broad re-baseline lifecycle or baseline comparison cleanup
- any derived KPI semantics beyond existing cost/schedule fields already carried by the change request

## Canonical sources and link-back

Canonical source inputs for `S3.3` should stay as narrow as possible:

- `change_requests` is the canonical source for the apply trigger, workflow state, approved cost/schedule values, and actor/timestamp context.
- `tasks` is the canonical task surface.
- `baselines` and `baseline_history` are the canonical baseline surfaces.

Recommended minimal link-back rule:

- task link-back should use existing `cr_links` rows with `linked_type = task`
- baseline link-back should use existing `baselines.linked_contract_id` when a baseline row is created or versioned

Why this is the least-assumption path:

- `ChangeRequest` already exposes `links()` and `linkedTasks()` through `cr_links`.
- `Task` does not currently expose a direct `change_request_id` field.
- `Baseline` already exposes `linked_contract_id`.

Still `UNKNOWN`:

- whether `baseline_history` also needs a direct change-request foreign key in a later round
- whether one change request may own multiple baseline delta artifacts
- whether `change_requests.task_id` should be used for anything beyond legacy/single-task context

## Explicitly deferred

- any `/api/v1/*` behavior or proof
- any broad app-vs-src ownership cleanup
- any stakeholder/notification expansion
- any broad baseline architecture redesign
- any semantic not backed by canonical controller/model/table evidence

## Runtime-round verify target

Minimum verify expectations for the later runtime round:

- `php artisan route:list | grep change-requests`
- targeted canonical change-request apply tests on `/api/zena/change-requests/{id}/apply`
- database assertions proving:
  - change request reaches `implemented`
  - at least one canonical task delta exists
  - task delta has change-request link-back
  - at least one canonical `baselines` delta row exists
  - that baseline row has change-request link-back through `linked_contract_id`

## Verdict

This story is ready for a narrow runtime round only if implementation stays anchored at `apply()` and refuses to invent broader delta semantics than the minimal contracts above.
