---
work_id: GAP-109
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-109/02-design.md
---

# GAP-109 — Staff register and monthly payroll cost: implementation plan

Executes Gate 2 C-1, B-1, H-1, L-1, P-1, Q-1, R-1 (`docs/owner-decisions/GAP-109/02-design.md`). One PR.

1. **Permissions and staff register:**
   - Red: `tests/Feature/Payroll/StaffRegisterTest.php`.
   - Green: migration (three tables); `payroll.manage`, `payroll.close` in `ZenaPermissionsSeeder` and
     `PayrollRolePermissionSeeder` (Owner / Admin both, Finance manage); `StaffMember` model; `StaffService` (unique code,
     same-tenant user / party, date order, audit); staff pages and menu item.
2. **Monthly payroll:**
   - Red: `tests/Feature/Payroll/PayrollPeriodTest.php`.
   - Green: `PayrollPeriod`, `PayrollLine` models; `PayrollService` (create period with lines for active staff and
     1/12 bonus accrual; update line in draft with net pay, company cost, standard hours, hourly cost; close / reopen with
     reason; mark paid after close; audit; row locks); period pages and CSV export.
3. MySQL 8: migration up / down / up and the two new test files.
