---
work_id: GAP-068
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-068/02-design.md
---

# GAP-068 — Reconciliation mutation responses by id + paged history: implementation plan

Executes approved Gate 2 Option B (`docs/owner-decisions/GAP-068/02-design.md`); TDD; no migration.

1. **Red first** — `tests/Feature/Treasury/TreasuryReconciliationHistoryPagingTest.php`: with more than
   100 newer reconciliations, store / undo / undo-line responses carry the affected reconciliation
   (fails on main with `data: null`).
2. **Service** `TreasuryReconciliationService::history()` gains `$reconciliationId`, `$page`, `$perPage`;
   `historyItem()` by-id helper.
3. **API** `TreasuryReconciliationController`: mutation responses via `historyItem()`; `index`
   accepts `page` (≥ 1) and `per_page` (1–100, default 100), 422 otherwise.
4. **Web** `reconcileWallet` reads `?page=`, 50 per page; "Trang trước / Trang sau" links.
5. Verification: Treasury suite, PHPStan, SSOT / governance / docs lints, exact-head CI.
