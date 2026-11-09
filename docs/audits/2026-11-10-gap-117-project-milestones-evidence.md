# GAP-117 — Project milestones (mốc tiến độ): discovery evidence

Date: 2026-11-10. Base: `main` at `41daaca199f5fd1f288a42e7c3ebaa6228c73d71` (after GAP-116).

## 1. What exists

- Table `project_milestones` (migration `2025_09_20_163612_fix_project_milestones_table_schema.php`):
  - columns: ULID `id`, `project_id`, `name`, `description`, `target_date`, `completed_date`, `status`
    (`pending` / `completed` / `overdue` / `cancelled`), `order`, `metadata`, `created_by`, timestamps;
  - foreign key to `projects` with cascade delete;
  - **no `tenant_id`**: rows are scoped only through the project.
- Model `App\Models\ProjectMilestone`, plus the relation `Project::milestones()`.

## 2. No way to create or edit milestones

- The write API (`Api\ProjectMilestoneController`) was removed on 2026-07-22 because it never worked: the route had no
  `{project}` segment. See `docs/superpowers/specs/2026-07-22-dead-milestone-template-api-removal-design.md`.
- `tests/Feature/Architecture/DeadMilestoneTemplateApiRemovedTest.php` guards that the old route names
  (`milestones.*`) and URIs stay removed.
- No web form, seeder or import creates milestones.
- The end-to-end audit lists this as AUD-08: the model exists, but no high-level plan above tasks can be created
  (`docs/audits/2026-07-23-end-to-end-operational-audit.md`).

**Effect:** in practice every project has 0 milestones.

## 3. Screens that already read milestones (and show nothing today)

| Reader | Where shown | What it shows |
| --- | --- | --- |
| `ProjectHealthReadService` (schedule) | OPPM page, block "Mốc" | "x/y hoàn thành · z quá hạn"; "Chưa có dữ liệu" when there are none |
| `ProjectHealthReadService` (attention) | OPPM page | count of overdue milestones |
| `UpcomingMilestoneQuery` | "Hôm nay" (Today) page, milestones table | milestones overdue or due soon, for projects where the user has work or is PM |
| `ProjectAnalyticsService` | analytics API | milestone counts, completion rate, timeline |

When milestones can be created, these screens start showing them with no other change.

## 4. Defects found in the existing model

- **Stored "overdue" status:**
  - the saving hook writes `status = overdue` when the target date has passed;
  - it never sets the status back, even if the target date is moved later;
  - the readers already compute "overdue" live from the target date, so the stored value only adds confusion.
- `markCompleted()` stores `now()` (date and time) in a date column. Harmless, but the completion day should be "today".
- Event `ProjectMilestoneCompleted`:
  - it lacks the `use` imports for `ProjectMilestone` and `Project`, so it cannot be built;
  - it is never dispatched.
- `Project::calculateProgress()` (progress by milestones) is never called. Project progress comes from components
  (`recalculateProgress()`) and the shared health model.

## 5. Patterns to reuse

- **Project-page writes in OPPM (GAP-110):**
  - routes `rbac:project.view` plus a tenant-scoped project lookup;
  - every write checks `OppmOverlayService::canEdit`: the project's PM, or a user with `project.update`.
- **Tenant isolation:** readers filter through the tenant-checked project. AUD-05 noted that the missing `tenant_id` is
  a leak risk when a query forgets that filter.

## 6. Open points for Gate 2

- **Where:**
  - a "Mốc tiến độ" card on the project page;
  - or a separate page linked from the project page and from OPPM.
- **Who can edit:** PM / `project.update` (same as OPPM), or a new permission.
- **Which actions:**
  - add, edit, mark done, cancel, reorder;
  - delete or not: cancel keeps history.
- **Tenant isolation:**
  - add `tenant_id` (migration plus backfill from the project);
  - or keep scoping through the project with tests.
- **Overdue:** stop storing `overdue` and compute it live, normalizing existing rows; or keep it.
- **Broken event and unused progress method:** fix, remove, or leave alone.
- Whether milestone changes go to the project activity log.

## 7. Exclusions

- Link to tasks or work templates.
- Gantt chart.
- Notifications or reminders.
- Client portal.
- Mobile UI.
- Changes to how project progress is calculated.
- Reintroducing the old API routes.
- Deploy.
