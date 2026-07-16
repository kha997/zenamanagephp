# Procurement Receiving + Contract Cost Dry-Run Execution Result Pack

Date: 2026-04-15
Type: bounded docs-only execution/evidence lock
Status: accepted for internal dry-run evidence capture only

## Purpose

Provide one canonical internal execution-result pack so the team can run the controlled dry-run and record results in a structured, comparable way.

This is an internal execution/evidence pack. It is not release sign-off and not a production readiness claim.

## Session Metadata Template

Record this before the session starts:

- Date:
- Start time:
- End time:
- Environment:
- Environment URL/base host:
- Participants:
- Roles covered:
- Tenant used:
- Project used:
- Commit SHA / ref if known:
- Dry-run operator:
- Dev lead approver:
- QA recorder:

## Preflight Result

Record exactly:

- Preflight command run:
  - `bash scripts/pilot/procurement_receiving_contract_cost_preflight.sh`
- Result:
  - `PASS`
  - `FAIL`
- Started at:
- Finished at:
- Evidence reference:
- Notes:

If preflight fails, stop the dry-run and move directly to the issue log plus final outcome section.

## Step-by-Step Execution Log

Use this table during the session.

| Step | Action | Expected Result | Actual Result | Pass/Fail | Evidence Ref | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | Confirm internal environment and no forbidden debug/probe surfaces | Environment matches dry-run pack assumptions |  |  |  |  |
| 2 | Run canonical preflight script | Preflight passes fully |  |  |  |  |
| 3 | Confirm tenant/project/users for session | One usable tenant/project and required actors available |  |  |  |  |
| 4 | Create material on `/api/zena/materials` | Material created on canonical owner path |  |  |  |  |
| 5 | Create vendor on `/api/zena/vendors` | Vendor created on canonical owner path |  |  |  |  |
| 6 | Create contract on `/api/zena/projects/{project}/contracts` | Project-scoped contract created |  |  |  |  |
| 7 | Create material request on `/api/zena/material-requests` | Draft request created with bounded schema |  |  |  |  |
| 8 | Submit material request | Request moves `draft -> submitted` |  |  |  |  |
| 9 | Approve material request | Request moves `submitted -> approved` |  |  |  |  |
| 10 | Create material receipt on `/api/zena/material-receipts` | Receipt created with bounded header semantics |  |  |  |  |
| 11 | Link receipt to request by `material_request_id` | Soft header reference persists only |  |  |  |  |
| 12 | Link receipt to contract by `contract_id` | Header mapping persists only |  |  |  |  |
| 13 | Create receipt checklist | Checklist snapshot created under receipt owner family |  |  |  |  |
| 14 | Create receipt line | Receipt line created with bounded fields only |  |  |  |  |
| 15 | Read request-owned receipts projection | Linked receipt appears under request |  |  |  |  |
| 16 | Read receipt-owned linked request projection | Linked request appears under receipt |  |  |  |  |
| 17 | Read contract mapped receipts projection | Linked receipt appears under contract child projection |  |  |  |  |
| 18 | Read contract cost summary | Partial priced-only summary is returned |  |  |  |  |
| 19 | Confirm no out-of-scope semantic need blocked completion | Session stayed within pilot boundary |  |  |  |  |

## Issue Log

Record each issue found during the session in this form:

| ID | Severity | Summary | Step | Reproducible (Y/N) | Within Pilot Scope or Beyond Scope | Recommended Disposition | Evidence Ref | Owner | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
|  | `critical/high/medium/low` |  |  |  | `within-scope` / `beyond-scope` | `accept` / `fix` / `defer` / `stop` |  |  |  |

Guidance:

- use `stop` if the issue breaks pilot boundary assumptions or blocks the controlled dry-run
- use `defer` if the issue is beyond pilot scope and should not be solved by semantic expansion
- use `accept` only for minor, understood issues that do not invalidate the session result

## Final Outcome

Choose exactly one:

- `PASS`
- `PASS WITH ISSUES`
- `FAIL`

Record:

- Final outcome:
- Summary reason:
- Number of issues logged:
- Any scope-boundary violations:
- Evidence pack location:

## Go / No-Go Recommendation Format

Use exactly one:

- `GO: proceed to next controlled internal dry-run session with same pilot boundary`
- `GO WITH FIXES: do not widen scope; fix only within-pilot issues before next session`
- `NO-GO: stop further dry-run rollout until blocking issue is resolved or pack is re-scoped`

## Next-Action Decision Tree

After the session:

1. If final outcome is `PASS` and no issue exceeds low severity:
   - keep pilot boundary unchanged
   - schedule next internal session or limited operator repetition
2. If final outcome is `PASS WITH ISSUES` and all issues are within scope:
   - fix only bounded within-scope issues
   - rerun preflight
   - rerun dry-run with the same pack
3. If any issue is beyond scope but non-blocking:
   - log it as `defer`
   - do not expand semantics inside this pilot lane
4. If final outcome is `FAIL` or any stop condition triggers:
   - mark `NO-GO`
   - halt the session series
   - re-scope the pack or open a separate bounded follow-up round

## Controlled Boundary Reminder

This execution-result pack does not authorize:

- inventory semantics
- PO matching
- invoice, compensation, payment execution, or reconciliation semantics
- request↔receipt quantity reconciliation
- line-level request↔receipt linkage
- dashboard/UI parity claims
- use of `/api/v1/*` as pilot truth
