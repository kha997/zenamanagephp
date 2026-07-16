# S6.2 Alert Taxonomy First Owner Lock

Date: 2026-04-04
Status: docs-only planning adjustment
Story: `S6.2`
Story title: `Alert taxonomy + rules`

## Why this round exists

`S6.1` is now runtime-proved on the canonical designer dashboard owner path.

The exact next roadmap story in SSOT is therefore `S6.2`.

As currently written, `S6.2` is still too broad to execute honestly. "Alert taxonomy + rules" can drift into at least four different surfaces:

- a task-owned trigger action
- a notification inbox/read-model
- a dashboard alert projection
- a notification-rule engine

Current canonical evidence does not prove those as one converged owner family.

## Current evidence

### Backlog truth

- `docs/roadmap/backlog.yaml` now marks `S6.1` as `done`
- `docs/roadmap/backlog.yaml` marks `S6.2` as the next unresolved story in `EPIC-6`
- current story wording is still broad enough to blur rule ownership, alert taxonomy ownership, and delivery/read-model ownership

### Canonical trigger truth

From `routes/api_zena.php`, `app/Http/Controllers/Api/TaskController.php`, and `tests/Feature/Api/TaskOverdueEscalationApiTest.php`:

- the repo already proves one canonical `/api/zena/*` alert-producing trigger:
  - `POST /api/zena/tasks/{id}/escalate-overdue`
- the proved trigger is task-owned, not dashboard-owned and not notification-rule-owned
- the proved trigger emits exactly one direct in-app notification
- the proved taxonomy tuple is already concrete in runtime:
  - `event_key = zena.task.overdue_escalated`
  - `notification.type = task_overdue_escalated`

### Canonical notification truth

From `routes/api_zena.php`, `app/Http/Controllers/Api/NotificationController.php`, and `docs/architecture/module-ownership-ssot.md`:

- canonical `/api/zena/notifications` exists today
- that surface owns inbox/read-model behavior such as:
  - list
  - show
  - mark-read
  - mark-all-read
  - unread count / summary stats
- this surface does not yet prove rule-definition ownership for `S6.2`

### Compatibility-only rule truth

From `routes/api.php`, `tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`, `tests/Feature/Api/NotificationApiTest.php`, and `docs/architecture/module-ownership-ssot.md`:

- `/api/v1/notification-rules` is still mounted
- its owner remains the `Src\Notification` family
- `docs/architecture/module-ownership-ssot.md` explicitly says there is no canonical `/api/zena/notification-rules` family today
- therefore `/api/v1/notification-rules*` cannot serve as forward canonical owner proof for `S6.2`

### Dashboard-alert truth

From `routes/api_zena.php`, `routes/api.php`, `php artisan route:list`, and `tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`:

- `routes/api_zena.php` still advertises `/api/zena/dashboard/alerts` in the info payload
- runtime proof still shows no mounted canonical `/api/zena/dashboard/alerts` route
- generic `/api/dashboard/alerts` and `/api/v1/dashboard/alerts` residue do exist elsewhere
- those dashboard surfaces are not valid canonical owner proof for `S6.2`

## What blocks runtime now

`S6.2` is not runtime-ready because the story still lacks one exact canonical owner contract.

Current blockers:

- no canonical `/api/zena/notification-rules` family exists
- no mounted canonical `/api/zena/dashboard/alerts` route exists
- canonical `/api/zena/notifications` is read-model ownership, not proved rule ownership
- the only already-proved canonical trigger is task-owned under `/api/zena/tasks`
- broad dashboard and `/api/v1/*` rule surfaces would blur ownership instead of proving it

## Decision

Choose `Option B`: docs-only planning adjustment.

Reason:

- the exact next story is clear, but the first runtime owner is not yet locked in backlog wording
- going into runtime now would force one of two bad moves:
  - invent a new canonical `/api/zena/notification-rules` owner family without planning lock
  - misuse dashboard residue or `/api/v1/*` compatibility surfaces as proof
- there is already enough evidence to lock the first future runtime target narrowly without touching runtime code

## Locked first future runtime target

Choose the already-proved task-owned trigger as the first future `S6.2` owner anchor:

- owner anchor: `POST /api/zena/tasks/{id}/escalate-overdue`

Why this is the best first owner:

- it is already canonical on `/api/zena/*`
- it already emits one proved alert-like event/notification pair
- it already has tenant-safe and RBAC-backed runtime proof
- it is narrower and more honest than starting from dashboard or rule-engine residue

## Locked minimal taxonomy/rule boundary

The first future `S6.2` runtime slice must stay limited to one taxonomy tuple only:

- `event_key = zena.task.overdue_escalated`
- `notification.type = task_overdue_escalated`
- delivery = direct in-app only

Implication:

- the first future runtime round should formalize taxonomy/rule handling only around the overdue CAPA escalation trigger that already exists
- canonical `/api/zena/notifications` may be used only as the read-model surface for the emitted record
- do not claim broader event matrices, user-configurable rule CRUD, or multi-channel delivery from this first slice

## Explicit non-claims

This planning adjustment does not claim:

- canonical `/api/zena/notification-rules` ownership exists today
- canonical `/api/zena/dashboard/alerts` ownership exists today
- alert bundles or role-based dashboard convergence
- QC or Finance alert owners
- stakeholder fan-out or fallback-recipient matrices
- email/push/webhook delivery
- outbox/event-record semantics under `S6.3`

## Deferred / UNKNOWN

Deferred:

- runtime implementation for `S6.2`
- any dashboard alert projection
- any user-configurable rule CRUD surface
- any broader taxonomy beyond overdue CAPA escalation

UNKNOWN:

- whether the eventual first `S6.2` runtime proof should stay entirely on the task owner path or add a narrowly-scoped canonical read helper under `/api/zena/notifications`
- whether later rounds should split rule definition from alert read-model ownership

## Verdict

`S6.2` is the exact next roadmap story after proved `S6.1`, but it still needs one more narrowing round before runtime. This round now locks the first future owner anchor to the already-proved task-owned overdue CAPA escalation action, keeps canonical notifications as read-model only, and excludes generic dashboard or `/api/v1/*` rule surfaces from forward proof.
