# S4.5b Split Lock

Date: 2026-04-03
Status: split lock
Stories: `S4.5b`, `S4.5c`, `S4.5d`
Original story title: `Receipt-to-contract cost summary mapping (read-only)`

## Why this exists

The prior planning lock correctly fixed the owner anchor under the canonical contract family.

It did not yet answer whether the remaining gap was:

- one mixed blocker inside a single story
- or two independent blockers that should be separated before runtime

Current repo evidence supports a split.

## Question 1: Is there a canonically defensible mapping key already?

No.

Current canonical receipt proof exposes:

- `material_receipts.tenant_id`
- `material_receipts.project_id`
- optional `material_receipts.vendor_id`
- `material_receipts.receipt_number`
- `material_receipt_lines.material_id`
- `material_receipt_lines.quantity_received`
- optional `material_receipt_lines.notes`

Current canonical contract proof exposes:

- `contracts.id`
- `contracts.project_id`
- `contracts.code`
- `contracts.contract_number`

What current evidence does not prove:

- `contract_id` on `material_receipts`
- `contract_id` on `material_receipt_lines`
- any receipt-contract pivot
- any canonical route/query surface that resolves receipt facts to a contract
- any runtime rule that vendor, material, or receipt number can identify one contract

## Question 2: Is `project_id` by itself enough as the first mapping key?

No.

Evidence:

- canonical owner family is plural: `/api/zena/projects/{project}/contracts`
- `contracts` table indexes `project_id` but does not make it unique
- canonical contract CRUD already supports multiple contracts per project

Planning consequence:

- `project_id` is a shared scope, not a defensible receipt-to-contract mapping key

## Question 3: Is there a first monetary basis already proved?

No canonical one.

Current canonical receipt-line runtime proves only:

- `material_id`
- `quantity_received`
- `notes`

Current repo residue does contain money-like fields elsewhere:

- `contracts.total_value`
- `contract_payments.amount`
- `tasks_compensation.snapshot_contract_value`
- `tasks_compensation` percent-based compensation calculations

Why those do not qualify:

- `contracts.total_value` is contract-level, not receipt-line-derived
- `contract_payments.amount` is payment-runtime residue and compatibility-only
- `tasks_compensation` is task/contract projection residue, not canonical receipt-side money proof

Planning consequence:

- the first monetary basis is a separate missing concern from mapping

## Question 4: Are mapping and monetary basis independent enough to split?

Yes.

Why:

- a mapping key can be proved without proving money semantics
- a monetary basis can be proved without proving receipt-to-contract attachment
- neither blocker resolves the other automatically

## Locked Decision

Choose split.

The roadmap should become:

- `S4.5b` = receipt-to-contract mapping key convergence
- `S4.5c` = first monetary basis for receipt cost summaries
- `S4.5d` = receipt-to-contract cost summary mapping (read-only)

## Ordering

1. `S4.5b` receipt-to-contract mapping key convergence
2. `S4.5c` first monetary basis for receipt cost summaries
3. `S4.5d` receipt-to-contract cost summary mapping (read-only)

Why this order:

- mapping key is the clearer owner-boundary blocker because owner anchor is already locked under contracts
- monetary basis is still necessary, but it does not answer which contract a receipt fact belongs to
- the final read-only projection still depends on both

## Smallest Future Runtime Target

The smallest future runtime-capable story is `S4.5b`.

What it should try to prove:

- one canonical mapping contract under `/api/zena/*`
- attached to the canonical project-contract owner family
- without money totals

What still remains `UNKNOWN` even after this split:

- exact route tail under `/api/zena/projects/{project}/contracts/{contract}/...`
- exact payload contract for the mapping-only slice

## Non-Claims

This split does not claim:

- canonical payments on `/api/zena/*`
- invoice workflow
- payment execution workflow
- compensation write semantics
- receipt status workflow

## Deferred / UNKNOWN

Deferred:

- exact route tail for mapping-only runtime
- exact first monetary field for the money-basis story

UNKNOWN:

- whether the eventual mapping key should be a direct foreign key, pivot, or another canonical reference contract
- whether the eventual read-only projection should expose only totals or a deeper source breakdown
