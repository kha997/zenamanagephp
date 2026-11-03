---
work_id: GAP-105
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-105/02-design.md
---

# GAP-105 — Treasury BOQ link, fund chains, investor receivable: implementation plan

Executes Gate 2 R-1, B-1, F-1 and P-1 (`docs/owner-decisions/GAP-105/02-design.md`): one PR, three commits, in this order.

1. **R — investor receivable.**
   - Red: `tests/Feature/Treasury/TreasuryReceivableTest.php`.
   - Green: `TreasuryReceivableService`.
   - Add the dashboard cards "Giá trị hợp đồng" and "Còn phải thu".
   - Add the report `investor_receivable`, with CSV export.
2. **F — fund chains.**
   - Red: `tests/Feature/Treasury/TreasuryFundChainTest.php`.
   - Green: `TreasuryFundChainService` (create / add / remove, with audit).
   - `TreasuryFundChainPageController` with 5 routes and two views.
   - Add the report `fund_chain_trace` and a register filter by fund chain.
3. **B — BOQ link.**
   - Red: `tests/Feature/Treasury/TreasuryBoqLinkTest.php`.
   - Green: the migration `boq_line_item_id` (restrict delete).
   - `TreasuryExpenseService` validates that the BOQ line belongs to the same project and can be set only while the
     expense is a draft.
   - Add a BOQ line selector to the expense form.
   - Add the report `expense_by_boq` (budget only when a unit price exists) and the dashboard alert for BOQ overrun.
   - Add a register filter by BOQ line.
4. **Verify.**
   - Query count stays flat for the new reports.
   - Migration runs on MySQL and `down()` works.
   - PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac.
   - Unit + Feature + Integration and `tests/Feature/Api`.
5. **Gate 3:** release packet with subject and implementation-tree digest.
