# Workload view (khối lượng công việc — "ai đang làm gì")

Date: 2026-07-19
Status: approved for implementation planning

## Problem

The 2026-07-19 management-axis audit found this is the largest remaining gap: `Task.assigned_to` and `DesignItem.assigned_to` hold real assignment data, but **no screen answers "tuần này ai đang làm gì, ai quá tải, ai rảnh, việc nào chưa ai nhận"**. The tasks list doesn't even show an assignee column (its query doesn't select it); the Team page is a plain user directory. A manager must open items one by one to reconstruct workload.

Brainstorm decisions (2026-07-19, with user): work units = Task + DesignItem (both carry `assigned_to`; for an architecture firm, design items ARE most people's work — `TaskAssignment` multi-assignee/hours table explicitly excluded: no UI writes it, building on an empty table is meaningless); surface = a new grouped-by-person page plus an assignee column + filter retrofitted onto the existing tasks list; no overload threshold — sort people by open-count descending and let red overdue / amber blocked counts speak (a hard threshold is a magic number, hours-based capacity needs the empty `TaskAssignment` data); permission = existing `task.view`, matching the adjacent Gantt page (`schedule.index`).

## Scope

**In scope:**
- New page `GET /app/workload` (route name `app.workload.index`, middleware `rbac:task.view`), nav link in the "Dự án" section next to Công việc.
- New `App\Http\Controllers\Web\WorkloadPageController` with a single `index` action.
- Assignee column + `?assigned_to=` filter on the existing tasks list (`AppController::tasks` + `resources/views/app/tasks.blade.php`).

**Out of scope (deferred):**
- `TaskAssignment` (multi-assignee, hours, capacity math) — untouched.
- Drag-drop reassignment / kanban — build only if quick-reassign demand materializes.
- Overload thresholds or per-person capacity settings.
- Any JS, any migration, any new permission, any change to `Task`/`DesignItem` models.

## Definitions (pinned)

- **Open Task:** `status ∈ {pending, in_progress, on_hold}` (i.e. not completed/cancelled).
- **Open DesignItem:** `status ∉ {approved, final}`.
- **Overdue:** Task with `end_date < today` (date-only). DesignItems have no own deadline → they never count as overdue; they contribute to open/blocked only.
- **Blocked:** `blocked_at` not null (both types).
- **Unassigned:** open item with `assigned_to` null → grouped into a "Chưa phân công" block at the end of the page.
- A user with zero open items still appears (shows who is free), with count 0, sorted last among users.

## Architecture

- **`WorkloadPageController::index`** — three tenant-scoped queries: (1) users of the tenant (id, name, ordered by name); (2) open Tasks with `project:id,name` eager load; (3) open DesignItems with `project:id,name` eager load. Grouping and counting happen in PHP: build per-user view-models `{user, open_count, overdue_count, blocked_count, items[]}` where each item carries `{kind: task|design_item, name, project_name, end_date|null, is_overdue, is_blocked, status, url}`. Sort blocks by `open_count` desc, then zero-count users by name; "Chưa phân công" block last.
- **Tasks list retrofit** — `AppController::tasks()`: add `assigned_to` to the selected columns, eager-load `assignee:id,name`, accept `?assigned_to=<user_id>` (validated as a same-tenant user id; invalid/foreign values are ignored, not an error); view gains a "Người phụ trách" column and a select-filter above the table listing tenant users (submits as a plain GET form, no JS).
- No service class: the grouping logic lives in the controller as small private methods — it is presentation aggregation, not domain logic, and feature tests cover it end-to-end. (If a second consumer ever appears, extract then.)

## UX

- Page title "Khối lượng công việc". One card per person, ordered as above. Card header: name + `N đang mở · M quá hạn · K bị chặn` — M styled red when > 0, K amber when > 0.
- Item rows inside a card: name (links to the task/design-item page), project name, kind label ("Công việc"/"Hạng mục thiết kế"), deadline (`d/m/Y`, tasks only), and a status badge: overdue → red "Quá hạn"; blocked → amber "Bị chặn"; otherwise the item's existing status badge (`<x-ui.status-badge>`).
- An item both overdue and blocked shows red "Quá hạn" (overdue wins — it is the more actionable signal) and still counts in both M and K.
- Empty tenant states: no users → the page just shows "Chưa phân công" (or an empty message); no open items anywhere → "Không có việc nào đang mở."

## Testing approach

Feature (`WorkloadPageTest`): (1) user with `task.view` gets 200 and sees a person's name with their open task and design item; (2) user without `task.view` → 403; (3) cross-tenant isolation — tenant B's items never render on tenant A's page; (4) completed task and `final` design item do not appear; (5) unassigned open item appears under "Chưa phân công"; (6) overdue task counts and shows red badge label; (7) person with zero items still renders with count 0. Tasks-list retrofit (`TasksListAssigneeTest` or folded into the same file): assignee name renders in the list; `?assigned_to=` filters to that user's tasks only; foreign-tenant user id in the filter is ignored (shows all). GET pages only — no CSRF/session gymnastics needed.

## Migration safety

None needed — read-only feature over existing columns; purely additive route/controller/view code.
