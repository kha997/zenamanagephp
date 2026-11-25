# OWN-2026-024 (O-17h) — H3 local implementation: remove the global RBAC bypass

**Status: see §11.** H3 is **local, uncommitted and unpushed**. PR #422 stays Draft and MERGE_NO_GO at remote head `c94ff67c0`. Nothing was deployed, there was no production or staging access, and no production grant was changed. The original checkout (`a0381f5e3`, 39 entries) was not touched.

- **Owner decision (2026-11-25):** "H2B_ACCEPTED / H3_LOCAL_IMPLEMENTATION_APPROVED".
- **Prior documents:** 100 (assessment), 101 (H0/H1 design and the measured dependency inventory), 102 (H2a), 103 (H2b).
- **Out of scope and untouched:** the `DocumentPolicy` and `SidebarConfigPolicy` shortcuts (H4); BLK-1, CMP-1, AUD-1, O8-1, O16-1.

## 1. Gate 0: baseline preserved

| Check | Result |
|---|---|
| PR #422 head / state | `c94ff67c0`, Draft |
| H2a / H2b work | present in the worktree, unchanged except the H3 items in §2 |
| H2b migration on disposable databases | applied by `RefreshDatabase` (SQLite) and by `migrate:fresh` on the scratch MariaDB (170 tables, migration row present, `role_permissions.grant_source` present) |
| RED/GREEN evidence | H2b numbers (103 §8) used as the baseline; RED set 7 before H3 |
| Production / staging access | none |
| Original checkout | untouched, 39 entries |

## 2. Changed files (H3 only; H2a/H2b items are in 102 §1 and 103 §2)

**Runtime**
- `app/Http/Middleware/RoleBasedAccessControlMiddleware.php`: bypass removed (§3).
- `app/Support/Rbac/RbacSchema.php`:
  - new `assertMigrated()`;
  - only a **present** H2b table is memoized, so a long-lived worker started before the migration starts honouring revocations once the migration lands (Gate 7 finding 4).
- `app/Support/Rbac/PermissionRevocations.php`:
  - `revoke()` / `regrant()` call `assertMigrated()`;
  - `isRevoked()` / `supersedeInsideTransaction()` return false before the migration (no revocation can exist yet). Before this, a pre-migration HTTP role sync would have failed on the missing table.
- `app/Support/Rbac/PlatformOperators.php`: `grant()` / `revoke()` call `assertMigrated()`.
- `src/RBAC/Controllers/RoleController.php`: `syncPermissions` refuses to remove `admin.company.manage` from the Owner role (lock-out protection, Gate 7 finding 2). Defense in depth only: the global Owner role is already read-only over HTTP (404).
- `database/migrations/2026_11_24_200000_own_2026_024_h2b_rbac_revocations_and_platform_operators.php`: `down()` refuses while active revocations or Platform Operator records exist (§7).

**Tests**
- New `tests/Feature/Recovery/UatRecovery07dO17hH3Test.php` (8 tests, §5).
- Quarantine set changed from 7 to 2:
  - `UatRecovery07O17hExplicitRbacTest`: 5 group attributes removed; comments only, **no assertion changed**;
  - `UatRecovery07O17hQuarantineGuardTest`;
  - `QUARANTINE-O17H.md`.
- `UatRecovery07cO17hH2bTest`: two comments updated for the H3 state.
- `tests/Traits/TenantUserFactoryTrait.php`: new helpers `grantThroughTestRole()` (test-owned role, never `super_admin`) and `provisionTestPlatformOperator()` (explicit `platform.*` code plus an active ledger row; rejects non-platform codes).
- 19 fixture files: §4 and `evidence/o17h-h3-test-migration-inventory.tsv`.

## 3. Exact bypass removal

