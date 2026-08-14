---
work_id: GAP-035
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: null
  plan: null
  branch: docs/GAP-035-route-name-collision-gate1-prep
  pr: https://github.com/kha997/zenamanagephp/pull/261
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-14T08:39:08+07:00"
  owner_response_reference: "Owner Gate 2 round 2 decision — APPROVE, recorded in-session on 2026-08-14 at actual wall-clock time 2026-08-14T08:39:08+07:00, against design head d9f55edf544a3fb3f5c351bea62d4418e6f42e79. Binding approved design: Groups 1-5 preserve projects.store/show/update/destroy and tasks.store unchanged on the currently-winning routes/api.php apiResource routes; rename only the colliding routes/web.php side to web.projects.store/show/update/destroy and web.tasks.store. Group 6 (12 api.v1.dashboard.* routes): binding final names api.v1.dashboard.users-v2.index/store/profile/show/update/destroy, api.v1.dashboard.tasks.assignments.index/store, api.v1.dashboard.assignments.update/destroy, api.v1.dashboard.users.assignments.index/stats — a subgroup ->as(...) may compose the prefix but every one of the 12 leaves must have its own explicit terminal ->name(...); ->as() alone is not acceptable. Group 7 (5 routes): binding final names api.zena.debug.simple-test/minimal-auth-test/sanctum-auth-test/me-test/auth-test. Scope lock: implementation may change route-name declarations only, plus tests needed to prove the approved contract; for all 27 affected route entries preserve HTTP method, URI, middleware, handler/action, tenant/RBAC/security behavior, and request/response business behavior; do not merge/delete/redirect/consolidate routes, do not modify Project/Task controller behavior or closure bodies, do not modify models/services/business lifecycle, do not change Service Line semantics, do not rename the already-unique api.zena.projects.*/api.zena.tasks.store, do not touch GAP-011. Permanent regression guard: add a committed generic architecture test dynamically inspecting the complete runtime route collection (not an allowlist of the 7 known groups) that fails if any non-empty route name occurs more than once; before technical-ready declaration, mutation-proof the COMMITTED test itself (not solely the pre-implementation standalone-script proof) — introduce a temporary brand-new duplicate route name, prove the committed guard fails, revert byte-clean, prove the guard passes. Required implementation verification under both APP_ENV=testing and APP_ENV=production: zero duplicate non-empty route names across the complete route collection including package/vendor routes; all 27 approved entries have the expected final names; method/URI/middleware/handler unchanged from the approved baseline; the five preserved names still resolve to the same API-side endpoints; php artisan route:cache exits successfully; route inspection after cache generation succeeds and preserves the expected 27 entries; route:clear runs in teardown/finally and leaves no generated route cache behind, including failure paths; run the identified URI-consumer regression tests and relevant route/security tests, then the broader/full suite before Gate 3 where feasible. Anticipated runtime scope: routes/web.php, routes/api.php, routes/api_zena.php, new/updated architecture tests — any additional runtime/source file requires explicit evidence it is necessary to satisfy the approved contract, no casual scope broadening. Gate 2 approval authorizes implementation/testing only. No mark-ready. No merge. No release. No deployment. No self-approval of Gate 3."
  reconciliation_required: false
supersedes: "docs/owner-decisions/GAP-035/02-design.md"
superseded_by: null
timestamps:
  created_at: "2026-08-14T08:00:20+07:00"
  updated_at: "2026-08-14T08:39:08+07:00"
generated_by: agent
---

## OWNER GATE 2: APPROVED (round 2) — routing/deployability design, implementation now authorized

This packet does not implement anything. No route, middleware, or handler has changed. Round 1 (`docs/owner-decisions/GAP-035/02-design.md`, frozen) received Owner **CHANGES REQUESTED** — the overall direction was accepted (preserve winning API-side names, rename only the losing side, assign unique names to unnamed children, preserve all behavior, no HTTP surface consolidation, GAP-011 untouched); ten corrections are applied below. §0 maps each to where it is resolved.

## 0. Round-2 corrections applied

