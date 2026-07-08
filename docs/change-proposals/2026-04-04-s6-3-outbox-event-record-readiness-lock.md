# S6.3 Internal Outbox / Event Record First Slice Readiness Lock

Date: 2026-04-04
Status: docs-only planning adjustment
Story: `S6.3`
Story title: `Internal outbox/event record (future microservice boundary)`

## Why this round exists

`S6.2` is now runtime-proved at a narrow task-owned taxonomy slice.

The exact next roadmap story in SSOT is therefore `S6.3`.

As currently written, `S6.3` is still too broad to execute honestly. "Internal outbox/event record" can drift into at least five different claims:

- request audit logging
- notification inbox/read-model behavior
- a generic event bus or consumer platform
- retry or fan-out orchestration
- a broad replay API

Current repo evidence does not prove those are the same thing.

## Current evidence

### Backlog truth

- `docs/roadmap/backlog.yaml` marks `S6.2` as `done`
- `docs/roadmap/backlog.yaml` marks `S6.3` as the next unresolved story in `EPIC-6`
- current `S6.3` wording is only `Events recorded/replayable without breaking tenant/RBAC.`

### Closest existing canonical owner anchor

From `routes/api_zena.php`, `app/Http/Controllers/Api/TaskController.php`, and `tests/Feature/Api/TaskOverdueEscalationApiTest.php`:

- one canonical `/api/zena/*` trigger already emits a stable event-shaped payload:
  - `POST /api/zena/tasks/{id}/escalate-overdue`
- the trigger is already tenant-safe and RBAC-protected
- the trigger already proves one exact tuple:
  - `event_key = zena.task.overdue_escalated`
  - `notification.type = task_overdue_escalated`
  - `channel = inapp`

This makes it the narrowest honest first owner anchor for `S6.3`.

### Notification inbox truth

From `routes/api_zena.php`, `app/Http/Controllers/Api/NotificationController.php`, `app/Models/Notification.php`, and `database/migrations/2025_09_20_160100_recreate_notifications_table.php`:

- canonical `/api/zena/notifications` is mounted and app-owned
- `notifications` rows carry `type`, `channel`, `event_key`, `data`, and `metadata`
- this is still a delivery/read-model surface for user notifications
- this surface is already part of the proved `S6.2` boundary

Decision:

- do not use `notifications` as the first `S6.3` persistence owner
- do not use `notifications.event_key` as first event-record proof
- it may remain secondary comparison evidence only after a dedicated event-record write exists

### Audit truth

From `app/Services/ZenaAuditLogger.php`, `app/Models/AuditLog.php`, `database/migrations/2025_09_15_094811_add_tenant_constraints_for_security.php`, `database/migrations/2026_01_30_000001_add_zena_fields_to_audit_logs.php`, and `tests/Feature/Zena/ZenaAuditInvariantTest.php`:

- `audit_logs` persists request/audit metadata such as:
  - `action`
  - `entity_type`
  - `entity_id`
  - `route`
  - `method`
  - `status_code`
  - sanitized `meta`
- current audit proof is request-history proof, not immutable business-event proof
- there is no event payload contract, no event version, no replay contract, and no event-specific owner route

Decision:

- do not use `audit_logs` as `S6.3` event-record proof surface
- audit remains adjacent evidence only

### Route truth

From `php artisan route:list`, `routes/api_zena.php`, and `tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`:

- no canonical `/api/zena/*` event-record or outbox route family is mounted today
- no canonical `/api/zena/notification-rules` route family is mounted today
- no canonical `/api/zena/dashboard/alerts` route family is mounted today

Implication:

- the first honest `S6.3` slice should not depend on a new public read API
- the first proof can stay internal-only on write

## Locked first future runtime target

Choose `Option A`: ready the story for one minimal runtime slice.

### Owner anchor

Use exactly one owner anchor:

- `POST /api/zena/tasks/{id}/escalate-overdue`

Why this is the best anchor:

- already canonical on `/api/zena/*`
- already tenant-safe and RBAC-protected
- already emits one exact tuple proved in `S6.2`
- avoids inventing a generic platform owner

### Persistence surface

The first `S6.3` runtime slice should add one dedicated internal persistence surface only:

- a new internal table for immutable event records on the app-owned runtime path
- recommended name: `event_records`

The first persisted row should stay minimal and carry only the canonical facts needed for the already-proved overdue escalation event:

- `tenant_id`
- `project_id`
- `aggregate_type = task`
- `aggregate_id = {taskId}`
- `event_key = zena.task.overdue_escalated`
- a narrow payload for the already-proved tuple and task reference facts
- occurrence timestamp

This is an internal persistence surface only for the first slice.

### Proof contract

The first `S6.3` runtime proof should stay internal-only and prove exactly:

- `recorded`: successful overdue escalation writes exactly one immutable event-record row in the same canonical action
- `replayable`: the persisted row contains enough canonical facts to deterministically re-derive the already-proved direct in-app notification tuple for this one event path only
- tenant isolation: the persisted row carries the same tenant and project scope as the task owner action
- RBAC boundary: no new public read/write route is introduced; access control remains inherited from the existing task-owned trigger route

### What "replayable" means in the first slice

For the first slice only, "replayable" does **not** mean a user-facing replay API, queue worker, bus, retry engine, or consumer registry.

It means only:

- the event record is persisted in a shape that a runtime test can load internally
- the persisted event data is sufficient to reconstruct the same already-proved overdue escalation notification tuple for one event path

## Explicit non-goals

This planning lock does not claim:

- generic event-bus ownership
- multi-event taxonomy beyond `zena.task.overdue_escalated`
- fan-out, retries, dead-letter handling, or consumer registry
- email/webhook delivery
- dashboard alert bundles
- notification-rule CRUD
- canonical `/api/zena/*` event-record read routes
- use of `notifications` or `audit_logs` as substitute event-record owners

## Runtime-ready verdict

After this lock, `S6.3` is runtime-ready for one narrow first slice:

- one task-owned trigger
- one internal event-record table
- one immutable recorded row per successful trigger
- one test-only replayability proof through internal persistence, not public API

## Deferred / UNKNOWN

Deferred:

- any canonical read API for event records
- any replay endpoint
- any additional event families
- any notification fan-out or transport pipeline

UNKNOWN:

- final exact table name if migration naming pressure in repo requires a more specific variant than `event_records`
- whether a later round should expose a canonical read surface, and if so under which owner family

## Verdict

`S6.3` no longer needs another planning split before the first honest runtime round. The first runtime slice is now locked to the existing task-owned overdue escalation trigger, a new internal immutable event-record persistence surface, and a test-only replayability proof that stays strictly narrower than a platform or bus claim.
