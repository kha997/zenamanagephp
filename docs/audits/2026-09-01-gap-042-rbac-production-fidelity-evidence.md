# GAP-042 — RBAC Production Fidelity: Gate 1 Evidence

> Fact-finding pass for Gate 1. Records **facts** only, verified against exact `origin/main` head `ed8ca00b120064165f54c2ee9c8c44e946a0ef88`. No fix proposed, no code changed to produce this evidence. Read-only, evidence-first, per Owner instruction that GAP-042 is its own governed work item, independent of GAP-040/GAP-041/GAP-044/GAP-045.

## Attestation

- **Work ID:** GAP-042.
- **Baseline:** `origin/main @ ed8ca00b120064165f54c2ee9c8c44e946a0ef88`, verified by `git fetch origin` + `git log -1 --format='%H' origin/main` matching exactly, then `git checkout -b docs/GAP-042-gate1-production-fidelity origin/main` inside the worktree pinned to this investigation (`.claude/worktrees/GAP-043-performance-test-mysql-portability` — a shared worktree directory reused across gaps for this environment; the branch created inside it is what is new and pinned to `ed8ca00b`, not the directory name).
- **Method:** (a) direct read of every RBAC-adjacent model, middleware, controller, provider, and route file reachable from the register's cited surfaces, expanded by grep to the full transitive set; (b) direct read of migration history for every `zena_roles`/`zena_permissions`/`zena_role_permissions`/`zena_user_roles` reference in `database/migrations/`; (c) exhaustive repo-wide grep for any non-migration, non-test mechanism (seeders, deploy scripts, boot logic, raw SQL, views) that creates or aliases those four tables; (d) a **LIVE** clean-room reproduction: a disposable, throwaway MySQL 8.0 Docker container, a plain `.env` pointed at it, `php artisan migrate:fresh --force` run directly (no PHPUnit, no `Tests\TestCase`, no test bootstrap), followed by direct table inventory, a raw Eloquent probe script, a full HTTP round-trip via `php artisan serve` + `curl` with a real Sanctum token through the real middleware stack, and a discriminating same-database PHPUnit run of `tests/Feature/RbacApiTest.php` to observe what the test harness changes.

---

## 1. Two parallel RBAC model/table systems coexist in this codebase

| | Models | Table(s) | Confirmed live-routed? |
|---|---|---|---|
| **"Standard" RBAC** | `App\Models\Role`, `App\Models\Permission` | `roles`, `permissions`, `role_permissions`, `user_roles` (standard, post-rename names) | **Yes** — this is what `App\Models\User::roles()`/`hasRole()`/`hasPermission()`/`hasAnyRole()` query (`app/Models/User.php:93,127-134,182-190,194-198`), and what the actual `'rbac'` middleware alias resolves to (`App\Http\Middleware\RoleBasedAccessControlMiddleware`, `app/Http/Kernel.php:57`), via `App\Models\Permission::where('code', ...)` (`app/Http/Middleware/RoleBasedAccessControlMiddleware.php:9,151`). |
| **"Src\RBAC" module RBAC** | `Src\RBAC\Models\Role`, `Src\RBAC\Models\Permission` | `zena_roles`, `zena_permissions` (`src/RBAC/Models/Role.php:27`, `src/RBAC/Models/Permission.php:23`) | **Yes** — see §2. |

These are two structurally independent object graphs pointed at two different sets of tables, sharing only naming. The register's original hypothesis conflated them as one concern; this Gate 1 establishes they are separable, and that only the second is affected by the rename.

## 2. `Src\RBAC` module is genuinely live-mounted, not dead code

- `config/app.php:203` registers `Src\RBAC\Providers\RBACServiceProvider::class` as a real provider.
- That provider's `boot()` explicitly does **not** load its own routes (`// DISABLED (mounted in routes/api.php)`), because `routes/api.php:1028` does `require base_path('src/RBAC/routes/api.php');` unconditionally, for every request.
- `src/RBAC/routes/api.php` defines ~20 real API endpoints under `/api/v1/rbac/*` (roles CRUD, permissions CRUD, permission-matrix import/export, user effective-permissions, bulk role assignment, audit log, user/project role assignment), each gated by `auth:sanctum`, `tenant.isolation`, and a per-route `rbac:<permission-code>` middleware (the **standard** `RoleBasedAccessControlMiddleware`, §1 — this gate uses the correct, unaffected table).
- The controllers behind these routes — `Src\RBAC\Controllers\RoleController`, `PermissionController`, `AssignmentController`, `RBACController`, `PermissionMatrixController` — are constructed with `Src\RBAC\Services\RBACManager` (bound as a singleton in `RBACServiceProvider::register()`) and directly call `Src\RBAC\Models\Role::query()` / `Permission::query()` for their actual business logic (e.g. `src/RBAC/Controllers/RoleController.php:35`, `GET /api/v1/rbac/roles`).

