# Procurement Receiving + Contract Cost Pilot Smoke Proof Lock

Date: 2026-04-15
Type: bounded implementation lock
Status: accepted

## Purpose

Add one focused end-to-end smoke proof for the already locked procurement receiving + contract cost read-side pilot vertical on canonical `/api/zena/*`.

This round does not expand pilot scope or business semantics. It proves the existing pilot journey can run end to end through the already accepted owner families and read-side projections.

## Exact Smoke Flow Proved

The smoke proof now covers this canonical pilot journey:

1. seed one tenant, one canonical pilot operator user, and one project context
2. create material on `/api/zena/materials`
3. create vendor on `/api/zena/vendors`
4. create project-scoped contract on `/api/zena/projects/{project}/contracts`
5. create material request on `/api/zena/material-requests`
6. submit material request
7. approve material request
8. create material receipt on `/api/zena/material-receipts`
9. persist soft header linkage to both `material_request_id` and `contract_id`
10. create receipt checklist on `/api/zena/material-receipts/{receipt}/checklists`
11. create receipt line on `/api/zena/material-receipts/{receipt}/lines`
12. read request-owned receipts projection
13. read receipt-owned linked request projection
14. read contract mapped receipts projection
15. read contract cost summary projection

## Root Cause Found During Smoke Authoring

No new runtime semantic gap was found.

The only failure discovered during smoke authoring was test setup drift:

- `zena_material_requests.project_id` is backed by a foreign key to `zena_projects`
- the first smoke draft created only the base `projects` record
- existing canonical material-request tests already prove the correct fixture pattern by seeding a paired `zena_projects` mirror row

The smoke proof now follows that existing fixture contract. This is a smoke setup correction, not a runtime behavior change.

## Runtime Patch Boundary

Runtime patches in this round: none.

The final accepted smoke proof required:

- one new focused feature test only
- no controller changes
- no route changes
- no policy changes
- no payload changes
- no semantic expansion

## Guardrails Reconfirmed

- no inventory semantics
- no PO matching
- no invoice, compensation, or payment execution semantics
- no quantity reconciliation between request and receipt
- no line-level request↔receipt linkage
- no `/api/v1/*` promotion to canonical pilot truth
- no dashboard or UI claim expansion

## Verify Lane

Mandatory route verification:

```bash
php artisan route:list --path=api/zena
```

Mandatory smoke command requested for this round:

```bash
php ./vendor/bin/phpunit \
  tests/Feature/Api/ProcurementReceivingContractCostPilotSmokeTest.php \
  tests/Feature/Api/MaterialRequestApiTest.php \
  tests/Feature/Api/MaterialReceiptApiTest.php \
  tests/Feature/Api/MaterialReceiptLineApiTest.php \
  tests/Feature/Api/MaterialReceiptChecklistApiTest.php \
  tests/Feature/Api/ContractApiTest.php \
  tests/Feature/Api/ContractApiHardeningTest.php \
  tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php
```

Observed repo quirk:

- in this repo's current PHPUnit CLI behavior, passing multiple positional file paths only executed the first file
- the round therefore keeps the exact requested command as evidence, and also verifies each targeted suite individually to obtain real per-suite proof

## Evidence Summary

- `tests/Feature/Api/ProcurementReceivingContractCostPilotSmokeTest.php` now proves the canonical pilot journey on `/api/zena/*`
- existing request, receipt, checklist, line, contract, hardening, and route-invariant suites remain green after the smoke proof addition
- route table remains unchanged on canonical `/api/zena/*`

## Deferred

- any runtime refactor just to make the smoke more elegant
- any role-separation claim beyond permission-gated canonical behavior
- any new procurement, finance, or dashboard frontier beyond the already locked pilot pack
