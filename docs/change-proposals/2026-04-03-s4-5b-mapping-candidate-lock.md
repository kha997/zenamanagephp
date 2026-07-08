# S4.5b Mapping Candidate Lock

Date: 2026-04-03
Status: planning adjustment
Story: `S4.5b`
Story title: `Receipt-to-contract mapping key convergence`

## Why this exists

The split lock already proved that `S4.5b` is the next roadmap story and that it must stay mapping-only.

What it still left too open was:

- the best current mapping-key candidate
- the exact blocker that keeps runtime from starting honestly
- the smallest future runtime proof surface

This review narrows those three items without reopening money semantics or broad finance cleanup.

## Current repo evidence

Canonical receipt header proof currently exposes only:

- `material_receipts.tenant_id`
- `material_receipts.project_id`
- optional `material_receipts.vendor_id`
- `material_receipts.receipt_number`
- `material_receipts.receipt_date`

Canonical receipt-line proof currently exposes only:

- `material_receipt_lines.material_receipt_id`
- `material_receipt_lines.material_id`
- `material_receipt_lines.quantity_received`
- optional `material_receipt_lines.notes`

Canonical contract proof currently exposes only:

- `/api/zena/projects/{project}/contracts`
- `/api/zena/projects/{project}/contracts/{contract}`
- plural contracts per project via `contracts.project_id`

What current evidence still does not prove:

- `contract_id` on `material_receipts`
- `contract_id` on `material_receipt_lines`
- any receipt-contract pivot
- any canonical mapped-receipt child route under contracts

## Best candidate today

The narrowest defensible mapping-key candidate is:

- direct receipt-header `material_receipts.contract_id`

Why this is the best candidate:

- receipt identity already lives on the header aggregate
- receipt lines already inherit parent receipt ownership through `material_receipt_id`
- adding contract linkage per line would duplicate mapping on a child aggregate before any evidence proves a single receipt splits across multiple canonical contracts
- introducing a separate pivot would create a larger owner contract than current evidence requires

This is still only a planning candidate, not runtime proof.

## Exact blocker

`S4.5b` is still not runtime-ready because the repo lacks all of the following:

- persisted receipt-header `contract_id`
- same-tenant same-project validation for that bridge
- receipt model/controller serialization for that bridge
- canonical contract-child route that reads mapped receipts back under the proved contract owner family
- feature-test proof for mapped receipt visibility and anti-enumeration behavior

## Smallest future runtime proof surface

The smallest honest runtime slice should be:

1. persist a nullable receipt-header bridge on `material_receipts.contract_id`
2. validate it against the same tenant and same project as the parent receipt
3. expose only a header-level mapped-receipt projection under the canonical contract owner family

Candidate route:

- `GET /api/zena/projects/{project}/contracts/{contract}/material-receipts`

Candidate proof scope:

- header-only receipt identity
- read-only mapping projection
- wrong-project / wrong-contract / cross-tenant anti-enumeration `404`

What this future runtime slice must not claim:

- line-level contract splits
- unit cost or amount
- cost totals
- payments
- invoices
- compensation write semantics

## Locked direction

Choose one more narrow docs-only adjustment now.

`S4.5b` still needs a planning lock before runtime.

## Deferred / UNKNOWN

Deferred:

- exact create/update surface that will first persist `material_receipts.contract_id`
- whether canonical receipt header create/update can carry that field in the same runtime round as the contract-child read projection

UNKNOWN:

- whether the future mapped-receipt projection needs only list semantics or also contract-child show semantics for one mapped receipt
