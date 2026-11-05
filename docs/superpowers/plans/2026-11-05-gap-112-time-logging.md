---
work_id: GAP-112
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-112/02-design.md
---

# GAP-112 — Time logging per task: implementation plan

Executes Gate 2 T-1, M-1, C-1, R-1, L-1, A-1, P-1 (`docs/owner-decisions/GAP-112/02-design.md`). One PR.

1. **Entries:**
   - Red: `tests/Feature/TimeTracking/TimeEntryTest.php`.
   - Green: migration (`time_entries`), `TimeEntry` model, `TimeRuleViolation`, `TimeEntryService`:
     - start / stop / closeStale (10 h or 23:59, flagged);
     - manual with reason, no overlap, 24 h per day;
     - edit / delete before approval;
     - P-1 scope; L-1 payroll-month lock; audit.
   - Timesheet page; start / stop on "My work"; menu.
2. **Review:**
   - Red: `tests/Feature/TimeTracking/TimeReviewTest.php`.
   - Green: `TimeReviewService` (queue by week, approve / reject with reason, scope by project PM / project.update or
     payroll.manage for non-project, no self-approval except payroll.close, L-1, audit); review page.
3. **Reports:**
   - Red: `tests/Feature/TimeTracking/TimeReportTest.php`.
   - Green: `TimeReportService` (hours by project × person and person × project / category, approved vs pending,
     scoped, fixed queries); report page and CSV.
4. MySQL 8: migration up / down / up and the three new test files.
