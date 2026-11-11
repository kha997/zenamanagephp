---
work_id: GAP-124
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-124/02-design.md
---

# GAP-124 — Business notifications: implementation plan

Executes Gate 2 B-1, S-1, E-1, O-1 (`docs/owner-decisions/GAP-124/02-design.md`). Only files in the allowlist change.

1. **Red tests** `tests/Feature/Notifications/BusinessNotificationsTest.php`:
   - bell count;
   - notifications page;
   - open / read-all;
   - external link blocked;
   - Slack settings: permission, domain check, encrypted at rest, test send;
   - E-1 events: one message per event, CRM excluded, Slack failure does not break the action, other tenants untouched;
   - overdue digest: PM in-app, Slack summary, idempotent per day, `--dry-run`.
2. **`SlackChannelService`** (URL validation, encryption, read / write tenant setting) and **`SendSlackMessage`** job.
3. **`NotificationSlackObserver`** registered in `AppServiceProvider`. It holds the E-1 event list and a 10-minute dedupe
   lock.
4. **`NotificationPageController`** and **`NotificationSettingsPageController`**, plus routes and views. The bell goes in
   `layouts/operator.blade.php` and the CSS in `operator.css`.
5. **Permission** `notification.channel.manage` in `ZenaPermissionsSeeder`.
6. **Digest:** `OverdueTaskDigestService` and `reminders:overdue-tasks`, scheduled in `Console\Kernel`; add the command to
   `ScheduleContractTest`.
7. **Go-live runbook:** the Slack URL step and the digest command.
8. **Green;** full suite; lints; demo screenshots and a test send to a mock URL.
9. **Gate 3 packet.**
