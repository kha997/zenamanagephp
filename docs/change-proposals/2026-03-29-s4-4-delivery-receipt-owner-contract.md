# S4.4 Material Receipt Aggregate Owner Contract

Date: 2026-03-29
Status: runtime proved for the narrowed header-only owner slice
Story: `S4.4`
Story title: `Material receipt aggregate owner`

## Why this exists

`S4.4` backlog wording was too broad for the current locked snapshot.

Current repo evidence proves:

- canonical material master-data ownership exists at `/api/zena/materials`
- canonical vendor master-data ownership exists at `/api/zena/vendors`
- canonical BOQ ownership exists at `/api/zena/boqs`
- canonical submittal package ownership exists at `/api/zena/submittals`
- canonical Document Center ownership exists at `/api/zena/documents`

Current repo evidence does not prove:

- that `MaterialRequest` or `Submittal` owns receipt tracking
- any receipt-scoped checklist contract
- any receipt-line contract
- any receipt document link type

Without this planning split, the next round would have to guess whether receipt ownership and acceptance checklist semantics should stay bundled even though only receipt ownership has a clean candidate owner path today.

## Current Evidence

### Canonical route truth

From `php artisan route:list | grep -E "material|receipt|receipts|delivery|accept|checklist|inspection|purchase|document" || true`:

- canonical route family present: `/api/zena/materials*`
- canonical route family present: `/api/zena/material-receipts*`
- canonical route family present: `/api/zena/documents*`
- canonical route family present: `/api/zena/inspections*`
- no canonical `/api/zena/deliveries*`
- no canonical `/api/zena/receipts*`

What that proves:

- current procurement-adjacent canonical ownership now includes a dedicated receipt header family
- receipt ownership is runtime-proved only on `/api/zena/material-receipts`

### Procurement residue boundary

What exists now:

- `app/Models/MaterialReceipt.php`
- `app/Http/Controllers/Api/MaterialReceiptController.php`
- `database/migrations/2026_03_29_200000_create_material_receipts_table.php`
- `tests/Feature/Api/MaterialReceiptApiTest.php`
- `app/Models/MaterialRequest.php`
- `database/migrations/2025_09_14_110000_create_zena_system_tables.php` creates:
  - `zena_material_requests`
  - `zena_purchase_orders`
- `routes/api_zena.php` exposes only `GET /api/zena/site-engineer/material-requests` via `SiteEngineerDashboardController`

What that evidence proves:

- the repo now contains a dedicated canonical receipt header aggregate with its own table, model, controller, and feature proof
- the repo contains procurement request and purchase-order residue
- the only live procurement-adjacent runtime surface in this area is a dashboard projection

What it does not prove:

- canonical receipt ownership
- receipt line-item persistence
- vendor/material receiving workflow on `/api/zena/*`

Execution consequence:

- keep using only `/api/zena/material-receipts` as the canonical owner proof for `S4.4`
- do not use `MaterialRequest`, dashboard projections, or `zena_purchase_orders.status = received` as canonical proof for `S4.4`

### Checklist boundary

What exists now:

- `S5.1` proved generated checklist execution only on `/api/zena/inspections`
- `InspectionController` owns checklist result sync against `work_instance_steps`
- no procurement receipt route or controller reuses that contract today

What that evidence proves:

- the repo has one canonical checklist execution pattern
- that pattern is currently owned by inspections, not procurement receipt

What it does not prove:

- that `S4.4` should reuse inspection ownership directly
- that a receipt checklist already exists on a procurement owner path

Planning consequence:

- `S4.4` must stop at receipt aggregate ownership
- acceptance checklist semantics move to follow-up `S4.4a` after receipt ownership exists

### Evidence boundary

What exists now:

- Document Center owns canonical file CRUD, versioning, and entity linkage on `/api/zena/documents`
- `S4.3` already locked package file ownership away from `Submittal`

What that evidence proves:

- if `S4.4` needs proof-bearing documents, those artifacts should stay in Document Center

What it does not prove:

- exact receipt entity type
- whether delivery evidence should attach to a receipt header, receipt line, or another procurement entity

Planning consequence:

- keep file/evidence ownership with Document Center
- do not invent a second file owner under receipt

## Owner Surface Options

### Option A

Reuse `MaterialRequest` as the owner:

- `/api/zena/material-requests`

Pros:

- procurement-adjacent residue exists already

Cons:

- current live route is only a site-engineer dashboard projection
- request workflow is not the same thing as receipt confirmation
- would merge owner semantics that are not proved