```diff
     private function checkAccess($user, string $roleOrPermission, Request $request, ?string $projectParam = null): bool
     {
-        // Super admin has access to everything
-        if ($user->isSuperAdmin()) {
-            return true;
-        }
+        // OWN-2026-024 (O-17h) H3, Owner Gate 2 Option B: no role-name short-circuit. The role named `super_admin` (the company
+        // Owner) is authorized exactly like every other role: by its canonical, revocable grants (User::hasPermission, which
+        // also applies durable revocations and, for platform.* codes, the active Platform Operator ledger).
```

A permission argument is now decided only by:
- `User::hasPermission()`;
- canonical grants through `user_roles` → `role_permissions` → `permissions.name`;
- active revocations, which are excluded at check time;
- for `platform.*` codes, an active `platform_operators` row as well.

Object policies and tenant middleware still apply after the gate. A static guard test fails if `isSuperAdmin`, `super_admin` or `hasRole(` reappears in `checkAccess()`.

**Not changed (reported, §6):**
- `getUserRoles()` still maps `isSuperAdmin()` to the legacy role argument `admin`. `admin` is not among the role arguments the middleware accepts (`project_manager`, `team_member`, `client`, `viewer`), so this is unreachable.
- `handleGeneralAccess()`, the parameterless `rbac` gate (O-8).
- The project-manager dashboard role shortcut.

## 4. Test migration (Gate 2)

**Measured, not estimated.** These are sequential SQLite runs, with the Backup classes excluded as in CI. The baseline is the H2b tree, where every suite passes except the 5 pre-existing Unit failures. Removing the short-circuit alone gave:

| Suite | Failures |
|---|---|
| Feature (incl. Architecture and Recovery) | 42 |
| Api | 81 |
| Unit | 5 (pre-existing) |
| Integration | 0 |

The 101 measurement listed 116 middleware-dependent tests:
- **115 reproduced.**
- **1 was already fixed by H2a:** `AdminRolesCompanyAndSessionTest`.
- **5 new ones appeared**, all on host routes that H2a re-gated from the non-permission `rbac:admin` (which their personas held as a fake `admin` code) to `platform.*`: `LegacyRouteRollbackTest` ×3, `AccessibilityTest` ×1, `ButtonAuthorizationTest` ×1. The bypass hid this.
- **3 failures came from editing files mid-run** and pass on a clean rerun: the quarantine guard ×1 and `07c` ×2.

**120 tests were migrated.** The 2 policy (M3) tests are untouched (H4).

**Method.** For each failing file, the middleware's own "Access denied" log line gave the exact `rbac:<code>` its actor was refused. Only those codes were granted, then the file was rerun until green, iterating because a test stops at its first denial.

| Approach | Files |
|---|---|
| Grant exactly the route codes to the file's existing persona | 11 |
| Grant through a **test-owned role**: codes the Owner must not hold under D3 (`component.*`, `change_request.create`) | 3 |
| **Test Platform Operator** for host routes (explicit code plus ledger row) | 6 |
| Fixture bug the bypass hid | 1 |
| Assertion asserted the bypass itself, now superseded | 1 |

**Why this is not privilege inflation.**
- In these files every fixture user shares the same role named `super_admin`, and under the bypass that role passed **every** gate. Each grant added here is a strict subset of what the persona had before.
- So no denied case can have become an allowed one. The files' own 403/404 assertions (tenant and object boundaries, restricted users) passed under the bypass and still pass.
- The runs with logging show the remaining denials come from exactly those expected-denial tests.
- No fixture grants "all permissions".
- No D3-excluded code was placed on the role named `super_admin`.

**Fixture bugs exposed** in `Feature/ChangeRequestApiTest`:
1. its test role was attached only through `system_user_roles`, which authorization never reads;
2. its codes were created through `Src\RBAC\Models\Permission`, whose `$fillable` has no `name`, so they were nameless, and `hasPermission` matches on `name`.

Both are fixed in the fixture. The second also affects the product; see §6, PRD-1.

**Superseded assertion (preserved here, Gate 2).** In `tests/Feature/Buttons/ButtonAuthorizationTest.php::test_super_admin_has_full_access`, the original first assertion was:

