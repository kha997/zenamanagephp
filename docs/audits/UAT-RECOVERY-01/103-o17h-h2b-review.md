# OWN-2026-024 (O-17h) — H2b local implementation: review package

**Status: see §11.** H2b is **local, uncommitted and unpushed**. PR #422 stays Draft and MERGE_NO_GO at remote head `c94ff67c0`. Nothing was deployed and there was no production or staging access. **The legacy `super_admin` middleware short-circuit is still present (H3)**; the policy role-name fallbacks are still present (H4).

- **Owner decision (2026-11-24):** "H2A_ACCEPTED / H2B_LOCAL_IMPLEMENTATION_APPROVED". The three H2a test adaptations are approved, "provided no unauthorized behavior is accepted by their assertions". None of them accepts unauthorized behaviour: each narrows or keeps the assertion (102 §1).
- **Prior documents:** design in `101-…`; H2a review in `102-…`.
- **Worktree:** the isolated `integration/uat-recovery-s1-s4d`. The original checkout (`a0381f5e3`, 39 entries) was not touched.

## 1. Gate 0: H2a preserved

The H2a guard tests (`UatRecovery07bO17hH2aPlatformTest`, 7) and the quarantine guard pass unchanged in intent. They confirm:
- the 5 platform codes exist;
- no implicit platform grant (after `DatabaseSeeder` + `production:rbac` ×2);
- 62 re-gated routes in the pinned mapping;
- 16 retired routes with no replacement;
- the 2 redirect pages on `admin.company.manage`;
- no tenant-admin platform privilege.

**One H2a fixture adapted for D6:** the read-only platform holder in 07b now also gets an active operator ledger row, because platform authority now requires the ledger.

## 2. Changed-file list (vs `c94ff67c0`; H2a items in 102 §1, H2b additions below)

**New runtime files:**

| File | Purpose |
|---|---|
| `database/migrations/2026_11_24_200000_own_2026_024_h2b_rbac_revocations_and_platform_operators.php` (+ `classifications.json`: `expand`) | 2 new tables + 1 nullable column (§3) |
| `app/Support/Rbac/PermissionRevocations.php` | D4: revoke / regrant / supersede, active-key uniqueness, audit in the same transaction, Owner-authority lock-out protection |
| `app/Support/Rbac/RbacSeedGrants.php` | The single seeder grant path: additive, global roles only, never `platform.*`, never a revoked grant |
| `app/Support/Rbac/RbacProtection.php` | D5: canonical Owner authority, protected codes, reserved role names, privileged-role change rules (incl. no self-assignment of any role) |
| `app/Support/Rbac/PlatformOperators.php` | D6: server-console-only provisioning and revocation, operator ledger, "Platform Operator" role holding exactly the 5 codes |
| `app/Support/Rbac/SecurityAudit.php` | Strict `audit_logs` writer, always inside the caller's transaction |
| `app/Support/Rbac/RbacSchema.php` | Once-per-process schema checks: code deployed before its migration neither crashes nor fails open (§3) |
| `app/Console/Commands/RbacPermissionRevocationCommand.php` | `rbac:permission revoke\|regrant\|list` (server console; `--approved-by` = an Owner with canonical authority) |
| `app/Console/Commands/PlatformOperatorCommand.php` | `platform:operator grant\|revoke\|list` (server console) |
| `app/Http/Middleware/GuardedPlatformOperation.php`, `config/platform_operations.php`, alias `platform.guarded` in `app/Http/Kernel.php` | Gate 4 safeguards |

**Modified runtime files:**

| File | Change |
|---|---|
| `app/Models/User.php` | `hasPermission` / `hasAnyPermission` ignore actively revoked (role, permission) pairs and require an active operator ledger row for every `platform.*` code |
| `app/Services/Admin/StaffAccountService.php` | Creating, promoting, demoting or deactivating an Owner from the web needs canonical Owner authority in the same company; no self-promotion. The existing last-Owner and self-demotion rules are kept. |
| `src/RBAC/Services/RBACManager.php` | Privileged-role assign and remove guarded (system, custom and project scopes; covers single and bulk); audit inside the existing transaction |
| `src/RBAC/Controllers/AssignmentController.php` | Explicit generic 403 for privileged assign and remove |
| `src/RBAC/Controllers/RoleController.php` | Reserved role names refused on create and rename. Sync: protected codes need Owner authority plus the actor holding them; re-adding a revoked code needs Owner authority and supersedes the revocation in the transaction; every sync is audited; `grant_source = manual`. |
| `src/RBAC/Controllers/PermissionController.php` | Protected namespaces can't be minted over HTTP |
| `src/RBAC/Services/PermissionMatrixService.php` | CSV import follows the sync rules; never re-adds a revoked code; audited |
| `database/seeders/{ZenaAdmin,Treasury,Payroll,V1PermissionGap,ZenaProjectManager}RolePermissionSeeder.php`, `PermissionSeeder.php`, `ZenaPermissionsSeeder.php`, `app/Console/Commands/ProductionRbacCommand.php` | All grant paths go through `RbacSeedGrants`. Alias names resolve to **global roles only**. |
| `routes/web.php`, `routes/api.php` | `platform.guarded:<operation>` on the 8 destructive / export routes |

