---
work_id: GAP-107
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-107/02-design.md
---

# GAP-107 — Treasury period lock and bank statement import: implementation plan

Executes Gate 2 L-1, U-1, R-1, I-1, P-1 (`docs/owner-decisions/GAP-107/02-design.md`). One PR, two commits.

1. **Period lock:**
   - Red: `tests/Feature/Treasury/TreasuryPeriodLockTest.php`.
   - Green:
     - migration (three tables);
     - `TreasuryPeriodLock` model;
     - `TreasuryPeriodLockService` (lock forward only, unlock with reason, history, audit, `requireOpen`);
     - `requireOpen` called inside the posting transactions of the posting / expense / advance / route services;
     - lock panel on the project Treasury page (`treasury.manage_period_lock`).
2. **Bank import:**
   - Red: `tests/Feature/Treasury/TreasuryBankImportTest.php`.
   - Green:
     - import and line models;
     - `TreasuryBankImportService`:
       - CSV parse (delimiter, encoding, debit/credit, number and date formats, 2 MB / 5,000 lines);
       - import (bank wallet only, file-hash duplicate, line fingerprints);
       - suggestions (same direction and amount, ±3 days, one ledger entry each);
       - confirm through `TreasuryReconciliationService::reconcile` (type bank);
       - ignore;
     - reconciliation undo reopens lines;
     - bank-import page (`treasury.reconcile`).
3. **Verify:**
   - migration up / down / up and a concurrent lock-vs-post race on MySQL;
   - PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac;
   - Unit + Feature + Integration and `tests/Feature/Api`.
4. **Gate 3:** release packet with subject and implementation-tree digest.