```php
// Test admin access
$response = $this->getFollowingRedirects('/admin/dashboard');
$response->assertStatus(200);
```

It asserted that the tenant Owner role name opens a host page (`rbac:platform.ops.view`), which is exactly the removed bypass. It is replaced by the explicit guard `$this->getJson('/admin/dashboard')->assertForbidden();`. The tenant-function assertions in the same test are unchanged.

## 5. Security acceptance (Gate 3)

| Item | Evidence | Result |
|---|---|---|
| A. Zero-grant `super_admin` passes no gate | H1 `test_zero_grant_owner_is_denied_by_every_permission_gate` (every `rbac:<permission>` route in the table) | GREEN, released from quarantine |
| B. Explicit grants open only their operations | 07d `test_explicit_grants_open_exactly_the_gates_they_name`. A narrow role passes exactly its 2 codes. The full Owner passes exactly its granted codes, never a `platform.*` one. A role-less user passes nothing. | GREEN |
| C. Revocation holds, including after reseeding | 07d revocation test: `rbac:permission revoke`, `production:rbac` ×2, then 403 at the route; a stray pivot row still 403. Also H1 revocation tests and 07c D4 tests. | GREEN |
| D. Tenant Owner without operator record denied on host routes | 07d platform test (all 62 platform-gated routes); H1 `test_tenant_owner_cannot_reach_host_level_routes_by_role_name` | GREEN |
| E. Platform permission without operator status denied | 07d: platform codes inserted directly on the Owner role, 0 of 62 pass | GREEN |
| F. Operator needs both permission **and** active status | 07d: ledger row alone passes 0 of 62; provisioned operator passes 62 of 62 and no tenant code; after `revoke`, 0 | GREEN |
| G. Role administrator cannot self-promote to Owner or Platform Operator | 07d (single and bulk paths, table snapshot unchanged); H1 RBAC-ESC-1; 07c D5 | GREEN |
| H. Cross-tenant authorization denied | H1 `test_owner_cannot_reach_another_tenants_objects`; 07c cross-tenant appointment; fixture files' cross-tenant 404 tests | GREEN |
| I. O-17 creation and membership rules intact | Recovery O-17 classes (`UatRecovery06…`) and O-18 baseline green on SQLite and MariaDB | GREEN |
| J. Legitimate Owner administration works through explicit grants | H1 `test_granted_owner_permissions_work_without_the_role_name`, `test_provisioned_owner_keeps_granted_workflows`; 07c preservation and appointment tests | GREEN |
| K. 62 platform routes protected, 16 retired absent | 07b mapping and retired tests; 07d (exactly 62 platform-gated routes) | GREEN |
| L. The five H3 RED tests are GREEN | assertions unchanged, group attribute removed | **5 of 5 GREEN** |
| M. H4 tests stay RED and quarantined | `--group uat-recovery-07-o17h-red`: 2 tests, 2 failures; guard enforces exactly these 2 | as intended |
| N. Missing or invalid migration state fails closed | 07d pre-migration test. Ordinary grants keep working; platform gate shut; `rbac:permission revoke` and `platform:operator grant` exit 1; nothing persisted | GREEN |
| Owner lock-out | 07d: Owner-authority removal over HTTP refused (404 global read-only, 403 guard); `revoke()` refuses it (H2b); last-Owner protection (07c) | GREEN |
| Rollback | 07d: `down()` refused while a revocation is active | GREEN |

## 6. Additional bypass discovery (Gate 4)

No `Gate::before` or `Gate::after` hook exists. Only `SidebarConfigPolicy` defines `before()` (H4). `User::hasPermission` and `hasAnyPermission` have no role-name branch. Nothing overrides `can()`.