**Tests and test fixtures:**

| File | Change |
|---|---|
| `tests/Feature/Recovery/UatRecovery07cO17hH2bTest.php` (new) | 11 tests |
| `UatRecovery07O17hExplicitRbacTest` | Two RED tests released, with an amendment to one (§8) |
| Quarantine manifest, guard test, `phpunit.xml` comment | 9 → 7 RED tests |
| `tests/Traits/AuthenticationTrait.php` | The operator test persona gets an operator ledger row |
| `tests/Feature/RbacApiTest.php` | Fixture role name "Project Manager" → "Site Coordinator" (now a reserved name) |
| `tests/Feature/Legacy/LegacyRouteRollbackTest.php` | Fixture enables the kill switch and sends the confirmation |

Assertions are unchanged in all fixture adaptations.

## 3. Migration and rollback assessment (Gate 5)

**Classification: `expand`** (additive, backward compatible). The migration:
- creates `role_permission_revocations` and `platform_operators`;
- adds nullable `role_permissions.grant_source`;
- **does not change, backfill or revoke any existing row**.

| Check | Result |
|---|---|
| Fresh migrate (SQLite, every test run) | OK |
| Fresh migrate, isolated **MariaDB 10.4.28** | OK. `rpr_active_key_unique` present; `grant_source varchar(16) NULL`. |
| Rollback `--step=1` on MariaDB after `production:rbac` | Exactly the 2 tables and the column dropped. **All 350 grants intact.** |
| Re-apply on MariaDB | OK, 350 grants intact |
| **Pre-migration compatibility** (H2b code, migration rolled back, MariaDB) | `production:rbac` exits 0. The Owner's `hasPermission('role.view')` is true. Platform authority is **false** (fails closed). Owner authority is true. |

**Ordering and the production compatibility boundary:**
- The migration runs before any H2b code writes revocations, ledgers or `grant_source`.
- If code is deployed first: seeding and authorization keep working without revocation filtering (no revocation can exist yet), and **platform authority stays closed**.
- The **commands** `rbac:permission` and `platform:operator` need the migration (they write the new tables).
- No automatic production backfill. No Platform Operator is created by migration or seed. No production grant is touched.

**O-17 / O-18 preserved:**
- The Recovery suite runs green (SQLite 198; MariaDB 185 of 187, the 2 being the known `split_percent` defect).
- Pinned grant counts: Owner 223 (222 + D3), PM 67, Member 27, Finance 15, site_engineer 18.

## 4. Revocation design and proof (Gate 1)

**Records:**
- `role_permission_revocations` holds: permission (id and code), affected role, the role's tenant scope (NULL = global), authorising actor (`revoked_by_actor` `server-cli:<os user>;approved-by:<owner id>` or `user:<id>`), reason, approval reference, `revoked_at`, `audit_log_id`, and the supersede fields.
- It is **active** while `superseded_at IS NULL`.
- `active_key` (`role|code`, NULL once superseded) has a UNIQUE index, so two concurrent active revocations of the same grant cannot exist on MySQL or SQLite. The role row is locked `FOR UPDATE` (effective on MySQL).

**Grant kinds (`grant_source`):**

| Kind | Recorded as |
|---|---|
| seed-managed baseline | `seed`, only through `RbacSeedGrants` |
| explicitly approved manual grant | `manual`: HTTP sync or CSV by an authorised actor, or the `regrant` command |
| platform-only | `platform`: only `PlatformOperators` |
| deliberately revoked | an active revocation row |
| before H2b | NULL (unknown origin; never treated as platform) |

