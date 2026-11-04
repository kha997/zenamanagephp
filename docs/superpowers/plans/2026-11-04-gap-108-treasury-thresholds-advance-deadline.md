---
work_id: GAP-108
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-108/02-design.md
---

# GAP-108 — Treasury configurable thresholds and per-advance deadline: implementation plan

Executes Gate 2 N-1, S-1, H-1, Q-1, P-1 (`docs/owner-decisions/GAP-108/02-design.md`). One PR, two commits.

1. **Thresholds:**
   - Red: `tests/Feature/Treasury/TreasuryThresholdsTest.php`.
   - Green:
     - `TreasurySettingsService` (tenant settings keys, defaults 30 / 90, 1–365, owner-level only, audit);
     - `TreasuryDashboardService` reads the funding threshold and counts overdue advances through the ageing row flag;
     - `TreasuryAdvanceService::summaryRow` overdue flag for advances without a due date (configurable term);
     - settings form on the company Treasury page (`TreasurySettingsPageController`, `treasury.self_approve_expense`).
2. **Advance due date:**
   - Red: `tests/Feature/Treasury/TreasuryAdvanceDueDateTest.php`.
   - Green:
     - migration adding nullable `due_date` to `treasury_financial_documents` and `treasury_advances`;
     - draft / update / copy carry `due_date` (not before the request date); approval copies it to the advance row;
     - `changeDueDate` (posted and owed, approver policy, reason, audit, row lock);
     - overdue flag and days for advances with a due date;
     - web form fields, due-date change form, ageing columns, report columns, alert label; API fields and route.
3. MySQL 8: migration up / down / up and the two new test files.
