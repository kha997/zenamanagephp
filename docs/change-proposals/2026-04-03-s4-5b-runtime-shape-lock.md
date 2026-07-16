# S4.5b Runtime Shape Lock

Date: 2026-04-03
Status: runtime proved
Story: `S4.5b`
Story title: `Receipt-to-contract mapping key convergence`

## Why this exists

The prior mapping-candidate lock narrowed `S4.5b` to the right bridge candidate:

- `material_receipts.contract_id`

What still needed an exact answer before runtime was:

- where that bridge should first be written
- whether first contract-child proof needs list only or list plus show
- the smallest honest serialization contract with no money semantics

This lock answers those items.

## Decision 1: first persisted bridge

Yes.

`material_receipts.contract_id` is sufficient as the first canonical persisted bridge.

Why:

- receipt ownership is already proved at the header aggregate
- receipt lines already inherit header identity through `material_receipt_id`
- no current evidence proves a first mapping slice must support one receipt splitting across multiple contracts

What this excludes for the first slice:

- `material_receipt_lines.contract_id`
- a receipt-contract pivot table
- inference from `project_id`, `vendor_id`, `material_id`, `receipt_number`, or contract code

## Decision 2: first bridge write surface

Choose create-time on the canonical receipt-header write surface.

Exact direction:

- extend `POST /api/zena/material-receipts`
- allow nullable `contract_id`

Why create-time is the narrowest honest choice:

- canonical receipt runtime already proves header `store()`
- canonical receipt runtime does not prove header update
- adding a mapping-specific write route would introduce a larger contract than needed just to persist the first bridge

Why not update-time:

- it would require creating a new canonical `PUT/PATCH /api/zena/material-receipts/{id}` surface that is not part of the proved receipt-header slice

Why not mapping-specific write surface:

- it would create a second write owner surface before the bridge itself is even canonically persisted on the receipt header

## Decision 3: first contract-child read surface

Choose list-only.

Exact direction:

- `GET /api/zena/projects/{project}/contracts/{contract}/material-receipts`

Why list-only is enough:

- the new story only needs to prove contract-anchored membership of mapped receipts
- canonical receipt ownership already proves `GET /api/zena/material-receipts/{id}`
- adding `GET /api/zena/projects/{project}/contracts/{contract}/material-receipts/{receipt}` would duplicate existing receipt show semantics without proving a new mapping concept

## Minimal future runtime slice

Persisted bridge field:

- nullable `material_receipts.contract_id`

Minimal validation rules:

- `contract_id` is optional
- if present, it must exist on `contracts`
- if present, it must belong to the same tenant
- if present, it must belong to the same `project_id` as the receipt header

Minimal serialization contract:

- receipt header continues to serialize receipt identity facts
- add `contract_id`
- do not add money fields
- do not add summary or aggregate fields

Minimal contract-child read projection:

- list-only mapped receipt headers for one canonical contract owner
- return receipt header identity only
- no line rollups
- no totals
- no payment or invoice state

Minimal proof targets:

- mapped receipt can be created on the canonical receipt header path with a same-project same-tenant contract
- cross-tenant or wrong-project contract reference is rejected
- contract-child list returns only receipts mapped to that contract
- wrong-project / wrong-contract / cross-tenant reads stay anti-enumeration `404`

## Deferred / UNKNOWN

Deferred:

- whether contract child list should embed vendor summary or remain raw header fields only
- whether receipt header index should later support `contract_id` filtering once the first contract-child proof exists

UNKNOWN:

- whether the first runtime round should expose the mapped `contract` object inline on receipt show/list, or only the foreign key

## Locked direction

Choose ready-for-runtime after this planning round.

The remaining work for `S4.5b` is now runtime implementation and proof, not story-shape ambiguity.

## Runtime Follow-up

`S4.5b` is now runtime-proved.

Current canonical runtime truth now includes:

- nullable `material_receipts.contract_id`
- optional `contract_id` on `POST /api/zena/material-receipts`
- `GET /api/zena/projects/{project}/contracts/{contract}/material-receipts`

What this proof establishes:

- the first canonical persisted receipt-to-contract bridge is the receipt-header foreign key
- create-time validation rejects contract references outside the current tenant or current receipt project
- receipt header serialization adds only `contract_id` and keeps the existing header identity shape otherwise
- contract-child read proof is list-only and returns only mapped receipt headers for the resolved canonical contract owner
- wrong-project and cross-tenant contract-child reads stay anti-enumeration `404`

What this proof still does not establish:

- receipt header update-time mapping writes
- line-level contract splits
- pivot-based mapping
- money fields
- totals or summary aggregation
- payment, compensation, or invoice workflow semantics