### Option B

Reuse `Submittal` as the owner:

- `/api/zena/submittals`

Pros:

- canonical procurement-adjacent owner path already exists

Cons:

- `S4.3` already locked submittals as package workflow only
- would mix package approvals with physical receipt semantics

### Option C

Create a dedicated receipt owner family:

- `/api/zena/material-receipts`

Pros:

- cleanly separates receipt confirmation from requests, BOQs, submittals, and files
- allows later checklist or evidence layering without changing existing owners
- matches the narrowed story intent directly

Cons:

- requires fresh runtime implementation in a later round
- exact linkage to BOQ lines, vendors, or purchase orders is still not proved

## Recommended Owner Contract

Best current planning candidate remains Option C:

- candidate canonical receipt owner surface: `/api/zena/material-receipts`
- inferred aggregate owner: a dedicated material-receipt header aggregate, separate from `MaterialRequest`, `Submittal`, BOQ, and purchase-order residue
- Document Center remains the canonical evidence/file owner surface
- checklist semantics move to follow-up `S4.4a`

Reason:

- this is the smallest clean owner boundary that does not reopen `MaterialRequest`, overload `Submittal`, or duplicate file ownership already proved in Document Center
- current evidence is still not strong enough to lock receipt-line ownership or document link type under that owner

## Minimal S4.4 Story Shape

Current slice candidates, ordered from narrowest to broadest:

### Option 1

Header-only receipt registry on `/api/zena/material-receipts`:

- create/list/show receipt headers only
- no receipt-line ownership claim yet
- no status workflow claim yet
- no document-link entity type locked yet
- proved minimal fields: `id`, `project_id`, optional `vendor_id`, `receipt_number`, `receipt_date`

### Option 2

Header plus receipt lines:

- would need a proved receipt-line persistence contract
- current repo has no receipt-line schema or model residue

### Option 3

Header plus Document Center evidence linkage:

- would need a proved receipt entity type in `documents.linked_entity_type`
- current canonical link targets are only `task`, `component`, `cr`, and `submittal`

Current runtime consequence:

- Option 1 is now the proved `S4.4` contract
- checklist semantics are no longer part of `S4.4`; they move to `S4.4a`
- the repo still lacks enough evidence to widen beyond header-only ownership

Do not include in the first slice:

- receipt lines
- purchase-order lifecycle ownership
- broad delivery scheduling or logistics tracking
- reviewer/approver matrix
- notification fan-out
- compensation or contract-cost mapping
- any invented receipt status chain
- any invented `linked_entity_type`

## Dependency / Readiness

Ready inputs:

- `S4.1` material and vendor owners are locked
- `S4.2` BOQ owner is locked
- `S4.3` submittal owner and Document Center split are locked
- Document Center can carry evidence files

Missing inputs:

- no canonical receipt document-link target in Document Center
- no receipt-line persistence contract
- no proved receipt workflow/status chain

Execution verdict:

- `S4.4` is now runtime-proved as a header-only receipt owner slice
- the checklist split is now locked: `S4.4` owns receipt aggregate only and `S4.4a` owns any later acceptance checklist semantics

## Deferred / UNKNOWN

Deferred:

- exact linkage to BOQ header vs BOQ line item
- exact linkage to vendor or purchase order residue
- notification semantics
- delivery scheduling semantics before receipt
- compensation linkage
- acceptance checklist semantics

UNKNOWN until runtime evidence exists:

- receipt status field and allowed first-slice state set
- whether receipt lines belong to the first runtime slice
- whether the first runtime slice should avoid status workflow entirely
- whether receipt should be nested under BOQ in read models
- whether receipt evidence attaches to header or a later child entity
- exact `linked_entity_type` name for receipt evidence in Document Center

## Verify Target For The Next Runtime Round

- `php artisan route:list | grep -E "material|receipt|receipts|delivery|accept|checklist|inspection|purchase|document" || true`
- `rg -n "MaterialRequest|purchase_orders|delivery|receipt|checklist|linked_entity_type" app tests database docs routes`
- targeted canonical tests for `/api/zena/material-receipts*`
- no proof depending on `/api/v1/*`

## Verdict

`S4.4` is now proved at the narrowed receipt-owner-first slice on `/api/zena/material-receipts`, limited to header aggregate create/list/show with required project context, optional same-tenant vendor linkage, and no claimed status, lines, checklist, or document-link semantics. Checklist semantics remain explicitly deferred to `S4.4a`.
