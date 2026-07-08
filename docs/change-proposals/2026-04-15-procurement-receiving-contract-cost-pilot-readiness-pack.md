# Procurement Receiving + Contract Cost Read-Side Pilot Readiness Pack

Date: 2026-04-15
Type: bounded docs-first pilot-readiness lock
Status: accepted for pilot packaging only

## Purpose

This memo defines a narrow pilot pack for the strongest runtime-proved procurement slice currently mounted on the canonical `/api/zena/*` surface.

This is not a full procurement-suite promise. It is a constrained pilot for:

- receiving basic material deliveries against project context
- optionally linking a receipt to a bounded material request
- optionally mapping a receipt to a project-scoped contract
- reading a partial, priced-only contract cost summary

## Pilot Target

Pilot the smallest production-facing workflow that is already runtime-proved end to end:

- master data setup for materials and vendors
- bounded material request header workflow
- receipt header creation and correction before lines exist
- receipt checklist capture
- receipt line capture with optional unit cost
- project-scoped contract lookup
- contract-child mapped receipt projection
- contract-child cost summary read-side

## Exact In-Scope Surfaces

Canonical owner families and child surfaces used by this pilot:

- `GET|POST|PUT|DELETE /api/zena/materials`
- `GET|POST|PUT|DELETE /api/zena/vendors`
- `GET|POST /api/zena/material-requests`
- `GET|PUT /api/zena/material-requests/{id}`
- `POST /api/zena/material-requests/{id}/submit`
- `POST /api/zena/material-requests/{id}/approve`
- `POST /api/zena/material-requests/{id}/reject`
- `POST /api/zena/material-requests/{id}/fulfill`
- `GET /api/zena/material-requests/{id}/receipts`
- `GET|POST /api/zena/material-receipts`
- `GET|PUT /api/zena/material-receipts/{id}`
- `GET /api/zena/material-receipts/{id}/material-request`
- `POST /api/zena/material-receipts/{receipt}/checklists`
- `GET /api/zena/material-receipts/{receipt}/checklists/{checklist}`
- `GET|POST /api/zena/material-receipts/{receipt}/lines`
- `GET|PUT /api/zena/material-receipts/{receipt}/lines/{line}`
- `GET|POST /api/zena/projects/{project}/contracts`
- `GET|PUT|DELETE /api/zena/projects/{project}/contracts/{contract}`
- `GET /api/zena/projects/{project}/contracts/{contract}/material-receipts`
- `GET /api/zena/projects/{project}/contracts/{contract}/cost-summary`

Runtime-proved semantics inside scope:

- material requests are bounded request headers with the existing `draft -> submitted -> approved/rejected -> fulfilled` status machine only
- receipt creation may optionally carry `vendor_id`, `material_request_id`, and `contract_id` when tenant/project constraints pass
- receipt header correction is limited to existing proved semantics before any lines exist
- receipt checklist is a self-contained create/show snapshot under the receipt owner family
- receipt lines support bounded create/show/list and correction of `unit_cost` and `notes` only
- contract mapped receipts are a read-side projection of receipts linked by `contract_id`
- contract cost summary is a read-only partial rollup of mapped receipt lines and is priced-only, not financial completion

## Exact Out-of-Scope / Deferred

The pilot does not promise or imply:

- inventory, stock ledger, warehouse, or on-hand balance semantics
- purchase orders, PO matching, or supplier order workflow
- invoice, compensation, payment execution, or payment reconciliation semantics
- contract write-side expansion beyond the already mounted CRUD family
- contract payment runtime even though a canonical owner family exists
- dashboard parity or any UI completeness claim
- receipt evidence/documents/attachments workflow
- line-level contract linkage, contract split allocation, or receipt-line remapping semantics
- any new request↔receipt semantics beyond the current nullable linkage and read projections
- quantity received reinterpretation or any quantity normalization layer
- full procurement analytics, reporting, or BI promises

## Canonical Owner Truth For Pilot

Canonical owner truth used in this pack:

