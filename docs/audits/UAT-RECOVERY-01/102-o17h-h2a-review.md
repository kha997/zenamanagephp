# OWN-2026-024 (O-17h) — H2a local implementation: independent review package

**Status: see §11.** H2a is **local, uncommitted and unpushed**. PR #422 stays Draft and MERGE_NO_GO at `c94ff67c0`. Nothing was deployed or changed in production. The legacy `super_admin` middleware bypass is **still present**, so **H2a alone is not the security fix**: the Owner role still reaches every `platform.*` route by name until H3.

- **Owner decision (2026-11-24):** "GATE2_CONDITIONAL_APPROVED / H2A_LOCAL_IMPLEMENTATION_AUTHORIZED", decisions D1–D7. Recorded verbatim in `docs/owner-decisions/OWN-2026-024/02-design.md`, which is now `approved` with the conditions in its body. No Gate 3 record was created.
- **Design basis:** `101-o17h-h0-h1-design.md`.
- **H0/H1 evidence:** preserved unchanged, except the H1 test file, as listed in §6.
- **Worktree:** isolated, `integration/uat-recovery-s1-s4d` at `c94ff67c0`. The original checkout (`a0381f5e3`, 39 entries) was not touched.

## 1. Exact changed-file list (vs `c94ff67c0`)

**Runtime (authorization / routing / seeding):**

| File | Change |
|---|---|
| `app/Support/PlatformPermissions.php` (new) | The 5 platform codes and helpers: `isPlatformCode`, `isPlatformModule`, `containsPlatformCode`, `roleHoldsPlatformPermission`, `excludeFrom` |
| `database/seeders/PlatformPermissionCatalogueSeeder.php` (new) | Creates the 5 codes (`name` = `code`, module `platform`). Grants nothing. |
| `database/seeders/ZenaPermissionsSeeder.php` | Calls the catalogue seeder. Codes are **not** added to `CANONICAL_PERMISSIONS`, so no alias seeder grants them. |
| `database/seeders/PermissionSeeder.php` | "System Admin = all permissions" now uses `excludeFrom()`, i.e. all **non-platform** permissions |
| `database/seeders/TestDatabaseSeeder.php`, `RolePermissionSeeder.php`, `ZenaRbacSeeder.php` | The same exclusion on their "all permissions" / `'*'` paths (legacy seeders, not run by `DatabaseSeeder` or `production:rbac`) |
| `app/Console/Commands/ProductionRbacCommand.php` | D3: `BASELINE_GRANTS['super_admin'] = ['notification.manage_rules']` (additive); docblock |
| `src/RBAC/Controllers/RoleController.php` | `syncPermissions` returns 422 if the request contains a `platform.*` code or the role already holds one |
| `src/RBAC/Controllers/PermissionController.php` | `store` refuses module `platform` (400). `update` / `destroy` return 422 for a `platform.*` permission. |
| `src/RBAC/Services/PermissionMatrixService.php` | CSV rows with a `platform.*` code or module are skipped with an error, so nothing is created or attached |
| `src/RBAC/Services/RBACManager.php` | `assignSystemRole` / `assignCustomRole` / `assignProjectRole` return false for a role that holds any `platform.*` code (covers single, project and bulk assignment) |
| `routes/api.php`, `routes/web.php` | 80 `rbac:admin` routes: 16 retired, 62 re-gated on `platform.*`, 2 legacy redirects on `admin.company.manage` (§3, §4) |

**Tests, test configuration and lint configuration:**