So: the route-level authorization gate uses the correct (`roles`/`permissions`) tables, but the business logic behind that gate, once passed, queries the renamed-away (`zena_roles`/`zena_permissions`) tables. A request can pass authorization and still fail at the data layer.

## 3. Migration history — permanent, one-way rename, no later recreation

Repo-wide, exactly 3 migrations reference `zena_roles`/`zena_permissions` (confirmed via `grep -rl "zena_roles\|zena_permissions" database/migrations/`):

1. `database/migrations/2025_09_14_140000_create_zena_rbac_fixed.php` — creates `zena_roles`/`zena_permissions`/`zena_role_permissions`/`zena_user_roles`.
2. `database/migrations/2025_09_17_165315_add_tenant_id_to_zena_roles_table.php` — adds a column.
3. `database/migrations/2025_09_19_174648_rename_zena_tables_to_standard_names.php` — the **last** migration chronologically to touch these tables. Its `up()` (lines 16-40) does `Schema::rename('zena_roles', 'roles')` / `Schema::rename('zena_permissions', 'permissions')` / etc., guarded only by `if (Schema::hasTable($old) && !Schema::hasTable($new))`. No subsequent migration (checked against the full `database/migrations/` directory, chronologically past `2025_09_19`) ever creates `zena_roles`/`zena_permissions`/`zena_role_permissions`/`zena_user_roles` again. This rename is a `Schema::rename`, not a copy — after it runs, the `zena_`-prefixed names cease to exist entirely; nothing preserves them under both names.

This means every `migrate:fresh` (and every `migrate` against a schema that already ran this migration) leaves `roles`/`permissions` present and `zena_roles`/`zena_permissions` absent, permanently.

## 4. Exhaustive search: no other production mechanism creates or aliases these tables

Repo-wide grep for `zena_roles`/`zena_permissions` outside `database/migrations/`, `src/RBAC/Models/`:

```
tests/TestCase.php
tests/Support/GAP040ColdStartTransactionIsolationAssertions.php
tests/Feature/RbacApiTest.php
scripts/refactor_naming_convention.php
scripts/safe_refactoring_executor.php
scripts/analyze_zena_references.php
scripts/validate_refactoring.php
```

- `tests/TestCase.php` — test-only (§6).
- `tests/Support/GAP040ColdStartTransactionIsolationAssertions.php`, `tests/Feature/RbacApiTest.php` — test-only.
- `scripts/refactor_naming_convention.php`, `scripts/safe_refactoring_executor.php`, `scripts/analyze_zena_references.php`, `scripts/validate_refactoring.php` — one-off analysis/refactor tooling from the original `zena_*` → standard-name migration project; not referenced from any CI workflow, deploy script, or `composer.json` script, not invoked at boot, not a Laravel command registered anywhere (`grep -rn "refactor_naming_convention\|safe_refactoring_executor\|analyze_zena_references\|validate_refactoring" .github/ scripts/ci/ composer.json` returns no invocations outside their own files). These are historical developer utilities, not a live production mechanism.

No seeder (`grep -rln "zena_roles\|zena_permissions" database/seeders/` — empty), no deployment script, no `AppServiceProvider`/other provider `boot()` logic, no raw SQL migration step, no database view, and no compatibility shim of any kind was found anywhere in application, provider, or deployment code that creates or aliases `zena_roles`/`zena_permissions` for a real running application. This search was repo-wide (`.php`, `.sh`, `.sql` files), not limited to the directories the register originally cited.

## 5. LIVE reproduction — clean-room MySQL 8.0, no test harness

**Environment:** disposable `mysql:8.0` Docker container (`docker run --name gap042-mysql8 -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=zenamanage_gap042 -p 33061:3306 mysql:8.0`), confirmed `SELECT VERSION()` → `8.0.43`. A plain `.env` (copied from `.env.example`, `DB_CONNECTION=mysql`, pointed at the container) — no `tests/bootstrap.php`, no `ZENA_INVARIANTS_DB`, no PHPUnit process involved in this step.