- Materials: `/api/zena/materials`
- Vendors: `/api/zena/vendors`
- Material Requests: `/api/zena/material-requests`
- Material Receipts: `/api/zena/material-receipts`
- Receipt Checklist: `/api/zena/material-receipts/{receipt}/checklists`
- Receipt Lines: `/api/zena/material-receipts/{receipt}/lines`
- Project-Scoped Contracts: `/api/zena/projects/{project}/contracts`
- Contract Mapped Receipts: `/api/zena/projects/{project}/contracts/{contract}/material-receipts`
- Contract Cost Summary: `/api/zena/projects/{project}/contracts/{contract}/cost-summary`

Compatibility aliases are not canonical pilot truth:

- `/api/v1/projects/{project}/contracts`
- `/api/v1/contracts/{contract}/payments`

Those compatibility surfaces may still exist for older callers, but this pilot pack must describe and validate the canonical `/api/zena/*` runtime only.

## Runtime-Proved vs Docs-Only

Runtime-proved in tests and route table:

- all canonical route families listed in this memo are mounted on `/api/zena/*`
- material request authorization is explicitly policy-hardened on the owner family
- non-business probe/debug surfaces are unmounted by default from production-facing runtime
- contract ownership docs were reconciled to the canonical project-scoped `/api/zena/*` owner family

Docs/planning/decision lock only:

- this pilot-readiness pack itself
- operator framing, preflight checklist, guardrails, and stop conditions in this memo

## Actor / Operator Flows

Minimal pilot flows expected to work without semantic expansion:

1. Procurement admin seeds materials and vendors.
2. Project user creates a bounded material request header, then submits it.
3. Approver approves or rejects the request using the existing status machine.
4. Receiving operator creates a material receipt for the project and may attach `vendor_id`, `material_request_id`, and `contract_id` if the current runtime constraints pass.
5. Receiving operator records checklist findings on the receipt.
6. Receiving operator records receipt lines and may later correct only `unit_cost` and `notes`.
7. Contract read-side operator reviews contract-child mapped receipts.
8. Contract read-side operator reviews the contract cost summary as a partial priced-line projection, not as invoice or payment truth.

## Preflight Checklist Before Pilot

- confirm runtime route table still mounts the canonical `/api/zena/*` families listed in this memo
- confirm no test/debug/probe surfaces are mounted by default in the target environment
- confirm material request owner-family policy hardening remains registered and enforced
- confirm tenant/project seed data exists for materials, vendors, projects, contracts, and pilot users
- confirm pilot users only rely on the bounded roles/permissions already proved by tests
- confirm operators understand that contract cost summary is partial and priced-line only
- confirm operators understand that `/api/v1/*` contract/payment routes are compatibility only and are not the pilot contract

## Verify Lane Before Deploy / Pilot

Run exactly this narrow evidence-first lane:

```bash
php artisan route:list --path=api/zena
php ./vendor/bin/phpunit \
  tests/Feature/Api/MaterialRequestApiTest.php \
  tests/Feature/Api/MaterialReceiptApiTest.php \
  tests/Feature/Api/MaterialReceiptLineApiTest.php \
  tests/Feature/Api/MaterialReceiptChecklistApiTest.php \
  tests/Feature/Api/ContractApiTest.php \
  tests/Feature/Api/ContractApiHardeningTest.php \
  tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php
```

## Known Risks / Guardrails

- do not sell contract cost summary as full contract financial truth; it is a partial read-side projection only
- do not describe material request linkage as inventory reservation, PO fulfillment, or supplier settlement
- do not rely on compatibility `/api/v1/*` routes as pilot owner truth
- do not imply dashboard parity, mobile parity, or non-proved UI coverage
- do not attach new receipt workflow semantics to checklist or line correction flows
- do not treat payment runtime availability as in-scope for this pilot pack

## Rollback / Stop Conditions

Stop or pause the pilot if any of the following become false:

- canonical `/api/zena/*` procurement receiving and contract read-side routes no longer match this memo
- required verify lane tests fail
- production-facing runtime re-exposes debug/probe surfaces
- operators need invoice, payment, PO, inventory, or line-level allocation semantics that this pilot explicitly excludes
- users start depending on compatibility `/api/v1/*` behavior as if it were canonical scope

If a stop condition triggers, the response is documentation rollback of pilot claims and re-scoping, not ad-hoc semantic expansion under the same pilot label.
