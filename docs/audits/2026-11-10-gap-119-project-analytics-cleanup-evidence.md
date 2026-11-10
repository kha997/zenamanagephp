# GAP-119 — Broken project analytics API and dead milestone event code: discovery evidence

Date: 2026-11-10. Base: `main` at `7352fc46f3dd3f7c55484767d399f3c60b7fc8ab` (after GAP-118).

Backlog origin:
- GAP-103 Gate 2 X-1 ("ProjectAnalyticsService left for a separate Work ID");
- GAP-117 C-2 ("broken milestone event, listener and `Project::calculateProgress()` left untouched").

## 1. Project analytics API: every route fails

`routes/api.php:282-288` registers five routes on `Api\ProjectAnalyticsController`. `route:list` shows:
- `GET api/analytics/dashboard` is actually served by another controller (`Api\AnalyticsController`), registered elsewhere;
- the other four point to `ProjectAnalyticsController`.

| Route | Controller method in the route | Exists? |
| --- | --- | --- |
| `GET api/analytics/projects/{project}` | `projectAnalytics` | no |
| `GET api/analytics/filter-options` | `filterOptions` | no |
| `GET api/analytics/search-suggestions` | `searchSuggestions` | no |
| `POST api/analytics/search` | `advancedSearch` | no |

**Probe.** I ran a throw-away feature test (not committed) as an authenticated user of the same tenant. All four routes
return **HTTP 500** with "Method …ProjectAnalyticsController::<name> does not exist".

**Controller.** `ProjectAnalyticsController` only has `getMetrics`, `getTrends`, `getPerformance` and `getRiskAnalysis`:
- none of them is routed;
- they type-hint `Request` without importing it.

## 2. `ProjectAnalyticsService`: never called, and fails if called

- 709 lines.
- Not referenced by any controller, view, job, or test. The only mention is a comment in a GAP-117 test.
- **Probe:** `getProjectAnalytics()` throws `BadMethodCallException: Call to undefined method Project::isOverdue()`.
- Further defects found while reading (GAP-117 notes):
  - private helpers type-hint `Collection` without importing it, so it resolves to `App\Services\Collection`;
  - it calls `getDaysRemaining()` / `getBudgetUtilization()`, which do not exist on `Project`.

**What replaced it.** Project health for the project page and OPPM is now `ProjectHealthReadService` (GAP-103):
- progress;
- schedule;
- milestones (GAP-117);
- attention;
- finance.

The PM progress API (`PmDashboardController`) has its own milestone metrics (fixed in GAP-117).

## 3. Dead milestone / project event code (GAP-117 C-2)

| Item | State |
| --- | --- |
| `App\Events\ProjectMilestoneCompleted` | No `use` for `ProjectMilestone` / `Project` (resolves to `App\Events\*`); never dispatched. |
| `App\Events\ProjectTaskUpdated`, `App\Events\ProjectTeamMemberJoined` | Same pattern; never dispatched. |
| `App\Listeners\LogProjectActivity` | Handles only the three events above; event classes not imported; not registered anywhere. |
| `App\Services\RealTimeNotificationService` (418 lines) | No caller anywhere; its `sendMilestoneCompleted()` is the only other user of `ProjectActivity::logMilestoneCompleted()`. |
| `ProjectActivity::logMilestoneCompleted()` | Only called by the two dead items above. `ProjectActivity` itself is live (AuditService, RealTimeController). |
| `Project::calculateProgress()` | Progress by milestone count. Only used by `tests/Feature/ProjectModuleTest.php`; real progress comes from `recalculateProgress()` / `ProjectHealthReadService`. |

## 4. Guards already in place

`tests/Feature/Architecture/DeadMilestoneTemplateApiRemovedTest.php` is the pattern used on 2026-07-22 for removing
never-working routes. It asserts:
- the routes are gone (negative probes marked `SSOT_ALLOW_ORPHAN`);
- the models still work.

`tests/Feature/RouteHygieneTest.php` lists the prefix `api/analytics/dashboard`. That route belongs to
`AnalyticsController` and is out of scope.

## 5. Open points for Gate 2

- **Analytics API:**
  - remove the four dead routes, `ProjectAnalyticsController` and `ProjectAnalyticsService`, with a guard test;
  - or rebuild `GET api/analytics/projects/{project}` on top of `ProjectHealthReadService`.
- **Dead event code:**
  - remove only the milestone pieces;
  - or remove the whole unused group (three events, listener, `RealTimeNotificationService`,
    `logMilestoneCompleted`);
  - or leave it.
- `Project::calculateProgress()`: remove it, with its one test assertion, or keep it.

## 6. Exclusions

- `Api\AnalyticsController` and its routes, including `api/analytics/dashboard`.
- `ProjectHealthReadService`, OPPM.
- `ProjectActivity` model and its live callers.
- `RealTimeController`.
- Broadcasting setup.
- Deploy.
