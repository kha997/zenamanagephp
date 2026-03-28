# S3.2 Change Proposal: Change Request Workflow State Machine Unification

Date: 2026-03-28
Status: proposal only, no implementation in this round

## Post-Lock Note

Later on 2026-03-28, the narrow canonical runtime slice proposed here was implemented and locked at `fb45a35ab6ebd3a7177a7d1317a459c7d416e270`.

That locked slice proved on `/api/zena/change-requests`:

- `submit: draft -> submitted`
- `approve: only from submitted`
- `reject: only from submitted`
- `apply: only from approved -> implemented`
- `update()` blocks direct workflow status mutation
- canonical audit alignment via `App\Services\ZenaAuditLogger` for `submit`, `approve`, `reject`, and `apply`

What remains outside the proved slice:

- canonical notification proof is still deferred
- backlog story `S3.2` therefore remains `todo`
- this proposal now serves as planning context for acceptance-boundary alignment rather than pending runtime implementation

## Context Snapshot

Canonical runtime for change requests currently lives on `/api/zena/change-requests` and is owned by `App\Http\Controllers\Api\ChangeRequestController`.

Current runtime route surface from `php artisan route:list --path=api/zena/change-requests`:

- `GET|HEAD /api/zena/change-requests`
- `POST /api/zena/change-requests`
- `GET|HEAD /api/zena/change-requests/{id}`
- `PUT /api/zena/change-requests/{id}`
- `DELETE /api/zena/change-requests/{id}`
- `POST /api/zena/change-requests/{id}/submit`
- `POST /api/zena/change-requests/{id}/approve`
- `POST /api/zena/change-requests/{id}/reject`
- `POST /api/zena/change-requests/{id}/apply`

## Problem Statement

The canonical change-request workflow on `/api/zena/change-requests` is carrying state and proof drift in three places:

- runtime status vocabulary is inconsistent across controller, model, form request, and tests
- active-owner transition proof is only partial: `submit` currently proves `draft -> submitted`, and `apply` currently proves `approved -> implemented`, while `approve/reject` do not yet prove explicit status guards
- canonical audit proof is not yet aligned on the active Zena path, and notification proof is only capability-level rather than canonical end-to-end proof

Without resolving those drifts first, any additional workflow work risks encoding the wrong state machine, the wrong audit source of truth, or notification behavior that the repo does not actually prove.

## Evidence

### Status vocabulary drift

- `app/Http/Controllers/Api/ChangeRequestController.php`
  - `update()` validation allows `draft,submitted,pending_approval,approved,rejected,implemented`
  - `submit()` writes `status = submitted`
- `app/Models/ChangeRequest.php`
  - canonical constants use `draft, awaiting_approval, approved, rejected`
  - `STATUS_TRANSITIONS` is based on `awaiting_approval`
- `app/Http/Requests/ChangeRequestFormRequest.php`
  - validation allows `draft, awaiting_approval, approved, rejected`
- tests prove mixed expectations:
  - `tests/Feature/Api/ChangeRequestApiTest.php` uses `pending_approval` for approve/reject setup
  - `tests/Feature/ChangeRequestApiTest.php` uses `submitted` for approve setup
  - `tests/Feature/Integration/EventWorkflowTest.php` uses `awaiting_approval`

Observed live vocabulary set from repo evidence:

- `draft`
- `submitted`
- `pending_approval`
- `awaiting_approval`
- `approved`
- `rejected`
- `implemented`

### Canonical audit proof is unclear

- `app/Http/Controllers/Api/ChangeRequestController.php` does not inject or call `App\Services\ZenaAuditLogger`
- other canonical Zena controllers such as `RfiController`, `SubmittalController`, `WorkTemplateController`, `WorkInstanceController`, and `DeliverableTemplateController` do use `ZenaAuditLogger`
- this means the repo has an established canonical audit pattern, but the active owner controller is not yet aligned with it

### Notification proof is insufficient on canonical Zena path

- `app/Models/NotificationRule.php` defines `EVENT_CHANGE_REQUEST_SUBMITTED`
- `app/Http/Controllers/Api/NotificationController.php` can create notifications including `change_request_submitted` and `change_request_approved`
- current evidence does not show `App\Http\Controllers\Api\ChangeRequestController` dispatching canonical notification events or directly creating notifications on submit/approve/reject
- current evidence therefore proves notification capability exists in the repo, but not canonical end-to-end CR workflow notification proof

## Decision Drivers

