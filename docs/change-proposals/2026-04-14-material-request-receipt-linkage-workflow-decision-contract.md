# Material Request Receipt Linkage Workflow Decision Contract

Date: 2026-04-14
Status: decision locked, runtime unchanged

## Purpose

This memo closes the current workflow-meaning gap around the already-implemented header-level linkage primitive between canonical material requests and canonical material receipts.

It is a decision contract only.

It does not change runtime truth, routes, schema, tests, or existing bounded linkage behavior.

## Current proved runtime boundary

- Header-level linkage is already operationally real through nullable `material_receipts.material_request_id`.
- Canonical receipt create accepts optional `material_request_id`.
- Canonical receipt list supports optional `material_request_id` filtering.
- Canonical request-owned linked-receipts projection exists.
- Canonical receipt-owned linked-request projection exists.
- Canonical receipt-header correction may set or clear `material_request_id` while the receipt still has no lines.
- No request status transition is currently triggered by any linkage create or correction path.
- No line-level linkage exists.

## Workflow questions this contract resolves

### 1. Which MaterialRequest statuses may be linked by a receipt

Decision:

- Receipts may link to material requests in any currently proved canonical request status:
  - `draft`
  - `submitted`
  - `approved`
  - `rejected`
  - `fulfilled`

Why:

- Current linkage runtime only proves same-tenant-through-project and same-project validation.
- Current request workflow runtime proves status transitions on the request owner family, but no linkage runtime proves any status-based restriction.
- Adding status-gated linkage would introduce new workflow semantics beyond the current bounded linkage primitive.

### 2. Whether rejected requests may be linked

Decision:

- Yes. Rejected requests may still be linked by a receipt for now.

Why:

- The linkage remains a soft header reference only.
- Current runtime does not prove that request rejection forbids historical, corrective, or documentation-style receipt association.

### 3. Whether fulfilled requests may still accept new linked receipts

Decision:

- Yes. Fulfilled requests may still accept new linked receipts for now.

Why:

- Current runtime does not prove that `fulfilled` means “hard-closed against future receipt linkage.”
- The canonical `fulfill` transition is request-owned and status-only today; it is not currently coupled to receipt membership counts, quantities, or receipt lifecycle state.

### 4. Whether receipt-header linkage correction remains allowed after certain request states

Decision:

- Receipt-header linkage correction remains governed only by the existing receipt-side precondition:
  - correction is allowed only when the receipt has no receipt lines yet
- Request status does not add any extra correction lock in the current decision contract.

Why:

- Current bounded correction runtime already proves the line-existence lock.
- No current runtime proves any request-status-based lock on receipt correction.
- Adding request-state locks here would couple two owner families without any proved downstream workflow need.

### 5. Whether any automatic request status transition should happen from receipt create or correction

Decision:

- No automatic request status transition should happen from receipt create or receipt-header correction.
- Receipt linkage must not auto-submit, auto-approve, auto-reject, auto-fulfill, or otherwise mutate request workflow state.

Why:

- Current linkage should remain additive and side-effect free.
- Current request workflow is already explicit on the request owner family:
  - `PUT /api/zena/material-requests/{id}`
  - `POST /api/zena/material-requests/{id}/submit`
  - `POST /api/zena/material-requests/{id}/approve`
  - `POST /api/zena/material-requests/{id}/reject`
  - `POST /api/zena/material-requests/{id}/fulfill`
- Automatic transitions would introduce hidden workflow coupling and would require new evidence for quantity meaning, completion meaning, and reversal behavior.

### 6. Whether the system should keep linkage as a soft reference only for now

Decision:

- Yes. The system should keep request↔receipt linkage as a soft header reference only for now.

Meaning of “soft reference” in this contract:

- the link is queryable and correctable
- the link carries navigation/read-model meaning
- the link does not itself imply fulfillment math
- the link does not itself imply status progression
- the link does not itself imply inventory or PO semantics
- the link does not require line-level matching

## Exact non-decisions deferred on purpose

- whether future request fulfillment should ever be derived from linked receipt quantities
- whether future request status should gain a distinct “partially received” state
- whether future request status should be computed rather than explicitly transitioned
- whether fulfilled requests should become hard-locked against new receipt linkage in a later, quantity-aware design
- whether rejected requests should be hard-blocked in a later approval-coupled design
- whether receipt lines should ever link to request lines
- whether request↔receipt linkage should feed inventory, PO, vendor, notification, or audit fan-out semantics beyond current route-level behavior

## Next implementation guidance

This decision contract means the next implementation slice, if any, should not be an automatic workflow coupling slice.

The next honest implementation slice should be chosen from:

- a purely additive request-owned read refinement
- a purely additive receipt-owned read refinement
- or a future docs-first quantity/status coupling proposal if business evidence later requires it

It should not be:

- automatic request status mutation from receipt create/correction
- line-level linkage
- quantity-derived fulfillment logic

