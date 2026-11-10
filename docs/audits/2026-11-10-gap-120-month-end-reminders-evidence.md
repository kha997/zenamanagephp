# GAP-120 — Month-end reminders: discovery evidence

Date: 2026-11-10. Base: `main` at `5ff8671a4b9efeead2e496420baf512880842364` (after GAP-119).

## 1. The month-end flow today (GAP-112 … GAP-114)

1. **Staff log time.** Entries are `running` → `submitted`.
2. **Reviewers approve or reject submitted time** (`TimeReviewService`):
   - project entries: the project PM (`pm_id`) or `project.update` holders;
   - non-project entries: `payroll.manage` holders;
   - the review page is `app.time.review`, by week.
3. **Payroll close.** `PayrollService::close` (`payroll.close`) is **refused while the month still has running or submitted
   entries** (GAP-113 H-1). On close it locks the month and allocates labour cost.
4. **Overhead close.** `OverheadService::close` (`payroll.close`) needs the payroll month closed first; it allocates
   overhead.

**Gap:** nothing tells anyone that any of this is due. Each person has to open the pages and check:
- a PM does not learn that submitted hours are waiting;
- the Owner / accountant does not learn that last month is still open.

## 2. Notification channels that exist

| Channel | State |
| --- | --- |
| In-app `notifications` table (`App\Models\Notification`) | Live. Rows have `tenant_id`, `user_id`, `type`, `title`, `body`, `link_url`, `priority`, `read_at`. Written today by `SubmittalLifecycleService`, `RfiEscalationService` and listeners. Shown to the user on the **"Hôm nay"** page ("Cập nhật chưa đọc", `UnreadUpdateQuery`, latest 10 unread). |
| Email | Laravel mail is configured (`MAIL_MAILER`, default `smtp`). `EmailNotificationJob` exists. Whether production mail is set up is not confirmed. |
| Push / SMS / Zalo | None. |

## 3. Scheduler

- `app/Console/Kernel.php` defines scheduled jobs (backup, maintenance, queue restart).
- **All scheduled jobs are skipped unless `ENABLE_SCHEDULER=true`** (`config/app.php`, default `false`).
- The go-live runbook expects the cron `php artisan schedule:run` every minute, but does not mention `ENABLE_SCHEDULER`.
- No reminder command exists yet.

## 4. Open points for Gate 2

**Who gets which reminder**
- **PM / reviewers:** submitted hours waiting for their approval (count, oldest date), with a link to the review page.
- **Owner / accountant (`payroll.close`):**
  - payroll month not closed after month end;
  - overhead month not closed after payroll is closed.
- **Staff (optional):** a running timer left over night; entries rejected and waiting for a fix.

**When**
- For example on the last working days of the month and in the first days of the next month.
- Or daily while something is pending.
- Avoid duplicates: one reminder per person, kind and month (or per day).

**Channel**
- In-app (Hôm nay page) only;
- or in-app plus email.

**How it runs**
- A console command `reminders:month-end` scheduled in `Kernel.php`.
- Whether to set `ENABLE_SCHEDULER=true` in the runbook (the Owner's server change, not done by the agent).
- `--dry-run` for checking.

## 5. Exclusions

- Push / SMS / Zalo.
- Changing approval or close rules.
- Reminders for other modules (RFI, documents).
- A notification settings page.
- Deploy.
- Turning on the scheduler on the server.