### 5a. Migration

```
$ php artisan migrate:fresh --force
```
Ran clean, end to end, through every migration up to the current tip (`2026_08_30_100000_make_opportunities_service_category_nullable`). No error.

### 5b. Table inventory — direct SQL, independent of application code

```sql
SHOW TABLES LIKE 'zena%';
```
→ `zena_change_requests, zena_components, zena_drawings, zena_invoices, zena_material_requests, zena_ncrs, zena_project_users, zena_projects, zena_purchase_orders, zena_qc_inspections, zena_qc_plans, zena_rfis, zena_submittals, zena_task_assignments, zena_tasks`

**`zena_roles` and `zena_permissions` are absent from this list.**

```sql
SHOW TABLES LIKE '%role%'; SHOW TABLES LIKE '%permission%';
```
→ `project_user_roles, role_permissions, roles, system_user_roles, user_roles` / `permissions, role_permissions`

Only the standard names exist. This directly confirms §3's static analysis on a genuine, freshly-migrated MySQL 8.0 instance.

### 5c. LIVE — real Eloquent code path, no HTTP, no test harness

A standalone PHP script (not PHPUnit) bootstrapped the real Laravel application (`bootstrap/app.php`) against this exact database and called the same model classes the live controllers call:

```
=== Src\RBAC\Models\Role::query()->count() (production code path used by Src/RBAC/Controllers/RoleController::index) ===
EXCEPTION: Illuminate\Database\QueryException: SQLSTATE[42S02]: Base table or view not found: 1146
  Table 'zenamanage_gap042.zena_roles' doesn't exist
  (Connection: mysql, Host: 127.0.0.1, Port: 33061, Database: zenamanage_gap042,
   SQL: select count(*) as aggregate from `zena_roles`)

=== Src\RBAC\Models\Permission::query()->count() ===
EXCEPTION: Illuminate\Database\QueryException: SQLSTATE[42S02]: Base table or view not found: 1146
  Table 'zenamanage_gap042.zena_permissions' doesn't exist ...

=== App\Models\Role::query()->count() (standard, production auth path) ===
SUCCESS: count=0
```

### 5d. LIVE — full HTTP round-trip through the real middleware stack

`php artisan serve` against the same database. A real tenant, user, Sanctum token, and `App\Models\Role`/`Permission` grant (`role.view`, `permission.view`) were created via the real Eloquent models (not fixtures, not test factories) so the request would pass the **standard**, unaffected `rbac:role.view` authorization gate before reaching the controller.

```
$ curl -i -H "Authorization: Bearer <token>" -H "X-Tenant-ID: <tenant>" \
       -H "Accept: application/json" http://127.0.0.1:8931/api/v1/rbac/roles

HTTP/1.1 500 Internal Server Error
Content-Type: application/json
{"status":"error","success":false,"message":"SQLSTATE[42S02]: Base table or view not found: 1146
  Table 'zenamanage_gap042.zena_roles' doesn't exist ...","error":{"id":"req_vf5xAmky","code":"E500.SERVER_ERROR", ...}}
```

Identically reproduced on a second, independent live-mounted endpoint after granting the corresponding permission:

```
$ curl ... http://127.0.0.1:8931/api/v1/rbac/permissions
HTTP 500, SQLSTATE[42S02]: Base table or view not found: zena_permissions doesn't exist
```

This is an unambiguous, end-to-end, real-HTTP, real-auth, real-tenant, real-MySQL-8.0 production-fidelity failure: a legitimately authorized, legitimately authenticated, correctly tenant-scoped request to a live-mounted production API route fails with an uncaught `QueryException` surfaced as HTTP 500.

**Not part of this finding (adjacent, distinct bug, noted for completeness only):** `POST /api/v1/rbac/assignments/users/{user}/roles` returned `Call to undefined method Src\RBAC\Controllers\AssignmentController::getUserRoles()` — a separate, pre-existing controller/route-binding defect, unrelated to the `zena_*` rename, not investigated further here. A second live surface was also probed — `src/Compensation/Controllers/CompensationController` applies `Src\RBAC\Middleware\RBACMiddleware` (a *different* RBAC middleware class, `Src\RBAC\Middleware\RBACMiddleware`, which does route through `Src\RBAC\Services\RBACManager`/`zena_roles` internally) in its constructor — but that middleware's `handle()` signature requires a `$permission` argument the constructor-style `$this->middleware(RBACMiddleware::class)` call never supplies (no `:permission.code` suffix), so every request to that controller fails first with `Too few arguments to function ... handle()`, an independent, unrelated wiring bug that pre-empts ever reaching the `zena_roles` code path on that specific surface. This is reported as a discovered adjacent defect, not claimed as additional GAP-042 blast radius, since the intervening bug means GAP-042's specific mechanism is never actually reached there today.

