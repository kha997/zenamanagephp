---
work_id: GAP-114
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-114/02-design.md
---

# GAP-114 — Overhead allocation and project P&L: implementation plan

Executes Gate 2 N-1, C-1, P-1, A-1, R-1, D-1, V-1 (`docs/owner-decisions/GAP-114/02-design.md`). One PR, allowlist as in
that packet.

1. **Tests first:** `tests/Feature/Payroll/OverheadTest.php`, `ProjectPnlTest.php` (red).
2. **Migration + models:** `overhead_periods`, `overhead_cost_lines`, `overhead_allocations`.
3. **`OverheadService`:**
   - period create; line add / update / delete while draft;
   - `close`: payroll month must be closed. The pool is lines + GAP-113 rows without project. Split by approved project
     minutes from `labour_cost_allocations`, whole đồng, largest remainder; no project minutes leaves the whole pool
     unallocated;
   - `reopen` with reason; every write locks the period row and is audited.
   - `PayrollService::reopen` refuses while overhead of that month is closed.
4. **`ProjectPnlService`:**
   - month: per project revenue (paid contract payments by `paid_at`), Treasury posted expenses net of reversals (by
     `transaction_date`), labour, overhead, profit, margin; preview when the overhead month is not closed;
   - cumulative over closed overhead months plus contract value and projected P&L;
   - company totals; CSV; fixed query count.
5. **Web:** overhead page and P&L page (`rbac:payroll.manage`, close / reopen `rbac:payroll.close`); links on the payroll
   index; OPPM closed P&L line (V-1).
6. **Verify:**
   - full suites;
   - MySQL migrate up / down / up and the new tests on MySQL;
   - PHPStan, php-cs-fixer, SSOT lint, deptrac.
