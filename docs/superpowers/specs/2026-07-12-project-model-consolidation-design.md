# Project Model Consolidation — Discovery Slice (revised)

> **Revision note (2026-07-12, same day):** the original version of this spec proposed migrating 11 files in `src/CoreProject/Services`/`Listeners` off `Src\CoreProject\Models\Project`, based on a reachability trace that turned out to be **incomplete** — it missed that `routes/api.php` `require`s `src/CoreProject/routes/api.php`, which mounts all 6 `Src\CoreProject\Controllers\*` classes live at `/api/v1/*`. That route family is `docs/architecture/module-ownership-ssot.md`'s explicit, tested, intentional **compatibility runtime** for Projects — not dead code, and not safe to touch in a "first slice" without full inventory and test proof. This revision drops that migration entirely and replaces it with a correctly-scoped discovery + forward-guard slice. §2 below preserves the corrected facts for the record; §3 is the new, much narrower scope.

## 1. Purpose

A full-system audit (2026-07-12) found repeated references to Project-related classes across `App\Models\Project`, `App\Models\ZenaProject`, `Src\CoreProject\Models\Project`, and `Src\CoreProject\Models\LegacyProjectAdapter`. `docs/architecture/module-ownership-ssot.md` already establishes the authoritative ownership answer for this — this spec does not re-decide it, only builds the missing inventory and forward-guards that let future work rely on it safely.

**From the SSOT (verbatim facts this spec must not contradict):**
- Canonical model owner for Projects: `App\Models\Project`.
- Canonical route/controller family: `/api/zena/projects` → `App\Http\Controllers\Api\ProjectController` → `App\Services\ProjectService` → `App\Models\Project`.
- `/api/v1/projects` → `Src\CoreProject\Controllers\ProjectController` is a **still-mounted compatibility runtime** — real, tested, intentional, not dead code. `docs/architecture/module-ownership-ssot.md`'s policy: "do not delete blind," "treat as compatibility/debt mounting layers... unless a module has no app-owned runtime yet" (Projects already has one), "do not create a second controller/model owner."
- `App\Models\ZenaProject extends App\Models\Project` (empty subclass) is a **frozen thin alias**, kept only because tests/factories reference it. Policy: keep thin, no new behavior, no new `Zena*` aliases.
- `LegacyProjectServiceAdapter` (a *Service*, not the *Model* adapter this spec is about) was already removed and must not be reintroduced — enforced today by `tests/Feature/Architecture/ModuleOwnershipSourceInvariantTest.php`.

## 2. Confirmed real facts (verified 2026-07-12 — includes a correction mid-session)

