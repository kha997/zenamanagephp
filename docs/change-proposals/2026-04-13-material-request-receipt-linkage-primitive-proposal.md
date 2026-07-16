# Material Request To Receipt Header Linkage Primitive Proposal

Date: 2026-04-13
Status: proposed docs-only planning memo

## Why this memo exists

Canonical Material Requests and canonical Material Receipts are now both mounted on `/api/zena/*`, but current repo truth still proves them as separate owner families.

This memo does not claim that linkage already exists.

This memo does not patch runtime.

This memo does not patch tests.

This memo exists only to propose the smallest honest linkage primitive for a later implementation-bound round.

## Current truth that shapes the proposal

- Canonical material requests already exist as an owner family on `/api/zena/material-requests` with bounded workflow core through list/show/create/update/submit/approve/reject/fulfill.
- Canonical material receipts already exist as an owner family on `/api/zena/material-receipts` with bounded header create/list/show/update plus child checklist and line families.
- Current receipt header schema proves optional header-level foreign-key style linkage already for `vendor_id` and `contract_id`, but proves no `material_request_id`.
- Current receipt line schema proves `material_receipt_id` -> line ownership only; it does not prove any request-side field or line-to-line bridge.
- Current material request schema proves request header workflow only; it does not prove any receipt linkage field, receipt counter, or fulfillment-by-receipt contract.

## Decision

The strongest next proposal is:

- nullable `material_request_id` on `material_receipts`

This is a proposal only.

It is not current runtime truth.

## Why this is the best next move

Why it ranks above the alternatives:

- better than `no primitive / stay separate` because the missing bridge remains the main procurement continuity gap between two already-proved owner families
- better than line-level linkage because current receipt and request truth is header-first; line-level linkage would invent allocation semantics that current repo truth does not lock
- better than many-to-many or a pivot because current evidence does not prove one receipt needs to belong to multiple requests or that one receipt line must satisfy multiple request lines
- better than request-owned write-first linkage because the existing receipt header `store()` contract is already the natural point where a receipt first comes into existence, and `material_receipts` already carries optional linkage keys

## Exact proposed primitive

Proposed schema primitive:

- add nullable `material_request_id` to `material_receipts`
- foreign key target:
  - `zena_material_requests.id`
- proposed index:
  - `['tenant_id', 'project_id', 'material_request_id']`

Proposed model direction:

- `MaterialReceipt`:
  - optional `belongsTo(MaterialRequest::class, 'material_request_id')`
- `MaterialRequest`:
  - optional `hasMany(MaterialReceipt::class, 'material_request_id')`

The primitive is header-only.

It does not imply line-to-line matching.

It does not imply fulfillment math.

## Exact proposed cardinality

Proposed first cardinality:

- one receipt may optionally belong to one material request
- one material request may have many receipts

Why this is the right first cardinality:

- it matches the user-supplied smallest practical bias
- it preserves receipt independence because `material_request_id` stays nullable
- it avoids inventing split-allocation semantics at line level
- it allows multiple receipts over time against one approved request without introducing a pivot prematurely

## Exact proposed owner family for first proof

The first proof should stay on the canonical receipt header owner family:

- `POST /api/zena/material-receipts`

Reason:

- the primitive lives on `material_receipts`
- receipt creation is the first honest place where the linkage can be persisted
- this keeps the first runtime slice to one existing route family and one additive field only

## Exact proposed first proof surface

Proposed first runtime round:

- extend canonical `POST /api/zena/material-receipts`

Exact first proof surface:

- request payload may include optional `material_request_id`
- if present, `material_request_id` is persisted on the new receipt header
- created receipt serialization includes `material_request_id`
- existing header list/show surfaces include the field as additive header truth

This first proof surface should not require a new request-child route yet.

The first request-anchored read projection can remain deferred until after the bridge field itself is runtime-proved.

## Exact validation boundaries

If `material_request_id` is absent:

- receipt create semantics remain unchanged

If `material_request_id` is present:

- it must reference an existing `zena_material_requests.id`
- it must resolve through the same tenant boundary as the authenticated request by joining through the request's linked project tenant
- it must belong to the same `project_id` as the receipt header being created

Recommended first-slice failure behavior:

- cross-tenant or out-of-scope receipt access remains safe `404` under existing receipt lookup rules
- invalid `material_request_id` relation checks on create return safe `422`

Recommended first-slice optionality boundary:

- `material_request_id` is optional
- `material_request_id` may be null
- no rule should require receipts to belong to requests
- no rule should require requests to be approved or fulfilled in this first slice unless separately decided in a later memo

## Brief alternative comparison

### No primitive / stay separate

Not the best next move.

Reason:

- it preserves the current gap and leaves no honest canonical bridge between already-proved request and receipt owners

### Line-level linkage

Not the best next move.

Reason:

- current line truth only proves receipt-line ownership and quantity/cost notes semantics
- it would force unproved matching, split, or partial-fulfillment semantics

### Many-to-many / pivot

Not the best next move.

Reason:

- current evidence does not prove multi-request receipts or multi-receipt-request splits need a pivot as the first primitive
- a pivot would widen surface area before the smallest header-level bridge is tested

## Out of scope

This proposal does not include:

- line-to-line linkage
- line allocation or split semantics
- request fulfillment math
- automatic request status transitions from receipt creation
- receipt-to-request quantity reconciliation
- inventory, PO, vendor, contract, invoice, or payment side effects
- new linkage-specific route families
- `/api/v1/*`
- any claim that current runtime already has request-receipt linkage

## Exact follow-up implementation shape if accepted

If maintainers accept this proposal, the next implementation-bound round should be locked to:

- schema:
  - nullable `material_receipts.material_request_id`
- model:
  - additive header relation only
- route:
  - existing `POST /api/zena/material-receipts`
- validation:
  - same-tenant via request-project boundary
  - same-project with the receipt header
  - optional field only
- tests:
  - happy-path create with linked request
  - create with `null` request link
  - cross-tenant request rejected with `422`
  - same-tenant wrong-project request rejected with `422`
  - list/show expose additive `material_request_id`

## Proposed verification lane for the follow-up runtime round

- `php artisan route:list --except-vendor -v --path=api/zena/material-receipts`
- `php ./vendor/bin/phpunit tests/Feature/Api/MaterialReceiptApiTest.php tests/Feature/Api/MaterialRequestApiTest.php tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`

## Explicit non-claims

- this memo does not claim `material_request_id` already exists
- this memo does not claim any request-child receipt route is mounted today
- this memo does not claim request fulfillment semantics are unlocked
- this memo does not claim receipt lines or request lines are linkable

## Verdict

If one smallest practical linkage primitive should be chosen next, the best proposal is:

- add optional `material_request_id` on `material_receipts`
- keep cardinality to optional receipt -> one request, request -> many receipts
- make the first proof surface additive on existing `POST /api/zena/material-receipts`

That is the narrowest proposal that opens a real request-receipt bridge without inventing line matching, pivot complexity, or inventory-style side effects.
