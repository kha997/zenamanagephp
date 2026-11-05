# GAP-112 — Time logging per task (labour cost part 2/4): discovery evidence

Date: 2026-11-05. Base: `main` at `ef278c5c78961ff97f96f00406f76f3ae89bc76f` (after GAP-111).

Sources: the Owner's in-session direction of 2026-11-04 (recorded in GAP-109 evidence §1) and the code on `main`.
Method: static reading; no production data was used.

## 1. Owner direction (binding)

**How it should work:**
- Each person opens the management page when starting work and registers which task they are working on.
- The system records the task and so the project.
- The person's full monthly company cost ÷ hours gives the labour cost charged to each project (part 3).

**Agreed advice:**
- Start / stop on a task.
- Manual correction with a reason.
- "Not project work" categories (internal meeting, training, admin).
- Weekly review by a manager.
- Lock when the payroll month is closed.
- Hourly rate on standard hours, so unlogged hours show as cost not charged to any project (part 3).

This GAP is part 2: time logging only. No money.

## 2. What exists

| Item | Detail |
|---|---|
| `tasks` | Has `estimated_hours`, `actual_hours`, `spent_hours` (single numbers per task), `assigned_to`, `project_id`, `tenant_id`, status, dates. No per-person / per-day record, no history. |
| `task_assignments` | Per-user `assigned_hours` / `actual_hours` (single numbers), `TaskAssignment::logHours()` overwrites the value. No date, no start / stop. |
| "My work" | `/app/my-work` (`WorkloadPageController::myWork`, `rbac:task.view`): the user's open tasks from the open-work read query. Natural place for start / stop buttons. |
| Staff register | `staff_members.user_id` (GAP-109) links a login to a paid person, so logged hours can be matched to payroll in part 3. |
| Payroll month | `payroll_periods` (draft / closed, GAP-109): the lock point for time entries of that month. |
| Timesheets | None anywhere (no table, no route, no service). |

The existing hour fields are single totals with no who / when. They cannot support per-person, per-day costing and
should not be overwritten by this feature.

## 3. Open points for Gate 2

1. Data: a new `time_entries` table:
   - person (user), task (nullable for non-project work), project, category;
   - start, end, minutes, source (timer / manual), note / reason.
2. Timer rules:
   - one running timer per person;
   - auto-stop at end of day or a cap (e.g. 10 hours);
   - forgotten timers.
3. Manual entries:
   - allowed with a reason;
   - edit / delete only before approval.
4. Non-project categories: fixed list or configurable.
5. Review:
   - who approves (the project manager of the task's project, or the staff member's manager);
   - weekly or per entry; rejected entries go back to the person.
6. Lock: entries inside a closed payroll month cannot change.
7. Views:
   - my timesheet (day / week);
   - review queue;
   - hours per project / per person reports (hours only, no money until part 3).
8. Whether to write back `tasks.actual_hours` (sum of approved entries) or leave it untouched.
9. Permissions: reuse `task.view` for logging own time; a new permission for reviewing others or reuse
   `project.update`.

## 4. Constraints

- Tenant and project isolation; a person can only log on tasks of projects they can see.
- Logged hours are personal work data: a person sees their own; reviewers see their scope; no salary or cost here.
- No change to payroll numbers in this part.
- MySQL parity; query-count bounds on list pages.

## 5. Out of scope

- Money:
  - hours × rate (part 3);
  - overhead and project P&L (part 4).
- Mobile app / offline;
- idle detection;
- automatic screen tracking;
- deployment.