| # | Owner correction (round 1 decision) | Where resolved |
|---|---|---|
| 1 | Freeze round 1, supersede with this file | `02-design.md` frozen in the same commit as this file |
| 2 | Group 6: every one of the 12 leaves needs its own explicit unique `->name()` terminal segment, not a subgroup `->as()` alone | §3f — rewritten with explicit per-leaf names and corrected mechanics |
| 3 | Group 7: confirmed as originally proposed | §3g — unchanged, restated |
| 4 | Web-side prefix `legacy.` → `web.` | §3a-3e — renamed throughout |
| 5 | Affected-route count 24 → 27 | §1a (new), §4, §5 |
| 6 | Acceptance proof tightened — bind method+URI+middleware+handler+name for all 27, plus vendor-inclusive duplicate check, preserved-name resolution, cache lifecycle | §4 — rewritten |
| 7 | Add a permanent, generic duplicate-name architecture guard; mutation-prove it now | §6 (new) — designed and mutation-proved in this round |
| 8 | Preserve the Gate 1 evidence correction, don't rewrite the frozen table | §1 — carried forward unchanged |
| 9 | PR #261 title corrected to not narrow to Project routes | Done outside this file — see §8 |
| 10 | Re-present with full detail | This document |

## 1. Evidence correction (unchanged from round 1 — carried forward, not rewritten)

The colliding API-side routes are generated by `Route::apiResource('projects', \App\Http\Controllers\Api\ProjectController::class)` at **`routes/api.php:268`** and `Route::apiResource('tasks', \App\Http\Controllers\Api\TaskController::class)` at **`routes/api.php:582`** — **not** by `routes/api_zena.php`. `routes/api_zena.php:217-221,266`'s own `projects.*`/`tasks.store`-looking declarations are actually named `api.zena.projects.*`/`api.zena.tasks.store` (inheriting the `api.zena.` group prefix from `routes/api_zena.php:12`), mounted at `api/zena/projects*`/`api/zena/tasks` — **already unique, not colliding, out of renaming scope**. Gate 1's original evidence table (`docs/owner-decisions/GAP-035/01-request.md`) is not rewritten; this correction is recorded there as an addendum and repeated here per Owner instruction #8.

### 1a. Affected-route count corrected: 27, not 24

Round 1 undercounted by presenting the projects/tasks pairs as "24 affected URIs" without a clear accounting. The correct count:

| Source | Count |
|---|---|
| `projects.store`/`show`/`update`/`destroy`/`tasks.store` — 5 duplicate pairs, 2 routes each | **10** |
| `api.v1.dashboard.` — currently-unnamed children | **12** |
| `api.zena.` — currently-unnamed children | **5** |
| **Total affected route entries** | **27** |

## 2. Named-route compatibility findings (unchanged from round 1, restated)

Real named-route consumers (`route(...)`, `redirect()->route(...)`, `to_route(...)`, `URL::route(...)`, and test helpers wrapping them) were searched across `app/`, `resources/`, `tests/`, `routes/` for all 7 group names — **none found for any of them**. `app/Services/PermissionService.php:166,168,170`'s `'projects.update'`/`'projects.show'`/`'projects.destroy'` are permission-map array keys, not `route()` calls — confirmed coincidental, do not constrain naming, per the Owner's binding rule.

Current named-route-resolution winners, verified live via `tinker`:

| Name | `route(...)` output | Winner |
|---|---|---|
| `projects.store` | `http://localhost/api/projects` | `routes/api.php:268` `apiResource` → `Api\ProjectController@store` |
| `projects.show` | `http://localhost/api/projects/{project}` | same → `Api\ProjectController@show` |
| `projects.update` | `http://localhost/api/projects/{project}` | same → `Api\ProjectController@update` |
| `projects.destroy` | `http://localhost/api/projects/{project}` | same → `Api\ProjectController@destroy` |
| `tasks.store` | `http://localhost/api/tasks` | `routes/api.php:582` `apiResource` → `Api\TaskController@store` |

Real URI-based (non-named) consumers of the web-side routes, unaffected by renaming since URIs don't change:

| Route (URI) | Real consumers |
|---|---|
| `POST /projects` | `tests/Feature/Buttons/ButtonCRUDTest.php` (2), `tests/Feature/CsrfProtectionTest.php` (2), `tests/Feature/SecurityFeaturesTest.php` (1), `tests/Feature/Buttons/ButtonAuthorizationTest.php` (2) |
| `POST /tasks` | `tests/Feature/CsrfProtectionTest.php`, `tests/Feature/Legacy/LegacyTaskCreationPersistsTest.php` |

## 3. Route-by-route design matrix (27 entries)