| File | Change |
|---|---|
| `tests/Feature/Recovery/UatRecovery07O17hExplicitRbacTest.php` (new since H1) | H1 file. Released `test_every_rbac_argument_is_a_real_permission_code` from RED (green since H2a). The host-route test now counts `platform.*` routes (62) instead of `rbac:admin` (80). Pinned Owner grant count 222 → **223** (D3) plus D3 deny-list assertions. |
| `tests/Feature/Recovery/UatRecovery07bO17hH2aPlatformTest.php` (new) | 7 H2a guard tests (§6) |
| `tests/Feature/Recovery/UatRecovery07O17hQuarantineGuardTest.php` (new) | Enforces the exact 9-test RED manifest |
| `tests/Feature/Recovery/QUARANTINE-O17H.md` (new) | The quarantine manifest: each RED test, what it proves, and the slice that releases it. Flags the bypass. |
| `phpunit.xml` | `<group>uat-recovery-07-o17h-red</group>` exclusion (group name only, guarded) |
| `tests/Feature/Recovery/UatRecovery05O18PermissionBaselineTest.php` | **Assertion amended (Owner review requested):** "System Admin keeps every permission" → "every **non-platform** permission", plus "holds no platform permission". This is the D1/D6 consequence; nothing else changed. |
| `tests/Feature/RouteMiddleware/V1LegacyRouteHardeningContractTest.php` | The expected stack required `rbac:admin` (a non-permission). It now requires an explicit `rbac:platform.*` gate, which is stricter. |
| `tests/Traits/AuthenticationTrait.php` | `createRbacAdminUser()` (the test "admin" persona, which used a test-only permission literally coded `admin`) also gets the 5 platform codes explicitly, because the 22 cache, WebSocket and integration API tests exercise host endpoints as an operator. Test fixture only. |
| `scripts/ssot/allow_orphan_routes.txt` | The 14 retired paths as `NEGATIVE_PROBE_LEGACY_SURFACE` (the governed pattern used by GAP-058 and the dead-milestone retirement) |

**Docs:** this file, `evidence/o17h-h2a-host-route-mapping.tsv`, Gate 2 packet update, register row.

## 2. Platform permission matrix (D1)

| Code | Category | Routes | Granted to |
|---|---|---|---|
| `platform.ops.view` | read-only host information (incl. GAP-057 check-only launch POSTs, legacy console pages) | 43 | **nobody** |
| `platform.cache.manage` | mutating: shared cache invalidate / warm / clear | 5 | **nobody** |
| `platform.realtime.broadcast` | mutating: publish broadcasts / system notifications / create system alert | 4 | **nobody** |
| `platform.maintenance.execute` | destructive: DB optimize, log clean-up, telemetry write/clean-up, performance "optimize", GAP-055 clear-cache (refuses anyway) | 9 | **nobody** |
| `platform.backup.execute` | sensitive export: full database dump | 1 | **nobody** |

Verified by `test_platform_catalogue_is_exactly_the_five_approved_codes_and_no_role_holds_them`, after the full `DatabaseSeeder` (including the "System Admin" all-permissions role) and `production:rbac` twice:
- exactly these 5 codes exist;
- for each, `name` = `code` (the gate matches on name);
- **zero** `role_permissions` rows for any `platform.*` code.

`system.admin` is not reused.

## 3. Host-route mapping (all 80 former `rbac:admin` routes)

Per-route table: `evidence/o17h-h2a-host-route-mapping.tsv` (H1 class, reference counts, outcome, gate). It is pinned in `UatRecovery07bO17hH2aPlatformTest::PLATFORM_ROUTES`.

| H1 class | Routes | H2a outcome |
|---|---|---|
| READ-HOST (15) + CHECK-ONLY GAP-057 (10) | 25 | `platform.ops.view` |
| MUTATING-SHARED (cache 5, realtime 3, telemetry write 1) | 9 | `platform.cache.manage` ×5; `platform.realtime.broadcast` ×3; `platform.maintenance.execute` ×1 |
| MUTATING (effect unverified): performance optimize | 4 | `platform.maintenance.execute` |
| DESTRUCTIVE | 3 | `platform.maintenance.execute` |
| REFUSED (GAP-055 clear-cache) | 1 | `platform.maintenance.execute` (still returns 409) |
| SENSITIVE-EXPORT | 1 | `platform.backup.execute` |
| Mock / legacy (37) | 37 | **16 retired**; 21 kept because they are not provably unused: 6 READ-MOCK and 2 widget POSTs → `platform.ops.view`; `alerts/create` → `platform.realtime.broadcast`; 10 legacy console pages → `platform.ops.view`; 2 redirect pages (`/admin/users`, `/admin/tenants`) → **`admin.company.manage`**, the permission of the company-admin page they redirect to |

**Distinct controls (Gate C3):**
- Read, shared mutation, broadcast, destructive maintenance and full export each have their own code.
- A holder of only `platform.ops.view` passes all read routes and **no** mutating, destructive or export route (`test_read_only_platform_grant_does_not_open_mutating_destructive_or_export_routes`).
- The extra safeguards for destructive and export operations in 101 §6 (confirmation token, kill switch, per-operation audit) are **not** in H2a. They are part of the H2b proposal (§10).

## 4. Retired routes (D2): evidence