### 5e. LIVE — what the test harness changes (discriminating comparison)

Same MySQL 8.0 database (already migrated, §5a), same `zena_roles`/`zena_permissions` absence (§5b) — now run through PHPUnit with `Tests\TestCase`:

```
$ ZENA_INVARIANTS_DB=mysql DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=33061 \
  DB_DATABASE=zenamanage_gap042 DB_USERNAME=root DB_PASSWORD=root \
  ./vendor/bin/phpunit tests/Feature/RbacApiTest.php

OK (9 tests, 104 assertions)
```

`tests/Feature/RbacApiTest.php` directly imports and exercises **both** `App\Models\Role`/`Permission` and `Src\RBAC\Models\Role`/`Permission` (`tests/Feature/RbacApiTest.php:9-12`) against the same live-mounted `/api/v1/rbac/*` routes probed in §5d — and passes cleanly, with no `QueryException`, no 500. The only difference between this run and §5d's failing HTTP reproduction is that this run goes through `Tests\TestCase::setUp()`, which calls `ensureSqliteZenaRbacTables()` (`tests/TestCase.php:214-`, GAP-040-fixed to route its DDL through an isolated `zena_ddl_bootstrap` connection so it does not implicit-commit `RefreshDatabase`'s transaction) — this method unconditionally `DROP`s and re-`CREATE`s `zena_roles`/`zena_permissions`/`zena_role_permissions`/`zena_user_roles` on every single test's `setUp()`, on every driver, regardless of whether a real migration ever produces them. This is confirmed, this pass, to be the **only** mechanism in the entire repository (§4) that makes `zena_roles`/`zena_permissions` exist on a MySQL connection that was migrated from `origin/main`'s real migrations.

One incidental observation from this same run: on a first attempt, the identical command failed non-deterministically with `SQLSTATE[HY000]: General error: 1412 Table definition has changed, please retry transaction` on the exact same `zena_permissions` table (a re-run with no code change passed cleanly). This is consistent with the `ensureSqliteZenaRbacTables()`/`zena_ddl_bootstrap` DDL-recreation shim itself being fragile under InnoDB's metadata-cache invalidation on repeated `DROP`/`CREATE` cycles against the same physical MySQL server process — noted as a secondary observation, not further root-caused here (out of GAP-042's scope; if pursued, it is adjacent to GAP-040/GAP-044's own already-approved territory, not new ground for GAP-042).

**Conclusion of this discriminating comparison:** current MySQL-parity CI coverage of the `Src\RBAC` module (`tests/Feature/RbacApiTest.php`, and any other `RefreshDatabase`-using test that touches `Src\RBAC\Models\Role`/`Permission`) can only pass because `Tests\TestCase`'s test-only DDL shim manufactures `zena_roles`/`zena_permissions` on every run. No production migration path (§3, §4) does this. Green CI on this surface is not evidence of production correctness — it is evidence that the test harness compensates for a schema production never has.

## 6. Blast radius

