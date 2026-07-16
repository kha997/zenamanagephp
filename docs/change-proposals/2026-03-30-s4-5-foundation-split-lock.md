# S4.5 Foundation Split Lock

Date: 2026-03-30
Status: split lock with `S4.5` runtime proved
Stories: `S4.5`, `S4.5a`, `S4.5b`
Original story title: `Cost mapping to Compensation/Contract (minimal)`

## Why this exists

The prior `S4.5` planning lock correctly identified two missing prerequisites, but it still left one ambiguity unresolved:

- whether those prerequisites are just dependencies inside one story
- or whether they are distinct owner concerns that deserve their own foundation stories

Current repo evidence supports a split.

## Question 1: Is cost mapping blocked on more than one unproved owner concern?

Yes.

It is blocked by two separate unproved owner concerns:

- procurement-side receipt-line ownership
- finance-side canonical contract ownership on `/api/zena/*`

The future read-only summary depends on both.

## Question 2: Is receipt-line ownership a real owner boundary or just an implementation detail?

It is a real owner boundary.

Current canonical receipt runtime proves:

- header-only `material_receipts`
- receipt-header child checklist snapshots

Current canonical receipt runtime does not prove:

- any persisted receipt-line child aggregate
- any per-line quantity semantics
- any per-line amount semantics

That means receipt lines are not a hidden field-level implementation detail inside the proved header contract. They require a new child owner contract under the receipt family.

## Question 3: Does canonical contract/compensation ownership already have a candidate owner family on `/api/zena/*`?

No proved candidate exists yet on `/api/zena/*`.

Current repo evidence proves only:

- compatibility `projects/{project}/contracts`
- compatibility `contracts/{contract}/payments`

Those shapes suggest a likely finance-side owner direction, but they are still compatibility-only and cannot be promoted as canonical proof.

## Question 4: Is compensation itself a separate owner family that must be split again now?

Not yet.

Current repo evidence shows `TaskCompensation` as internal/legacy residue tied to tasks and contracts.

What it does not prove:

- a standalone canonical `/api/zena/compensation*` family
- that compensation should own the first procurement cost-summary slice directly

Planning consequence:

- the finance-side foundation story should anchor canonical contract ownership first
- payment and compensation remain child/projection concerns unless later runtime proof says otherwise

## Question 5: Where should the future read-only cost summary attach canonically?

It should attach to the finance-side contract owner, not the receipt header.

Reason:

- receipt ownership should carry procurement facts
- contract ownership is the more honest business anchor for a cost-mapping summary
- attaching summary ownership to receipt too early would blur procurement owner scope with finance owner scope

Exact route shape remains `UNKNOWN` until canonical contract ownership is actually proved.

## Locked Decision

Choose split.

The roadmap should become:

- `S4.5` = receipt line aggregate owner
- `S4.5a` = canonical contract owner convergence for procurement cost summaries
- `S4.5b` = receipt-to-contract cost summary mapping (read-only)

## Ordering

1. `S4.5` receipt line aggregate owner
2. `S4.5a` canonical contract owner convergence for procurement cost summaries
3. `S4.5b` receipt-to-contract cost summary mapping (read-only)

Why this order:

- `S4.5` is the smallest runtime-ready foundation on the procurement side once a narrow line contract is locked
- `S4.5a` can proceed independently, but the future mapping story still cannot be honest until the receipt-side line aggregate exists
- `S4.5b` depends on both and should remain read-only

## Runtime Readiness Implication

The most likely next runtime-ready foundation story is `S4.5`, because:

- it extends an already proved canonical receipt owner family
- it does not require canonical finance-side convergence to define its own owner boundary

`S4.5a` remains independently necessary, but it is a larger convergence concern because current evidence still lives only on `/api/v1/*`.

## Runtime Follow-up

`S4.5` is now runtime-proved.

Current canonical receipt owner truth now includes:

- `GET /api/zena/material-receipts/{receipt}/lines`
- `POST /api/zena/material-receipts/{receipt}/lines`
- `GET /api/zena/material-receipts/{receipt}/lines/{line}`

What this proof establishes:

- receipt lines are a real persisted child aggregate under the canonical receipt owner family
- the minimal persisted payload is `material_id`, `quantity_received`, and optional `notes`
- project and tenant context are inherited from the parent receipt
- wrong-parent and cross-tenant access stay anti-enumeration `404`

What this proof still does not establish:

- unit cost or unit price semantics
- purchase-order linkage
- document linkage
- checklist coupling
- finance-side contract ownership
- receipt-to-contract cost summary mapping

Planning consequence:

- the split remains valid
- the next dependency before any honest cost-summary runtime is `S4.5a`

## Non-Claims

This split does not claim:

- receipt evidence linkage
- receipt workflow gates
- invoice workflow
- payment execution workflow
- standalone canonical compensation ownership
- broad finance cleanup

## Deferred / UNKNOWN

Deferred:

- exact receipt-line payload
- exact canonical `/api/zena/*` contract route shape
- whether payments become canonical in the same story as contract convergence or later

UNKNOWN:

- exact route and payload for the future read-only cost summary