**Enforcement in three layers:**
1. `revoke()` detaches the pivot row. Revocation row, detach and audit row are one transaction.
2. Every seeder and `production:rbac` grant path filters active revocations and is additive-only.
3. **At authorization time**, `User::hasPermission` / `hasAnyPermission` skip any (role, permission) pair with an active revocation. So even a pivot row re-added by an unknown path does not authorize. This was proven by inserting the pivot row directly in the test.

**Re-grant:**
- `regrant` (server console + approving Owner) or an authorised HTTP sync (Owner authority) supersedes the record. The re-grant is audited with `rbac.permission.regranted` in the same transaction.
- Refused, with nothing persisted:
  - a non-Owner approver;
  - a missing reason;
  - a tenant role administrator syncing the code back;
  - a CSV re-import.
- **Lock-out protection:** `admin.company.manage` (the Owner authority anchor) cannot be durably revoked from the Owner role, the same way the last active Owner is protected.

**Atomicity proof** (`test_failed_revocation_leaves_no_revocation_grant_change_or_audit_row`, SQLite and MariaDB):
- A failure is forced after the audit insert and before the revocation insert, through Laravel's `beforeExecuting` hook.
- Every table count is unchanged afterwards (grants, revocations, audit rows, assignments, operators).
- The first version used a `CREATE TRIGGER`, which on MariaDB **implicitly committed the test transaction** (the GAP-044 lesson) and left an audit row. The DDL-free version passes on both engines.

**Existing grants:** none revoked. The revocations table is empty after `production:rbac` (`test_existing_grants_and_owner_workflows_are_preserved`).

## 5. Privilege-escalation regression evidence (Gate 2)

**Canonical Owner authority** = an active user holding the Owner role (`super_admin`) **and** the real grant `admin.company.manage`, checked through `hasPermission` (revocation-aware, not the middleware short-circuit).
- In production that code is held only by the Owner role, so **no new permission was introduced** (Gate 2 STOP condition not triggered).

**Protected codes:** `role.*`, `permission.*`, `system.*`, `platform.*`, `admin.company.manage`, `auth.bulk.manage`, `user.manage_roles`, `project.assign_manager`.

**Every reachable role-grant surface:**

| Surface | Rule now | Evidence |
|---|---|---|
| `POST …/assignments/users/{user}/roles`, `POST …/user-roles` | Privileged role → Owner authority; same tenant; not self; actor holds the role's protected codes. **No self-assignment of any role.** Generic 403. | 07c: appointment denials (non-Owner admin to other or self, Owner to self, Owner cross-tenant); H1 test 8 released |
| `POST …/bulk-assign-roles` | Same rules, in `RBACManager` | 07c (nothing persisted) |
| `POST …/assignments/projects/{p}/users/{u}/roles` (project / custom scope) | Same guard in `RBACManager` | code path + tests above |
| `DELETE …/users/{user}/roles/{role}` | Privileged role removal → Owner authority, not self, last active Owner protected | 07c: Owner self-removal 403, still the sole Owner |
| `POST …/roles/{role}/permissions` (sync) | Protected code added or removed → Owner authority + actor holds it; revoked code → Owner authority + supersede; audited; global roles 404 | 07c: tenant admin `role.assign` 403; Owner `role.view` OK + audit; Owner `system.admin` 403 |
| `POST …/permission-matrix/import` (CSV) | Same as sync; never re-adds a revoked code | code path |
| `POST …/roles`, `PUT …/roles/{role}` | Reserved names (every seeder alias + production roles + Owner + Platform Operator) refused | 07c: "Admin", "platform operator" refused |
| `POST …/permissions` | Protected namespaces refused | 07c: `system.root` refused |
| Web staff admin (`/app/admin/users`: create, change role, deactivate) | Owner create / promote / demote / deactivate → Owner authority, same company, no self-promotion; last-Owner kept | 07c: 4 refusals, then an Owner promotion audited |
| Seeder alias resolution | Global roles only | 07c: a tenant role named `system_admin` gets 0 grants after `production:rbac` |
| CLI `production:user` | Unchanged (server bootstrap path, audited) | — |
| Zero-grant Owner (role name only) | Denied by the in-controller guard even though the middleware lets it in | 07c: 403 |

**Every denial asserts a full table snapshot** of `roles`, `permissions`, `role_permissions`, `user_roles`, `system_user_roles`, the revocation tables, `audit_logs` and `platform_operators`. Nothing is persisted.

## 6. Platform Operator provisioning proof (Gate 3)

