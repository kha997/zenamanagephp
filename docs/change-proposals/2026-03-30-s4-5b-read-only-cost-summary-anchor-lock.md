# S4.5b Read-Only Cost Summary Anchor Lock

Date: 2026-03-30
Status: superseded by split lock
Story: `S4.5b`
Story title: `Receipt-to-contract cost summary mapping (read-only)`

## Why this exists

`S4.5` and `S4.5a` are now runtime-proved, so the old `S4.5b` looked like the next roadmap story.

That does not automatically make `S4.5b` runtime-ready.

The repo now proves:

- canonical receipt header ownership on `/api/zena/material-receipts`
- canonical receipt-line ownership on `/api/zena/material-receipts/{receipt}/lines`
- canonical project-contract ownership on `/api/zena/projects/{project}/contracts`

The repo still does not prove:

- any canonical link from receipt or receipt line to a contract
- any receipt-line unit cost, unit price, extended amount, or other monetary basis
- any existing read-only cost summary route on the canonical contract owner path

Without those pieces, a runtime round would have to invent both the mapping key and the money semantics.

This planning lock is now superseded by:

- `docs/change-proposals/2026-04-03-s4-5b-split-lock.md`

That follow-up concludes the blocker set is not one mixed gap but two independent foundation concerns:

- mapping key convergence
- first monetary basis convergence

## Locked Owner Anchor

The future read-only summary must attach to the canonical finance-side contract owner.

Locked anchor:

- `/api/zena/projects/{project}/contracts/{contract}/...`

Why:

- `S4.5a` already proved the canonical finance-side owner family there
- the split lock already chose contract ownership rather than receipt ownership as the honest business anchor
- putting the summary under receipt would blur procurement facts with finance projection ownership

What is not locked yet:

- the exact tail segment after `{contract}`

Examples that remain only candidates, not proof:

- `/cost-summary`
- `/receipt-cost-summary`
- `/procurement-cost-summary`

## Minimal Payload Candidate

Only a very small candidate can be locked today.

The future response should be:

- read-only
- contract-scoped
- derived from canonical receipt-line inputs
- explicit about projection basis rather than pretending to be payment or invoice workflow state

The only safe payload candidate today is an envelope-level projection with:

- contract identity coming from the canonical route params
- project identity implied by the canonical route params
- a read-only summary body derived from receipt lines

What cannot be locked honestly yet:

- exact aggregate money fields
- exact source-trace fields
- whether the response needs line-level rollup buckets or only a top-level total

Reason:

- current receipt-line proof exposes only `material_id`, `quantity_received`, and `notes`
- current contract proof exposes contract CRUD, not receipt mapping or payable amount semantics

## Exact Blockers Before Runtime

Two blockers remain:

1. mapping key blocker

- no canonical field or relation currently proves which contract a material receipt or receipt line maps to
- no `contract_id` exists on `material_receipts`
- no `contract_id` exists on `material_receipt_lines`
- no canonical join/projection route proves an alternative mapping key

2. monetary basis blocker

- no unit cost or unit price exists on the proved receipt-line contract
- no extended amount is persisted or derived on the proved receipt-line contract
- contract `total_value` alone is not enough to build a receipt-to-contract cost summary honestly

## Locked Direction

Choose docs-only planning adjustment.

`S4.5b` is not runtime-ready yet.

## Non-Claims

This lock does not claim:

- canonical contract payments on `/api/zena/*`
- invoice workflow
- payment execution workflow
- compensation write semantics
- receipt workflow/status changes

## Deferred / UNKNOWN

Deferred:

- exact canonical read-only summary route tail under the contract owner path
- exact payload keys once mapping key and money basis exist

UNKNOWN:

- which future canonical field or relation should map receipt-side facts to a contract
- whether the first honest summary can be top-level-only or must include source breakdown details