### 3a-3e. Groups 1-5 — `projects.*` / `tasks.store` (10 entries)

API-side names **unchanged** (currently-winning, per §2). Web-side renamed from round 1's `legacy.*` to **`web.*`**, per Owner correction #4 — these routes are live and directly tested, not authorized to be classified as deprecated/legacy; `web.*` is a neutral, factual namespace naming their declaration surface (`routes/web.php`), nothing more.

| # | Current name | Method + URI | Declaration source | Middleware | Handler | Proposed final name | Compatibility impact |
|---|---|---|---|---|---|---|---|
| 1 | `projects.store` | `POST api/projects` | `routes/api.php:268` | `api, auth:sanctum, tenant.isolation, rbac` | `Api\ProjectController@store` | **`projects.store`** (unchanged) | None |
| 2 | `projects.store` | `POST projects` | `routes/web.php:513` | `web, auth, tenant.isolation, rbac:project.create` | Closure | **`web.projects.store`** | None — name string only |
| 3 | `projects.show` | `GET\|HEAD api/projects/{project}` | `routes/api.php:268` | `api, auth:sanctum, tenant.isolation, rbac` | `Api\ProjectController@show` | **`projects.show`** (unchanged) | None |
| 4 | `projects.show` | `GET\|HEAD projects/{project}` | `routes/web.php` (~519) | `web, auth, tenant.isolation, rbac:project.view` | Closure | **`web.projects.show`** | None |
| 5 | `projects.update` | `PUT\|PATCH api/projects/{project}` | `routes/api.php:268` | `api, auth:sanctum, tenant.isolation, rbac` | `Api\ProjectController@update` | **`projects.update`** (unchanged) | None |
| 6 | `projects.update` | `PUT projects/{project}` | `routes/web.php` (~527) | `web, auth, tenant.isolation, rbac:project.update` | Closure | **`web.projects.update`** | None |
| 7 | `projects.destroy` | `DELETE api/projects/{project}` | `routes/api.php:268` | `api, auth:sanctum, tenant.isolation, rbac` | `Api\ProjectController@destroy` | **`projects.destroy`** (unchanged) | None |
| 8 | `projects.destroy` | `DELETE projects/{project}` | `routes/web.php:544` | `web, auth, tenant.isolation, rbac:project.delete` | Closure | **`web.projects.destroy`** | None |
| 9 | `tasks.store` | `POST api/tasks` | `routes/api.php:582` | `api, auth:sanctum, tenant.isolation, rbac` | `Api\TaskController@store` | **`tasks.store`** (unchanged) | None |
| 10 | `tasks.store` | `POST tasks` | `routes/web.php:560` | `web, auth, tenant.isolation, rbac:task.create` | `Web\TaskController@store` | **`web.tasks.store`** | None |

Reason preserved for every row: only the internal `->name()` string is added/changed; method, URI, middleware, and handler are byte-identical before and after. No verified real consumer (§2) depends on any of these names, so no test requires a behavioral update — only the 5 web-side rows' own name string changes, invisible to HTTP clients and to the URI-based consumers listed in §2.

### 3f. Group 6 — `api.v1.dashboard.` (12 entries, corrected mechanics)