D2 is applied strictly. A route was retired only if it is in the 37-route mock/legacy set **and** has zero references of any kind:
- UI: `resources/`, `public/js`, `public/build`;
- code: `app/`, `src/`, `config/`, `routes/`, including route-name use;
- tests and fixtures: `tests/`, including `tests/Fixtures/middleware_gate_baseline.json`.

Also checked: prefix and dynamic-URL searches for `universal-frame` and `api/admin/dashboard` found nothing outside route definitions.

**Retired (16):**
- `GET api/admin/dashboard/activities`, `GET api/admin/dashboard/alerts` (static mock data);
- `GET api/v1/universal-frame/alerts`, `/alerts/stats`; `POST …/alerts/acknowledge`, `/alerts/mute`, `/alerts/dismiss-all`;
- `GET …/activities`, `/activities/by-type`, `/activities/stats`;
- `GET …/filters/presets`, `/filters/deep`, `GET|POST …/filters/saved-views`, `DELETE …/filters/saved-views/{viewId}`;
- `GET admin/tasks` (placeholder HTML).

**Kept, because they are not provably unused** (re-gated instead):
- `POST …/alerts/create`, `POST …/filters/apply`: listed in the middleware-gate baseline fixture;
- `POST …/analysis` and the four `GET …/analysis/{context}…` routes, `GET api/admin/dashboard/stats|metrics`: test references;
- the 12 legacy `admin/*` pages: views, named routes or tests reference them (e.g. `AdminRolesCompanyAndSessionTest` asserts the `/admin/users` and `/admin/tenants` redirects).

**No replacement path** (`test_retired_mock_routes_are_gone_and_not_reachable_elsewhere`):
- every retired `METHOD uri` resolves to 404 or 405;
- none of the 15 retired controller actions is bound to any other route.

The orphan-route lint carries the 14 retired API paths as governed negative probes.

**Pre-existing oddity, not changed:** the legacy `GET admin/activities` placeholder closure registers three routes (`api.alerts.resolve`, `api.activities.create`, `api.activities.clear-old`) at request time. It is now gated on `platform.ops.view`. Recommend retiring it under H2b or a clean-up item.

## 5. Seeder exposure matrix

| Seeder / path | Runs in | Bulk "all" grant? | Platform codes after H2a | Revoked grants restored? |
|---|---|---|---|---|
| `ZenaPermissionsSeeder` | `DatabaseSeeder`, `production:rbac` (×2) | no (fixed lists; System-Admin-only list) | **creates** the 5 (catalogue only) | yes (System Admin / PM codes) |
| `PermissionSeeder` (System Admin = all) | both | **yes** | **excluded** (`excludeFrom`) | yes (`sync` of all non-platform) |
| `ZenaAdminRolePermissionSeeder` (`System Admin`/`Admin`/`super_admin`/`system_admin`) | both | canonical list only | not in list, so never granted | **yes** (H1 RED test 9) |
| `TreasuryRolePermissionSeeder`, `PayrollRolePermissionSeeder`, `V1PermissionGapSeeder` | both | own lists | never granted | yes |
| `ZenaProjectManagerRolePermissionSeeder` | both | own list | never granted | yes |
| `production:rbac` `BASELINE_GRANTS` | `production:rbac` | named codes | never granted; D3 adds `notification.manage_rules` to `super_admin` | yes |
| `TestDatabaseSeeder`, `RolePermissionSeeder`, `ZenaRbacSeeder` (`'*'`) | not run by either entry point | yes | **excluded** | n/a |

**Revocation is unresolved in H2a, by design (D4 is H2b).** Every additive seeder still restores a deliberately revoked grant. This is flagged by the RED test `test_reseeding_does_not_restore_a_deliberately_revoked_grant` (quarantine manifest, release slice **H2b**).

## 6. RED/GREEN test results (local, SQLite)

Run sequentially at the H2a working tree (`c94ff67c0` + uncommitted H2a), SQLite, Backup classes excluded as in CI.

| Suite | Result | vs H1 baseline at the same head |
|---|---|---|
| Feature (incl. Architecture and Recovery) | **2266 tests, 0 failures** (33 skipped) | +17 new tests; 0 failures, as before |
| Feature/Api | **825 tests, 0 failures** | unchanged |
| Unit | 948 tests, **5 failures**, all `EvidenceFreshnessLiveCheckTest` (pre-existing, needs live GitHub) | the earlier 7 pre-existing failures included `ZenaInvariantsDiscoveryTest` ×2, which pass in this run. No new failure. |
| Integration | 31 tests, 0 failures | unchanged |
| RED group `uat-recovery-07-o17h-red` (excluded from the ordinary run) | **9 tests, 9 failures, as intended** | was 10. `test_every_rbac_argument_is_a_real_permission_code` was released (green) |

