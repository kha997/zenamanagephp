# GAP-097 — Treasury S6 dashboard and reports: Gate-1 evidence

**Date:** 2026-10-27 (+07:00)

**Canonical base:** `129f3f390704a23447229a72cf5d057c9d0174c6` (S5 released, GAP-096)

**Branch:** `docs/GAP-097-treasury-s6-dashboard-reports`

**Scope:** Read-only analysis for slice S6 of Issue #244. No code or schema change.

## 1. Slice

GAP-063 slicing: **S6 — dashboard and reports**: dashboard cards, transaction register, timeline, reports and exports.
User-visible result: a management view of project money.

## 2. Requirements (canonical spec `2026-08-07-project-treasury-cashflow-design.md`, branch `docs/project-treasury-spec`)

- **§10.1 dashboard:** cards (investor paid, funding through intermediaries, owner contribution, posted expenses,
  held funds, unreconciled funding, pending approvals, outstanding advances, missing evidence, budget variance), a
  wallet table (custodian, balance, unreconciled, outstanding advances, last activity) and alerts.
- **§10.2 register:** filters (date range, type, status, parties, wallets, creator, approver, reconciliation,
  self-approved, BOQ, fund chain, route).
- **§10.7 timeline:** chronological events ("A paid … to X") linking to documents, routes and wallets.
- **§11 reports:** 14 reports, exported through existing export infrastructure where practical.
- **§5.3 / §5.5:** self-approved expenses reported separately; intermediary amounts (paid, in transit, received) shown.

## 3. What exists on main

- Data from S1–S5: documents, ledger entries, wallets and custodians, parties, approval log (self-approval mode),
  routes and legs, contract-payment traces, reconciliation, advances and settlements, cost allocations to
  `ContractExpense` (category) and `MaterialReceiptLine`.
- Project Treasury page: balance summary, wallets with reconciliation summary, register (last 100, status filter
  only), expense / route / advance cards. `TreasuryBalanceService::projectSummary`, `TreasuryAdvanceService::ageing`.
- Treasury index page: project list only. Permissions `treasury.export` and `treasury.view_audit` are seeded but used
  by no route. CSV export pattern: `Web\ReportPageController::export` (`streamDownload`, formula-injection escaping).
- **Not available:** evidence attachments, BOQ linkage of treasury costs, fund-chain creation (table exists, unused),
  contract/budget value per project for treasury, per-advance due dates, configurable thresholds.

## 4. Owner answers (in-session, 2026-10-27)

1. **Reports:** only reports whose data exists — project cashflow summary, wallet balances, money held per custodian,
   advance ageing, expenses by category, expenses by party (supplier / labour / subcontractor…), self-approved
   expenses, unreconciled transactions, payment routes / in transit, reversals and adjustments. Evidence, BOQ and
   fund-chain reports wait for a later slice (schema change).
2. **Export:** CSV, as the existing reports.
3. **Alert thresholds:** fixed — funding unreconciled for more than 30 days; an advance is overdue when outstanding for
   more than 90 days (S5 ageing).
4. **Dashboard level:** per project, plus an all-projects table on the Treasury index.

## 5. Explicit exclusions

Evidence / missing-evidence, BOQ and budget variance, fund chains, investor receivable against contract value,
configurable thresholds, Excel files, charts beyond tables, schema changes, deployment.

## 6. Risks

- Read-only: no posting path changes; the risk is wrong figures. Every figure is derived from the same services the
  pages already use, and tests compare report totals with the ledger.
- Scope / visibility: reports and exports must stay tenant- and project-scoped (`treasury.all_projects` for the
  overview).
