# Procurement Receiving + Contract Cost Dry-Run Readiness Pack

Date: 2026-04-15
Type: bounded docs-first dry-run lock
Status: accepted for controlled internal dry-run only

## Pilot Objective

Run one controlled internal dry-run proving that the already locked procurement receiving + contract cost read-side pilot can be exercised end to end on canonical `/api/zena/*` surfaces by internal operators without expanding business semantics.

This is not a production rollout promise.

## Environment Assumptions

The dry-run pack assumes:

- one staging-like or pilot-like internal environment with canonical `/api/zena/*` routes mounted
- runtime business code matches the currently proved pilot vertical
- non-business debug/probe surfaces remain unmounted by default
- operator accounts, tenant context, and seeded project data are available before the session starts
- internal participants understand that this pack validates bounded API/runtime behavior, not UI completeness or broad operational readiness

## Required Runtime Flags / Forbidden Runtime Surfaces

Required runtime intent:

- canonical pilot truth must remain on `/api/zena/*`
- the preflight script `scripts/pilot/procurement_receiving_contract_cost_preflight.sh` must pass immediately before the dry-run

Forbidden runtime surfaces for this dry-run:

- any reliance on `/api/v1/*` as canonical pilot truth
- any mounted test/debug/probe surfaces such as app probes, `/_debug/*`, `/_ignition/*`, or `/_dusk/*`
- any unreviewed environment-only overrides that change route registration or policy behavior during the dry-run

## Exact Actor Matrix

Minimum internal actor matrix for the controlled dry-run:

- Dev lead
  - owns go/no-go for preflight pass, confirms environment matches this pack, and stops the dry-run if scope drift appears
- QA operator
  - executes the step-by-step dry-run checklist and captures evidence at each checkpoint
- Procurement operator
  - performs material/vendor seed and bounded material-request + receipt actions within current runtime proof
- Approver
  - performs bounded material-request approval or rejection using the existing status machine only
- Contract read-side reviewer
  - verifies contract mapped receipts and contract cost summary as read-side outputs only

One person may hold multiple roles in a small internal dry-run, but every responsibility above must still be explicitly covered.

## Operator Dry-Run Checklist

Run these steps in order:

1. Confirm the environment is internal-only and no debug/probe surfaces are exposed.
2. Run the mandatory preflight command and stop immediately on any failure.
3. Confirm one tenant, one project, and pilot users are available for the dry-run.
4. Create one material through the canonical materials owner family.
5. Create one vendor through the canonical vendors owner family.
6. Create one project-scoped contract through the canonical contracts owner family.
7. Create one bounded material request header.
8. Submit the material request.
9. Approve the material request.
10. Create one material receipt using only current proved header semantics.
11. Include `material_request_id` as a soft header reference only.
12. Include `contract_id` only as the already proved receipt↔contract header mapping.
13. Create one receipt checklist snapshot.
14. Create one receipt line with bounded `quantity_received` and optional `unit_cost`.
15. Read back request-owned receipts projection and confirm the linked receipt appears.
16. Read back receipt-owned linked request projection and confirm the linked request appears.
17. Read back contract mapped receipts projection and confirm the mapped receipt appears.
18. Read back contract cost summary and confirm it is treated as partial priced-line read-side output only.
19. Record pass/fail outcome and attach the evidence pack before ending the session.

## Mandatory Preflight Command

Run this command from repo root immediately before the dry-run:

```bash
bash scripts/pilot/procurement_receiving_contract_cost_preflight.sh
```

Do not replace it with one multi-file positional PHPUnit command in this repo.

## Pass / Fail Criteria

Pass criteria:

- preflight script passes in full
- all dry-run steps complete on canonical `/api/zena/*` surfaces only
- material request flow stays within bounded `draft -> submitted -> approved|rejected -> fulfilled` semantics
- receipt linkage remains soft header reference only
- contract mapped receipts projection returns the expected mapped receipt
- contract cost summary returns the expected partial priced-only read-side values
- no participant needs inventory, PO, invoice, payment execution, reconciliation, or dashboard semantics to complete the dry-run

Fail criteria:

- preflight script fails
- any actor needs `/api/v1/*` compatibility routes to finish the scenario
- any participant expects line-level linkage, quantity reconciliation, payment execution, or dashboard semantics not proved in the pilot
- route registration, payload shape, or permission behavior drifts from the current canonical pilot truth

## Stop Conditions / Rollback Conditions

Stop immediately if:

- the preflight script fails
- canonical `/api/zena/*` pilot routes are missing or altered
- debug/probe surfaces appear in the runtime route table
- operators need business semantics that this pack explicitly excludes
- evidence capture is incomplete or contradictory

Rollback condition at the documentation/process level:

- if a stop condition occurs, roll back the dry-run claim itself and re-scope the pack; do not patch semantics or re-label compatibility behavior as canonical during the same dry-run window

## Evidence Capture Checklist

Capture and store:

- the full console output of the preflight script
- the IDs for created material, vendor, project-scoped contract, material request, receipt, checklist, and receipt line
- the request-owned receipts projection response showing the linked receipt
- the receipt-owned linked request projection response showing the linked request
- the contract mapped receipts projection response
- the contract cost summary response
- one short operator note stating whether any out-of-scope semantic need was encountered
- one short dev-lead note stating pass/fail and whether the environment stayed inside the locked pilot boundary

## Known Limitations / Out Of Scope

This dry-run pack does not prove:

- inventory or stock semantics
- purchase order or PO matching semantics
- invoice, payment execution, payment reconciliation, or compensation semantics
- request↔receipt quantity reconciliation
- line-level request↔receipt linkage
- line-level contract allocation or remapping
- dashboard parity
- UI parity
- production rollout readiness beyond the current pilot evidence

`/api/v1/*` remains compatibility only and must not be described as pilot truth in this pack.