**First full run before the fixture work: 4 Feature + 22 Api failures, all caused by H2a. Resolved without changing any security assertion:**

| Failure | Resolution |
|---|---|
| `RouteRbacCodesSeededTest` ("platform codes nobody seeds") | Catalogue moved into `ZenaPermissionsSeeder` (every seeding path) |
| `ProjectStoreRbacTest::test_web_rbac_params_reference_real_permissions_or_roles` | Same fix |
| `V1LegacyRouteHardeningContractTest` ×2 (required `rbac:admin`) | Contract now requires an explicit `rbac:platform.*` gate (stricter) |
| Cache ×9, WebSocket ×9, ComprehensiveApiIntegration ×4 (403) | Their operator persona gets the platform codes explicitly (§1). Assertions unchanged. |
| `UatRecovery05O18…` "System Admin keeps every permission" | Amended to "every non-platform permission + no platform permission" (D1/D6). **Owner review requested.** |

**New GREEN guards** (`UatRecovery07bO17hH2aPlatformTest`, 7 tests, all passing):
- no implicit platform grants after `DatabaseSeeder` + `production:rbac` ×2;
- the exact route → code mapping, with destructive and export codes kept distinct;
- a read-only grant opens no mutating route;
- PM, Member, Finance, site_engineer and a tenant role administrator pass no platform gate;
- neither the Owner nor a tenant role administrator can create, edit, delete, attach (sync or CSV) or assign platform authority, self included, and nothing persists;
- retired routes are gone with no replacement;
- D3 grant is explicit, grant-based and self-scoped.

**Quarantine guard** (`UatRecovery07O17hQuarantineGuardTest`, 3 tests): exactly the 9 manifest tests, a single group exclusion, each documented with its releasing slice, the bypass flag present, and no method deleted.

**Static and lint checks:**

| Check | Result |
|---|---|
| PHPStan (project config) | No errors |
| `composer ssot:lint` | Pass (14 retired paths allowlisted as governed negative probes) |
| `owner_governance_lint` + `--enforce-gate-ordering` | Pass |
| `lint-mysql-claim-truthfulness` | Pass |
| php-cs-fixer on every changed or new PHP file | Clean, except 4 `src/RBAC` files that already had findings at HEAD; the fixer reports the identical set of finding types before and after |

**Not run:** Dusk browser tests (no local chromedriver). MySQL 8 (SQLite only).

## 7. Existing admin workflow compatibility

- **Tenant Owner (`super_admin`):** no visible change in H2a. The bypass admits every `platform.*` route, the role still holds its 222 codes, and it gains `notification.manage_rules` (D3).
  - The two legacy redirect pages now need `admin.company.manage`, which the Owner holds.
  - The company admin pages under `/app/admin/*` (staff, roles, company) are unchanged (`admin.company.manage`).
  - The 16 retired routes were unused.
- **System Admin (dev/test seeds):** loses only what it never legitimately needed: the 5 platform codes, which it never had either, because they did not exist before.
- **PM, Member, Finance, site_engineer:** unchanged. They pass no platform gate, as before (`rbac:admin` denied them too).
- **Test personas:** `createRbacAdminUser()` now carries explicit platform codes, so the 22 cache, WebSocket and integration API tests pass with **unchanged assertions**.
- **Notification settings:** `PATCH api/v1/settings/notifications` also carries the parameterless `rbac` gate, which only accepts legacy role names (O8-1). Only roles with those names (for example the Owner) can reach it. D3's grant is effective for the Owner and is self-scoped (the endpoint has no target-user parameter).
  - **New minor finding NTF-1:** the endpoint's unexpected-key rejection is discarded, because `Validator::fails()` re-runs validation after the manual `errors()->add()`. Extra keys such as `user_id` are silently ignored, not refused. The settings write stays on the caller. Recorded, not fixed.

## 8. Security gaps H2a cannot close