- **Directly confirmed broken (LIVE, §5d):** the entire `Src\RBAC` module's live-mounted `/api/v1/rbac/*` surface (~20 routes across `RoleController`, `PermissionController`, `PermissionMatrixController`, `RBACController`, `AssignmentController`) whose handlers call `Src\RBAC\Models\Role`/`Permission` directly. Confirmed for `GET /roles` and `GET /permissions`; the remaining ~18 routes were not each individually re-probed this pass but call the identical model classes by direct code inspection (`grep -n "Role::\|Permission::" src/RBAC/Controllers/*.php`) and are assessed as equally exposed, not independently proven live one-by-one.
- **Failure mode:** startup-time — **no**; authorization-time — **no** (the route-level `rbac:` gate itself uses the correct, unaffected tables and evaluates first); **data/query-time**, inside the controller action, after authorization has already succeeded. Surfaces as an uncaught `QueryException` → HTTP 500 (`E500.SERVER_ERROR` envelope), not a silent authorization failure, on this specific confirmed surface.
- **Tenant/security implications:** none observed as a data-leakage or cross-tenant-access risk — the failure is a hard crash (500), not a permissive fallback; no request that should have been denied is instead allowed, and no request returns another tenant's data. The risk is availability/functionality, not confidentiality/isolation, for this specific confirmed surface.
- **Is first real production deployment blocked?** Any tenant/user attempting real role or permission management through this module's live API (`/api/v1/rbac/roles`, `/api/v1/rbac/permissions`, and by code-path necessity the other ~18 routes in `src/RBAC/routes/api.php`) would receive a hard 500 on a freshly, correctly migrated production database — this module's core CRUD functionality is non-functional today, on current `main`, independent of any test result. Per §5, GAP-044's/GAP-045's release evidence, and every prior CI run — this has never been exercised on a genuinely production-fidelity schema in CI, because the only real-MySQL, `RefreshDatabase`-using coverage of this module (`RbacApiTest.php`) always runs with the masking shim active.
- **Adjacent, not-this-gap:** `Src\RBAC\Middleware\RBACMiddleware`-gated controllers (`src/Compensation/Controllers/CompensationController`, live-routed) are currently blocked from ever reaching the `zena_roles` code path by an unrelated, pre-existing wiring bug (§5d) — their exposure to GAP-042's specific mechanism could not be demonstrated today, though the underlying `RBACManager::hasPermission()` call chain (`src/RBAC/Services/RBACManager.php:195-199` → `calculateEffectivePermissions()` → `Src\RBAC\Models\Role`/`Permission`) would hit the identical `zena_roles` failure if that wiring bug were ever fixed independently. Not claimed as current GAP-042 exposure.
- **Not affected:** `App\Http\Controllers\{RoleController,PermissionController,AssignmentController,RBACController}.php` and `App\Services\{RBACManager,PermissionMatrixService}.php` exist as apparent duplicates of the `Src\RBAC` set but are **not routed anywhere** (`grep -rn "App\\Http\\Controllers\\RoleController" routes/` — no match); orphaned dead code, consistent with this repository's prior dead-code-sweep findings. Not part of this gap's live blast radius.

## 7. Summary of facts

1. Two independent RBAC model/table pairs exist: `App\Models\Role`/`Permission` (tables `roles`/`permissions`, live, correct) and `Src\RBAC\Models\Role`/`Permission` (tables `zena_roles`/`zena_permissions`, live-routed, broken).
2. `src/RBAC/routes/api.php` is unconditionally mounted in production (`routes/api.php:1028`), defining ~20 real API endpoints that use the second, broken pair internally, despite being gated at the route level by the first, correct pair.
3. `database/migrations/2025_09_19_174648_rename_zena_tables_to_standard_names.php` is a one-way `Schema::rename`, the last migration to touch these tables, chronologically; no later migration recreates `zena_roles`/`zena_permissions`.
4. Exhaustive repo-wide search found no seeder, deploy script, boot-time logic, raw SQL, view, or compatibility layer that creates or aliases these two tables for a real running application — only test-only code and inactive one-off refactor-tooling scripts reference them outside migrations.
5. **LIVE, this pass:** a clean `mysql:8.0` container, migrated purely via `php artisan migrate:fresh` (no test harness), does not contain `zena_roles`/`zena_permissions` (direct SQL inventory).
6. **LIVE, this pass:** the exact Eloquent model classes the live controllers use throw `QueryException`/`SQLSTATE[42S02]` (`Base table or view not found`) against that clean schema.
7. **LIVE, this pass:** a full, real HTTP request — real Sanctum auth, real tenant, real granted permission, through the real middleware stack — to the live-mounted `/api/v1/rbac/roles` and `/api/v1/rbac/permissions` endpoints returns HTTP 500 with that exact `QueryException` message, twice, on two independent endpoints.
8. **LIVE, this pass, discriminating:** the same database, same tables, run through `tests/Feature/RbacApiTest.php` (via `Tests\TestCase`) passes cleanly (`OK (9 tests, 104 assertions)`) — because `TestCase::ensureSqliteZenaRbacTables()` unconditionally recreates `zena_roles`/`zena_permissions` every `setUp()`, on every driver, a mechanism that exists only in test code and has no production analogue.
9. The route-level authorization gate for this module (`rbac:<code>` middleware) uses the correct, unaffected tables and is not itself broken — the failure is specifically in the controller business-logic layer, after authorization succeeds.
10. This is a genuine, reproducible, live production-fidelity defect — not a test-only artifact, and not merely a residual concern from GAP-040/GAP-044 (which explicitly scoped `zena_roles`/`zena_permissions` as "legacy pre-rename artifacts of a test-only helper, not the live `roles`/`permissions` tables the application actually uses" — that characterization is now shown, by this Gate 1's live evidence, to be incomplete: `Src\RBAC\Models\Role`/`Permission` **are** live-routed production code, not test-only). This does not reopen or edit any GAP-040/GAP-044 file; it is reported here as new, independently-scoped evidence under GAP-042.

## 8. Governance classification

GAP-042 is a genuine production-fidelity/RBAC-authorization defect in the `Src\RBAC` module's business-logic layer (not its route-level authorization gate, which is unaffected). Because remediation would touch a live-routed production RBAC surface (which tables `Src\RBAC\Models\Role`/`Permission` resolve to, or how the `Src\RBAC` module's business logic reaches role/permission data), any Gate 2 design work must consider whether it triggers the repository's Design Dependency Preflight before proceeding — this Gate 1 does not select a technical mechanism and does not itself trigger that preflight, but flags it as a likely Gate 2 consideration given the surface involves live authorization data, not test-only or schema-noise scope (contrast with GAP-040/GAP-044, which were correctly scoped as test-infrastructure-only).