- `App\Models\Project` is the richer, canonical class: `TenantScope` (auto tenant-filtering global scope), `SoftDeletes`, and the full field/relation set. This matches the SSOT's designation, not a fresh judgment call.
- `Src\CoreProject\Models\Project` is weaker (no `TenantScope`, no `SoftDeletes`, fewer fields, a live `scopeForTenant(int $tenantId)` type-hint bug against a string ULID column) — but per the SSOT this is **known, accepted compatibility debt behind a real mounted route**, not a defect this slice is authorized to fix.
- `Src\CoreProject\Models\LegacyProjectAdapter extends App\Models\Project` (empty body) is not mentioned in the SSOT's ownership matrix at all — it is a separate, smaller, lower-profile adapter used only by 6 already-`App`-namespaced controllers, distinct from the `/api/v1/*` `Src\CoreProject` compatibility runtime.
- **Correction, found while re-checking this spec against the SSOT:** the original draft's reachability trace for 11 files in `src/CoreProject/Services`/`Listeners` was done by grepping `routes/api.php`/`routes/web.php` directly and concluded 6 of 11 were dead code. This was wrong for the controller layer — `routes/api.php:1039` contains `require base_path('src/CoreProject/routes/api.php');`, a raw PHP `require` (not a `Route::` call), which the grep methodology didn't check. That file mounts **all 6** `Src\CoreProject\Controllers\*` classes (`ProjectController`, `TaskController`, `ComponentController`, `WorkTemplateController`, `TaskAssignmentController`, `BaselineController`) live at `/api/v1/*`. `ProjectController::index()`/`store()`/`show()`/`update()`/`destroy()` all operate on `Project::` (i.e. `Src\CoreProject\Models\Project`) directly. `ComponentController` does instantiate `Src\CoreProject\Services\ComponentService` (previously miscategorized as dead). This confirms the SSOT's own framing was correct and the original draft's "safe to migrate" conclusion was not — several of those 11 files are load-bearing for a real, tested compatibility surface.
- The same `require`-based pattern mounts 6 more `Src/*` module route files (`ChangeRequest`, `RBAC`, `DocumentManagement`, `Compensation`, `Notification`, `WorkTemplate`) — any future reachability trace in this codebase must check `routes/api.php` for `require base_path(...)` lines, not just `Route::` call sites, before concluding anything under `src/` is dead.
- Existing test infrastructure already locks this down: `tests/Feature/Architecture/ModuleOwnershipSourceInvariantTest.php` asserts the canonical `Api\ProjectController` uses `App\Models\Project`/`App\Services\ProjectService` and never references either legacy adapter, and cross-checks 6 documentation files for consistent ownership phrasing. `tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php` asserts `/api/v1/projects` stays owned by `Src\CoreProject\Controllers\ProjectController` by name — this test would fail if that compatibility route were ever un-mounted or re-pointed. `composer ssot:lint` runs a route dump, orphan-route detection, and a domain-ownership doc linter as a combined CI gate.

## 3. Scope (revised — discovery + forward-guard only)

### 3.1 Phase A — `LegacyProjectAdapter` consolidation (6 files, unchanged from the original draft, still safe)

These 6 files are `App`-namespaced controllers, not part of the `/api/v1/*` `Src\CoreProject` compatibility runtime, and not mentioned anywhere in the SSOT's ownership matrix or its protective tests. Swapping their `use` import from `Src\CoreProject\Models\LegacyProjectAdapter` to `App\Models\Project` directly is consistent with — not contradictory to — the SSOT's existing rule that the canonical controller must not reference `LegacyProjectAdapter`; it just extends the same already-established direction to 6 more files that happen to share the same adapter today:

- `app/Http/Controllers/Web/ProjectBulkController.php`
- `app/Http/Controllers/Web/DocumentManagementController.php`
- `app/Http/Controllers/Api/ProjectTemplateController.php`
- `app/Http/Controllers/Api/ProjectAnalyticsController.php`
- `app/Http/Controllers/Api/ProjectManagerController.php`
- `app/Http/Controllers/Api/ProjectMilestoneController.php`

`LegacyProjectAdapter.php` itself is left in place (file deletion unavailable this session — see §5) but becomes fully unreferenced by this batch.

### 3.2 Discovery phase (new, required — replaces the old "Phase B batch 1")

Produce a single, accurate, versioned reference inventory covering exactly these 4 classes, re-done with the corrected methodology (checking `require base_path(...)` route mounts, not just `Route::` call sites, for every file found):

- `App\Models\Project`
- `App\Models\ZenaProject`
- `Src\CoreProject\Models\Project`
- `Src\CoreProject\Models\LegacyProjectAdapter`

For every consuming file, record: file path, which of the 4 classes it references, and a reachability verdict (live route / live event-listener / live queue job / live Artisan command / genuinely dead) with the specific evidence line — matching the rigor already demonstrated in §2 above, not a bare grep count. Save as `docs/architecture/project-model-reference-inventory.md`.

This inventory is the "reference inventory... proves removal safe" precondition the SSOT's compatibility policy requires before any future slice may touch `Src\CoreProject\Models\Project`'s 50+ remaining references or its controllers. This slice **builds** that precondition; it does not yet act on it.

