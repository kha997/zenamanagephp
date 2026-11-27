# OWN-2026-024 (O-17h) — H0 preparation, H1 tests and the Option-B design

**Status: O17H_H0_H1_READY_FOR_OWNER_REVIEW.** This is not Gate 3 and is not approved for merge. PR #422 remains Draft and MERGE_NO_GO.

**Owner decision (2026-11-24):** "WORK ID: OWN-2026-024 / OWNER DECISION: O17H_OPTION_B_APPROVED / IMPLEMENTATION AUTHORITY: H0 PREPARATION + H1 TESTS ONLY".

**Base:** PR #422 head `c94ff67c031c60a862def26e0c5c9f3bc33e2ffc`, Draft, 35/35 checks. Isolated worktree `integration/uat-recovery-s1-s4d`. The original checkout (`a0381f5e3`, 39 dirty entries) was not touched.

**Not done:**
- no runtime, policy, route, seeder or migration change;
- no grant or revocation;
- no production or staging access;
- no commit, push, merge or deployment.

**Measurement method:** the three measurement copies (§3) are extracted archives of the committed tree in the session scratchpad. They are not part of the repository and are never committed.

**Governance records:**
- `docs/owner-decisions/OWN-2026-024/01-request.md`: Gate 1, approved in session, recorded verbatim;
- `docs/owner-decisions/OWN-2026-024/02-design.md`: Gate 2, awaiting the Owner.

**Predecessor:** `100-o17h-super-admin-bypass-assessment.md` (root cause M1–M5, route inventory, probes).

**New evidence:**
- `evidence/o17h-h0-readonly-inventory.sql`: H0 procedure (§2).
- `evidence/o17h-h1-red-run.txt`: H1 RED/GREEN run (§4).
- `evidence/o17h-bypass-dependent-tests.tsv`: measured dependency inventory (§3).
- `evidence/o17h-host-route-classification.tsv`: the 80 host-wide routes (§5).
- `tests/Feature/Recovery/UatRecovery07O17hExplicitRbacTest.php`: H1 tests (uncommitted).

---

## 1. Gate 0 — governance

| Check | Result |
|---|---|
| Branch / HEAD / PR | `integration/uat-recovery-s1-s4d` = `origin/…` = `c94ff67c0`; PR #422 Draft; auto-merge null |
| `OWN-2026-024` already allocated? | **No.** Not found in the worktree, `origin/main`, any branch name, `git log --all -S`, the original checkout's `docs/`, or GitHub PRs. The highest existing ID is `OWN-2026-023`. |
| Registration | Work IDs of the `OWN-` family are registered by their packet directory (`docs/owner-decisions/<id>/`, `OWNER_OPERATING_MODEL.md`). They are not rows in `OPERATIONAL_GAP_REGISTER.md`. Created: `OWN-2026-024/01-request.md` and `02-design.md` (canonical schema `packet-schema.yml`, templates `gate-1-business-request.md` and `gate-2-business-design.md`). |
| Gate 1 | `approved`. The Owner's in-session words are recorded verbatim (`claimed_repo_record`, `recorded_by: agent`), following the OWN-2026-023 precedent. |
| Gate 2 | `awaiting_owner`, `decision_requested: approve_or_changes_or_decline`. The spec is this document. |
| Gate 3 | **Not created, not claimed.** |
| Lint | `owner_governance_lint.php` and `--enforce-gate-ordering`: see §11. |

## 2. H0 — read-only production inventory procedure (prepared, NOT executed)

**Artifact:** `evidence/o17h-h0-readonly-inventory.sql`. It contains 12 SELECTs inside `START TRANSACTION READ ONLY … ROLLBACK`. Every SELECT was executed against a synthetic `production:rbac`-seeded SQLite schema to prove that the columns and joins exist. The MySQL-only session statements were not exercised there.

**What it inventories:**

