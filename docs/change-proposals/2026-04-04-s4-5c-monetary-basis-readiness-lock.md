# S4.5c Monetary Basis Readiness Lock

Date: 2026-04-04
Status: runtime proved
Story: `S4.5c`
Story title: `First monetary basis for receipt cost summaries`

## Why this exists

`S4.5b` is now runtime-proved, so `S4.5c` is the next roadmap story.

That still does not make `S4.5c` runtime-ready.

Current canonical runtime now proves:

- receipt headers on `/api/zena/material-receipts`
- receipt lines on `/api/zena/material-receipts/{receipt}/lines`
- project contracts on `/api/zena/projects/{project}/contracts`
- receipt-to-contract mapping on `/api/zena/projects/{project}/contracts/{contract}/material-receipts`

Current canonical runtime still does not prove:

- any money-bearing field on the receipt-line contract
- any money-bearing field on the receipt-header contract
- any canonical contract-child cost projection under `/api/zena/*`

Without one more planning lock, a runtime round would still have to invent the first money field by preference rather than evidence.

## Current Evidence

### Receipt-line boundary

Current canonical receipt-line proof establishes only:

- `material_id`
- `quantity_received`
- `notes`

What this means:

- the current receipt-line aggregate is the only proved place where receipt-side quantity facts live
- any first monetary basis that multiplies, prices, or extends receipt-side facts should stay attached to the same owner unless a stronger owner boundary is proved later

What it does not prove:

- `unit_cost`
- `unit_price`
- `amount`
- `extended_amount`
- tax, discount, retention, invoice, or payment semantics

### Receipt-header boundary

Current canonical receipt-header proof establishes:

- header identity
- project/vendor linkage
- optional `contract_id`

What this means:

- header ownership now maps a receipt to a contract
- header ownership still does not prove per-line economic basis

What it does not prove:

- one money snapshot for the whole receipt
- any line rollup or cost total

Planning consequence:

- putting the first monetary basis on the header would blur line-owned procurement facts into a premature aggregate

### Contract / payment / compensation boundary

Current repo still exposes money-like residue elsewhere:

- `contracts.total_value`
- `/api/v1/contracts/{contract}/payments` with `amount`
- compensation residue with `total_amount` and related calculations

Why those do not qualify:

- `contracts.total_value` is contract-level, not receipt-side basis
- contract payments remain compatibility-only `/api/v1/*`
- compensation remains non-canonical residue for this story

Planning consequence:

- no existing finance residue can be promoted as the first canonical receipt-side monetary basis

## Candidate Comparison

### Candidate 1: receipt-line `unit_cost`

Pros:

- stays on the proved receipt-line owner aggregate
- combines naturally with already proved `quantity_received`
- is narrow enough to establish basis without claiming totals
- avoids invoice/payment wording

Cons:

- not yet proved in schema, controller, route payload, or tests

### Candidate 2: receipt-line `unit_price`

Pros:

- also line-local and multiplicative with quantity

Cons:

- wording is more ambiguous because it can suggest quote, billing, or invoice semantics
- current canonical runtime does not prove any commercial pricing vocabulary on receipts

### Candidate 3: receipt-line `amount` or `extended_amount`

Pros:

- directly money-bearing

Cons:

- skips the basis step and jumps to a derived total-like field
- hides whether the source is quantity times a unit basis or some other snapshot
- is broader than the smallest future runtime proof surface

### Candidate 4: receipt-header cost snapshot

Cons:

- wrong owner for the first basis because quantities already live on receipt lines
- would introduce aggregation semantics before a summary story exists

### Candidate 5: contract-child monetary projection

Cons:

- wrong story boundary because `S4.5c` must establish basis first, not summary
- would move source-of-truth economics onto a projection owner before the basis exists canonically on receipt-side facts

## Exact Field Decision

Choose:

- nullable receipt-line `unit_cost`

Why this is the best candidate:

- receipt lines already own the proved quantity fact
- `unit_cost` is the smallest additive field that creates a monetary basis without forcing totals
- it stays on canonical procurement facts rather than finance projections
- it is less semantically overloaded than `unit_price`

