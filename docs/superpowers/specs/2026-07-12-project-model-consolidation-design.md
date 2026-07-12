# Project Model Consolidation — Phase A + Phase B Batch 1

## 1. Purpose

A full-system audit (2026-07-12) found 3 different Eloquent classes mapping to the same `projects` table: `App\Models\Project` (70 file references), `Src\CoreProject\Models\Project` (56 references), and `Src\CoreProject\Models\LegacyProjectAdapter` (5 references, an empty subclass of `App\Models\Project`). This spec covers the first two phases of consolidating toward `App\Models\Project` as the single canonical class — the full 56-reference `Src\CoreProject\Models\Project` migration is too large for one plan (see §6, deferred scope).

## 2. Confirmed real facts (verified 2026-07-12, not assumed from the audit)

- `App\Models\Project` is the richer, more correct class: uses `TenantScope` (auto tenant-filtering global scope) and `SoftDeletes`; has more fields (`pm_id`, `manager_id`, `priority`, `actual_cost`, `budget_actual`, `budget`, `spent_amount`) and relations (`boqs`, `milestones`, `teams`, `manager`, `client`).
- `Src\CoreProject\Models\Project` is weaker: no `TenantScope`, no `SoftDeletes`, fewer fields/relations, and has a **live bug** — `scopeForTenant($query, int $tenantId)` type-hints `int` when `tenant_id` is a ULID string; with `declare(strict_types=1)` at the top of its only real caller (`Src\CoreProject\Services\ProjectService::getProjectsList()`), calling it with a real tenant ID throws a `TypeError` today. This caller is confirmed dead code (see below), so the bug is currently inert, but consolidating fixes it as a side effect if that file is ever revived.
- `Src\CoreProject\Models\LegacyProjectAdapter extends App\Models\Project` with an empty body — behaviorally identical to `App\Models\Project`, zero-risk to consolidate.
- **Reachability of the 11 files targeted for this phase's "Phase B batch 1" was traced end-to-end** (routes → controllers → constructors → event-provider registrations → `config/app.php`), not assumed from a grep. Only 5 of 11 are live:

  | File | Status | Evidence |
  |---|---|---|
  | `Src\CoreProject\Services\ProjectService` | Dead | Zero references anywhere outside its own file |
  | `Src\CoreProject\Services\WorkTemplateApplicationService` | **Live** | `app()`-resolved inside `Src\CoreProject\Controllers\WorkTemplateController` (routed: `Route::apiResource('work-templates', ...)` in `routes/api.php`) |
  | `Src\CoreProject\Services\ConditionalTagService` | **Live** | Same routed `WorkTemplateController`, plus constructor-injected into the (live) `WorkTemplateApplicationService` |
  | `Src\CoreProject\Services\TaskService` | **Live** | Constructor-injected into the (live) `WorkTemplateApplicationService` (same-namespace type-hint, no `use` needed) |
  | `Src\CoreProject\Services\BaselineService` | **Live** | Constructor-injected into `Src\CoreProject\Controllers\BaselineController` (routed: `/projects/{projectId}/baselines/*` in `routes/api.php`) |
  | `Src\CoreProject\Services\ComponentService` | Dead | Only consumer is `Src\CoreProject\Controllers\ComponentController`, which is not registered in any route file. The live `ComponentService` is a different class, `App\Services\ComponentService`, bound in `app/Providers/CustomServiceProvider.php` |
  | `Src\CoreProject\Listeners\ConditionalTagListener` | Dead | Not registered in any provider |
  | `Src\CoreProject\Listeners\ProgressCalculationListener` | Dead | Registered only in `src/Foundation/Providers/EventServiceProvider.php`, which is itself **not** listed in `config/app.php`'s providers array — the registration is inert |
  | `Src\CoreProject\Listeners\ProjectProgressListener` | Dead | Not registered anywhere (a similarly-named but different class, `UpdateProjectProgressListener`, exists and is registered — do not confuse the two) |
  | `Src\CoreProject\Listeners\ProjectCalculationListener` | **Live** | Registered in `src/Foundation/Providers/EventBusServiceProvider.php`, which **is** listed in `config/app.php`. Maps `Src\CoreProject\Events\ComponentProgressUpdated`/`ComponentCostUpdated` → this listener. Both events are dispatched by `App\Models\Component` (the live Component model) during normal component progress/cost updates |
  | `Src\CoreProject\Listeners\NotificationListener` | Dead | Not registered anywhere |

- Test coverage for the 5 live files is thin: `WorkTemplateApplicationService` and `ConditionalTagService` have zero direct unit tests today (only indirect architecture/documentation-coverage tests touch `WorkTemplateController`). `BaselineService`/`BaselineController` have zero tests at all. A file named `tests/Unit/TaskServiceTest.php` exists but tests `App\Services\TaskService` (a different class) — it provides no coverage for `Src\CoreProject\Services\TaskService`. `ProjectCalculationListener` has partial coverage via `tests/Feature/Integration/EventSystemIntegrationTest.php`.
- None of the 5 live files call `->delete()` or `->forTenant()`/`scopeForTenant()` on the `Project` model — they only do `Project::find($id)`/`findOrFail($id)` lookups by primary key. The delete/forTenant risk identified during brainstorming turned out to live entirely in `ProjectService.php`, which is confirmed dead code.