### 3.3 Forward-guard architecture test (new, required)

Add a test asserting that no file **outside** an explicit, versioned allowlist imports `Src\CoreProject\Models\Project`. Seed the allowlist from the discovery-phase inventory's exact current file list (so the test passes immediately against today's code) — its job is to fail loudly the moment a *new* app-owned module starts importing this class, not to shrink the existing list. Model this on the existing `deptrac.yaml` `skip_violations` baseline pattern already used elsewhere in this codebase (accept current debt explicitly, block new debt).

## 4. Out of scope (this slice)

- Any change to `Src\CoreProject\Services\*`, `Src\CoreProject\Listeners\*`, or `Src\CoreProject\Controllers\*` bodies — the original draft's plan to swap their `Project` import is dropped. Several are load-bearing for the tested `/api/v1/*` compatibility runtime; the rest need the discovery inventory (§3.2) to exist first before anyone can respons­ibly claim they're safe to touch.
- Any change to `App\Models\ZenaProject` or `Src\CoreProject\Models\LegacyProjectAdapter`'s own class bodies — both stay exactly as thin, empty subclasses. No new methods, no new behavior.
- Any new `Zena*`-prefixed alias class of any kind.
- Consolidating any Web/API controller pair (e.g. merging the canonical and compatibility Project controllers) — out of bounds for a model-reference slice per the SSOT's "do not consolidate Web/API controllers" guidance.
- Any database migration. This is a class-reference and documentation slice; no schema mismatch between the `projects` table and any of the 4 classes has been found or is being alleged.
- Deleting any file — unavailable this session (see §5).

## 5. Constraints

- **File deletion is unavailable this session.** Phase A's `LegacyProjectAdapter.php` becomes unreferenced but stays on disk.
- **`declare(strict_types=1)`** at the top of every modified/created file, matching existing convention.
- No behavior change anywhere in `Src\CoreProject\Models\Project`, `LegacyProjectAdapter`, or `ZenaProject` — Phase A only changes which class 6 *other* files point at; it does not modify any of the 4 classes themselves.

## 6. Completion gates (required before this slice is considered done)

All of the following must pass, in this order, before merge:

1. `php artisan route:list` — spot-check that `/api/v1/projects` and `/api/zena/projects` both still resolve to their SSOT-documented controllers (no accidental route drift from Phase A's import swaps, which should not touch routing at all, but this is the cheap confirmation that they didn't).
2. `composer ssot:lint` — the existing combined route-dump/orphan-route/domain-ownership-doc CI gate.
3. `tests/Feature/Architecture/ModuleOwnershipSourceInvariantTest.php` and `tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php` — must stay green; these are the existing tests that would fail first if the compatibility surface were disturbed.
4. Project-related feature tests (`tests/Feature/Api/ProjectApiTest.php`-style coverage if present, plus any test touching the 6 Phase-A controllers) — confirm Phase A's import swap is behavior-preserving.
5. The new forward-guard architecture test from §3.3 — must pass against the current codebase (proving the allowlist is accurate) and must be shown to fail if a throwaway new import of `Src\CoreProject\Models\Project` is added to a scratch file (proving it actually guards).

## 7. Deferred future work

A future slice — only after this one's discovery inventory (§3.2) exists and has been reviewed — can use that inventory to decide, file by file, which `Src\CoreProject\Models\Project` references are safe to migrate versus which are load-bearing compatibility runtime that must stay frozen per the SSOT. That slice should budget for the same `require base_path(...)`-aware reachability tracing this revision had to add mid-session, applied to every remaining directory (`src/CoreProject/Controllers`, `src/CoreProject/Middleware`, `src/CoreProject/Requests`, and the other `Src/*` modules) — do not assume any file is dead without checking both `Route::` call sites and `require base_path(...)` inclusions.
