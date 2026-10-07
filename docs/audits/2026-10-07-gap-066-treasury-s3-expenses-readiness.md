# GAP-066 — Treasury S3 expenses & approvals: Gate-1 evidence

**Date:** 2026-10-07 (+07:00)

**Canonical base:** `7e783c675b49314c5201d1781c8fdec478b4accd` (S2 released, GAP-064)

**Branch:** `docs/GAP-066-treasury-s3-expenses`

**Scope:** Read-only analysis for slice S3 of Issue #244. No code or schema change.

## 1. Binding rules (GAP-037 approved)

- **Architecture A3 + A.5:** Treasury owns only the cash side; an expense
  cash-out **must** be allocated to existing cost records (`ContractExpense` or
  `MaterialReceiptLine`), many-to-many with partial amounts; no unlinked expense.
  When no cost record exists, the canonical cost record is created **in the same
  atomic operation** as the cash-out (A3 correction).
- **v17 §2.3:** `expense` = source wallet → destination party
  (`destination_party_id` required). §5a: one `debit` at the source wallet.
- **v17 §10 / §10.1:** `treasury_expense_approvals` event log
  (`event`, `from_status`, `to_status`, `actor_id`, `note`, `context`); an
  expense may reach `posted_unreconciled` only if its latest approval row has
  `to_status = approved`.
- **v17 §2.1a:** `draft → submitted → approved → posted_unreconciled`;
  `rejected` reachable from `submitted`/`approved`, **terminal**.
- **v17 §6 / §7:** `treasury_cost_settlement_allocations` (`apply`/`reverse`,
  `reverses_allocation_id` unique); 6.2 a direct expense's net allocation =
  its amount; 6.3 `0 ≤ net_allocation(cost) ≤ incurred(cost)`, where incurred =
  `ContractExpense.amount` or `MaterialReceiptLine.quantity_received × unit_cost`.
- **v17 §2.2b:** reversing a posted expense atomically creates one `reverse`
  allocation per active `apply`, linked to the reversal document.
- **v17 §11:** class 2 (cost-source rows, `(source_table, id)` order) before
  class 5 (documents); S2 added class 0 (wallet) + shared-locked balance read.
- **v17 §14:** cost source must share tenant and project (via its contract).

PR #245 (non-normative): approval and posting may be one atomic operation;
self-approval recorded as `approval_mode = self_approval` and separately
reportable; only X may self-approve by default.

## 2. Repository facts

- `ContractExpense` (`contract_id`, `expense_date`, `amount`, `category` ∈
  labor/subcontractor/design_outsource/misc, `description`, `recorded_by`);
  contracts belong to a project.
- `MaterialReceiptLine` (`project_id`, `quantity_received`, `unit_cost`).
- Permission codes from S1: `create_expense`, `submit_expense` (Z, X),
  `approve_expense` (X, accountant), `self_approve_expense` (X only).
- S2 services: `TreasuryPostingService` (posting, reversal, guard),
  `TreasuryBalanceService`.

## 3. Proposed S3 scope

Expense draft (wallet, payee party, amount, date, reference, allocations to
existing cost records and/or one new `ContractExpense` created atomically);
submit; approve (= post, guarded by balance) or reject (terminal); copy a
rejected expense into a new draft; self-approval by X recorded in
`treasury_expense_approvals.context` and listed separately; reversal of posted
expenses with allocation coupling; per-cost-record "đã trả / còn phải trả"
view; API + web approval queue.