1. **M1 bypass (H3):** the role named `super_admin` passes all 872 permission gates, the 62 platform gates included. Platform authority is therefore *not yet* independent of the tenant Owner in practice.
2. **Policy role-name fallbacks (H4):** `DocumentPolicy`, `SidebarConfigPolicy::before()`.
3. **Revocation (H2b, D4):** reseeding restores revoked grants.
4. **RBAC-ESC-1 (H2b, D5):**
   - A tenant role administrator can still self-grant `system.*` codes and assign itself the **global Owner role**.
   - H2a blocks only `platform.*` creation, attachment and assignment.
   - Rules still missing: Owner appointment (same tenant, no self-promotion, last-Owner protection, transactional audit).
5. **Platform Operator provisioning (H2b, D6):** none exists yet. That is intended: everything fails closed except the bypass.
6. **Destructive/export safeguards beyond the permission (H2b):** confirmation, kill switch, per-operation audit.
7. **Independent, not fixed:**
   - BLK-1: bulk user creation across tenants;
   - CMP-1: v1 component routes are cross-tenant;
   - AUD-1: no audit rows on RBAC changes;
   - O8-1: parameterless gate;
   - O16-1: no production System Admin;
   - NTF-1 (new, minor).

   None of these prevented safe H2a completion.

## 9. Rollback and migration risks

- **Rollback** = revert the H2a change set (one commit when authorized). No migration and no schema change.
  - The 5 `platform.*` permission rows stay inert after a revert: nobody holds them, and no route references them.
  - The D3 `notification.manage_rules` grant on `super_admin` is additive and revocable. Under current seeders a reseed re-adds it until H2b.
- **Deployment risk** (when ever authorized):
  - `production:rbac` must run so the 5 codes exist. Without them, `isPermission()` is false, so the platform routes deny everyone except the bypass, which fails closed.
  - Any external caller of a retired route gets 404. None is known (zero references).
- **No production role or grant change is part of H2a.** `production:rbac` was **not** run anywhere except the synthetic test databases.
- **H3 precondition** (unchanged): run the H0 inventory (D7 conditions) before removing the bypass.

## 10. Precise H2b proposal (for a separate Owner gate)

1. **D4 revocation records:**
   - migration `role_permission_revocations` (`role_id`, `permission_code`, `revoked_by`, `reason`, `approval_reference`, `revoked_at`; unique on role + code);
   - `RevocationLedger::filter()` applied in every grant path of the seeders in §5 and `production:rbac`;
   - a revoke API/command that writes the ledger row and an audit row in one transaction;
   - re-grant = an explicit ledger removal with its own audit row;
   - releases RED test 9.
2. **D5 Owner appointment:** a dedicated path. The actor holds a real canonical grant (new `owner.appoint`, granted only to `super_admin`). The target is in the same tenant, is not the actor, and the last active Owner cannot be removed. The audit row is written in the same transaction, and no role-name check is used.
   - `role.assign` refuses `super_admin` everywhere else.
   - Protected codes (`role.*`, `permission.*`, `system.*`, `owner.appoint`) can be attached to a role only by an actor who already holds them.
   - Closes RBAC-ESC-1 and releases RED test 8.
3. **D6 Platform Operator provisioning:** artisan `platform:operator:grant|revoke {user} --reason= --approval=` creates the global "Platform Operator" role (holding the 5 codes) if missing and assigns or revokes it.
   - It writes an audit row.
   - It refuses when run over HTTP: CLI only, never registered as a route.
   - `role.assign` already refuses it (H2a).
4. **Destructive/export safeguards:**
   - `PLATFORM_DESTRUCTIVE_OPS_ENABLED` kill switch (default off in production) on `platform.maintenance.execute` and `platform.backup.execute` routes;
   - a short-lived confirmation token;
   - a per-operation audit row;
   - backups are never downloadable over HTTP.
5. **AUD-1 (RBAC part):** audit rows for grant, revoke, assign and role/permission create.
6. **Optional:** retire the request-time route registration in the `admin/activities` placeholder.

H3 (bypass removal, 116 measured fixture migrations) and H4 (policy fallbacks, 2 fixtures) stay separate gates.

## 11. Result

**O17H_H2A_LOCAL_VERIFIED.** D1, D2 (strict), D3 and the D6 "no HTTP path" part are implemented locally and verified on SQLite.

**Not done:** D4, D5, the D6 provisioning command, and the destructive-operation safeguards (all H2b), plus H3 and H4.

**Repository state:**
- nothing committed or pushed;
- PR #422 Draft and MERGE_NO_GO;
- no production or staging access;
- the original checkout untouched.

**The legacy `super_admin` bypass remains. H2a does not block the Owner from host operations.**
