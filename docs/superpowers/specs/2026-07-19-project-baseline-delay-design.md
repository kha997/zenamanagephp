# Project baseline & delay flags (kế hoạch gốc + cờ trễ tiến độ)

Date: 2026-07-19
Status: approved for implementation planning

## Problem

The 2026-07-19 management-axis audit found the codebase answers most operational questions well (per-project cockpit, Gantt, site flows, contract finances) but **cannot answer "is this project late, and by how much?"** against a committed plan. `Baseline`/`BaselineHistory` models, `BaselineService` (create-from-project, versioning, history, compare, report), and API routes all exist — but **zero web UI uses them**. The only "late" signal today is task `end_date < now()` counts, which loses all meaning the moment someone edits a deadline. A committed baseline is the anchor that makes delay measurable and un-erasable.

Brainstorm decisions (2026-07-19, with user): project-level baseline semantics (reuse the existing schema — task-level snapshots explicitly deferred); delay flags surface on both the project detail page and the projects list; committing/rebaselining reuses the existing `project.update` permission (no new permission); implementation is server-rendered over the existing `BaselineService` (no JS, no API calls from the browser).

## Scope

**In scope:**
- One new pure-PHP evaluator: `App\Services\ProjectDelayStatus`.
- One new web action: `POST /app/projects/{project}/baseline` (commit / re-commit the plan from current project dates).
- A `latestBaseline()` relation on `App\Models\Project` (`HasOne` + `latestOfMany('created_at')`).
- UI: "Kế hoạch gốc" card on `projects/show`, delay badge on both `projects/show` and the projects list.

**Out of scope (deferred):**
- Task-level baseline snapshots / dual-bar Gantt overlay (would need new schema; upgrade path exists later).
- Dashboard "late projects" block (add after the list-level badge proves useful).
- Any change to `BaselineService`, `Baseline`, `BaselineHistory`, or the existing baseline API routes.
- Cost-variance display (the baseline stores `cost`, but this slice answers the schedule question only).
- Editing or deleting baselines from the web (append-only by design; a wrong baseline is corrected by committing a new version with a note).

## Architecture

- **`App\Services\ProjectDelayStatus`** — pure PHP, no I/O. `evaluate(Project $project, ?Baseline $baseline): array` returning `{state: string, days_late: int|null, baseline: ?Baseline}` with `state ∈ {completed, no_baseline, late, forecast_late, on_track}`.
- **`ProjectController::show`** — additionally loads `latestBaseline` and passes the evaluator result to the view.
- **`ProjectController::storeBaseline`** (new) — route `POST /app/projects/{project}/baseline`, middleware `rbac:project.update`, validates `type` (`in:contract,execution`) + `note` (nullable, max 1000). **Tenant-checks the project via `App\Models\Project` BEFORE calling the service** — mandatory, because the `baselines` table has NO `tenant_id` column (isolation rides on the project FK) and `Src\CoreProject\Services\BaselineService::createBaselineFromProject()` does a bare `findOrFail`. On success: redirect back with a flash message. Versioning/history/events are the service's existing job — the web layer adds nothing.
- **Projects list** — eager-load `latestBaseline` via `with()` (one query, no N+1), evaluate per row in PHP, render the badge.
- "Latest baseline" = newest `created_at` across both types (`version` numbering is per-type, so recency — "the most recently committed plan" — is the correct cross-type ordering). The card shows the baseline's type and version alongside.

## Delay semantics (evaluation order)

1. `project.status === 'completed'` → `completed` — no delay flag (post-mortem lives in the existing API report).
2. No baseline → `no_baseline` — "Chưa chốt kế hoạch gốc" + commit button (button visible only with `project.update`).
3. Today > `baseline.end_date` (project not completed) → **`late`**, `days_late = today − baseline.end_date` in whole days, **date-only comparison** (the Gantt already learned the timezone-shift lesson; reuse it).
4. Not yet past the committed end, but current `project.end_date` > `baseline.end_date` → **`forecast_late`**, `days_late = project.end_date − baseline.end_date` — the current plan itself admits it will finish late.
5. Otherwise → `on_track`.

Edge cases pinned: project with no current `end_date` → rule 4 is skipped (never guess); a changed `start_date` does not affect the flag (only end dates answer "late"); `days_late` is `null` for the three non-late states.

## UI

- **Badge** (list + card): `late` → red "Trễ N ngày"; `forecast_late` → amber "Dự kiến trễ N ngày"; `on_track` → green "Đúng tiến độ"; `no_baseline` → gray "Chưa chốt KH"; `completed` → no delay badge (the existing status badge already says completed).
- **"Kế hoạch gốc" card** on `projects/show`: committed start–end dates, type label (hợp đồng/thực thi) + version, committed-by + committed-at, note, the delay badge, and — for `project.update` holders — a re-commit form (type select + note textarea + "Chốt kế hoạch từ ngày hiện tại" button). Every re-commit creates a new version; history is append-only via the existing service.
- The note field on re-commit is where the *reason for moving the plan* is recorded — same evidence philosophy as goal #2: delay marks are never erased, only annotated.

## Testing approach

- **Unit `ProjectDelayStatusTest`** (the core): all 5 states; `late`/`forecast_late` day counts exact under `Carbon::setTestNow`; edge: project without `end_date` skips rule 4; date-only comparison immune to time-of-day.
- **Feature `ProjectBaselineTest`**: (1) `project.update` holder commits → 302 + one new `baselines` row, version incremented; (2) second commit → version 2, first row untouched (append-only); (3) `project.view`-only user → 403, no row; (4) **cross-tenant commit attempt → 404, no row** — the key security test, since `baselines` has no tenant column; (5) project show renders the "Kế hoạch gốc" card after committing. POST tests establish a real session via `$this->get('/login')` in `setUp()` (the known CSRF gotcha).
- No dedicated view/color tests; Blade compile verified via `view:cache`.

## Migration safety

No migration. The `baselines`/`baseline_history` tables and their service/API already exist and are untouched. Purely additive web/UI code.
