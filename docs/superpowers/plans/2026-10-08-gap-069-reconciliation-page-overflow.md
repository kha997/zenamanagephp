---
work_id: GAP-069
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-069/02-design.md
---

# GAP-069 — Bound the reconciliation history page number: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-069/02-design.md`); TDD; no migration.

1. **Red first** — `TreasuryReconciliationHistoryPagingTest`: web `?page=9223372036854775807` /
   `?page=99999999999999999999` return 200 (500 on main); API `page=10001` → 422, `page=10000` → 200 empty.
2. **Service** `TreasuryReconciliationService::MAX_HISTORY_PAGE = 10000`; `history()` clamps page and
   per_page before computing the offset.
3. **API** `index`: `page` max 10000.
4. **Web** `reconcileWallet`: clamp `?page=`; next-page probe only below the bound.
5. Verification: Treasury suite, PHPStan, SSOT / governance / docs lints, exact-head CI.