## Explicit exclusions

Does not modify `src/RBAC/**`, `app/Http/Middleware/**`, any migration, any test file, `OPERATIONAL_GAP_REGISTER.md`, or any GAP-040/GAP-041/GAP-044/GAP-045 file or decision record. Does not select or propose a technical remediation mechanism (table rename reversal, model retargeting, compatibility view, code deletion of the orphaned `App\Http\Controllers` duplicates, or any other option) — that is a Gate 2 decision, not authorized by this Gate 1 submission. All live reproduction used a disposable, throwaway `mysql:8.0` Docker container and a local `.env`/`php artisan serve` process, both torn down after evidence capture; no production environment was created, accessed, or modified. Does not absorb GAP-041 (selector truthfulness) or GAP-045 (latency budget) scope.

## 9. Evidence sources

| # | Evidence | Type | Source |
|---|---|---|---|
| 1 | `src/RBAC/Models/Role.php`, `Permission.php` (`protected $table = 'zena_roles'/'zena_permissions'`) | **STATIC** | source at `origin/main @ ed8ca00b` |
| 2 | `app/Models/Role.php`, `Permission.php`, `app/Models/User.php` (`roles()`, `hasRole()`, `hasPermission()`, `hasAnyRole()`) | **STATIC** | same baseline |
| 3 | `app/Http/Kernel.php:57` (`'rbac' => RoleBasedAccessControlMiddleware::class`), `app/Http/Middleware/RoleBasedAccessControlMiddleware.php` (uses `App\Models\Permission`) | **STATIC** | same baseline |
| 4 | `config/app.php:203`, `src/RBAC/Providers/RBACServiceProvider.php`, `routes/api.php:1028`, `src/RBAC/routes/api.php` (full route list) | **STATIC** | same baseline |
| 5 | `src/RBAC/Controllers/RoleController.php:35` (`Role::query()`), `src/RBAC/Services/RBACManager.php` (`Src\RBAC\Models\Role`/`Permission` imports) | **STATIC** | same baseline |
| 6 | `database/migrations/2025_09_19_174648_rename_zena_tables_to_standard_names.php` full source | **STATIC** | same baseline |
| 7 | Repo-wide grep inventory: 3 migrations, 3 test files, 4 inactive one-off scripts reference `zena_roles`/`zena_permissions`; 0 seeders, 0 deploy scripts, 0 other production code | **STATIC** | `grep -rl "zena_roles\|zena_permissions" .` (excluding vendor/node_modules/.git), this pass |
| 8 | `mysql:8.0` container `SELECT VERSION()` → `8.0.43`; `php artisan migrate:fresh --force` full clean run | **LIVE** | disposable Docker container `gap042-mysql8`, this pass, torn down after capture |
| 9 | `SHOW TABLES LIKE 'zena%'` / `'%role%'` / `'%permission%'` direct SQL inventory | **LIVE** | same container, this pass |
| 10 | Standalone PHP script bootstrapping the real Laravel app, calling `Src\RBAC\Models\Role::query()->count()` / `Permission::query()->count()` / `App\Models\Role::query()->count()` | **LIVE** | same container, this pass |
| 11 | `php artisan serve` + `curl` full HTTP round-trip, real Sanctum token, real tenant, real granted `App\Models\Permission` — `GET /api/v1/rbac/roles` → HTTP 500 `SQLSTATE[42S02]`; `GET /api/v1/rbac/permissions` → HTTP 500 `SQLSTATE[42S02]` (identical) | **LIVE** | same container/server, this pass |
| 12 | `POST /api/v1/rbac/assignments/users/{user}/roles` → `Call to undefined method ...getUserRoles()` (adjacent, distinct bug, not this gap) | **LIVE** | same server, this pass |
| 13 | `GET /api/v1/compensation/tasks` (real Sanctum token, real granted `compensation.view`) → `Too few arguments to function Src\RBAC\Middleware\RBACMiddleware::handle()` (adjacent, distinct bug, pre-empts reaching `zena_roles`) | **LIVE** | same server, this pass |
| 14 | `ZENA_INVARIANTS_DB=mysql DB_CONNECTION=mysql ... ./vendor/bin/phpunit tests/Feature/RbacApiTest.php` against the identical, already-migrated MySQL database → `OK (9 tests, 104 assertions)` (2nd attempt; 1st attempt hit an unrelated, non-deterministic `SQLSTATE[HY000] 1412` on the same shim's DDL) | **LIVE** | same container, this pass |
| 15 | `tests/TestCase.php:200-242` (`ensureSqliteZenaRbacTables()`), confirmed GAP-040-fixed (`zena_ddl_bootstrap` isolated connection) — the only mechanism found anywhere that recreates these tables | **STATIC** | same baseline |
| 16 | `tests/Feature/RbacApiTest.php:9-12` (imports both `App\Models\Role`/`Permission` and `Src\RBAC\Models\Role`/`Permission`) | **STATIC** | same baseline |
| 17 | `App\Http\Controllers\{RoleController,PermissionController,AssignmentController,RBACController}.php`, `App\Services\{RBACManager,PermissionMatrixService}.php` confirmed unrouted (orphaned duplicates) | **STATIC** | `grep -rn "App\\Http\\Controllers\\RoleController" routes/` — no match, this pass |
| 18 | OPERATIONAL_GAP_REGISTER.md, GAP-042 row (original hypothesis) | **STATIC** (documentary) | `OPERATIONAL_GAP_REGISTER.md` line 33 |
| 19 | GAP-040 Gate 1 evidence/decision records (read-only reference, not modified) | **STATIC** (documentary) | `docs/audits/2026-08-20-gap-040-testcase-mysql-transaction-isolation-evidence.md`, `docs/owner-decisions/GAP-040/*` |
| 20 | GAP-044 Gate 1 evidence/decision records (read-only reference, not modified) | **STATIC** (documentary) | `docs/audits/2026-08-22-gap-044-savepoint-trans2-root-cause-evidence.md`, `docs/owner-decisions/GAP-044/*` |

## Final Recommendation

**A. REPRODUCED** — real production-fidelity defect, live-confirmed end-to-end (clean MySQL 8.0, no test harness, real HTTP, real auth, real tenant), independently distinguished from GAP-040/GAP-044's test-infrastructure-only scope. Recommend Gate 1 approval to proceed to Gate 2 design.

**Proposed smallest Gate 2 problem boundary (not designed here):** how `Src\RBAC\Models\Role`/`Permission` (and, transitively, `Src\RBAC\Services\RBACManager`/`RolePermission`) resolve their data — scoped to the `src/RBAC/` module's model layer and its ~20 live-routed `/api/v1/rbac/*` endpoints. Explicitly **not** in scope for Gate 2 unless separately authorized: the `App\Http\Controllers` orphaned duplicates (dead-code cleanup is a distinct, lower-stakes concern), the `Src\RBAC\Middleware\RBACMiddleware` constructor-wiring bug on `CompensationController` (independent defect, §5d/§6), the `AssignmentController::getUserRoles()` missing-method bug (independent defect, §5d), and any GAP-040/GAP-041/GAP-044/GAP-045 territory.