- **Default state:** none exists after `DatabaseSeeder` + `production:rbac` ×2. There is no operator ledger row and no "Platform Operator" role. The tenant Owner holds **no** platform authority (`hasPermission('platform.ops.view')` is false).
- **`platform:operator grant`:**
  - needs `--reason` and `--approval`, otherwise exit 1 and nothing written;
  - refuses outside the server console (`PHP_SAPI = cli`);
  - creates the global role with **exactly** the 5 codes (`grant_source = platform`), the ledger row and the user role, plus a `platform.operator.granted` audit row, in one transaction.
- **Independent validation:** `hasPermission(platform.*)` requires the active ledger row. A user holding the "Platform Operator" role **without** a ledger row (any non-server path) passes no platform gate.
- **HTTP:**
  - assigning the role → 403;
  - syncing it → 404/422 (global role);
  - creating the name → refused (reserved);
  - no route action reaches provisioning (route scan).
- **`revoke`:** clears the ledger, removes the role assignment, writes `platform.operator.revoked`, and authority is gone.
- **Never** granted to `super_admin`, System Admin or the tenant Owner by any seeder (H2a 07b and 07c).
- **After H3,** with no operator, platform routes fail closed. **Until H3** the role named `super_admin` still passes the `rbac:platform.*` middleware (flagged in the quarantine manifest).

## 7. Audit evidence

All audit rows are written in the same transaction as the change; a failure rolls both back.

| Action | Trigger | `user_id` |
|---|---|---|
| `rbac.permission.revoked` | durable revocation | approving Owner |
| `rbac.permission.regranted` | re-grant / supersede | approving Owner or actor |
| `rbac.role.permissions_synced` | every HTTP sync (old + added / removed) | actor |
| `rbac.role.permissions_imported` | CSV import per role | actor |
| `rbac.privileged_role.assigned` | privileged role assignment | actor |
| `rbac.privileged_role.removed` | privileged role removal | actor |
| `admin.user.role_changed`, `admin.user.deactivated`, `production.user.created` | staff service (existing) | actor, or subject for CLI |
| `platform.operator.granted` | operator provisioning | subject user; the actor is `server-cli:<os user>` in `new_data` |
| `platform.operator.revoked` | operator revocation | subject user; actor as above |
| `platform.operation.requested` | before a guarded destructive / export operation (none written while switched off or unconfirmed) | actor |
| `platform.operation.completed` | after it, with the status code | actor |

**Gate 4:**
- Kill switch `PLATFORM_DESTRUCTIVE_OPS_ENABLED` defaults to **off**: 503, nothing runs.
- `confirm_operation=<operation id>` is required: 428 otherwise. Each of the 8 routes has its own id.
- The backup endpoint returns JSON only; any file or stream response is replaced by a refusal.
- No destructive operation ran outside the synthetic test database.

## 8. Test results and baseline comparison

**SQLite, sequential, final run** (Backup classes excluded as in CI):

| Suite | H2a (102) | **H2b** |
|---|---|---|
| Feature (incl. Architecture and Recovery) | 2266, 0 failures | **2279, 0 failures** (33 skipped) |
| Feature/Api | 825, 0 | **825, 0** |
| Unit | 948, 5 (`EvidenceFreshnessLiveCheck`, pre-existing, needs live GitHub) | **948, 5 (same)** |
| Integration | 31, 0 | **31, 0** |
| RED group `uat-recovery-07-o17h-red` | 9 / 9 | **7 / 7** |

SQLite re-confirmed for the final edits:
- Recovery: 198 OK.
- RBAC assignment suites (`RbacApiTest`, GAP-042 ×2, `Admin/*`): 60 OK.

**Isolated MariaDB 10.4.28** (`ZENA_INVARIANTS_DB=mysql`, `phpunit.mysql.xml`, migrated fresh):
- Recovery minus the H2b class: 187 tests, **2 failures**, both the **known** `task_assignments.split_percent` defect in `UatRecovery04bTaskSurfaceTest` (identical to the earlier O-17/O-18 MariaDB runs; separate item).
- H2b + H2a + H1 + O-18 files: **36 / 36 OK**.
- RED group: 7 / 7 failing, as intended.

**MySQL 8: NOT verified.** No MySQL 8 server was available. MariaDB 10.4 is not MySQL 8.

**Static and lint checks:**

| Check | Result |
|---|---|
| PHPStan (project config) | No errors |
| `composer ssot:lint` | Pass |
| `owner_governance_lint` + gate ordering | Pass |
| `lint-mysql-claim-truthfulness` | Pass |
| php-cs-fixer on every changed or new file | Clean, except 5 `src/RBAC` files that already had findings at HEAD (identical finding types before and after) |