## 3. Scope

### Phase A — `LegacyProjectAdapter` consolidation (6 files, zero behavior change)

Swap the `use` import in these 6 controllers from `Src\CoreProject\Models\LegacyProjectAdapter` to `App\Models\Project` directly:

- `app/Http/Controllers/Web/ProjectBulkController.php`
- `app/Http/Controllers/Web/DocumentManagementController.php`
- `app/Http/Controllers/Api/ProjectTemplateController.php`
- `app/Http/Controllers/Api/ProjectAnalyticsController.php`
- `app/Http/Controllers/Api/ProjectManagerController.php`
- `app/Http/Controllers/Api/ProjectMilestoneController.php`

`LegacyProjectAdapter.php` itself is left in place (file deletion is not available this session — see §5) but becomes fully unreferenced.

### Phase B batch 1 — 5 live files + 6 dead files (11 total)

**Live files (careful, test-backed migration):**

- `src/CoreProject/Services/WorkTemplateApplicationService.php`
- `src/CoreProject/Services/ConditionalTagService.php`
- `src/CoreProject/Services/TaskService.php`
- `src/CoreProject/Services/BaselineService.php`
- `src/CoreProject/Listeners/ProjectCalculationListener.php`

For each: swap `use Src\CoreProject\Models\Project` → `use App\Models\Project`, write a test exercising the file's actual `Project::find()`/`findOrFail()` call path (new test where none exists), and verify the test passes both to confirm the swap doesn't throw (e.g., from `TenantScope`'s global scope unexpectedly filtering out a row in a listener/service context where tenant binding may not be present the way it is in a normal authenticated HTTP request).

**Dead files (cheap consistency swap + documentation, no test required since nothing exercises them):**

- `src/CoreProject/Services/ProjectService.php`
- `src/CoreProject/Services/ComponentService.php`
- `src/CoreProject/Listeners/ConditionalTagListener.php`
- `src/CoreProject/Listeners/ProgressCalculationListener.php`
- `src/CoreProject/Listeners/ProjectProgressListener.php`
- `src/CoreProject/Listeners/NotificationListener.php`

For each: swap the `use` import to `App\Models\Project`, and add a one-line class-doc comment: `// Confirmed unreachable 2026-07-12 — see docs/superpowers/specs/2026-07-12-project-model-consolidation-design.md §2 for the reachability trace.` No behavior-preservation work needed since nothing calls these files, but the import swap still removes one more live reference to the weaker `Src\CoreProject\Models\Project` class, which is the actual goal of this initiative.

## 4. Out of scope (this plan)

- The remaining ~45 references to `Src\CoreProject\Models\Project` outside this batch (in `src/CoreProject/Controllers`, `src/CoreProject/Middleware`, `src/CoreProject/Requests`, `src/WorkTemplate/*`, `src/RBAC/*`, `src/Notification/*`, `src/ChangeRequest/*`, `src/InteractionLogs/*`, `src/Compensation/*`, `src/DocumentManagement/*`, and a few more `app/` files) — left untouched, to be scoped as future batches.
- Deleting `LegacyProjectAdapter.php`, `Src\CoreProject\Models\Project.php` itself, or any of the 6 dead-file class bodies — file deletion is unavailable this session (see §5); these files remain on disk, increasingly unreferenced.
- Fixing the `scopeForTenant(int $tenantId)` bug in `Src\CoreProject\Models\Project.php` directly — moot for this batch since the only caller of that method (`ProjectService::getProjectsList()`) is confirmed dead code, and the class itself is out of scope until a future batch removes its last references.

## 5. Constraints

- **File deletion is unavailable this session** (a prior tool-permission denial). All "removals" in this plan are import swaps and route/registration cleanups on files that remain on disk, unreferenced. Note this explicitly in commit messages so a future session with delete permission knows what's safe to remove.
- **`declare(strict_types=1)`** at the top of every modified file, matching existing convention (already present in all 17 target files).
- Each live-file swap needs its own new test proving the swap doesn't break the call path — do not batch all 5 live-file changes into one untested commit.

## 6. Deferred future work

A second (and likely third+) batch will be needed to close out the remaining ~45 `Src\CoreProject\Models\Project` references. Given this batch's experience, future batches should budget time for the same reachability-tracing step per file/directory before assuming uniform risk — this session found the original "56 references, uniformly medium-risk" framing was wrong once traced (roughly half of the batch-1 files turned out to be entirely dead code). The remaining directories most likely to contain more dead code, by the same pattern observed here (an `App\`-namespaced modern rewrite superseding an unrouted `Src\`-namespaced original): `src/CoreProject/Controllers` (4 of 6 controllers already confirmed unrouted), `src/CoreProject/Middleware`, `src/CoreProject/Requests`.
