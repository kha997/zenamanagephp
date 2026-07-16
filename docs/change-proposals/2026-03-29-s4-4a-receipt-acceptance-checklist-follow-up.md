# S4.4a Receipt Acceptance Checklist Follow-up Contract

Date: 2026-03-30
Status: runtime proved for the narrowed receipt-header checklist child contract
Story: `S4.4a`
Story title: `Receipt acceptance checklist semantics`

## Why this exists

`S4.4` is now split because current evidence does not justify keeping receipt ownership and acceptance checklist semantics inside one runtime-ready story.

Current repo evidence proves:

- canonical checklist execution exists on `/api/zena/inspections`
- inspection checklist runtime is backed by `qc_inspections.work_instance_step_id` and `work_instance_steps`
- canonical Document Center ownership exists on `/api/zena/documents`
- canonical receipt header ownership now exists on `/api/zena/material-receipts`

Current repo evidence now proves:

- a receipt-scoped checklist runtime contract exists under `/api/zena/material-receipts/{receipt}/checklists`
- the first receipt checklist slice is receipt-header-scoped only
- the first receipt checklist payload is a self-contained snapshot using `acceptance_summary` and `items[*].item_key|label|result|notes?`

Current repo evidence still does not prove:

- any receipt-scoped `documents.linked_entity_type`
- any receipt-line checklist granularity or acceptance workflow gate

Planning consequence:

- do not let checklist wording block the narrow receipt-owner runtime slice
- do not overclaim inspection-owned checklist proof as procurement receipt proof

## Locked Boundary

This follow-up story depended on a prior runtime slice:

- `S4.4` has now proved a dedicated receipt header aggregate on `/api/zena/material-receipts`

That prerequisite is now satisfied and the narrowed runtime slice is now implemented.

What still remains `UNKNOWN` before runtime:

- whether later rounds should keep multiple persisted checklist rows per receipt or collapse into another projection shape
- whether any route beyond create/show should ever be canonical for this aggregate

Until that happens, the following remain `UNKNOWN`:

- whether checklist decisions affect any receipt status workflow
- whether Document Center should use a dedicated receipt `linked_entity_type`
- whether receipt checklist reuses inspection mechanics directly or only borrows parts of them

## Evidence-Based Owner Boundary

### What the repo proves today

- `material_receipts` is a header-only aggregate with only `tenant_id`, `project_id`, optional `vendor_id`, `receipt_number`, and `receipt_date`
- `MaterialReceiptController` proves only `index`, `store`, and `show` on `/api/zena/material-receipts`
- `MaterialReceiptApiTest` proves header create/list/show only
- there is no receipt-line model, migration, controller, route, or feature test

### What that means

Evidence does not prove that receipt lines will never matter later.

It does prove that the only honest first checklist owner boundary available now is the receipt header, because that is the only canonical receipt aggregate that exists at runtime.

Therefore this round locks the first checklist owner boundary to:

- receipt header only
- under the canonical receipt owner family `/api/zena/material-receipts`
- no claim of receipt-line checklist ownership in `S4.4a`

## Owner Surface Options

### Option A

Attach checklist directly onto the receipt header payload:

- extend `POST /api/zena/material-receipts`
- extend `GET /api/zena/material-receipts/{id}`

Pros:

- smallest route count

Cons:

- would reopen the already locked `S4.4` header contract
- mixes checklist semantics into a story that was explicitly proved as header-only aggregate ownership
- makes future checklist iteration harder to isolate

### Option B

Create a child checklist contract under the receipt owner:

- `/api/zena/material-receipts/{receipt}/checklists`

Pros:

- preserves the locked `S4.4` header contract
- keeps canonical ownership on the receipt path
- leaves room for later receipt-line or evidence semantics without changing the receipt header shape

Cons:

- requires a new child aggregate in a later runtime round

## Locked Direction

Best current direction is Option B.

The canonical future checklist surface should be a child route of the canonical receipt owner:

- `POST /api/zena/material-receipts/{receipt}/checklists`
- `GET /api/zena/material-receipts/{receipt}/checklists/{checklist}`

This round does not lock a list route as required proof.

Why this is the best fit:

- it keeps checklist ownership under the receipt owner path instead of inventing a separate aggregate
- it avoids mutating the already locked `S4.4` header-only contract
- it does not require any claim about receipt lines that the repo cannot prove yet

## Minimal Semantics That Can Be Locked Now

Current evidence is not enough to lock:

- a checklist template/reference source for receipts
- receipt evidence link type
- workflow/status side effects

Current evidence is enough to lock one narrow checklist meaning:

- a self-contained receipt-header acceptance checklist snapshot
- item-level pass/fail results as the minimum semantics that make this honestly a checklist
- optional header-level acceptance summary

Inference from current repo patterns:

- the only existing checklist runtime semantics in the repo are item-result semantics under inspections, not summary-only semantics
- therefore a summary-only receipt payload would be too weak to honestly prove `checklist semantics`

What stays outside this story:

- receipt-line acceptance
- checklist-template reuse claims
- receipt status gates
- document linkage
- notifications
- compensation linkage

## Non-Claims

This follow-up does not currently claim:

- inspection ownership of procurement acceptance
- full receipt workflow semantics
- notifications
- compensation linkage
- purchase-order closure semantics

## Runtime-Proved Slice

This round proves:

- `POST /api/zena/material-receipts/{receipt}/checklists`
- `GET /api/zena/material-receipts/{receipt}/checklists/{checklist}`
- create/show only
- a dedicated `material_receipt_checklists` table with ULID primary key, tenant scope, `project_id`, `material_receipt_id`, optional `acceptance_summary`, and `items` JSON
- item-level snapshot semantics with `item_key`, `label`, `result`, and optional `notes`

Focused runtime proof lives in:

- `app/Models/MaterialReceiptChecklist.php`
- `app/Policies/MaterialReceiptChecklistPolicy.php`
- `app/Http/Controllers/Api/MaterialReceiptChecklistController.php`
- `database/migrations/2026_03_30_080000_create_material_receipt_checklists_table.php`
- `tests/Feature/Api/MaterialReceiptChecklistApiTest.php`

What this proof does not claim:

- list/update/delete routes
- template-reference semantics
- receipt-line acceptance
- workflow/status side effects
- document link type
- notifications or compensation linkage

## Execution Verdict

`S4.4a` is now runtime-proved at the narrowed child-checklist slice.

Why:

- owner boundary stayed under the canonical receipt header aggregate
- route proof stayed on `/api/zena/material-receipts/{receipt}/checklists`
- payload proof stayed limited to self-contained snapshot semantics
- no broader workflow, evidence, notification, or line-item semantics were claimed