**Not run:**
- Dusk browser tests (no local chromedriver).
- The `performance`-group tests (`FinalSystemTest`, `PerformanceTest`; excluded by `phpunit.xml`). They call the destructive routes and would now need the kill switch and confirmation if run manually.

**Test adaptations in H2b** (assertions unchanged except where noted; none accepts unauthorized behaviour):

| Adaptation | Note |
|---|---|
| `RbacApiTest::test_can_create_role` | fixture name "Project Manager" → "Site Coordinator" (reserved names) |
| `LegacyRouteRollbackTest` ×2 | fixture enables the kill switch + confirmation |
| 07b read-only platform test and `AuthenticationTrait` operator persona | add an operator ledger row (D6) |
| **H1 `test_reseeding_does_not_restore_a_deliberately_revoked_grant`** | **Owner review requested.** The revoking step now uses the governed `rbac:permission revoke` instead of a bare pivot detach (a bare detach is not a deliberate revocation under D4). The assertion is unchanged. Released from RED. |
| H1 `test_tenant_role_admin_cannot_self_grant_host_or_owner_authority` | released from RED **unchanged** |

## 9. Remaining H3/H4 dependencies and residual risks

**H3** (M1 bypass) still admits the role named `super_admin` on all 872 permission gates and the 62 platform routes. The 5 H3 RED tests remain.
- **H3 compatibility:** H2b's guards do not rely on the bypass. Zero-grant `super_admin` cases are already denied in-controller. The Owner keeps all 223 codes, including `admin.company.manage`.
- **H3 preconditions:**
  - H0 inventory (D7 conditions) confirming production Owner grants;
  - the 116 measured fixture migrations (101 §3);
  - provisioning a Platform Operator if host operations are needed after H3.

**H4:** `DocumentPolicy` / `SidebarConfigPolicy` role-name fallbacks (2 RED tests, 2 measured fixtures).

**Residual risks:**

| ID | Risk | Mitigation / where it lives |
|---|---|---|
| R-1 | A `role.assign` holder can still assign **non-protected** roles carrying codes it does not itself hold, to **others**. Self-assignment is now denied for every role. | In production only the Owner holds `role.assign`. A general "assign only within your own grants" rule is a wider redesign: proposed as a follow-up, not done. |
| R-2 | Non-privileged role assignments and project-role changes are not audited (AUD-1 remainder) | AUD-1 |
| R-3 | `audit_logs.user_id` must be an app user, so server-CLI operator actions record the subject user with the real actor in `new_data.actor` | Same convention as the existing `production:user` |
| R-4 | `FOR UPDATE` on the role row is a no-op on SQLite | Uniqueness is still enforced by the `active_key` index |

BLK-1, CMP-1, AUD-1, O8-1, O16-1 and NTF-1 remain open and untouched.

## 10. Proposed integration strategy (for a later Owner gate; nothing done)

1. Keep H2a and H2b as **two separate commits** on PR #422 (or a follow-up PR), each self-contained:
   - **H2a:** catalogue, re-gating, retirement, D3, HTTP guards, quarantine.
   - **H2b:** migration, revocation, Owner rules, operator, safeguards.
2. Before committing: Owner sign-off on the three flagged test amendments:
   - the O-18 System Admin assertion (H2a);
   - the hardening contract (H2a);
   - the reseed test mechanism (H2b).
3. Deploy order, when ever authorized:
   - the migration (`expand`) first, then code;
   - then `production:rbac`, which is additive and creates the 5 codes and the D3 grant only;
   - **no** operator provisioned unless the Owner decides.
4. CI to watch: browser-tests (not run locally) and the `*-mysql` jobs. A **MySQL 8 run of the Recovery and H2b suites** is still a merge blocker (P2).
5. Then H0 (D7), then H3, then H4, each as its own gate.

## 11. Result

**O17H_H2B_LOCAL_VERIFIED.** D4, D5, D6 and the Gate 4 safeguards are implemented locally and verified:
- on SQLite: full suites, 0 new failures;
- on isolated MariaDB 10.4: the H2b-related files plus Recovery, with only the 2 known `split_percent` failures.

**Not verified:**
- MySQL 8;
- Dusk;
- the `performance` group.

**Repository and environment state:**
- nothing committed or pushed;
- PR #422 Draft and MERGE_NO_GO;
- no production or staging access;
- no production grant change;
- the original checkout untouched.

**The legacy `super_admin` bypass remains until H3.**
