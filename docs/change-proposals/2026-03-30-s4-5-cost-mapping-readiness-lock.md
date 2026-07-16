# S4.5 Cost Mapping Readiness Lock

Date: 2026-03-30
Status: superseded by follow-up split lock
Story: `S4.5`
Story title: `Cost mapping to Compensation/Contract (minimal)`

## Why this exists

`S4.5` is the next backlog story after `S4.4a`, but its current backlog wording is too thin to support an honest runtime round.

Current repo evidence proves:

- canonical receipt ownership exists only as a header aggregate on `/api/zena/material-receipts`
- canonical receipt checklist proof exists only as a receipt-header child resource on `/api/zena/material-receipts/{receipt}/checklists`
- contract and payment runtime exists only on compatibility `/api/v1/*`

Current repo evidence does not prove:

- any receipt-line aggregate under `/api/zena/material-receipts/{receipt}/...`
- any canonical `/api/zena/*` owner path for contracts, contract payments, or compensation
- any canonical cost summary route that connects procurement receipt data to contract or compensation data

Without a planning lock, a runtime round would have to invent both sides of the mapping.

This proposal is now superseded by:

- `docs/change-proposals/2026-03-30-s4-5-foundation-split-lock.md`

That follow-up resolves the remaining ambiguity by splitting the old single blocked story into two foundation stories plus the later read-only mapping story.

## Current Evidence

### Receipt-side boundary

What runtime proves now:

- `GET /api/zena/material-receipts`
- `POST /api/zena/material-receipts`
- `GET /api/zena/material-receipts/{id}`
- `POST /api/zena/material-receipts/{receipt}/checklists`
- `GET /api/zena/material-receipts/{receipt}/checklists/{checklist}`

What that proves:

- receipt ownership is currently header-only
- checklist ownership is currently receipt-header-child only

What it does not prove:

- receipt lines
- quantity or amount rollups from receipt lines
- any payable or compensable amount derived from receipt data

Execution consequence:

- `material lines aggregate into cost summary` is not runtime-ready because there is no canonical receipt-line owner to aggregate from

### Contract / compensation boundary

What runtime proves now:

- `routes/api.php` exposes `projects/{project}/contracts` and `contracts/{contract}/payments`
- `php artisan route:list` shows those surfaces only on `/api/v1/*`
- `app/Http/Controllers/Api/ContractController.php` and `app/Http/Controllers/Api/ContractPaymentController.php` back those routes
- `tests/Feature/Api/ContractApiTest.php` proves the compatibility routes, not a `/api/zena/*` owner path

What that proves:

- the repo has contract and payment residue that is tenant-safe and test-covered

What it does not prove:

- canonical `/api/zena/contracts*`
- canonical `/api/zena/contracts/{contract}/payments*`
- canonical `/api/zena/compensation*`

Execution consequence:

- `/api/v1/*` cannot be used as owner proof for `S4.5`
- a runtime slice cannot honestly claim canonical contract or compensation mapping yet

## Owner Surface Options

### Option A

Add cost summary directly under receipt header now:

- `/api/zena/material-receipts/{receipt}`
- `/api/zena/material-receipts/{receipt}/cost-summary`

Cons:

- would invent line semantics that do not exist yet
- would imply a mapping target even though no canonical contract/compensation owner path is proved

### Option B

Pause runtime and lock readiness/dependencies first.

Pros:

- respects current evidence boundary
- avoids inventing receipt-line, payment, or compensation semantics
- keeps `S4.5` available for a future narrow runtime slice once both sides of the mapping are real

## Locked Direction

Choose Option B.

`S4.5` is not runtime-ready today.

The next honest contract for this story is:

- docs-only readiness lock now
- runtime only after both prerequisites are independently proved:
  - a canonical receipt-line aggregate under `/api/zena/material-receipts/{receipt}/...`
  - a canonical contract or compensation owner path under `/api/zena/*`

## Minimal Future Runtime Shape

If those prerequisites become ready later, the smallest safe first slice should be:

- read-only cost summary only
- canonical owner path only
- no invoice workflow
- no payment execution workflow
- no compensation CRUD
- no receipt status side effects

## Deferred / UNKNOWN

Deferred:

- exact receipt-line persistence shape
- whether the future summary belongs under receipt, contract, or another canonical aggregate
- invoice/payment workflow semantics
- compensation write semantics

UNKNOWN:

- the first canonical `/api/zena/*` owner family for contract/compensation mapping
- the exact fields of the future cost summary payload
