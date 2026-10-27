# GAP-096 — Treasury S5 advances and settlements: Gate-1 evidence

**Date:** 2026-10-27 (+07:00)

**Canonical base:** `c7b8efffa06a4b81ca98134b1870ebde3bd59cc9` (S4b-2 released, GAP-095)

**Branch:** `docs/GAP-096-treasury-s5-advances`

**Scope:** Read-only analysis for slice S5 of Issue #244. No code or schema change.

## 1. Slice

GAP-063 slicing: **S5 — advances and settlements**: issue an advance, settle it with approved costs, return unused
cash, ageing (lock class 3). User-visible result: an engineer receives an advance and accounts for it (Scenario D).

## 2. Binding rules (GAP-037 v17, approved)

- **Tables (§7, migrated by GAP-038):** `treasury_advances` (`financial_party_id`, `originating_financial_document_id`
  unique, `amount`), `treasury_advance_settlements` (`settlement_type` `approved_expense|cash_return`, `direction`
  `apply|reverse`, `amount`, `financial_document_id` unique, `reverses_settlement_id` unique), and
  `treasury_cost_settlement_allocations` keyed by `advance_settlement_id` for advance settlements.
- **§7.5:** an `advance` document (wallet → party, always `direct`) posts its debit and creates its `treasury_advances`
  row in one write; amount and party match; lock class 3 before 5.
- **§6.5 / §6.6:** `outstanding = amount − Σapply + Σreverse`, kept within `0..amount`; `0` once the advance document is
  reversed.
- **§7.4:** an `approved_expense` settlement's own cost allocations sum to its amount; its reverse compensates every
  active allocation 1:1. It has no ledger entry (no cash moves).
- **§6.3:** cost allocations never exceed a cost's incurred amount (shared with S3's direct expenses, lock class 2).
- **§7.6 / §7.7:** an `advance_return` document (party → wallet) creates one `cash_return/apply` settlement atomically
  with its credit; a `cash_return/reverse` exists only through reversal of that document (§2.2b).
- **§2.2d:** an advance document can be reversed only when its settlement net is zero, re-checked at completion.
- **§11:** order 2 (cost sources) → 3 (advance) → 4 → 5.

## 3. What exists on main

- Models `TreasuryAdvance`, `TreasuryAdvanceSettlement`, `TreasuryCostSettlementAllocation`; document types `advance`
  and `advance_return` are allowed values. No service, API or page creates any of them.
- S3 (`TreasuryExpenseService`) has the draft → submit → approve/reject flow with self-approval auditing, the payables
  list and cost allocations to `ContractExpense` / `MaterialReceiptLine` under lock class 2.
- S2 `TreasuryPostingService::reverse()` refuses `advance` / `advance_return` (not in its reversible types).
- Parties include `employee`, `labour`, `supplier`; a supplier prepayment is an advance too (v17 §6.7).

## 4. Owner answers (in-session, 2026-10-27)

1. **Issuing an advance:** like an S3 expense — request → submit → approve (owner or accountant), self-approval audited.
2. **Settling with costs:** the advance holder files a settlement request allocated to contract expenses / material
   receipt lines → owner or accountant approves → it reduces what is outstanding.
3. **Returning unused cash:** straight into a project wallet only; via a route stays out of S5.
4. **Ageing:** outstanding advances grouped by days since issue: under 30, 30–60, 60–90, over 90.

## 5. Explicit exclusions

Cash return via a route (v17 §7.6 `via_route` branch); a per-advance settlement deadline (needs a schema change);
dashboard/reports (S6); bank-statement import; period lock; schema changes; deployment.

## 6. Risks

- Over-settlement: two settlements approved at once could push outstanding below zero, or a cost past its incurred
  amount; lock classes 2 and 3, proven on real MySQL as in S3/S4.
- Reversal ordering: an advance with active settlements cannot be reversed until they are undone (§2.2d); the UI must
  say so.
