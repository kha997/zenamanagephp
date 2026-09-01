---
work_id: GAP-042
plan_type: implementation
baseline: 673855f69a3633b64c378e965ae409ed3a098c50
gate2_spec: docs/superpowers/specs/2026-09-01-gap-042-rbac-model-consolidation-design.md
gate2_record: docs/owner-decisions/GAP-042/02-design.md
---

# GAP-042 — RBAC Production-Fidelity Implementation Plan

Implements Option A exactly as approved through Gate 2 Round 5. This plan maps every
approved acceptance item (§10, items 1-20) to concrete files, tests, RED/GREEN commands.

## 0. Pre-implementation notes / implementation-time rulings (logged, not scope changes)

1. **PermissionController is missing `show`/`update`/`destroy`.** §2c of the Gate-2 spec
   asserted these exist and are correctly wired (category A). Direct inspection at this
   baseline shows `src/RBAC/Controllers/PermissionController.php` only has `index`/`store` —
   the routes `GET|PUT|DELETE permissions/{permission}` are live but target nonexistent
   methods, an unconditional-500 defect of the exact same shape as §2a #4-#6
   (`AssignmentController`'s missing `assignments/projects/*` methods), which Gate 2 already
   designed a remediation pattern for ("add the missing controller methods... preserving the
   route's public signature"). This is a Gate-2 inventory error, not a business-semantics
   question — applying the identical, already-approved remediation pattern to this second
   instance within the same 5-controller approved boundary. Logged here, not silently
   absorbed. Implementation adds `show`/`update`/`destroy` to `PermissionController`
   following the same tenant-neutral pattern as the pre-existing `index`/`store` (permissions
   carry no `tenant_id` column, confirmed §2e/§3, so no tenant-scoping is required here,
   unlike `RoleController`).
2. **`RoleController::syncPermissions()` and `PermissionMatrixService::importFromCSV()` pass
   permission *codes* into `BelongsToMany::sync()`**, which requires related-model primary
   keys (`permissions.id`, a ULID), not `code` strings. This is a pre-existing latent bug
   that blocks Gate-2 acceptance item 20(d) (own-tenant `syncPermissions` must succeed) and
   item 10 (`role_permissions` sync must produce a verifiable row) from ever passing — no
   code path currently converts codes to ids before calling `sync()`. Fixed as part of the
   same method's implementation-surface change (§13a category A already includes
   `RoleController`/`PermissionMatrixService` for the tenant-scoping edit; this fix lives in
   the same touched methods).
3. **`RBACController::getUserEffectivePermissions()`/`checkUserPermission()` declare
   `int $userId`** under `declare(strict_types=1)`. User ids in this codebase are ULID
   strings; calling either route with a real user id causes an uncaught `TypeError` at the
   argument-binding boundary (before the method body's own `try/catch` can run) — a second,
   independent failure mode alongside the wrong-method-name defect §2a #1/#2 already
   documents. Both must be fixed together for item 7 to pass; changing `int` to `string` is
   part of "correct the call to use RBACManager's actual contracts" (§13a).
4. **`tests/TestCase.php::zenaRbacBootstrapSchema()` is shared infrastructure**, not
   RBAC-specific — `ensureInteractionLogsTable()`, `ensureProjectPhasesTable()`, and
   `ensureProjectTasksTable()` (unrelated tables, GAP-040/GAP-044 second-connection DDL
   technique) also call it. Removing the method itself would break those helpers, which are
   outside GAP-042's boundary. **Ruling:** remove only `ensureSqliteZenaRbacTables()` (the
   method that manufactures the impossible `zena_roles`/`zena_permissions`/
   `zena_role_permissions`/`zena_user_roles` schema) and its call site
   (`tests/TestCase.php:200`); keep `zenaRbacBootstrapSchema()` itself, since it is a generic
   second-connection-DDL helper with legitimate non-RBAC callers. This satisfies the
   design's actual intent ("test-only shim that manufactures production-impossible RBAC
   schema... must be removed") without an unauthorized, unrelated regression.

None of the above reinterprets any Gate-2 business/security decision; all are narrow,
logged, implementation-level factual corrections/completions of the already-approved
remediation pattern, consistent with the instruction to log rather than silently expand or
silently absorb scope.

## 1. File-by-file production changes

| # | File | Change |
|---|---|---|
| A1 | `src/RBAC/Models/Role.php` | `$table` → `roles`; `permissions()` pivot → `role_permissions`; `systemUsers()` pivot → `user_roles`; add `tenant_id`, `is_active` to `$fillable`; add `scopeTenantVisible($query, ?$tenantId)` grouped-predicate helper. |
| A2 | `src/RBAC/Models/Permission.php` | `$table` → `permissions`; `roles()` pivot → `role_permissions`. |
| A3 | `src/RBAC/Controllers/RoleController.php` | `index`/`show`/`update`/`destroy`/`syncPermissions`/`store` — grouped tenant-visibility predicate, server-derived `tenant_id` on create, reject `scope=system` on create, reject mutation of a resolved global role, fix `syncPermissions()` to resolve codes→ids before `sync()`. |
| A4 | `src/RBAC/Controllers/PermissionController.php` | Add `show`/`update`/`destroy` (§0.1). No tenant scoping (permissions have no `tenant_id`). |
| A5 | `src/RBAC/Controllers/RBACController.php` | `getUserEffectivePermissions()`/`checkUserPermission()`: `int $userId`→`string $userId`, call `RBACManager::calculateEffectivePermissions()`/`hasPermission()` (real method names/signatures). `getRolesByScope()`: grouped tenant-visibility predicate. `bulkAssignRoles()`: fix `assignProjectRole()` argument order, thread `$tenantId`, check boolean return before reporting `assigned: true`. |
| A6 | `src/RBAC/Controllers/AssignmentController.php` | Add `getProjectUsers`, `assignProjectRole`, `removeProjectRole` (§2a #4-#6, route targets already point at these names). `assignUserRoles(Request $request, ?string $user = null)`: route-param-authoritative identity (§2e) with HTTP 400 on route/body mismatch, works for both the `{user}`-parameterized and parameter-less `user-roles` mount. Thread tenant checks (§6a) into `assignUserRoles`/`removeUserRole`/the three new project methods. |
| A7 | `src/RBAC/Services/RBACManager.php` | Widen `assignSystemRole`/`assignCustomRole`/`assignProjectRole`/`revokeRole` to require `string $tenantId`; enforce §6a per-identity checks (target user tenant, target role scope+tenant, target project tenant) before any write, fail-closed, no partial writes. |
| B1 | `database/migrations/2026_09_02_000000_create_custom_user_roles_table.php` | New migration, exact shape from §2b. |
| C1 | `tests/TestCase.php` | Remove `ensureSqliteZenaRbacTables()` + call site only (§0.4). |
| C2 | `tests/Feature/RbacApiTest.php` | Remove `assertDatabaseHas/Missing('zena_roles', ...)` assumptions (now query `roles` — the converged table); keep as regression coverage of the pre-existing live surface. |
| C3 | `tests/Feature/GAP042RbacProductionFidelityTest.php` | New — the 20-item acceptance matrix. |

## 2. Acceptance-item → test mapping (all in `GAP042RbacProductionFidelityTest.php` unless noted)

| Item | Test method | RED reason (pre-fix) | GREEN condition |
|---|---|---|---|
| 1 | `test_migrate_fresh_produces_expected_schema` (MySQL run, see §4) | N/A — infra proof, not app-code RED/GREEN | tables present with real columns |
| 2 | `test_zena_tables_absent_after_migrate_fresh` (MySQL run) | same | `SHOW TABLES LIKE 'zena_%'` empty for roles/permissions |
| 3 | `test_authenticated_authorized_roles_and_permissions_endpoints_succeed` | 500 (table doesn't exist: `zena_roles`) | 200 with real data |
| 4 | `test_denied_access_without_permission_still_403` | passes before+after (regression guard) | 403 |
| 5 | `test_tenant_a_cannot_read_or_write_tenant_b_role` | Role model has no tenant scoping at all | 404 on all 4 ops, row unchanged (independent query) |
| 6 | `test_role_creation_binds_server_derived_tenant` | client-supplied tenant_id silently accepted (no scoping) | server tenant wins |
| 7 | `test_effective_permissions_and_check_permission_routes_return_200` | uncaught `TypeError`(int cast)/`Error` (undefined method) | 200 |
| 8 | `test_three_layer_permission_computation_system_custom_project` | custom_user_roles table doesn't exist → fatal | correct effective permission set per case |
| 9 | `test_custom_user_roles_service_level_write_read_mysql` (MySQL run) | table doesn't exist | write+read succeed |
| 10 | `test_assignment_paths_write_canonical_tables` | system_user_roles works but role points at zena_roles(missing) → FK/lookup fails; custom/project tables missing role FK target | independent row verified per table |
| 11 | `test_bulk_assign_project_scope_no_false_success` | reports `assigned:true` with 0 rows (arg-order bug) | row verified present |
| 12 | `test_tenant_a_cannot_assign_tenant_bs_role` | no role-tenant check exists | fail closed, 0 rows in all 3 tables |
| 13 | `test_tenant_a_cannot_assign_role_to_tenant_bs_user` | no user-tenant check exists | fail closed |
| 14 | `test_project_role_assignment_cannot_target_tenant_bs_project` | no project-tenant check exists | fail closed |
| 15 | `test_route_user_identity_not_overridable_by_body` | body `user_id` silently wins, no reconciliation | HTTP 400, no row for X or Y |
| 16 | (covered across 10-15 by independent multi-table absence assertions) | — | — |
| 17 | `test_project_assignment_routes_restored` | `Error: undefined method` (getProjectUsers/assignProjectRole/removeProjectRole don't exist) | 200/201/204, `project_user_roles` row verified |
| 18 | `test_shim_removed_class_has_no_zena_bootstrap_method` | shim exists today | reflection assertion: method absent from `Tests\TestCase` |
| 19 | `test_or_precedence_grouped_predicate_discriminates` | written to fail against literal ungrouped form; run mutation-style against current (pre-fix, ungrouped) `RoleController`/`RBACController::getRolesByScope` | passes only with grouped predicate |
| 20 | `test_global_role_readonly_through_tenant_surface` | global-role mutation currently unrestricted (once table exists) | (a) visible (b) POST scope=system rejected (c) PUT/DELETE/sync rejected+unchanged (d) own-tenant role still mutable (e) assignment of global role still succeeds |

## 3. RED → GREEN procedure

1. Land `C3` (new test file) and `C2`'s edits with **zero** production changes (A1-A7, B1)
   applied yet, but with `C1`'s shim-removal **also not yet applied** — run against current
   `main` code (sqlite, `ensureSqliteZenaRbacTables()` still active) to confirm the new tests
   fail for the intended reason (mostly: `zena_roles`/`zena_permissions` won't match the
   *canonical* `roles`/`permissions` factories the new test seeds through — expected RED).
2. Apply `C1` (delete the shim) alone, rerun — this exposes the true pre-fix failure mode
   (table-not-found / TypeError / Error-not-Exception / false-success / no-tenant-check),
   matching the RED reasons column above. This is the authoritative RED evidence.
3. Apply `A1`-`A7`, `B1` (all production + migration changes) together (they are mutually
   interdependent — e.g., A7's `$tenantId` requirement changes A6's call sites in the same
   commit).
4. Rerun full `GAP042RbacProductionFidelityTest` + `RbacApiTest` on sqlite — GREEN.
5. Rerun the same two files against real MySQL 8.0 (`phpunit.mysql.xml`, disposable
   container) for items 1, 2, 9 and to confirm no SQLite-only pass (MySQL-specific: strict
   FK enforcement, `SHOW TABLES`, real `migrate:fresh`).
6. Run the broader regression suite (`vendor/bin/phpunit`, excluding browser/performance
   groups per repo convention) to catch scope creep / unrelated breakage.
7. Sabotage/revert check on the two highest-risk regressions per Owner instruction:
   - Revert A6's route-identity fix (§2e) → rerun item 15 → must fail (proves discrimination).
   - Revert A1/A3's grouped-predicate fix (reintroduce literal
     `whereNull('tenant_id')->orWhere('tenant_id', $tenantId)->find($id)`) → rerun item 19 →
     must fail.

## 4. Genuine MySQL 8.0 evidence procedure

Reuse the disposable-container pattern from GAP-043/GAP-041 evidence:
```
docker run --rm -d --name gap042-mysql -e MYSQL_ALLOW_EMPTY_PASSWORD=yes \
  -e MYSQL_DATABASE=zenamanage_test -p <free-port>:3306 mysql:8.0
# wait for healthy
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=<free-port> DB_DATABASE=zenamanage_test \
  DB_USERNAME=root DB_PASSWORD= php artisan migrate:fresh --env=testing --force
# inventory tables, run phpunit.mysql.xml-targeted feature tests
docker stop gap042-mysql
```

## 5. Verification before Gate 3

- `php artisan route:list --path=rbac` — diff against baseline route count/targets; confirm
  no new `Route::` registrations for `assign/system|custom|project` or unprefixed
  `effective-permissions`; confirm the 3 project-assignment routes resolve to real methods.
- `git diff --stat 673855f6...HEAD` reviewed for scope creep.
- PHPStan on changed files (`phpstan.neon`, existing baseline).
- Full regression suite run, pre-existing-failure baseline recorded and honestly reported.
