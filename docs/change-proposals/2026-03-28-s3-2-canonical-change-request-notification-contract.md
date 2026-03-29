# S3.2 Change Proposal: Canonical Change Request Notification Contract

Date: 2026-03-28
Status: docs-only planning lock, no runtime implementation in this round

## Post-Lock Note

Later on 2026-03-28, the minimal canonical in-app notification slice defined here was implemented and locked at `a41ee056`.

What is now proved on `/api/zena/change-requests`:

- `submit` creates exactly one direct in-app notification to an explicit approver fixture via `change_requests.assigned_to`
- `approve` creates one direct in-app notification to `change_requests.requested_by`
- `reject` creates one direct in-app notification to `change_requests.requested_by`

What remains unproved:

- any notification claim for `apply`
- any broader approver discovery rule beyond the explicit fixture used for proof
- stakeholder recipient semantics and broad fan-out

Therefore `S3.2` still remains `todo`, and the cleanest next planning move is to split the already-proved direct-recipient notification slice from the still-unknown stakeholder/broader notification acceptance surface.

## Context Snapshot

Canonical change-request workflow runtime is already locked on `/api/zena/change-requests` under `App\Http\Controllers\Api\ChangeRequestController`.

Already proved on the canonical path:

- `submit: draft -> submitted`
- `approve: only from submitted`
- `reject: only from submitted`
- `apply: only from approved -> implemented`
- `update()` blocks direct workflow status mutation
- canonical audit via `App\Services\ZenaAuditLogger` for `submit`, `approve`, `reject`, and `apply`

Still not proved on the canonical path:

- any end-to-end notification trigger for `submit`, `approve`, `reject`, or `apply`
- canonical recipient resolution for approvers or stakeholders
- canonical email/job/mail dispatch

## Problem Statement

The repo has notification capability, but the active `/api/zena/change-requests` owner path does not currently prove a canonical notification contract.

Inventory findings for the active owner path:

- `App\Http\Controllers\Api\ChangeRequestController` does not currently dispatch canonical notification events or write `App\Models\Notification`
- notification-related code is split across `app/*` and `src/*`
- event, provider, and payload wiring drift across those parallel paths
- broad stakeholder semantics are not canonically defined by current route/controller/model evidence

Without locking a minimal contract first, the next runtime round would have to invent recipient semantics inside code.

## Decision Drivers

- keep the next runtime slice on `/api/zena/change-requests` only
- avoid `/api/v1/*` and broad `app/*` vs `src/*` cleanup
- use only recipient semantics grounded in active canonical fields or route truth
- prefer a proof path that does not depend on unresolved notification-rule ownership
- keep the proof small enough to verify with direct feature tests on the canonical path

## Current Contract Gaps

### Approver semantics

Current canonical evidence proves approve authority exists as a permissioned route action, but does not canonically define a project-driven approver resolution rule.

Therefore:

- the full approver discovery rule is `UNKNOWN`
- the next proof round must not invent stakeholder or approver fan-out logic

### Stakeholder semantics

Current canonical evidence does not define who counts as a stakeholder on `/api/zena/change-requests`.

Therefore:

- stakeholder recipient semantics are `UNKNOWN`
- broad stakeholder notification fan-out is deferred

### Apply notification boundary

`apply` is part of the locked runtime state machine, but current evidence does not justify including it in the minimal canonical notification proof boundary.

Therefore:

- `apply` notification remains deferred in the next narrow proof round

## Recommended Canonical Contract

Lock the next runtime proof round to minimal canonical in-app notifications only:

- `submit` should create an in-app notification for one explicit approver recipient fixture on the canonical path
- `approve` should create an in-app notification for the change-request requester
- `reject` should create an in-app notification for the change-request requester
- `apply` notification is deferred

This is intentionally narrower than broad backlog wording and does not mark `S3.2` done by itself.

## Recipient Matrix

| Mutation | Canonical recipient for next proof round | Evidence basis | Status |
| --- | --- | --- | --- |
| `submit` | one explicit approver recipient fixture | route-level approve authority exists; broad approver discovery is not canonically defined | minimal proof target |
| `approve` | requester (`change_requests.requested_by`) | canonical model/controller already carry requester identity | minimal proof target |
| `reject` | requester (`change_requests.requested_by`) | canonical model/controller already carry requester identity | minimal proof target |
| `apply` | deferred | no canonical recipient truth for this notification boundary | deferred |
| broad stakeholders | `UNKNOWN` | no canonical owner truth in current runtime evidence | deferred |

Clarifications:

- `approver(s)` in the next proof round means a deliberately seeded canonical recipient used to prove one notification path, not a newly invented fan-out rule
- `stakeholders` remain `UNKNOWN` until a later planning or ownership round proves who they are on the canonical path

## Recommended Mechanism

Recommend direct `App\Models\Notification` writes from the canonical `App\Http\Controllers\Api\ChangeRequestController` path.

Reason:

- the canonical route/controller owner is already clear in `App/*`
- current notification-rule ownership is still split and partially v1/src-owned
- current event/listener wiring is drifted and not yet a stable canonical proof surface
- one direct in-app write is the smallest proofable mechanism for a narrow canonical round

Not recommended first:

- canonical event + listener proof as the first notification slice

Reason:

- existing event/listener/provider wiring is split across `app/*` and `src/*`
- current repo evidence does not show one already-proven canonical notification listener path for change requests

## Explicit Defers

- `/api/v1/*`
- broad notification-rule convergence
- broad `app/*` vs `src/*` cleanup
- email/job/mail proof
- broad stakeholder fan-out
- any rule-engine ownership change
- any notification claim for `apply`

## Verify Plan For The Next Runtime Round

- add the smallest canonical notification write on `/api/zena/change-requests` only
- prove `submit` creates exactly one in-app notification for the explicit approver fixture
- prove `approve` creates one in-app notification for `requested_by`
- prove `reject` creates one in-app notification for `requested_by`
- prove no `/api/v1/*` surface was changed
- keep story status conservative until runtime proof exists

## Final Planning Verdict

The smallest safe next runtime slice is now defined tightly enough to implement without inventing stakeholder semantics.