| # | Item | Output (aggregate only) |
|---|---|---|
| Q1 | Tenant count | 2 counts |
| Q2 | Owner/admin-like role instances (`super_admin`, `System Admin`, `system_admin`, `admin`, `owner`) | name, scope, is_global, is_active |
| Q3 | `super_admin` holders per tenant | **tenant ordinal**, user count, active count |
| Q3b | `super_admin` without a tenant; `system_user_roles` → `user_roles` drift; legacy `users.role='super_admin'` | 3 counts |
| Q4 | The Owner role's canonical grants | count + code list |
| Q5 | Codes the Owner role lacks | code list (expected: the 26 of §7 on a clean `production:rbac`) |
| Q6 | Who holds platform-like and RBAC-administration codes (`system.*`, `platform.*`, `role.*`, `permission.*`, `admin.company.manage`, `auth.bulk.manage`, `project.assign_manager`, `user.manage_roles`) | code, role name, scope, users-with-role count |
| Q7 | Whether a permission named `admin` exists | count (expected 0: the 80 routes are Owner-only today) |
| Q8 | Non-Owner roles that can administer roles (RBAC-ESC-1 exposure) | roles and users per scope |
| Q9 | Seeder-state evidence: grants per role, first and last grant time | per role |
| Q10 | O16-1: projects without a PM | 2 counts |

**Items not answerable from the database** come from the release commit, not production:
- **80 routes and their dependencies:** `evidence/o17h-host-route-classification.tsv`.
- **Production seeder behavior:** §8.
- **Role-assignment restrictions:** §8 and §10. The H1 test shows a tenant role administrator can make itself Owner.

**Execution protocol** (requires a separate, explicit Owner authorization; nothing here authorizes it):
1. The Owner names the operator and the time window, and confirms the production host is the one from the go-live runbook (`app.zena.com.vn`).
2. The operator connects with the existing production database credentials **from the host's own environment**. No credentials are copied, pasted into chat, or stored in the repository. Preferred: a pre-existing read-only account, if one exists. Creating one is a mutation and needs its own approval.
3. Run exactly this file with the client in batch mode and `--safe-updates`. The first two statements force a read-only transaction; the last is `ROLLBACK`.
4. Capture stdout only. Before it leaves the host, check that it contains only counts, role names and permission codes (no e-mail, name or id columns are selected).
5. Do **not** run `production:rbac`, `production:bootstrap`, migrations, seeders, `artisan tinker`, or any other command.
6. Hand the output to the agent. The agent records it under `evidence/` and compares it with §7 and §8.

**Stop conditions:**
- any error other than "no rows";
- any statement that would need write access;
- any output column that looks like personal data.

## 3. Bypass-dependent test inventory (measured)

**Method.** The committed tree `c94ff67c0` was extracted three times into scratch directories, with the same vendor set:

| Copy | Change |
|---|---|
| `base` | Unchanged |
| `m1` | Only the middleware `isSuperAdmin()` short-circuit removed (the H3 change) |
| `m3` | `m1`, plus the `super_admin` role-name fallbacks removed from `DocumentPolicy` and `SidebarConfigPolicy` (the H4 change) |

The same suites as CI then ran in parallel (SQLite; Backup classes excluded as in CI): Feature (includes Architecture), Feature/Api, Unit, Integration. They are compared **per test** from JUnit logs. A test is bypass-dependent when it passes in `base` and fails in `m1` (M1), or passes in `m1`-equivalent `base` but fails only in `m3` (M3). Pre-existing failures are excluded by construction.

Dusk browser tests cannot run locally (no chromedriver) and are **not measured**. CI is the only place they run.

**Noise control.** The parallel full run showed failures in `base` that the earlier sequential runs at this head did not have, because the three copies share the local Redis. So every file with **any** failure in **any** copy was re-run **sequentially** per copy. Of the 32 such files, 13 are git-environment tests (Architecture workflow/password scanners, `Unit/OwnerGovernance`, `Feature/Deployment`). They need `.git`, which an extracted archive lacks. Their 38 failures are **identical in base, m1 and m3**, so they carry no authorization signal. The other 19 files (180 tests) were re-run sequentially: **base = 0 failures**.

**Result (`evidence/o17h-bypass-dependent-tests.tsv`, one row per test with its failure):**

| Layer | Dependent tests | Files |
|---|---|---|
| **M1** (middleware short-circuit) | **116** | 17 |
| **M3 only** (policy role-name fallbacks) | **2** | 2 |
| Unit / Integration | 0 | — |
| Browser (Dusk) | not measurable locally | — |