**Correction from round 1:** a subgroup `->as('users-v2.')` prefix alone is not sufficient — Laravel still requires each leaf route to carry its own terminal `->name(...)` segment, or that leaf remains unnamed (empty suffix) exactly as today. The design now specifies **both**: an `->as(...)` prefix on each of the four existing nested `Route::prefix(...)->group(...)` calls (for readability/consistency with the surrounding file's convention) **and** an explicit `->name(...)` on every one of the 12 leaf routes.

Conceptually:
```php
Route::prefix('users-v2')->as('users-v2.')->middleware(['production.security'])->group(function () {
    Route::get('/', [UserControllerV2::class, 'index'])->name('index');
    Route::post('/', [UserControllerV2::class, 'store'])->name('store');
    Route::get('/profile', [UserControllerV2::class, 'profile'])->name('profile');
    Route::get('/{id}', [UserControllerV2::class, 'show'])->name('show');
    Route::put('/{id}', [UserControllerV2::class, 'update'])->name('update');
    Route::delete('/{id}', [UserControllerV2::class, 'destroy'])->name('destroy');
});
```
combined with the outer `->as('api.v1.')` (already at `routes/api.php:785`) and `->as('dashboard.')` (already at `routes/api.php:840`) produces the full names below. **`->as()` alone, without the leaf `->name()`, is explicitly rejected as insufficient** — it would still leave every leaf's terminal segment empty and the group's own prefix (e.g. `api.v1.dashboard.users-v2.`) would itself still be the shared, colliding name across all 6 leaves under it.

| # | Sub-group | Declaration source | Method + URI | Handler | Approved final name |
|---|---|---|---|---|---|
| 11 | `users-v2` | `routes/api.php:928` | `GET\|HEAD /users-v2` | `UserControllerV2@index` | `api.v1.dashboard.users-v2.index` |
| 12 | `users-v2` | `routes/api.php:929` | `POST /users-v2` | `UserControllerV2@store` | `api.v1.dashboard.users-v2.store` |
| 13 | `users-v2` | `routes/api.php:930` | `GET\|HEAD /users-v2/profile` | `UserControllerV2@profile` | `api.v1.dashboard.users-v2.profile` |
| 14 | `users-v2` | `routes/api.php:931` | `GET\|HEAD /users-v2/{id}` | `UserControllerV2@show` | `api.v1.dashboard.users-v2.show` |
| 15 | `users-v2` | `routes/api.php:932` | `PUT /users-v2/{id}` | `UserControllerV2@update` | `api.v1.dashboard.users-v2.update` |
| 16 | `users-v2` | `routes/api.php:933` | `DELETE /users-v2/{id}` | `UserControllerV2@destroy` | `api.v1.dashboard.users-v2.destroy` |
| 17 | `tasks/{taskId}/assignments` | `routes/api.php:942` | `GET\|HEAD /tasks/{taskId}/assignments` | `TaskAssignmentController@getTaskAssignments` | `api.v1.dashboard.tasks.assignments.index` |
| 18 | `tasks/{taskId}/assignments` | `routes/api.php:943` | `POST /tasks/{taskId}/assignments` | `TaskAssignmentController@store` | `api.v1.dashboard.tasks.assignments.store` |
| 19 | `assignments/{assignmentId}` | `routes/api.php:947` | `PUT /assignments/{assignmentId}` | `TaskAssignmentController@update` | `api.v1.dashboard.assignments.update` |
| 20 | `assignments/{assignmentId}` | `routes/api.php:948` | `DELETE /assignments/{assignmentId}` | `TaskAssignmentController@destroy` | `api.v1.dashboard.assignments.destroy` |
| 21 | `users/{userId}/assignments` | `routes/api.php:952` | `GET\|HEAD /users/{userId}/assignments` | `TaskAssignmentController@getUserAssignments` | `api.v1.dashboard.users.assignments.index` |
| 22 | `users/{userId}/assignments` | `routes/api.php:953` | `GET\|HEAD /users/{userId}/assignments/stats` | `TaskAssignmentController@getUserStats` | `api.v1.dashboard.users.assignments.stats` |

All 12 middleware stacks unchanged from round 1's record: `auth:sanctum, tenant.isolation, rbac` (duplicated due to nested group middleware, pre-existing and not touched by this design) + `input.sanitization, error.envelope` (+ `production.security` for the `users-v2` sub-group only). **Collision check against already-valid descendants:** the outer `api.v1.` / `api.v1.dashboard.` prefixes already have other correctly-named children elsewhere in the file (e.g. `api.v1.dashboard.customization.*`, `api.v1.dashboard.role_based.*`, `api.v1.dashboard.simple.*`) — none of the 12 proposed names above collide with any of them (verified by exact-string comparison against the full route dump).

### 3g. Group 7 — `api.zena.` (5 entries, confirmed as proposed)

Unchanged from round 1, confirmed by Owner correction #3 — no restructuring, no behavior change, individual explicit names:

| # | Declaration source | Method + URI | Handler | Approved final name |
|---|---|---|---|---|
| 23 | `routes/api_zena.php:78` | `GET api/zena/simple-test` | Closure | `api.zena.debug.simple-test` |
| 24 | `routes/api_zena.php:89` | `GET api/zena/minimal-auth-test` | Closure | `api.zena.debug.minimal-auth-test` |
| 25 | `routes/api_zena.php:110` | `GET api/zena/sanctum-auth-test` | Closure | `api.zena.debug.sanctum-auth-test` |
| 26 | `routes/api_zena.php:131` | `GET api/zena/me-test` | Closure | `api.zena.debug.me-test` |
| 27 | `routes/api_zena.php:173` | `GET api/zena/auth-test` | Closure | `api.zena.debug.auth-test` |

Implementation: an individual `->name('debug.simple-test')` etc. added to each of the 5 closures (they don't share a common inner group of their own — each sits directly inside the `auth:sanctum`-middleware group at `routes/api_zena.php:66`). **Collision check:** the same `api.zena.` group already has correctly-named children (`api.zena.auth.logout`, `api.zena.auth.me`, `api.zena.health`, etc., each via their own explicit `->name(...)`) — none collide with the proposed `api.zena.debug.*` names.

## 4. Acceptance contract (tightened, all 27 entries, to be proven at implementation time — not proven yet)

For **every one of the 27 entries in §3**, before/after verification must bind, as a single comparable tuple:

- HTTP method
- URI
- middleware (full resolved stack, not just the group-level declaration)
- handler/action (controller@method or closure identity)
- final route name

**The only permitted difference between before and after is the route-name field** — method, URI, middleware, and handler must be byte-identical for all 27 rows. This is proven by a new test that captures the full `route:list --json` tuple for each of the 27 URIs before implementation (already captured in §3 above, from the current, unmodified route table) and asserts an exact match on method/URI/middleware/handler after, with only `name` allowed to differ per the approved mapping.

In addition, under **both `APP_ENV=testing` and `APP_ENV=production`**:

- [ ] Zero duplicate non-empty route names across the **complete registered route collection, including vendor routes** (i.e., `route:list --json` **without** `--except-vendor`, matching how §1a's inventory was re-verified at Gate 1 approval).
- [ ] All five preserved names (`projects.store`, `projects.show`, `projects.update`, `projects.destroy`, `tasks.store`) resolve via `route(...)` to the **same API-side endpoints** they resolve to today (verified in §2).
- [ ] `php artisan route:cache` succeeds.
- [ ] The **cached** `route:list` output remains valid and matches the uncached output for all 27 entries (method/URI/middleware/handler identical; this is the check GAP-011's Gate 3 is separately blocked on today).
- [ ] `route:clear` restores a deterministic clean state afterward (no leftover `bootstrap/cache/routes-v7.php`), run in an isolated process per test, always executed including on failure — same discipline already established in GAP-011's `tests/Architecture/DebugRouteBoundaryInvariantTest.php`.
- [ ] Full relevant regression suite green, including every consumer test identified in §2 (`ButtonCRUDTest`, `CsrfProtectionTest`, `SecurityFeaturesTest`, `ButtonAuthorizationTest`, `LegacyTaskCreationPersistsTest`) and `RouteHygieneTest` (asserts middleware by URI prefix, not by name — unaffected by renaming, but must still pass).
- [ ] No Project/Task business behavior changed — no controller, closure body, service, or model touched; only `->name()`/`->as()` calls added or changed across `routes/web.php`, `routes/api.php`, `routes/api_zena.php`.

## 5. Anticipated implementation files (for scoping only — not authorized to create/edit yet)

- `routes/web.php` — add `->name('web.projects.store')` etc. to the 5 standalone closures (lines ~513-560). Name string only.
- `routes/api.php` — add `->as(...)` + per-leaf `->name(...)` to the 4 nested groups at lines 927, 941, 946, 951 (12 names total, per §3f). Name strings only.
- `routes/api_zena.php` — add `->name('debug.simple-test')` etc. to the 5 closures at lines 78, 89, 110, 131, 173. Name strings only.
- New architecture test (final class name chosen at implementation time) — the permanent generic duplicate-name guard (§6) plus the 27-entry before/after binding and the `route:cache` lifecycle assertions from §4.
- No change anticipated to any controller, closure body, service, model, migration, or Blade view.

## 6. Permanent duplicate-name architecture guard (new, designed and mutation-proved this round)

Round 1 only proved `route:cache` would go green once. Per Owner correction #7, the implementation must also add a **permanent, generic** regression guard — not hard-coded to the 7 known groups — so a future contributor adding any new route with an accidentally-duplicate name fails CI automatically.

**Design:** a test that boots the application, calls `Route::getRoutes()`, collects every route's `getName()`, filters out `null`/empty names (an empty name is not itself a violation — group-prefix-only "leftover" names are exactly what §3f/§3g eliminate, but a route legitimately having no name at all is fine and common elsewhere in the app), groups by name, and asserts **zero groups with more than one route** — dynamically, for the entire route collection, not an enumerated allowlist of the 7 groups this design fixes.

```php
$byName = [];
foreach (Route::getRoutes() as $route) {
    $name = $route->getName();
    if ($name === null || $name === '') continue;
    $byName[$name][] = $route;
}
$duplicates = array_filter($byName, fn ($routes) => count($routes) > 1);
$this->assertSame([], array_keys($duplicates), 'Duplicate route name(s) found: ' . implode(', ', array_keys($duplicates)));
```

### Mutation-proof (performed this round, in the worktree, not committed)

1. **Baseline, current unmodified state:** ran the guard logic (as a standalone script against a live `route:list --json` dump) — correctly reported all 7 known groups (`projects.store`, `projects.show`, `projects.update`, `projects.destroy`, `tasks.store`, `api.v1.dashboard.`, `api.zena.`). This confirms the guard logic is not a no-op and does detect the real, current problem.
2. **Temporary mutation:** appended two new routes to `routes/web.php`, each named `->name('gap035-mutation-test')` (a brand-new name, not one of the 7 known groups) — `Route::get('/gap035-mutation-a', ...)` and `Route::get('/gap035-mutation-b', ...)`.
3. **Re-ran the guard:** now reported **8** duplicate groups — the original 7 plus `gap035-mutation-test` — proving the guard generically catches a **new** violation it was never told about in advance, not just the 7 it was designed against.
4. **Reverted:** restored `routes/web.php` from a clean backup; `diff` against the pre-mutation file confirmed byte-identical; re-ran the guard once more — back to exactly 7 groups, matching the true current state.

This proves the guard's generality (item 7's requirement) without touching any real route, and without leaving any trace in the working tree — `git status` shows no changes from this exercise.

## 7. Alternatives considered (carried forward from round 1, unchanged)

- Renaming the API-side (winning) routes instead of the web-side ones: rejected — higher risk for identical implementation cost, since API-side names are what `route()` currently resolves to.
- `->name()` per leaf vs. relying on `->as()` alone for groups 6/7: round 1 proposed `->as()` alone was sufficient; **corrected this round** — both mechanisms are required together for Group 6 (§3f), matching what the Owner specified.

## 8. PR #261 metadata correction (Owner instruction #9)

PR #261's title is corrected outside this file (metadata-only edit, no new commit) from the Project-routes-narrowing title to: **"Work ID: GAP-035 — Duplicate route names block Laravel route:cache and production deployment"** — matching the original, correctly-scoped problem statement (all 7 groups, not just the Project/Task pairs).

## 9. Scope exclusions (unchanged, reconfirmed round 2)

- No HTTP surface consolidation, deletion, redirect, or merging of the `projects.*`/`tasks.*` handler pairs.
- No Project/Task lifecycle or business-semantic change.
- No Service Line semantic change.
- No cleanup of legacy Project architecture beyond the 27 name assignments in §3.
- GAP-011 remains completely untouched (separate branch/PR — `docs/GAP-011-debug-route-cleanup-gate1-prep` / PR #260).
- The unrelated Redis/`DashboardApiTest` finding remains an unallocated observation, no Work ID.

## Decision recorded

**APPROVED** by the Owner, round 2, `2026-08-14T08:39:08+07:00` (verbatim decision text in `decision_provenance.owner_response_reference` above), against design head `d9f55edf544a3fb3f5c351bea62d4418e6f42e79`. Gate 2 is closed. Implementation and testing are authorized within the scope stated in §5 (routes/web.php, routes/api.php, routes/api_zena.php, new/updated architecture tests) and the binding names in §3. Mark-ready, merge, release, and deployment remain **not authorized** — Gate 3 is mandatory before any of those and is **NOT STARTED**. No `02-design-v3.md` was created for this approval, per the Owner's explicit instruction — this file (`02-design-v2.md`) is the final, approved Gate 2 packet.

## What the owner was NOT asked to decide

Not asked to re-approve the overall direction (preserve winning names, rename only the losing side, GAP-011 untouched) — already accepted in round 1 and not reopened. Not asked to decide anything about merging handler pairs, Project/Task business behavior, Service Line semantics, or GAP-011 — all remain out of scope.
