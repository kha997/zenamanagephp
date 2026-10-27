# GAP-064 — Treasury S2 ledger engine: Gate-1 evidence

**Date:** 2026-10-07 (+07:00)

**Canonical base:** `fbdc7b1c0fb1b6594e5216495121429659b60c35` (S1 released, GAP-063)

**Branch:** `docs/GAP-064-treasury-s2-ledger`

**Scope:** Read-only analysis for slice S2 of Issue #244 (GAP-063 Gate 1
slicing). No code or schema change.

## 1. What S1 left

Parties, project wallets, `treasury.*` permissions and the access rule exist
(GAP-063). Nothing writes `treasury_financial_documents` or
`treasury_ledger_entries`; no balance can be shown.

## 2. Binding rules S2 must implement (GAP-037 v17, approved)

- **Documents** (§2): types used by S2 — `funding`, `owner_contribution`,
  `internal_transfer`, `adjustment`, `reversal`. Closed status graph (§2.1a):
  `draft → submitted → approved → posted_unreconciled`;
  `posted_* → reversed` (terminal); `rejected` terminal. `posting_path`
  locks to `direct` at posting (§2.1); `amount > 0` and immutable once the
  path is set.
- **Endpoint shapes** (§2.3): funding/owner_contribution = party → wallet;
  internal_transfer = wallet → wallet; adjustment = one wallet; reversal =
  exact swap of the original.
- **Ledger** (§5, §5a direct rows): funding/owner_contribution/adjustment-increase
  → one `credit`; adjustment-decrease → one `debit`; internal_transfer → one
  `debit` + one `credit`, atomic; every entry `amount = document.amount`;
  idempotency via unique `original_posting_key`; entries immutable;
  `wallet_balance = SUM(credit) − SUM(debit)`.
- **Reversal** (§2.2, §2.2a, §2.2c): at most once, no reversal-of-reversal,
  same amount, swapped endpoints, original must be posted, same project; the
  original flips to `reversed` atomically when the reversal posts (direct);
  `replacement_document_id` is an audit link only.
- **Concurrency** (§11): document rows locked lower-`id` first (class 5);
  ledger entries class 4 (not needed for S2 writes beyond inserts).
- **Same-project integrity** (§15 rows 4–6): wallets and linked documents in
  the document's project.

PR #245 (non-normative) adds: funding posts immediately as
`posted_unreconciled` (§5.2.1–2); internal transfer needs no approval (§8.3);
every create/approve/post/reverse/adjust action logged (§5.8.6); duplicate
warning on project + amount + date + source + destination + reference
(§5.2.6); owner contribution reported separately from investor funding
(§5.2.7).

## 3. Gaps between v17 and the spec

- **No business date or reference column.** v17 has only `posted_at`
  (system time). Duplicate warning and the financial timeline need the date
  the money moved and the bank/receipt reference — requires an additive
  migration (Owner question 1).
- **Audit trail.** v17 has an approval log only for expenses
  (`treasury_expense_approvals`). Repository has a generic `audit_logs`
  table (entity type/id, project, tenant, old/new data) usable for S2
  actions.

## 4. Proposed S2 scope

Declare funding / owner contribution (posts immediately); internal transfer
(posts immediately); adjustment increase/decrease (reason required);
document reversal with optional replacement link; derived wallet and
project balances; transaction register on the project Treasury page; audit
entries for every action; API + web.

Out: expenses/approvals (S3), routes/`ContractPayment` traceability and
reconciliation (S4), advances (S5), dashboard/reports (S6), company wallets.