M1-dependent tests by file:

| Tests | File |
|---|---|
| 30 | `Feature/Api/RfiApiTest` |
| 20 | `Feature/ChangeRequestApiTest` |
| 13 | `Feature/Api/RealTimeNotificationsTest` |
| 11 | `Feature/Api/ChangeRequestApiTest` |
| 9 | `Feature/ProjectTaskControllerTest` |
| 8 | `Feature/Api/TaskDependenciesTest` |
| 7 | `Feature/Api/IntegrationTest` |
| 4 | `Feature/Api/ComponentApiTest` |
| 3 | `Feature/Api/V1ProjectAndChangeRequestRoutesTest` |
| 2 each | `Feature/Api/TaskApiTest`, `Feature/Api/V1TasksCompatibilityCrudTest`, `Feature/GAP057/LaunchEndpointsRunNoArtisanTest` |
| 1 each | `Feature/Admin/AdminRolesCompanyAndSessionTest`, `Feature/Api/SecurityTest`, `Feature/GAP055/AdminClearCacheFailsClosedTest`, `Feature/IntegrationTest`, `Feature/RouteMiddleware/V1LegacyRouteHardeningContractTest` |

M3-only: `Feature/Policies/PolicyTest::document_policy_allows_proper_access` and `Feature/Unit/Policies/DocumentPolicyTest::test_super_admin_can_perform_all_actions`. The second **asserts the bypass itself**, so under H4 it must go to the Owner and must not be rewritten.

**Failure character.** 115 of the 116 M1 failures are an expected status (200 / 201 / 400 / 404 / 409 / 422) that becomes **403**. The tests reach the endpoint only through the default `super_admin` persona, which lacks the route's code. Under H3 they need an explicit test grant; their assertions stay the same.

Five M1 tests exercise **host-level** routes and would legitimately stay 403 after H3 unless the actor is given the future `platform.*` code (H2):
- GAP-055 clear-cache;
- GAP-057 ×2;
- the Admin legacy-pages redirect;
- one route-hardening contract.

This is **118 tests**, not the ~198 test *files* estimated in document 100.

## 4. H1 RED tests

**File:** `tests/Feature/Recovery/UatRecovery07O17hExplicitRbacTest.php`.
- Group `uat-recovery-07-o17h`; the expected failures are additionally in group `uat-recovery-07-o17h-red`.
- Fixture: `production:rbac` on a fresh database, so the real production grant set applies (Owner = 222 codes).
- Test-owned roles only. "Zero-grant Owner" means the single global `super_admin` role (`roles.name` is UNIQUE) with its grants detached inside the test.

Run (SQLite): **16 tests: 10 RED (all in the RED group), 6 GREEN guards.** Output: `evidence/o17h-h1-red-run.txt`.

