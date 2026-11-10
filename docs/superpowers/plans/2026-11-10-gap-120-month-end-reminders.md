---
work_id: GAP-120
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-120/02-design.md
---

# GAP-120 — Month-end reminders: implementation plan

Executes Gate 2 W-1, T-1, C-1, R-1 (`docs/owner-decisions/GAP-120/02-design.md`). Only files in the packet's allowlist
change.

1. **Red:** add `tests/Feature/Reminders/MonthEndRemindersTest.php` and confirm it fails. It covers:
   - recipients for time review: PM, own entries, projects without a PM, entries not on a project;
   - the reminder windows;
   - payroll and overhead close reminders;
   - tenants that do not use payroll;
   - de-duplication;
   - tenant isolation;
   - `--dry-run`;
   - the links;
   - the schedule entry.
2. **Service:** `App\Services\Reminders\MonthEndReminderService`:
   - `plan()` is read-only;
   - `send()` writes in-app `Notification` rows keyed by `event_key`.
3. **Command:**
   - add `reminders:month-end {--dry-run} {--date=}`;
   - schedule it daily at 08:00 `Asia/Ho_Chi_Minh` in `Console\Kernel`.
4. **Runbook:** in `docs/runbooks/go-live.md`, document `ENABLE_SCHEDULER=true` and a dry run.
5. **Green:**
   - new tests;
   - full suites;
   - a dry run and a real run on sample data;
   - static checks.