| ID | Shortcut | Live? | Negates H3? | Proposed owner |
|---|---|---|---|---|
| M2 | `handleGeneralAccess()`: parameterless `rbac` admits 12 legacy role names, and `users.role` through `User::hasAnyRole` | Yes, on routes with only that gate | No: those routes never had a permission gate | O-8 |
| M3 | `DocumentPolicy` and `SidebarConfigPolicy` role-name and `users.role` fallbacks | Yes | Partly: object-level grants on documents and sidebar | **H4** |
| M3b | `ChangeRequestPolicy`, `ComponentPolicy`, `NcrPolicy`, `InvitationPolicy` (plus `RfiPolicy`, `TemplatePolicy`, `QcPlanPolicy`, `QcInspectionPolicy`, `UserPolicy`, `NotificationPolicy`, `TaskPolicy` legacy branches) use `hasRole([... 'super_admin' ...])` | Per 100, most have no controller callers; any caller would grant by name **where a route lacks an `rbac:<code>`** | Not on `rbac:`-gated routes | **Recommend adding to H4 scope** (Owner decision) |
| M4 | In-controller role checks: `RfiController::userHasAdminRole`, `LegacyRouteMonitoringController` (`hasRole('admin')`, an extra restriction), `ProjectManagerController` / `DashboardController` (`hasRole('project_manager')`), `Api\ProjectController` `hasRole(['SuperAdmin','Admin'])` (bypasses the team-member check on project metrics) | Yes | No, behind the route gate | Separate gate |
| M6 | Middleware PM-dashboard shortcut: `hasRole('project_manager')` passes `api/project-manager/dashboard*` before any argument check | Yes, read-only dashboard | No (not `super_admin`) | O-8 |
| M5 | Dead: `HasRoles::hasPermission` map; `TenantScopeMiddleware`, `TenantAbilityMiddleware`, `AdminOnly*`; parallel evaluators `src/RBAC/Services/RBACManager::hasPermission`, `app/Services/RBACManager`, `app/Services/PermissionService`; middlewares `RBACMiddleware`, `TaskAccessMiddleware`, `ComponentAccessMiddleware`; unrouted `app/Http/Controllers/RoleController@sync` | Not registered or routed | Would bypass revocations and the ledger **if re-wired** | H5 (optional deletion) |
| UI-1 | `AuthenticationController::getUserRoles/getUserPermissions` reports a role-name permission list to clients | Display only | No server-side effect | Separate |
| PRD-1 | `Src\RBAC\Models\Permission` does not mass-assign `name`, so a permission created through `POST api/v1/rbac/permissions` can never authorize | Yes | Fails closed (no escalation) | Separate fix |
| MDB-1 | `ProjectPhase::getEstimatedDurationAttribute(): int` returns a string on MySQL, giving 500 on 4 `ProjectTaskControllerTest` endpoints on MariaDB, after the permission gate passes | Yes, pre-existing | No | Separate fix |
| SEED-1 | `RolePermissionSeeder:156` and `ZenaRbacSeeder:307/309` still `sync()` outside `RbacSeedGrants` | Not in `production:rbac` | No: authorization-time revocation still denies | Separate (dev/test seeders) |
| OP-1 | `PlatformOperators::isActive` ignores `users.is_active`. Deactivating a user does not close its ledger row; login and `EnsureUserIsActive` (web) block, but API tokens depend on other checks | Yes | No escalation | Separate |

## 7. Migration and rollback (Gate 6)

Evidence: the isolated MariaDB 10.4.28 scratch server (`evidence/o17h-h3-verification.txt`), plus 07d on both engines.

1. **Existing grants unchanged by migration:** `production:rbac` gives 350 grants. Rollback and re-apply leave the grant set **identical** (checksum over every `role_id:permission_id` pair).
2. **No platform grant is seeded implicitly:** 0 `platform.*` grants after `production:rbac`, twice. No operator record exists by default.
3. **Revocations survive repeated seeding:** proven on both engines (07c, 07d, H1).
4. **Rollback cannot silently restore revoked access:**
   - with an active revocation, `migrate:rollback --step=1` **exits 1**: "Refusing to roll back OWN-2026-024 H2b: 1 active revocation / Platform Operator record(s) …";
   - both tables and the column remain;
   - a following `production:rbac` does **not** restore the revoked grant (0);
   - with no active records, rollback works as before.