| Req. | Test | Today | Failure (proves the defect) | Turns green in |
|---|---|---|---|---|
| 1 | `test_zero_grant_owner_is_denied_by_every_permission_gate` (route-table driven, middleware only) | **RED** | 888 of 888 permission-gated routes admit a zero-grant `super_admin` | H3 |
| 1 / 2 | `test_every_rbac_argument_is_a_real_permission_code` | **RED** | `{"admin":80}`: 80 routes gated on a string that is not a permission | H2 |
| 2 | `test_tenant_owner_cannot_reach_host_level_routes_by_role_name` (the fully provisioned Owner; asserts the inventory is exactly 80) | **RED** | 80 of 80 host-level routes reachable by the Owner role name | H2 + H3 |
| 3 | `test_revoking_a_canonical_grant_from_the_owner_removes_access` | **RED** | `role.view` revoked; `GET api/v1/rbac/roles` still 200 | H3 |
| 3 (control) | `test_revoking_a_canonical_grant_from_an_ordinary_role_removes_access` | GREEN | — | stays green |
| 4 | `test_zero_grant_owner_cannot_manage_roles_or_permissions` (assign Owner role, create role, create permission; table snapshot) | **RED** | 200 / 201 / 201 | H3 |
| 4 / 7 | `test_zero_grant_owner_cannot_approve_a_change_request` | **RED** | 200; status became `approved` | H3 |
| 5 | `test_document_policy_does_not_bypass_canonical_grants` (role and legacy `users.role`) | **RED** | 16 abilities granted by name (8 abilities × 2 actors) | H4 |
| 5 | `test_sidebar_config_policy_does_not_bypass_canonical_grants` | **RED** | `viewAny`, `create`, `manage`, `clone`, `reset` granted by `before()` | H4 |
| 5 (guard) | `test_document_policy_canonical_grant_still_works_and_stays_tenant_bound` | GREEN | — | stays green |
| 6 | `test_owner_cannot_reach_another_tenants_objects` (provisioned and zero-grant; read, update, user permissions, forged `X-Tenant-ID`) | GREEN | — | stays green |
| 7 | `test_granted_owner_permissions_work_without_the_role_name`: an Owner-equivalent clone role (same grants, other name) passes every route gate whose code it holds (more than 700 routes) | GREEN | — | stays green |
| 7 | `test_provisioned_owner_keeps_granted_workflows` (contracts, roles list, create lead, approve CR) | GREEN | — | stays green |
| 7 (no expansion) | `test_production_role_grant_counts_are_pinned` (Owner 222, PM 67, Member 27, Finance 15, site_engineer 18) | GREEN | — | H2 may change it only by Owner-approved codes |
| 8 | `test_tenant_role_admin_cannot_self_grant_host_or_owner_authority` | **RED** | A custom tenant role holding `role.view`, `role.edit` and `role.assign` (**not** the Owner) put `system.admin` and `system.settings` on itself (HTTP 200) **and assigned itself the global Owner role** (HTTP 200). This is **new finding RBAC-ESC-1**, independent of M1. | H2 (role-management guards) |
| 9 | `test_reseeding_does_not_restore_a_deliberately_revoked_grant` | **RED** | `production:rbac` silently restored the revoked `role.assign` | H2 (revocation ledger) |

No existing assertion was changed. The new tests use test-owned fixtures only and do not edit seeds.

