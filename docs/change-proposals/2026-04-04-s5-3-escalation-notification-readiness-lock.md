# S5.3 Escalation + Notification Readiness Lock

Date: 2026-04-04
Status: docs-only planning adjustment
Story: `S5.3`
Story title: `Escalation rules + notifications`

## Post-Lock Note

Later on 2026-04-04, the narrow runtime slice defined here was implemented and proved.

What is now proved on the canonical task owner path:

- `POST /api/zena/tasks/{id}/escalate-overdue` is the first task-owned escalation action
- eligibility is limited to CAPA handoff context marked by `inspection-ncr-capa`
- overdue is limited to `tasks.end_date < now()` on a non-terminal task
- the only recipient is `tasks.assigned_to`
- the action writes exactly one direct in-app notification and one audit log on success

What remains unproved:

- fallback recipients for unassigned tasks
- reminder cadence or batch overdue sweeps
- reverse-link projections from task back to NCR
- dashboard widgets or stakeholder fan-out

## Why this exists

`S5.2` is now runtime-proved, so `S5.3` is the next unresolved story in the active `EPIC-5` sequence.

That does not make `S5.3` runtime-ready.

Current backlog wording is still too broad:

- "Overdue CAPA"
- "alerts/notifications"
- "with audit"

Current repo evidence now locks the canonical owner path, the overdue source-of-truth, and the smallest recipient rule for the first `S5.3` runtime slice.

## Current Evidence

Canonical proof currently exists for:

- inspection-owned NCR routes on `/api/zena/inspections/{inspection}/ncrs`
- NCR create plus status transitions `open -> in_progress -> resolved -> closed`
- NCR audit writes on create and status update
- a minimal CAPA handoff payload that points to canonical `POST /api/zena/tasks`

Canonical proof still does not exist for:

- persisted NCR-to-task reverse-link storage
- any dedicated canonical escalation route on `/api/zena/*`
- any canonical overdue rule for CAPA tasks created from NCR handoff
- any canonical recipient rule for CAPA escalation notifications
- any canonical notification write on the inspection/NCR owner path for this story
- any canonical QC dashboard projection for escalations

## Decision Lock

This round now locks the smallest evidence-backed `S5.3` direction.

### Canonical owner anchor

Choose the canonical task owner path:

- `/api/zena/tasks`

Reason:

- `S5.2` proved inspection-owned NCR lifecycle only on `/api/zena/inspections/{inspection}/ncrs`
- the same `S5.2` proof explicitly hands CAPA execution off to canonical `/api/zena/tasks`
- `InspectionController::transformNcr()` already advertises `/api/zena/tasks` as `task_handoff.owner_route`
- there is still no canonical persistent reverse-link from task back to NCR, so an NCR-owned escalation proof would have to invent back-reference semantics before it could prove overdue CAPA behavior

Implication:

- NCR remains the context source for how the CAPA task originates
- the first escalation proof must be task-owned, not NCR-owned and not notification-owned

### First overdue source-of-truth

Choose canonical task deadline on `tasks.end_date`.

Reason:

- the canonical task owner surface already validates and persists `end_date` on `/api/zena/tasks`
- canonical task tests already prove create/update with `end_date`
- no canonical `tasks.due_date` field is proved on the active `/api/zena/tasks` owner path
- `Ncr::getIsOverdueAttribute()` is an NCR-age helper for open NCRs and does not prove overdue CAPA semantics

Implication:

- "task due date" for the first `S5.3` slice means canonical `tasks.end_date`
- do not use NCR age as the overdue basis for the first CAPA escalation proof

### First recipient rule

Choose assignee-only:

- `tasks.assigned_to`

Reason:

- the canonical task owner path already exposes assignee identity on the task aggregate
- `assigned_to` is the smallest direct recipient field already grounded in runtime task ownership
- `created_by`, inspection owner, NCR creator, project manager, and broader stakeholders would all add recipient semantics that current canonical evidence does not prove as escalation truth

Implication:

- if no task assignee exists, the first escalation slice should not fan out to a fallback actor
- unassigned overdue CAPA remains deferred rather than inventing a second recipient rule

## Locked Direction

Choose `Option A`: after this round, `S5.3` is ready for one narrow runtime slice.

What is now locked in SSOT:

- canonical owner anchor: `/api/zena/tasks`
- overdue source-of-truth: `tasks.end_date`
- minimal recipient rule: `tasks.assigned_to`
- minimal audit expectation: one audit event on the same task-owned escalation action

## Smallest Future Runtime Target

Choose one narrow task-owned notification trigger contract, not a read-only projection.

Why not read-only first:

- backlog wording for `S5.3` already requires one overdue CAPA alert/notification behavior plus audit
- a projection alone would still leave the actual escalation/notification contract undefined

Why not combine projection + trigger in the same first slice:

- that would widen the round without adding owner clarity
- the repo already has a canonical in-app notification model/controller surface, so the smallest honest proof is one direct write path

The future runtime slice should stay narrow:

- one overdue detection rule only
- one alert/notification write behavior only
- one canonical owner path only
- no dashboard scope in the same round
- no broad stakeholder fan-out

### Locked first runtime target

The smallest future runtime slice is:

- task-owned only, under `/api/zena/tasks`
- limited to CAPA tasks originating from inspection-owned NCR handoff context
- overdue defined only as `tasks.end_date < now()` on a not-yet-finished task
- recipient defined only as `tasks.assigned_to`
- notification mechanism limited to one direct in-app notification write
- audit expectation limited to the same canonical task-owned escalation action

Recommended narrow implementation direction:

- use task ownership, not `/api/zena/inspections/*`, as the runtime anchor
- gate the first slice to the NCR-handoff task shape already proved by `S5.2`; `inspection-ncr-capa` is the narrowest current evidence-backed marker because the handoff payload already emits that tag
- create exactly one in-app notification for the assigned user
- record exactly one audit event on the same canonical task-owned escalation action
- defer dashboards, repeated reminders, manager escalation, stakeholder fan-out, and reverse-link projections

## Non-Claims

This planning adjustment does not claim:

- standalone `/api/zena/ncrs/*` ownership
- standalone `/api/zena/capa*` ownership
- broad notification-rule engine convergence
- dashboard widgets
- broad QMS cleanup

## Deferred / UNKNOWN

Deferred:

- dashboard widgets under `S5.4`
- broader escalation matrices
- reminder cadence / repeated notifications

UNKNOWN:

- exact route tail or action verb for the first task-owned escalation endpoint
- whether later rounds should add fallback recipients for unassigned overdue CAPA
- whether later rounds should add reverse-link projections from task back to NCR

## Verdict

`S5.3` is now runtime-proved for its first narrow slice: task-owned overdue CAPA escalation stays on `/api/zena/tasks`, uses canonical `tasks.end_date` as the overdue basis, sends exactly one direct in-app notification to `tasks.assigned_to`, and records one audit event on the same canonical action.
