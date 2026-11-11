# GAP-124 — Business notifications (Slack and in-app): discovery evidence

Date: 2026-11-11. Base: `main` at `f3066c548211932b7dd29d65e93abbb0dd2dfddd` (after GAP-123). Roadmap "Open work" item 1:
"Business notifications to Slack (CR, submittal, NCR, overdue tasks to the internal channel)".

## 1. In-app notifications that exist today

**Where they are stored:** rows in `notifications` (`App\Models\Notification`: `type`, `priority`, `title`, `body`,
`link_url`, `channel` = `inapp`, `event_key`, `project_id`).

**Who creates them:**

| Area | `event_key` examples | Created in |
|---|---|---|
| Change requests | change request applied (GAP-012) | `Api\ChangeRequestController` |
| Submittals | `submittal.submitted` / `resubmitted` / decision / `revision_started` / `content_updated` (GAP-013) | `SubmittalLifecycleService` |
| Inspections / NCR | NCR events (GAP-014b) | `Api\InspectionController` |
| CAPA | `zena.task.overdue_escalated` — **only when someone calls** `POST /api/zena/tasks/{id}/escalate-overdue` | `Api\TaskController` |
| Design items | `design_item.status_changed` | `DesignItemStatusService` |
| CRM | `crm.lead.*`, `crm.opportunity.stage_changed`, appointments, `quote.sent` / `accepted` / `rejected` | CRM services and controllers |
| Contracts | `payment_certificate.approved` | `Web\ContractPageController` |
| Month end | `month_end.time_review` / `payroll_close` / `overhead_close` (GAP-120) | `MonthEndReminderService` |

## 2. Gaps

### F-1 — Nobody sees a notification unless they open "Hôm nay"

- Unread notifications appear only in the "Cập nhật chưa đọc" section of `/app/today`
  (`TodayWorkspaceReadService::unreadUpdates`).
- The operator layout (`layouts/operator.blade.php`) has:
  - no bell;
  - no unread count;
  - no page listing all notifications.

  The old `layouts/simple.blade.php` has a bell, but the operator pages do not use it.
- `/app/settings/notifications` only redirects to the general settings page.

### F-2 — Nothing leaves the app

- `src/Notification/Listeners/NotificationChannelListener` has `email` and `webhook` branches. Both are stubs:
  - `sendEmailNotification` has the `Mail::` line commented out;
  - `sendWebhookNotification` is `// TODO: Implement webhook notification`.
- There is no per-company Slack setting.
- The only Slack in the repo is CI / deploy messages (GAP-026, GitHub secret). It is not business events.

### F-3 — Overdue tasks are not announced

- `NotificationRule::EVENT_TASK_OVERDUE` (`task.overdue`) exists as a constant.
- Nothing creates such a notification on a schedule.
- The only overdue notification is the manual CAPA escalation above.
- "Hôm nay" and "Việc của tôi" show overdue tasks to the assignee, but:
  - the PM is not told;
  - no channel is told.

## 3. What a company would need

- **One place in the app** to see and clear notifications. For example: a bell with unread count in the top bar and a
  list page.
- **Selected business events sent to the company's Slack channel.** Each company sets its own Slack incoming-webhook URL.
- **A daily digest of overdue tasks** for the PM and/or the channel. It would run behind `ENABLE_SCHEDULER`, like
  GAP-120.

## 4. Open points for Gate 2

- **Which events** go to Slack (all of §1, or a chosen set).
- **Where the Slack URL lives:**
  - a company setting, stored encrypted;
  - or a server `.env` value (one channel for the whole install).
- **Overdue digest:**
  - who receives it (PM, assignee, channel);
  - time of day.
- **In-app bell and list page:** whether they are in this Work ID.
- **Email:** in or out (no mail provider is configured for production yet).

## 5. Exclusions

- Zalo OA / ZNS (roadmap item 2, separate Work ID).
- Push to phones.
- Realtime websockets.
- Deploy.