**Commit plan for H1 (not done; needs the Owner's word).** A test-only commit that keeps CI green must exclude the group `uat-recovery-07-o17h-red`, the same way the existing UAT-RECOVERY-01 quarantine is a group exclusion in `phpunit.xml`. It also needs a guard so that exactly these 10 tests are excluded. That is a test-configuration change, listed for approval in §10, H1-commit.

## 5. The 80 host-wide routes

Full table: `evidence/o17h-host-route-classification.tsv`. All 80 are gated `rbac:admin`, with no second check in any of the 11 controllers and no tenant scoping.

| Class | Routes | What they do | Proposed gate |
|---|---|---|---|
| DESTRUCTIVE | 3 | `admin/maintenance/database` (`OPTIMIZE TABLE` on every table); `admin/maintenance/cleanup-logs` (deletes `system_logs` older than 30 days, all tenants); `api/legacy-routes/cleanup` | `platform.maintenance.execute` + safeguards (§6) |
| SENSITIVE-EXPORT | 1 | `admin/maintenance/backup-database`: full MySQL dump of **all tenants** onto the host disk | `platform.backup.execute` + safeguards |
| MUTATING-SHARED | 9 | `api/cache/*` clear / invalidate (key, pattern, tags) / warm-up (`AdvancedCacheService`; tenant prefixing of patterns not verified); websocket broadcast, notification and test (publishes through Redis); legacy-route record-usage | `platform.cache.manage`, `platform.realtime.broadcast` |
| MUTATING-SHARED (effect unverified) | 4 | `performance/optimize-*` and `implement-caching` | `platform.maintenance.execute`, or retire |
| CHECK-ONLY (GAP-057) | 10 | `final-integration/*` POSTs: readiness and integration checks and checklist state. GAP-057 guarantees no artisan command runs from the web. | `platform.ops.view`, or retire |
| REFUSED (GAP-055) | 1 | `admin/maintenance/clear-cache` returns 409 and never flushes the shared cache | retire |
| READ-HOST | 15 | cache stats and config (live Redis INFO); websocket info, stats, channels; performance metrics, analysis, recommendations; legacy-route reports; final-integration status and reports | `platform.ops.view` |
| READ-MOCK | 16 | `api/admin/dashboard/*` ("Mock data for demo purposes"); universal-frame GETs (static data) | **retire** (recommended), or a tenant permission. Not platform. |
| MUTATING-TENANT-UI (mock) | 8 | universal-frame alert and saved-view actions | **retire**, or a tenant permission |
| PAGE | 13 | legacy `admin/*` pages (views or redirects; superseded by `/app/admin/*` under `admin.company.manage`) | **retire** |

No destructive or mutating endpoint was executed during this work.

## 6. Platform-operator authorization design (O17H-2)

**Principle.** Tenant Owner and platform operator are different authorities. No seeder, alias list or role name ever confers platform authority. When nobody has been provisioned, every platform operation is denied (fail closed).

1. **Namespace `platform.*`**, a new canonical catalogue. Codes are created by the catalogue seeder and **granted to nobody**:

   | Code | Kind | Routes |
   |---|---|---|
   | `platform.ops.view` | read-only | 15 READ-HOST + 10 CHECK-ONLY |
   | `platform.cache.manage` | mutating, shared | `api/cache/*` clear, invalidate and warm-up (5). The maintenance clear-cache route already refuses (GAP-055): retire it. |
   | `platform.realtime.broadcast` | mutating, shared | websocket broadcast, notification, test |
   | `platform.maintenance.execute` | **destructive** | DB optimize, log cleanup, legacy cleanup, performance optimize |
   | `platform.backup.execute` | **sensitive export** | backup-database |
   | *(none)* | — | the final-integration POSTs are check-only since GAP-057, so `platform.ops.view` is enough (or retire). No separate launch code is needed. |

   **`system.admin` is not reused.** Today it is held by nobody in production, and `PermissionSeeder` would hand it to any role named "System Admin" through `Permission::all()` (§8).
2. **Boundary.**
   - A single global role, "Platform Operator" (scope `system`, `tenant_id` NULL), holds the `platform.*` codes.
   - It is **never** in any `ADMIN_ROLE_NAMES` or `OWNER_ROLE_NAMES` list.
   - It is assigned **only** by a host-side artisan command (`platform:operator:grant` and `revoke`, run by someone with shell access to the host). The command requires an explicit `--reason` and the Owner approval reference, and writes an `audit_logs` row.
   - It is **not** assignable through any HTTP role-management endpoint.
3. **Role-management guards** (fixes RBAC-ESC-1; H2):
   - `role.edit` / sync-permissions refuses any `platform.*` code and any code on a protected list (`role.*`, `permission.*`, `system.*`) unless the actor already holds it. No escalation beyond one's own grants.
   - `role.assign` refuses the Platform Operator role always, and the `super_admin` role unless the actor is an Owner and the target is not themselves (O-17h-4).
   - Every grant, revoke and assignment writes an audit row (closes the RBAC part of AUD-1).
4. **Destructive and export safeguards:**
   - a re-authentication / confirmation token bound to the operation;
   - a per-operation audit row (before and after);
   - rate limiting;
   - an environment kill switch (`PLATFORM_DESTRUCTIVE_OPS_ENABLED`, default off in production);
   - the backup is written to the host only and **never downloadable over HTTP**.
5. **Read versus mutate.** A separate code per class, as in the table. Read-only never implies mutate.
6. **Retire, not re-gate,** the 37 mock and legacy-page routes (READ-MOCK, MUTATING-TENANT-UI, PAGE). The Owner chooses (Gate 2 decision 2).
7. **Fail closed.** After H3, with no Platform Operator provisioned, all platform routes return 403. That is the intended state until the Owner provisions someone.

## 7. Owner permission policy: the six codes the Owner reaches only through M1 (O17H-3)

**Source.** Document 100 §2, class N. The six exact codes are `task.assign`, `change_request.create`, `component.create`, `component.read`, `component.update` and `notification.manage_rules`. A seventh, `component.delete` (class Z), is held by nobody and stays denied.

| Code | Business operation | Routes (100 inventory) | Existing guards | Scope | Owner needs it? | Escalation risk | Proposal |
|---|---|---|---|---|---|---|---|
| `task.assign` | Assign people to a task (legacy v1) | 5: `GET/POST api/v1/task-assignments`, `PUT/PATCH/DELETE …/{id}` (`auth:api`) | O-13 in-controller: owning-project PM only; assignee must be in the task's tenant. The canonical path is `TaskPolicy::assign` (PM of the project holding `task.assign`). | tenant + project PM | **No.** Under the approved task model (S4, O-13, O-17), assignment is the project PM's act. An Owner who manages a project should hold the Project Manager role and be the PM. | Low with the grant (the PM relationship is still required), but it would widen authority beyond the approved model | **DENY** (do not grant). O-17j (`task.assign` for an assignee named at creation) stays a separate open decision. |
| `change_request.create` (underscore, legacy) | Create a change request (v1) | 1: `POST api/v1/change-requests` | `StoreChangeRequestRequest` validation only. The canonical `change-request.create` (hyphen, which the Owner holds) covers `api/zena` creation. | tenant (not re-verified) | **No:** the canonical path exists | Duplicate legacy entry point | **DENY.** Consider retiring the v1 route (separate item). |
| `component.create` | Create a project component (v1) | 1: `POST api/v1/projects/{projectId}/components` | **None for tenant**: `Project::findOrFail($projectId)` unscoped | **cross-tenant (CMP-1)** | Not until CMP-1 is fixed | **High**: would let the Owner write into another tenant's project | **DENY** until CMP-1 is fixed |
| `component.read` | Read components (v1) | 3: `GET api/v1/components/{id}`, `GET …/projects/{projectId}/components`, `…/tree` | **None**: unscoped queries | **cross-tenant (CMP-1)** | Not until CMP-1 is fixed | **High**: cross-tenant read | **DENY** until CMP-1 is fixed |
| `component.update` | Edit a component (v1) | 1: `PATCH api/v1/components/{id}` | **None**: `Component::findOrFail` unscoped | **cross-tenant (CMP-1)** | Not until CMP-1 is fixed | **High**: cross-tenant write | **DENY** until CMP-1 is fixed |
| `notification.manage_rules` | Change **one's own** notification preferences | 1: `PATCH api/v1/settings/notifications` (sanctum) | In-controller: the user's tenant must equal the request tenant; strict key allow-list; self-scoped | the user's own settings | **Yes**: every staff user, Owner included, should manage their own notifications | None (self-scoped) | **GRANT** explicitly to the Owner (additive, revocable) |

**CMP-1 (new independent blocker, confirmed by a synthetic probe).** The seeded production **Project Member** (holds `component.read`) and **Project Manager** (holds `component.read/create/update`) of tenant A:
- read tenant B's component through `GET api/v1/components/{id}` (200, content returned) and `GET api/v1/projects/{B}/components` (200);
- the PM renamed tenant B's component through `PATCH api/v1/components/{id}` (200, persisted).

This exists **today for ordinary staff**, independent of `super_admin`. It needs its own Work ID.

**Summary of the Owner set after H2, if the proposals are accepted:** 222 current codes, plus `notification.manage_rules` = **223**. No `platform.*`. The other 25 non-held codes in the catalogue (`system.*`, `user.*` legacy, `*.read` legacy and so on; Q5 of H0 shows them for production) stay ungranted unless the Owner decides otherwise.

## 8. Seeder revocability analysis

| Seeder (run by `production:rbac` in this order) | How it grants | Re-adds a revoked grant? |
|---|---|---|
| `ZenaPermissionsSeeder` (×2) | Creates the catalogue. `grantSystemAdminOnlyPermissions()` gives `SYSTEM_ADMIN_ONLY_PERMISSIONS` to the global "System Admin" (additive). PM grants are additive. | **Yes**, for System Admin and PM codes |
| `PermissionSeeder` | `System Admin`: **`sync(Permission::all())`**, i.e. every permission in the table, including any future `platform.*` code. PM/Member: additive (O-18). | **Yes.** Also auto-grants every new permission to System Admin. |
| `ZenaAdminRolePermissionSeeder` | Every canonical code → roles named `System Admin`, `Admin`, `super_admin` or `system_admin` (case-insensitive), via `syncWithoutDetaching` | **Yes: proven by H1 test 9** (`role.assign` restored on the Owner) |
| `TreasuryRolePermissionSeeder` / `PayrollRolePermissionSeeder` | Their codes → `OWNER_ROLE_NAMES` (same four names) + named roles, additive | **Yes** |
| `ZenaProjectManagerRolePermissionSeeder` | O-18 operational codes → PM, additive | **Yes** |
| `V1PermissionGapSeeder` | Creates its codes; grants to `ADMIN_ROLE_NAMES`, additive | **Yes** |
| `production:rbac` baseline grants | Finance, PM, site_engineer, Member: `task.view` / `project.view`, additive | **Yes** |

**Conclusion.** No grant to the Owner, System Admin, PM, Member, Finance or site_engineer can be revoked durably today. The next `production:rbac` run silently restores it. A revocation is therefore not real (requirement 9).

**Proposed governed model (H2; needs a schema change, so Owner approval):**
1. **Seed-managed grants:** the baseline declared in code, as today. Seeders stay additive.
2. **Deliberately revoked grants:** a ledger table `role_permission_revocations`:
   - columns `role_id`, `permission_code`, `revoked_by`, `reason`, `approval_reference`, `revoked_at`;
   - unique on (`role_id`, `permission_code`);
   - every seeder grant path filters out ledger entries before `syncWithoutDetaching`;
   - revoking through the API or a command writes the ledger row and the audit row in one transaction;
   - re-granting a revoked code requires removing the ledger entry with its own audit row.
3. **Manually approved grants:** granted through the API or command with an audit row. Seeders never touch them, because they are additive.
4. **Platform-only grants:**
   - `platform.*` is excluded from **every** bulk seeder path, including `PermissionSeeder`'s `Permission::all()` and `ZenaAdminRolePermissionSeeder`'s canonical list;
   - it is excluded from the `ADMIN_ROLE_NAMES` / `OWNER_ROLE_NAMES` aliases;
   - it is assignable only by the host-side operator command (§6).
5. **Seeder guard test:** after `production:rbac` twice, no role other than Platform Operator holds a `platform.*` code, and every ledger entry is still absent.

**Rejected alternative:** a config-file deny list. It is not auditable, not per-environment-safe, and is edited by a deploy rather than by an authorized person.

## 9. Compatibility and rollback

| Area | H2 (re-gate + ledger + guards; M1 still present) | H3 (M1 removed) | H4 (policies) |
|---|---|---|---|
| Owner in production | No visible change (M1 still admits). Gains `notification.manage_rules` if approved. | Keeps every code it holds. **Loses** the host-wide routes (by design) unless provisioned as Platform Operator. Loses the denied N-code routes (`task.assign` v1, v1 change request, v1 components). | Document and sidebar abilities become grant-based (the Owner holds `document.*`). Sidebar admin needs a code decision. |
| Other roles | No change | No change. They never had M1. | Users who edit documents only through a role **name** (pm/designer/admin without `document.update`) lose that path |
| `production:rbac` | Creates `platform.*` (granted to nobody) and the ledger table; honours the ledger; System Admin's `Permission::all()` excludes `platform.*` | — | — |
| System Admin (dev/test) | No longer auto-receives `platform.*` | — | — |
| O-15 / O-16 / O-17 / O-18 | Unaffected. O-17 creation is already independent of M1. O16-1 is independent. | Unaffected | `TaskPolicy::view()` alignment (existing blocker) |
| Legacy `users.role` | — | M1 never read it | Policy role-name paths (which read it) are removed |
| Tests | New tests | The fixture migration from the §3 measurement. No assertion changes; any test asserting bypass behavior goes to the Owner. | The fixtures from the §3 M3 rows |

**Rollback.**
- Each slice is one revertable commit.
- H2's ledger and `platform.*` codes are inert when the code is reverted. The migration has a `down()`, and the codes are harmless rows.
- H3 is a one-line revert.
- **Pre-deploy requirement for H3:** the H0 output must confirm that the production Owner role holds the expected grants. Otherwise the Owner could be locked out at deploy. Fail closed is acceptable, but it is disruptive.
- No step runs `production:rbac` on production without its own authorization.

## 10. Proposed implementation boundaries (each a separate Owner gate)

| Slice | Exact boundary | Not included |
|---|---|---|
| **H1-commit** | The new test file + `phpunit.xml` group exclusion `uat-recovery-07-o17h-red` + a guard that exactly these 10 tests are excluded + this document and evidence | No runtime change |
| **H2a** catalogue + gates | Create the `platform.*` codes (granted to nobody). Re-gate the 43 platform routes per §5. Retire the 37 mock/page routes, if the Owner chooses. Exclude `platform.*` from every bulk seeder path and alias list. Grant `notification.manage_rules` to `super_admin`, if approved. M1 untouched. | No Platform Operator provisioned; no M1 change |
| **H2b** revocation + escalation guards | `role_permission_revocations` migration and ledger, honoured by all seeders. Role-management guards (RBAC-ESC-1, O-17h-4). Audit rows for grant, revoke and assign. The `platform:operator:grant` / `revoke` command. | No production run of the command |
| **H3** | Remove the M1 `isSuperAdmin()` short-circuit (1 block). Migrate the fixtures listed in `evidence/o17h-bypass-dependent-tests.tsv` (M1 rows) to explicit grants without assertion changes. Un-quarantine the RED tests that H2/H3 fix. **Pre-deploy:** H0 executed and reviewed. | — |
| **H4** | Remove the role-name fallbacks from `DocumentPolicy` and `SidebarConfigPolicy`. Align `TaskPolicy::view()`. Migrate the M3 fixture rows. Un-quarantine the policy RED tests. | — |
| **H5** (optional) | Delete dead M5 code with guard tests | — |
| **Separate Work IDs** | BLK-1, AUD-1 (non-RBAC part), O8-1, O16-1, **CMP-1 (new, cross-tenant: high)**, **RBAC-ESC-1** (if the Owner prefers it outside H2b) | — |

## 11. Independent security blockers recorded, not fixed

| ID | Finding | Evidence | Severity |
|---|---|---|---|
| BLK-1 | `BulkOperationsService::createUser()` mass-assigns the request row: a request `tenant_id` creates users in another tenant; the fillable legacy `role` (e.g. `super_admin`) is accepted. Gated by `auth.bulk.manage` (Owner-only in production). | code (100 §4); not probed | High |
| AUD-1 | No `audit_logs` row for role assignment, role or permission creation, or document update and delete | probes E1, E2, E4, DOC1, DOC2 (100 §3) | Medium |
| O8-1 | The parameterless `rbac` gate accepts only legacy role names: production "Project Manager" and "Project Member" are refused on 298 routes (e.g. `api/documents`); "Finance" passes only under a case-insensitive collation | probes P1, DOC1, DOC2 (member 403) | Medium (availability) |
| O16-1 | Production has no "System Admin" role, so nobody holds `project.assign_manager` and nobody can appoint a PM | `production:rbac` seed analysis; H0 Q2, Q6, Q10 will confirm | Medium (availability) |
| **CMP-1** (new) | v1 component routes have no tenant scoping: ordinary production PM and Member read and edit other tenants' components | synthetic probe (this document §7) | **High** |
| **RBAC-ESC-1** (new) | A non-Owner tenant role administrator (`role.view` + `role.edit` + `role.assign`) can attach `system.*` codes to its own role and assign itself the global Owner role | H1 test 8 (RED) | **High** (in production only the Owner holds these codes today, but any delegated administrator becomes an Owner) |

## 12. Gate 1/2 packet status and checks

| Item | Status |
|---|---|
| `OWN-2026-024/01-request.md` | Gate 1 `approved` (in-session verbatim; `claimed_repo_record`) |
| `OWN-2026-024/02-design.md` | Gate 2 `awaiting_owner`; 7 decisions listed in plain Vietnamese |
| Gate 3 | none |
| `owner_governance_lint.php` and `--enforce-gate-ordering` | see the final report |
| PR #422 | Draft, MERGE_NO_GO, head unchanged at `c94ff67c0`; nothing committed or pushed |
