# S4.5d Read-Only Summary Shape Lock

Date: 2026-04-04
Status: runtime proved
Story: `S4.5d`
Story title: `Receipt-to-contract cost summary mapping (read-only)`

## Why this exists

`S4.5b` and `S4.5c` are now runtime-proved.

That removes the two hard prerequisites that previously blocked `S4.5d`:

- a canonical receipt-to-contract mapping key
- a canonical receipt-line monetary basis

The repo still needs one narrow planning decision before runtime:

- the exact read-only projection shape on the canonical contract owner path
- the exact aggregation rule for receipt lines where `unit_cost` is still `null`

Without that lock, a runtime round would still have to invent whether `S4.5d` means:

- a fully priced contract total
- a partial priced-only rollup
- an "unknown until all lines are priced" projection

## Current Evidence

What runtime already proves on the canonical `/api/zena/*` surface:

- `material_receipts.contract_id` exists and is written only on `POST /api/zena/material-receipts`
- `GET /api/zena/projects/{project}/contracts/{contract}/material-receipts` lists mapped receipt headers for one canonical contract
- `material_receipt_lines.unit_cost` exists and is written only on `POST /api/zena/material-receipts/{receipt}/lines`
- `GET /api/zena/material-receipts/{receipt}/lines` and `GET /api/zena/material-receipts/{receipt}/lines/{line}` expose `quantity_received` plus nullable `unit_cost`

What runtime still does not prove:

- any canonical contract-child summary route
- any locked summary payload keys
- any locked rule for how `unit_cost = null` should affect a derived rollup

## Locked Owner Anchor

The read-only summary stays under the canonical project-contract owner family:

- `/api/zena/projects/{project}/contracts/{contract}/...`

Locked route-tail candidate for the first runtime slice:

- `/cost-summary`

Canonical route candidate:

- `GET /api/zena/projects/{project}/contracts/{contract}/cost-summary`

Why this tail:

- it is the smallest explicit projection name that matches the story title
- it stays contract-anchored rather than reintroducing receipt ownership
- it avoids payment, invoice, or compensation naming drift

## Locked Minimal Payload Candidate

The first honest payload should stay top-level and partial:

- `project_id`
- `contract_id`
- `summary`

The first honest `summary` candidate is:

- `mapped_receipt_count`
- `line_count`
- `priced_line_count`
- `unpriced_line_count`
- `priced_line_cost_total`

Why this is the smallest safe payload:

- `mapped_receipt_count` proves contract-anchored source membership from `S4.5b`
- `line_count` proves the projection is actually derived from receipt lines
- `priced_line_count` and `unpriced_line_count` make nullable `unit_cost` explicit instead of hiding incompleteness
- `priced_line_cost_total` avoids overclaiming a full contract-procurement total when some receipt lines may still be unpriced

What the first runtime payload must not claim:

- `amount`
- `extended_amount`
- `total_amount`
- invoice state
- payment state
- compensation state
- receipt workflow state

## Locked Aggregation Rules

The first runtime slice should derive only from already proved canonical facts:

1. source receipts

- all canonical `material_receipts` where:
  - `tenant_id = current tenant`
  - `project_id = {project}`
  - `contract_id = {contract}`

2. source lines

- all canonical `material_receipt_lines` where:
  - `material_receipt_id` belongs to the mapped receipt set above

3. line pricing rule

- a line is `priced` only when `unit_cost` is not `null`
- a line is `unpriced` when `unit_cost` is `null`

4. derived money rule

- `priced_line_cost_total = sum(quantity_received * unit_cost)` across priced lines only

5. projection rule

- the response is read-only
- the response is a contract-child projection only
- the response does not persist summary state on either contracts or receipts

## Exact Blocker Resolved Here

The last blocker before runtime was:

- how to aggregate honestly when mapped receipt lines may still have `unit_cost = null`

This lock resolves that blocker by choosing:

- explicit priced vs unpriced line counts
- priced-only money rollup via `priced_line_cost_total`

This avoids pretending the first projection is a complete payable or invoice-ready total.

## Non-Claims

This lock does not claim:

- receipt header totals
- line-level `amount` or `extended_amount` fields
- contract payment canonicalization
- compensation write semantics
- invoice workflow
- receipt update/correction workflow

## Runtime Readiness After This Lock

After this lock, the next honest story is a narrow runtime slice for:

- `GET /api/zena/projects/{project}/contracts/{contract}/cost-summary`

with:

- read-only contract-child projection only
- priced-only rollup semantics
- explicit incompleteness counts for `unit_cost = null`

## Runtime Follow-up

`S4.5d` is now runtime-proved.

Canonical runtime truth now includes:

- `GET /api/zena/projects/{project}/contracts/{contract}/cost-summary`

What this proof establishes:

- the first read-only summary stays under the canonical contract owner family
- the response shape is limited to `project_id`, `contract_id`, and `summary`
- `summary` is limited to `mapped_receipt_count`, `line_count`, `priced_line_count`, `unpriced_line_count`, and `priced_line_cost_total`
- `priced_line_cost_total` is derived only from mapped receipt lines where `unit_cost` is not `null`
- mapped receipts belonging to other contracts or no contract do not contribute to the projection
- wrong-project access remains `404`, while cross-tenant access stays blocked by the existing contract-owner stack and is currently proved as `403`

What this proof still does not establish:

- header totals persisted anywhere
- line-level `amount` or `extended_amount`
- payment, invoice, or compensation semantics
- any contract-child subresource beyond this one read-only projection