- one canonical state machine must exist for `/api/zena/change-requests`
- active controller, model, validation, and tests must agree on the same vocabulary
- canonical audit proof should follow an already proven Zena pattern rather than inventing a separate mechanism
- notification behavior should stay minimal until canonical proof exists
- the next slice should be small enough to verify with route/runtime/tests and easy to roll back

## Options Considered

### Option A: keep current mixed vocabulary and patch tests opportunistically

Reject.

This preserves ambiguity between `submitted`, `pending_approval`, and `awaiting_approval`, and leaves future rounds guessing which state machine is authoritative.

### Option B: unify on `draft -> submitted -> approved|rejected`, add canonical audit, defer broad notifications

Recommend.

This matches existing controller submit behavior, stays close to current route surface, and is the smallest path to one canonical workflow contract.

### Option C: unify on `draft -> awaiting_approval -> approved|rejected`, rename controller behavior immediately, and wire full notifications

Reject for now.

The model/form-request constants support this direction, but runtime controller/tests already prove `submitted`, and broad notification fan-out is not sufficiently evidenced.

## Recommended Decision

Treat current runtime truth and implementation target as separate facts.

Current runtime truth on the active owner path:

- `submit` currently proves `draft -> submitted`
- `apply` currently proves `approved -> implemented`
- `approve/reject` currently do not prove explicit transition guards
- canonical audit is not yet aligned to `App\Services\ZenaAuditLogger` on `App\Http\Controllers\Api\ChangeRequestController`
- notifications are only proven at repo capability level, not as canonical end-to-end workflow behavior

Proposed implementation target for `/api/zena/change-requests`:

- `draft -> submitted -> approved|rejected`

Canonical audit path:

- use `App\Services\ZenaAuditLogger` on the active `App\Http\Controllers\Api\ChangeRequestController` path

Notification stance:

- prove one minimal canonical notification path only if direct repo evidence supports it during implementation
- otherwise defer full fan-out notifications, watcher expansion, and non-canonical event plumbing

This keeps the runtime contract aligned with the currently observed controller behavior and avoids opening a broader workflow engine round.

## Smallest Safe Slice

- choose one canonical vocabulary and remove the `submitted` vs `pending_approval` vs `awaiting_approval` drift from active controller/model/request/test surfaces
- make `submit`, `approve`, and `reject` transition rules explicit on the canonical controller, while leaving `apply` outside this proposal target state machine
- wire canonical audit logging for those workflow mutations through `ZenaAuditLogger`
- update or add only the smallest test coverage needed to prove:
  - `submit` still proves `draft -> submitted`
  - `approve/reject` gain explicit valid and invalid transition proof
  - canonical audit rows written on the chosen path
- keep notification work limited to proof-backed behavior only

## Explicit Non-Goals

- no new workflow engine
- no new module
- no changes to `/api/v1/*` compatibility surfaces in this round
- no broad notification-rule fan-out redesign
- no backlog rewrite in this round
- no migration unless implementation evidence later proves it is strictly required
- no attempt to reconcile every legacy or `src/*` change-request surface in the same slice

## Verify Plan

Before implementation:

- `cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden`
- `php artisan route:list --path=api/zena/change-requests`
- `rg -n "pending_approval|awaiting_approval|submitted" app tests routes docs --glob '!vendor'`
- `rg -n "ZenaAuditLogger|auditLogger" app/Http/Controllers/Api app --glob '!vendor'`
- `rg -n "change_request_submitted|change_request_approved" app tests --glob '!vendor'`

Implementation acceptance should require proof for:

- one canonical status vocabulary across active controller/model/validation/tests
- explicit `approve/reject` transition guard behavior on `/api/zena/change-requests`
- one canonical audit path on `/api/zena/change-requests` via `App\Services\ZenaAuditLogger`
- notification behavior either proven narrowly on the canonical path or explicitly deferred

## Risks / Rollback

Risks:

- test expectations currently disagree on pre-approval status naming
- downstream consumers may depend on mixed status strings or on `implemented` remaining reachable through `apply`
- changing audit or notification wording without runtime proof would create new narrative debt

Rollback:

- keep the slice isolated to the canonical `App\Http\Controllers\Api\ChangeRequestController` path
- keep `/api/v1/*` compatibility surfaces out of scope for this round
- if audit or status unification regresses behavior, revert that narrow slice without touching unrelated modules
- if notification proof is weak during implementation, ship the state-machine and audit unification first and defer notifications explicitly