5. **Upgrade order (explicit):**
   - deploy code;
   - `php artisan migrate`, an additive `expand` migration;
   - `php artisan production:rbac`, which is required because any `rbac:<code>` without a `permissions` row now denies everyone, the Owner included. H1 `test_every_rbac_argument_is_a_real_permission_code` checks the catalogue covers every route argument;
   - restart workers. Optional now, because an absent table is no longer cached.

   **Pre-deploy (from 101):** run H0 under the D7 conditions and review it, so the production Owner role is known to hold the expected grants before the bypass disappears. **Otherwise the Owner may be locked out at deploy.**
6. **No implicit migration or backfill:** the migration only creates tables and a nullable column.

**Blocker requiring an Owner/runbook gate (RB-1).** A **code-only rollback** to any pre-H2b release (the runbook's rollback for `expand` migrations, `docs/runbooks/gap-049-migration-safety.md`):
- restores the global `super_admin` bypass;
- ships seeders that do not read `role_permission_revocations`.

The next `production:rbac` on that old code would **re-add every durably revoked grant**. The `down()` guard cannot prevent this, because the old code does not run it.

Safe rollback therefore needs a manual gate. Before rolling code back past H2b/H3:
- list active revocations (`php artisan rbac:permission list`);
- do not run `production:rbac` on the old code;
- re-apply the revocations by hand afterwards.

Alternatively, the Owner can decide that H2b+H3 are roll-forward-only. **Not decided here.**

## 8. Results (Gate 5)

**SQLite (sequential, final tree)**

| Suite | H2b baseline | H3 |
|---|---|---|
| Feature (incl. Architecture and Recovery) | 2279, 0 failures | **2292, 0 failures** (33 skipped); +13 = the 8 new 07d tests + the 5 tests released from the RED quarantine |
| Api (`tests/Feature/Api`) | 825, 0 | **825, 0** |
| Unit | 948, 5 (pre-existing `EvidenceFreshnessLiveCheck`) | **960, the same 5**. +12 are Backup-related Unit tests the H2b run's filter excluded; they pass |
| Integration | 31, 0 | **31, 0** |
| RED group | 7 / 7 failing | **2 / 2 failing (H4 only)** |
| Recovery (inside Feature) | 198 | 211 (+8 07d, +5 released), 0 failures |

**Static checks**

| Check | Result |
|---|---|
| PHPStan | OK, no errors |
| SSOT lint | pass |
| `owner-governance-lint` and `--enforce-gate-ordering` | pass |
| `lint-mysql-claim-truthfulness` | pass |
| php-cs-fixer, H3-touched files | clean. `RoleController` has 1 finding, the same count as at HEAD |

**Isolated MariaDB 10.4.28** (scratch server on a local socket and port; `ZENA_INVARIANTS_DB=mysql`, `migrate:fresh`, `-c phpunit.mysql.xml`; confirmed by table count and migration row; now shut down)

| Run | Result |
|---|---|
| O-17h security files (H1 non-RED, 07b, 07c, 07d, O-18 baseline, quarantine guard) | **52 / 52 OK** |
| RED group | 2 / 2 failing (H4), as on SQLite |
| Recovery, with the same exclusions as ordinary CI | 211 tests, **2 failures**, both the known `split_percent` defect in `UatRecovery04b` (same as H2b) |
| Recovery without the `uat-recovery-01-quarantine` exclusion | the 2 extra failures are that pre-existing quarantine, not regressions |
| The 19 migrated fixture files | 186 tests, **4 failures**, all `ProjectTaskControllerTest` 500s from MDB-1 (pre-existing type bug, past the RBAC gate) |
| Gate 6 rollback round trip | §7 |

**Not verified:**
- **MySQL 8.** No MySQL 8 parity is claimed from MariaDB 10.4.
- Dusk browser tests (no chromedriver locally; CI only). The browser fixture persona may also depend on the bypass, so a CI run is required before any merge.
- The `performance` group (excluded by `phpunit.xml`; `PerformanceMonitoringTest` uses the PM dashboard).
- Full Feature/Api suites on MariaDB.

## 9. Independent security review (Gate 7)

A separate reviewer with fresh context, read-only (no shell, so the diff was read from the files), was asked to challenge the change. **Verdict: no Critical or High findings; nothing blocks H3 locally verified.** Its findings and what was done:

| # | Finding (severity) | Disposition |
|---|---|---|
| 1 | Dev/test seeders still `sync()` outside `RbacSeedGrants` (Medium, pre-existing) | Reported, SEED-1. Authorization-time revocation still denies. Not in `production:rbac`. |
| 2 | HTTP role sync could remove `admin.company.manage` from the Owner role, which would lock out Owner authority (Medium) | **Fixed** (RoleController guard plus 07d test). On verification, the global Owner role is already read-only over HTTP (404), so the guard is defense in depth. |
| 3 | Deploy ordering: unseeded `rbac:<code>` now denies everyone (Medium, introduced by H3) | Documented (§7.5). The catalogue-coverage test already exists (H1). |
| 4 | `RbacSchema` cached an absent table for the process lifetime; a long-lived worker would ignore revocations after the migration (Low) | **Fixed** (only presence is cached). |
| 5 | Operator ledger ignores `users.is_active` (Low) | Reported, OP-1. |
| 6 | HTTP sync rewrites `grant_source` to `manual` for retained codes (Low) | Reported. Provenance only, no enforcement effect. |
| 7 | Other policies still grant by role name (Low) | Reported, M3b. Recommend H4 scope. |
| 8 | Dormant parallel evaluators ignore revocations and the ledger if re-wired (Low) | Reported, M5. |
| 9 | Fixture helper roles accumulate codes if called repeatedly within one test (Low) | Checked: every call site calls once per test (`ComponentApiTest` grants one code in each of four separate tests). No over-grant. |

The reviewer could not run `git diff` or read every fixture line. §4 and the inventory TSV cover the fixtures.

## 10. Remaining H4 blockers and residual risks

**H4 must remove**
- the `DocumentPolicy` role-name and `users.role` fallbacks;
- `SidebarConfigPolicy::before()`;
- the `TaskPolicy::view()` alignment (101 §6).

Then release the 2 RED tests and handle the 2 M3 fixture tests from 101: `PolicyTest::document_policy_allows_proper_access`, and `DocumentPolicyTest::test_super_admin_can_perform_all_actions`, which asserts the bypass and goes to the Owner. **Recommendation for the H4 gate:** include M3b.

**Residual risks**
- **R-1 (from H2b):** a `role.assign` holder can still give *ordinary* roles carrying permissions it does not hold itself.
- **R-2:** AUD-1 remainder.
- **R-3:** CLI audit `user_id` is the subject user.
- **R-4:** `lockForUpdate` is a no-op on SQLite.
- **RB-1:** code rollback (§7).
- **Lock-out at deploy** if H0 is not run first.
- **Browser and Dusk fixtures unmeasured.**

## 11. Result

**O17H_H3_LOCAL_VERIFIED** (SQLite full suites plus isolated MariaDB 10.4; **MySQL 8 not verified**; Dusk not run).

**Owner decisions needed before integration**
1. Accept the superseded `ButtonAuthorizationTest` assertion (§4).
2. RB-1 rollback policy: manual gate or roll-forward-only.
3. H0 execution under D7 before any deploy.
4. H4 scope, including M3b.
5. Commit authority for H2a+H2b+H3.

Nothing is committed or pushed. PR #422 remains Draft and MERGE_NO_GO.
