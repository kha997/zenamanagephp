# GAP-095 — Treasury S4b-2 contract-payment traceability: Gate-1 evidence

**Date:** 2026-10-27 (+07:00)

**Canonical base:** `de952191e9f533cec95ea6c0ecdf960ef7f83a0c` (S4b-1 released, GAP-094)

**Branch:** `docs/GAP-095-treasury-s4b2-contract-payment-tracing`

**Scope:** Read-only analysis for slice S4b-2 of Issue #244. No code or schema change.

## 1. Where S4b-2 comes from

GAP-094 Gate 1 (Owner, 2026-10-27, split option) kept contract-payment traceability for a later Work ID. The Owner's
answers of 2026-10-08 (recorded in `docs/audits/2026-10-08-gap-067-treasury-s4a-reconciliation-readiness.md` §6)
apply: traceability is **optional**, with a **reminder list** of paid contract payments not fully traced; nothing is
blocked on the contract side; an amount traced this way **must not also be declared as funding**.

## 2. Binding rules already approved

- **Architecture B2 / B2-T** (`docs/superpowers/specs/2026-08-16-gap037-project-treasury-architecture-decisions.md`):
  `ContractPayment` stays the only authority for "the client paid X" (amount, `status`, `paid_at`); Treasury never
  writes to it and never re-asserts it. Treasury may record **where that paid money went** (custody). A contract
  payment may be allocated, wholly or in part, to one or more routes; `SUM(route amounts) <= ContractPayment.amount`.
  Legs are movement history, never summed across hops. A traced amount is never a separate `funding` document.
- **GAP-037 v17 §4.1 Case A:** a route linked to a contract payment carries `linked_contract_payment_id` and an
  `expected_destination_wallet_id`, both immutable; no financial document. **§4.2a:** a leg of such a route may settle
  only while the contract payment is `paid` with `paid_at` set, checked under a lock of the `contract_payments` row
  (§11 class 1, before the route). **§4.3:** complete when the expected destination holds the route amount and every
  intermediary is back to zero.

## 3. What exists on main

- `contract_payments` (`app/Models/ContractPayment.php`): `contract_id`, `name`, `amount`, `status`
  `planned|paid|overdue`, `due_date`, `paid_at`; managed by `Api\ContractPaymentController` and the contract pages;
  read by `ReportPageController::cashflow()` (thu) and `BusinessKpiService`. Contracts carry `project_id`.
- `treasury_payment_routes` already has `linked_contract_payment_id` (FK to `contract_payments`) and
  `expected_destination_wallet_id`, with the v17 CHECKs (exactly one link; contract link ⇔ expected wallet).
- S4b-1 (`App\Services\Treasury\TreasuryRouteService`) creates only Case B routes (linked to a Treasury document):
  legs, leg reversal, cancel, completion, reconciliation of leg entries through the route's document. Nothing creates
  a Case A route; there is no reminder list.

## 4. Proposed scope (details at Gate 2)

Case A routes for paid contract payments of the project (one payment may be split across several routes up to its
amount); legs, leg reversal and cancel reusing S4b-1; reconciliation of their leg entries per wallet (no document to
move); a reminder list of paid contract payments not fully traced; the rule that a traced amount is not also declared
as funding; API and the project Treasury page. No change to `ContractPayment`, contract pages or reports.

## 5. Explicit exclusions

Advances (S5), reports (S6), changing `ContractPayment` or its lifecycle, contract pages, cashflow/KPI reports,
bank-statement import, period lock, schema changes, deployment.

## 6. Risks

- Double counting between a traced contract payment and a separately declared funding (Owner rule 3).
- Lock order with the `contract_payments` row (class 1) ahead of wallet/route locks; to be proven on real MySQL as in
  S4b-1.