Why nullable in the first runtime slice:

- current canonical receipt-line proof does not establish that every first-line create must already know a final money value
- making the field required would enlarge the business claim from `first monetary basis available` to `monetary basis mandatory`
- nullable keeps the first money-basis proof additive and backward-compatible with the already proved line owner contract

What this excludes for the first runtime slice:

- required `unit_cost`
- `unit_price`
- `amount`
- `extended_amount`
- any header or contract-child money field

## Exact Blocker

`S4.5c` is still not runtime-ready because the repo lacks all of the following:

- a locked canonical field name for the first receipt-line money basis
- schema support for that field on `material_receipt_lines`
- canonical line create validation for that field
- canonical line serialization for that field
- feature-test proof that the field is tenant-safe, parent-safe, and mapping-safe without introducing totals

## Exact Surface Decision

Choose create-time plus existing read surfaces only.

Exact direction:

1. add nullable `unit_cost` to `material_receipt_lines`
2. extend only `POST /api/zena/material-receipts/{receipt}/lines`
3. extend existing `GET /api/zena/material-receipts/{receipt}/lines`
4. extend existing `GET /api/zena/material-receipts/{receipt}/lines/{line}`

Why update-time is not needed:

- canonical receipt-line runtime already proves create/list/show
- the story only needs to prove that one money-basis field can be written and read on the existing owner path
- adding `PUT/PATCH` for lines would create a larger owner contract than the first proof requires

Why create/list/show is enough:

- create-time proves the first write contract
- list/show prove persistence and serialization on the same owner path
- no extra route is needed to demonstrate the basis field exists canonically

## Minimal Validation / Serialization Shape

Minimal validation rules:

- `unit_cost` is optional
- if present, it must be numeric
- if present, it must be greater than or equal to `0`

What remains `UNKNOWN` and therefore unlocked:

- exact decimal precision rule beyond normal numeric acceptance
- any upper bound beyond generic non-negative validation

Minimal serialization contract:

- receipt-line identity stays unchanged
- add only `unit_cost`
- do not add `amount`
- do not add `extended_amount`
- do not add line or header totals
- do not add summary or aggregate state

What that future runtime slice must prove:

- canonical receipt lines accept optional `unit_cost`
- `unit_cost` is nullable and non-negative when present
- list/show return the same field on the receipt-line owner path
- wrong-parent and cross-tenant anti-enumeration stays unchanged

What that future runtime slice must not claim:

- `amount` or `extended_amount`
- line totals or header totals
- contract-child cost summary
- payment, compensation, or invoice workflow

## Locked Direction

Choose runtime proved after the implementation round.

## Deferred / UNKNOWN

Deferred:

- whether `unit_cost` should remain nullable or become required once later receipt flows mature
- whether receipt-line update/delete surfaces are needed later for corrections
- when `amount` or `extended_amount` should appear relative to `S4.5d`

UNKNOWN:

- whether later summary runtime should derive totals on read only or persist additional money fields

## Runtime Follow-up

`S4.5c` is now runtime-proved.

Current canonical runtime truth now includes:

- nullable `material_receipt_lines.unit_cost`
- optional `unit_cost` on `POST /api/zena/material-receipts/{receipt}/lines`
- `unit_cost` returned on `GET /api/zena/material-receipts/{receipt}/lines`
- `unit_cost` returned on `GET /api/zena/material-receipts/{receipt}/lines/{line}`

What this proof establishes:

- the first canonical receipt-side monetary basis lives on the receipt-line owner aggregate, not the receipt header or the contract-child projection
- create-time validation accepts omitted `unit_cost`, accepts numeric non-negative `unit_cost`, and rejects negative input
- existing line list/show serialization keeps the same identity shape and adds only `unit_cost`
- wrong-parent, cross-tenant, and RBAC behavior on the receipt-line owner path remain unchanged

What this proof still does not establish:

- `amount` or `extended_amount`
- header totals or contract-child summary projection
- line update/delete correction semantics
- payment, compensation, or invoice workflow
