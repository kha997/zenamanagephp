# "Việc của tôi" — personal work page

Date: 2026-07-20
Status: approved for implementation planning

## Problem

The 2026-07-19 workload view (#195) answers "ai đang làm gì" for managers — a page grouping every tenant employee's open work. It does not give an individual employee a way to see **just their own** open work when they log in; the only path today is opening the manager-facing workload page and scrolling to their own block among everyone else's.

Found during the user's live walkthrough (2026-07-20) alongside a related, explicitly **out-of-scope** finding: the app has no professional role taxonomy (Architect/Engineer/Site Supervisor etc.) — `RoleSeeder` only has System Admin/Project Manager/Project Member, and role names are never displayed anywhere in the UI (grep across `resources/views/` and `app/Http/Controllers/Web/` returns zero role-name-display sites). Since nothing user-facing depends on role identity, this page needs none of that — it filters by `assigned_to = auth()->id()`, which is orthogonal to what a user's role is called. Role taxonomy is deferred to its own future brainstorm.

## Scope

**In scope:**
- New route `GET /app/my-work` (route name `app.my-work.index`), gated by the existing `rbac:task.view` (same as `app.tasks`/`app.workload.index` — no new permission).
- New `WorkloadPageController::myWork()` action, reusing the exact open/overdue/blocked item-building logic already in `index()` (#195), extracted into a shared private method so neither action re-implements the rules.
- A one-line summary ("Bạn có N việc đang mở · M quá hạn · K bị chặn") followed by the existing `resources/views/app/_workload-items-table.blade.php` partial, reused as-is.
- One new nav link "Việc của tôi" in the "Dự án" section of `resources/views/layouts/operator.blade.php`, next to "Khối lượng".

**Out of scope (deliberately deferred):**
- Professional role taxonomy (Architect/Engineer/Site Supervisor/QC...) — separate future brainstorm; this page does not wait on it.
- Changing `/app/dashboard` as the post-login landing page — this ships as an additional nav destination only (brainstorm decision: option B).
- `TaskAssignment` (multi-assignee/hours) — untouched, same as #195.
- Any new UI for reassigning work from this page (read-only, same as #195's per-person blocks).

## Definitions (identical to #195, reused verbatim)

Open Task = `status ∈ {pending, in_progress, on_hold}`. Open DesignItem = `review_status ∉ {approved, final}`. Overdue = Task `end_date < today` (date-only); DesignItems are never overdue. Blocked = `blocked_at` not null. An item both overdue and blocked shows the red "Quá hạn" badge (overdue wins) and counts in both counters. This page shows **only** items where `assigned_to === auth()->id()` — no "unassigned" block (that concept belongs to the manager view only).

## Architecture

- **Extract `collectOpenItems(string $tenantId): Collection`** from `WorkloadPageController::index()` — the existing loop over `$tasks`/`$designItems` building the flat `$items` collection (each item: `assigned_to`, `kind_label`, `name`, `project_name`, `end_date`, `is_overdue`, `is_blocked`, `status`, `url`). `index()` calls it and keeps its own grouping-by-every-user logic unchanged; `myWork()` calls the same method and filters to one user.
- **`myWork(): View`** — `$tenantId` and `$userId` from `Auth::user()`; call `collectOpenItems($tenantId)`, filter `where('assigned_to', $userId)`, compute `open_count`/`overdue_count`/`blocked_count` the same way `index()` computes them per block. Render `app.my-work` with `{items, open_count, overdue_count, blocked_count}`.
- **View `resources/views/app/my-work.blade.php`**: page header "Việc của tôi", the one-line count summary (same red/amber conditional styling as the workload page's per-person header), then `@include('app._workload-items-table', ['items' => $items])`. Empty state: "Bạn chưa có việc nào đang mở." when `items` is empty — no separate empty-state component needed, a plain paragraph matches #195's own empty-state text style.

## Testing approach

New `tests/Feature/MyWorkPageTest.php` (fixture pattern copied from `WorkloadPageTest`): (1) authorized user sees only their own open task and design item, and does not see another tenant employee's items; (2) the count line matches (N/M/K) for a fixture with one overdue task and one blocked item; (3) user without `task.view` → 403; (4) cross-tenant isolation — another tenant's user with the same items never appears; (5) a user with zero assigned open items sees the empty-state message, not an error. `WorkloadPageTest` itself is unchanged (the extraction must not alter `index()`'s existing behavior — its current tests remain the regression guard).

## Migration safety

None — read-only, additive route/controller-method/view/nav-link only, reusing existing data and an existing Blade partial.
