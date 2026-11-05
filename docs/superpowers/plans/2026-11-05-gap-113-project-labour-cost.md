# GAP-113 — Labour cost per project: implementation plan

Owner Gate 2 approval: S-1, H-1, R-1, O-1, D-1, V-1 (`docs/owner-decisions/GAP-113/02-design.md`). Allowlist as in that
packet.

## Steps (TDD: tests red first, then code)

1. **Tests** `tests/Feature/Payroll/LabourCostAllocationTest.php`, `LabourCostReportTest.php` covering:
   - the design's cases (R-1 under / over standard hours, non-project hours, O-1, no login, unpriced hours, approved
     only, H-1 block, reopen clears, re-close recomputes, rounding sums, audit, tenant isolation);
   - V-1 access, CSV, preview vs closed, query count.
2. **Migration + model**: `labour_cost_allocations` (`2026_11_05_170000_…`), `App\Models\Payroll\LabourCostAllocation`.
3. **`LabourCostService`**: `pendingEntries`, `compute` (pure), `allocate`, `clear`, `preview`. Money in cents (GAP-109
   convention).
   - R-1 shares in 1/5-minute units: numerator = minutes × 5, denominator = max(total minutes × 5, standard hours in
     cents × 3).
   - Project total rounded to whole đồng, never above the cost floored to whole đồng.
   - Split across projects by largest remainder in whole đồng; the rest goes to the not-in-project row.
4. **`PayrollService`**:
   - `close`: H-1 check after locking the period, then `allocate` in the same transaction.
   - `reopen`: `clear`.
5. **`LabourCostReportService`**: `month`, `cumulative`, `projectTotal`, `csv`; fixed query count.
6. **Web**:
   - `LabourCostPageController` with routes `operator.payroll.labour-cost` and `operator.payroll.labour-cost.export`
     (`rbac:payroll.manage`).
   - Links and the pending-hours list on the payroll pages.
   - OPPM line in `ProjectOppmController` / `_oppm-body` (V-1).
7. **Verify**: full suites, MySQL migrate up / down / up and the new tests on MySQL, PHPStan, php-cs-fixer, SSOT lint,
   route guard, deptrac.
