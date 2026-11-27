# O-17h — `super_admin` authorization bypass: security assessment and Owner decision package

**Status: READY_FOR_O17H_OWNER_DECISION.** This is a read-only assessment; nothing in it is implemented.

- **Owner gate (2026-11-24):** "APPROVED_FOR_READ_ONLY_SECURITY_ASSESSMENT".
- **Base:** PR #422 head `c94ff67c031c60a862def26e0c5c9f3bc33e2ffc`. The PR was Draft, with 35/35 CI checks passing.
- **Worktree:** the isolated `integration/uat-recovery-s1-s4d` worktree. The original checkout (`a0381f5e3`, 39 dirty entries) was not touched.
- **What was not done:** no runtime code change, no grant or revocation, no commit or push, and no staging or production access.
- **Where the probes ran:** only on throwaway SQLite test databases created by PHPUnit `RefreshDatabase`. The probe classes live in the session scratchpad; copies of their source are kept as evidence.
- **Work ID:** none allocated. The Owner must allocate one before implementation (see §11).

Evidence:
- `evidence/o17h-route-exposure-inventory.tsv`: every `rbac` route, with its class.
- `evidence/o17h-probe-results.tsv`: the probe matrix.
- `evidence/o17h-probe-test-source.php.txt` and `evidence/o17h-classify-probe-source.php.txt`: the probe sources.

---

## 1. Root cause

Authority is decided by a **role name** in several places, not by canonical grants (`user_roles` → `role_permissions`). Some of those places run before any grant is checked.

| # | Mechanism | Location | Reachable? | Effect |
|---|---|---|---|---|
| M1 | **Middleware short-circuit** | `RoleBasedAccessControlMiddleware::checkAccess()`: `if ($user->isSuperAdmin()) return true;` | **Yes: every `rbac:<arg>` route (888 routes)** | Any user whose `user_roles` include the role named `super_admin` passes every route permission gate, whatever that role's grants are. Neither the permission string nor whether it exists is checked. |
| M2 | Parameterless `rbac` gate | `handleGeneralAccess()` checks `hasAnyRole([...12 legacy names incl. super_admin...])` | Yes: 298 routes have **only** this gate | A role-name gate, not specific to `super_admin`. `User::hasAnyRole` also reads the legacy `users.role` column. Owner decision O-8 left it unchanged. |
| M3 | Role-name policy fallbacks | `DocumentPolicy` (21 controller calls), `SidebarConfigPolicy::before()` (6 calls), legacy `TaskPolicy` branches (`view`, `comment`, etc.). `NcrPolicy`, `RfiPolicy`, `ChangeRequestPolicy`, `TemplatePolicy`, `QcPlanPolicy`, `QcInspectionPolicy`, `InvitationPolicy`, `ComponentPolicy`, `UserPolicy` and `NotificationPolicy` have **no** controller callers. | Document + Sidebar: **yes** | `$user->hasRole([...])` with an array calls `User::hasAnyRole`, which matches role names **and** `users.role`. Example: `DocumentPolicy::update/delete` lets `super_admin` or `admin` through without the canonical grant. |
| M4 | In-controller and service role checks | `RfiController::userHasAdminRole()`, `SupportTicketController` (staff manager), `DashboardRoleBasedController` (allowed roles), `Activity`/`Alert`/`Filter`/`AnalysisService` (`hasRole('super_admin')` returns admin views) | Partly | The service "admin" branches return static mock data (checked: `getAdminActivities`, `getUserAnalysis`, `Admin\DashboardController::getStats`), so they expose no business data. The RFI admin role list widens RFI actions. |
| M5 | Dead role-name maps | `HasRoles::hasPermission()` (`'super_admin' => ['*']`) is shadowed by `User::hasPermission()`. `TenantScopeMiddleware`, `TenantAbilityMiddleware`, `AdminOnly` and `AdminOnlyMiddleware` (`isSuperAdmin()` cross-tenant pass) are **not registered and not on any route**. | **No** | Latent only. They become live if anyone re-registers or re-uses them. |
| — | No global hook | No `Gate::before` or `Gate::after` is registered. The 15 `Gate::define` entries (Treasury) delegate to `TreasuryPolicy`. | — | — |

**The semantic root cause.**
- `super_admin` is the **company Owner** role:
  - `production:bootstrap` creates the first production account with it;
  - `StaffAccountService::OWNER_ROLE = 'super_admin'` ("a company always keeps one active Owner");
  - `production:rbac` lists it as "Owner / quản trị — toàn quyền".
- M1 treats that same tenant-level Owner role as a **platform super-user**. This has two consequences:
  - Owner authority cannot be narrowed by removing grants, because grants are irrelevant to M1.
  - Platform-wide host operations are gated only by holding a tenant's Owner role.

**What is NOT a root cause** (verified):
- `roles.name` is `UNIQUE`, so no second role can be named `super_admin`. A name-collision escalation through `POST /api/v1/rbac/roles` is impossible.
- `tenant.isolation` has no `super_admin` exception. A mismatched `X-Tenant-ID` header returned 403 (probe XT5).
- Tenant-scoped controller lookups returned 404 for another tenant's objects (probes XT1–XT4, XT6, XT7).
- The legacy `users.role = 'super_admin'` alone does **not** pass M1, because `isSuperAdmin()` reads `user_roles` only (all `legacy` probes 403).

## 2. Route exposure inventory

The source is `route:list` at `c94ff67c0`, classified against a synthetic database seeded by `production:rbac`. It holds 248 permission codes; the `super_admin` role holds 222 of them. Full table: `evidence/o17h-route-exposure-inventory.tsv`.

**Routes by class.** 1 332 routes are registered; 1 186 carry `rbac` middleware.

| Class | Meaning | Routes |
|---|---|---|
| **X** | `rbac:<arg>` is **not a permission at all**. Only a `super_admin` passes. All 80 are `rbac:admin`. | **80** (44 read, 35 write; 1 `users-roles` read) |
| **Z** | The permission exists, but no seeded role holds it. Only a `super_admin` passes. | 1 (`component.delete`) |
| **N** | Held by other roles but **not** by the seeded `super_admin`, so a `super_admin` passes only through M1. Codes: `task.assign`, `change_request.create`, `component.create`, `component.read`, `component.update`, `notification.manage_rules`. | 12 |
| **C** | The seeded `super_admin` holds the grant. M1 changes nothing while the grants exist. | 795 |
| **P** | Parameterless `rbac` only (M2). | 298 |

**Effective class by module** (read = GET, write = everything else):

| Module | C (read / write) | X | N | P (read / write) |
|---|---|---|---|---|
| Users and roles (`rbac/*`, admin users) | 19 / 31 | 1 | — | 18 / 16 |
| Admin / platform (cache, maintenance, performance, final-integration, universal-frame, websocket, legacy-routes) | 1 / — | **79** | — | 20 / 16 |
| Projects and tasks | 70 / 113 | — | 8 | 44 / 31 |
| Finance (treasury, payroll, payments, expenses, month close) | 49 / 97 | — | — | — |
| Contracts and BOQ | 25 / 36 | — | — | — |
| CRM | 12 / 25 | — | — | — |
| Documents | 27 / 60 | — | — | 8 / 9 |
| RFI / change request / NCR / QC | 16 / 31 | — | 1 | — |
| Procurement / materials | 20 / 19 | — | — | — |
| Audit / activity | 3 / — | — | — | — / 1 |
| Other | 141 | — | 3 (+1 Z) | 135 |

**Middleware exposure is not the same as exploitability.** Many C routes have a second, independent check, and a zero-grant `super_admin` was **denied** there:
- in-controller `User::hasPermission`;
- canonical policies (`ContractPolicy`, BOQ, CRM `LeadPolicy`);
- `TaskCreationAuthority` (O-17);
- `ProjectPmAssignment` (O-16);
- `StaffAccountService`.

Without probing all 888 routes, the exact count of C/N/Z/X routes that have **no** second check is not known. §3 lists what was confirmed, and slice H1 (§10) makes the full set measurable by route-table-driven tests.

**`rbac:admin` (X, 80 routes): no second check exists.** All 11 controllers have zero `authorize`, `hasPermission` or role checks and no tenant scoping:
- `Admin\MaintenanceController`: `OPTIMIZE TABLE` on all tables, backup database, log deletion, cache clear;
- `Api\CacheController`: invalidate, flush, warm-up;
- `PerformanceOptimizationController`: `optimize-database`, `implement-caching`;
- `FinalIntegrationController`: launch and readiness "tasks";
- `Api\WebSocketController`: broadcast, notification;
- `Api\LegacyRouteMonitoringController`;
- `Api\Admin\DashboardController`, plus the Activity, Alert, Filter and Analysis controllers (static mock data).

These operations act on the **whole host and database, not one tenant**.

## 3. Confirmed exploit paths (controlled probes)

All runs were in-process HTTP calls against a synthetic SQLite database seeded with `production:rbac`.
- `zeroSA`: holds the global `super_admin` role with all 222 of its grants detached. This models an environment where `production:bootstrap` ran without `production:rbac`, or where grants were removed. The grants were restored afterwards.
- Controls: `nobody` (a zero-permission custom role) and `member` (the seeded Project Member). Both were denied on every probe below.
- `legacy` (`users.role = super_admin`, no role rows) was denied on every probe below.
- `realSA` is the seeded Owner, as a reference.

| # | Route | Expected authority | zeroSA result | Persisted side effect | Audit | Severity |
|---|---|---|---|---|---|---|
| E1 | `POST api/v1/rbac/assignments/users/{user}/roles` (role = the real `super_admin`) | `role.assign` | **200** | **Yes**: a `user_roles` row gives the victim the Owner role | none in `audit_logs` (an event fires; no audit row) | **Critical**: a zero-grant holder can create more Owners |
| E2 | `POST api/v1/rbac/roles` | `role.create` | **201** | **Yes**: a role row | none | High |
| E4 | `POST api/v1/rbac/permissions` | `permission.create` | **201** | **Yes**: a permission row (`o17h.probe`) | none | High (catalogue integrity) |
| WF1 | `POST api/zena/change-requests/{id}/approve` | `change-request.approve` | **200** | **Yes**: status `submitted` → `approved` | 1 row | High (business workflow) |
| DOC1 | `PUT api/documents/{document}` | `document.update` | **200** | **Yes**: title changed | none | High: M1 at the route plus the M3 `DocumentPolicy` role-name fallback |
| DOC2 | `DELETE api/documents/{document}` | `document.delete` | **200** | **Yes**: soft-deleted | none | High (M1 + M3) |
| R3 | `GET api/v1/rbac/roles` | `role.view` | **200** | — | — | Medium (RBAC catalogue disclosure) |
| R4 | `GET api/v1/rbac/users/{user}/effective-permissions` | `user.view` | **200** | — | — | Medium |
| R5, R6 | `GET api/admin/dashboard/stats`, `GET api/cache/stats` | `admin` (not a permission) | **200** | — (R6 reads live cache-server statistics) | — | Medium. The write siblings were **not executed** because they have host side effects (see below). |
| P1 | `GET api/user-preferences` (M2 only) | any of the 12 legacy role names | 200 (`nobody` and `member` 403) | — | — | Low; an O-8 issue, not specific to `super_admin` |
| WF2 | `POST api/v1/task-assignments` (N, `task.assign`) | `task.assign` | passed the gate; 422 at validation | none | — | Gate bypass confirmed. Effect not shown because the payload was rejected. |

**Denied for zeroSA because a second layer held:**
- R1, R2, R7, W1, W2, D1, D2: contract, BOQ and CRM reads, writes and deletes, through the canonical policies.
- E3: syncing permissions onto the global role returned 404 (`tenantVisible`).
- E5: web admin role change was refused (no change) by `StaffAccountService`.
- P2: `GET api/users` was refused by the in-controller `user.view` check.
- Cross-tenant: every probe on tenant B returned 404 or 403, with no side effect.

**Probed vs. inferred (the seven Gate-3 questions):**

| Question | Result |
|---|---|
| 1. Read protected resources | **Confirmed** (R3, R4, R5, R6) |
| 2. Create or modify | **Confirmed** (E2, E4, WF1, DOC1) |
| 3. Delete | **Confirmed** (DOC2) |
| 4. Assign permissions or roles | **Confirmed** (E1, E2, E4). E3 was blocked by `tenantVisible`. |
| 5. Another tenant | **Not exploitable** in the probed surface (all 404/403). The `rbac:admin` host-wide operations are **cross-tenant by nature** (e.g. `POST admin/maintenance/database`, `api/cache/clear`). They are exposed at the middleware layer and have no second check, but they were **not executed**, because they act on the real host, cache and filesystem. |
| 6. Another project's records | Task records stay protected by S4/O-17. Documents in the same tenant can be changed through DOC1/DOC2 regardless of project. Contracts and BOQ stay protected. |
| 7. Protected workflows | **Confirmed** (WF1 change-request approval). Document `approve` is canonical (`hasPermission('document.approve')`), so it stays protected. |

## 4. Risk and severity

| Configuration | Who holds `super_admin` | Incremental exposure from M1 | Rating |
|---|---|---|---|
| **Production as provisioned:** `production:bootstrap` + `production:rbac`; Owner role holds 222 grants | Company Owner(s) only. Only `super_admin` holds `role.assign`, `role.create`, `admin.company.manage` and `auth.bulk.manage`, so no other seeded role can obtain it. | 80 X routes (host-wide maintenance, cache and performance operations); 12 N routes; any future route whose permission string is mistyped or not yet seeded. **Grant removal from the Owner role is ineffective.** | **High**, and **Critical if production holds more than one tenant**: any company Owner could then run host-wide operations that affect every tenant. |
| Any environment where the role lost or never received its grants (bootstrap without `production:rbac`, a partial reseed, a manual clean-up) | As above | Every confirmed path in §3 | **Critical** |
| Test suites | `createTenantUser()` defaults to `['super_admin', 'Admin']` and is used by about **198 test files** | Tests can pass only because of M1, as CB-4 already showed for task creation. That masks missing grants. | High (assurance), not runtime |

There is **no confirmed escalation from a non-`super_admin` account to `super_admin`** in the production configuration. The risk is unbounded authority for whoever holds the role, and irreversibility (it cannot be narrowed by grants).

**Separate defects found during the assessment** (not part of O-17h; each needs its own Work ID; none fixed):
- **BLK-1.** `BulkOperationsService::createUser()` passes the whole request row to `User::create`. That allows:
  - a request-supplied `tenant_id`, i.e. creating users in **another tenant**;
  - the fillable legacy `role` field, e.g. `users.role = 'super_admin'`.

  It is gated by `auth.bulk.manage`, which in production only the Owner holds. Not probed.
- **AUD-1.** Role assignment (E1), role creation (E2), permission creation (E4) and document update and delete (DOC1, DOC2) write **no `audit_logs` row**.
- **O8-1.** The parameterless gate (M2) only accepts the role names on its legacy list. "Project Manager" and "Project Member" are not on it.
  - Example: `member` was refused on probes P1 and DOC1/DOC2 (`api/documents` carries the parameterless gate).
  - "Finance" matches the list entry `finance` only under a case-insensitive collation (MySQL default), not on SQLite.
- **O16-1.** Production has no "System Admin" role (neither `production:rbac` nor `production:bootstrap` creates one). O-16 granted `project.assign_manager` to System Admin only, so **nobody in production can appoint a PM**. M1 does not help here, because `ProjectPmAssignment` checks the canonical grant.

## 5. Legitimate dependencies on today's behavior

1. **The production Owner account(s).** All Owner authority on the 80 `rbac:admin` routes and the 12 N routes comes **only** from M1. On the 795 C routes it comes from grants, so it is unaffected by removing M1.
2. **The admin UI.** `StaffAccountService` uses the `super_admin` role **name** as the Owner identity (keep-one-active-Owner, no self-demotion). That is a role-identity rule, not an authorization bypass, and it should stay.
3. **`ZenaAdminRolePermissionSeeder` / `TreasuryRolePermissionSeeder` / `V1PermissionGapSeeder`.** They already grant canonical codes to the role named `super_admin`. These are explicit grants, so they are compatible with Option B.
4. **Test fixtures.** Around 198 files use the `super_admin` + `Admin` persona. Some will turn red once M1 is removed. The exact count is unknown until slice H1 measures it.
5. **`DocumentPolicy` legacy path.** S3 explicitly kept the role-name path alongside the canonical grant. Removing it is an authorization change and needs this decision.

## 6. Options

| | A. Remove the bypass; real grants only | **B. Replace the bypass with explicit, independently authorized grants (recommended)** | C. Keep the bypass, document the risk |
|---|---|---|---|
| Middleware M1 | `isSuperAdmin()` short-circuit removed | Removed | Kept |
| `rbac:admin` (80 routes) | Stays unreachable for everyone (operator tooling lost) | Re-gated on a **real** canonical code, granted explicitly | Unchanged |
| The 6 N codes | Owner loses them | Owner gets the ones the Owner approves, as explicit grants | Unchanged |
| Owner authority after change | = its grants (222) | = its grants (222 + the approved explicit codes) | Unlimited, not narrowable |
| Production impact | Owner loses operator pages and the 12 N routes on deploy | None, if the grants land in the same deployment (additive, via `production:rbac`) | None |
| Test impact | Fixture migration needed | Fixture migration needed | None |
| Security posture | Strongest, but removes legitimate tooling | Same canonical model as O-16/O-17/O-18. Every authority is a revocable, reviewable grant. | Root cause stays; host-wide operations stay per-tenant-Owner |
| Fit with O-17 (no `super_admin` exception on creation) | Consistent | Consistent | Contradicts it everywhere except task creation |

## 7. Recommendation: Option B, in bounded slices

Choose B. It closes the root cause (authority by role name) without silently removing anything the Owner uses today. Every remaining super-user capability becomes an explicit grant that can be listed and revoked, the same pattern the Owner already approved for O-16 (`project.assign_manager`), O-17 and O-18.

**The exact implementation boundary for approval:**
- **In scope:**
  - `RoleBasedAccessControlMiddleware::checkAccess()`: remove the `isSuperAdmin()` short-circuit and nothing else in the middleware;
  - the 80 `rbac:admin` route declarations: re-gate them (or retire them; see O-17h-2);
  - additive grant(s) to the `super_admin` role, only those the Owner approves in O-17h-2/3, delivered through the existing additive seeders and `production:rbac`;
  - test fixtures that relied on M1, changed to explicit grants with no assertion changes;
  - new RED and guard tests (§9).
- **Separate slice, separately approved (H4):**
  - the M3 policy fallbacks: remove the `super_admin`/role-name fallback from the **reachable** policies (`DocumentPolicy`, `SidebarConfigPolicy::before`);
  - the legacy `TaskPolicy` branches, already a merge blocker (`TaskPolicy::view()`).
- **Out of scope:**
  - M2, the parameterless gate (O-8);
  - M5 dead code (it may be deleted only as no-behavior hygiene with a guard test);
  - `users.role` retirement;
  - BLK-1, AUD-1, O8-1, O16-1;
  - any global RBAC rewrite;
  - any automatic role conversion;
  - any production grant or revoke outside a reviewed `production:rbac` run.

## 8. Compatibility and migration

| Area | Effect under B |
|---|---|
| `production:rbac` | Additive `syncWithoutDetaching` of the approved code(s) to `super_admin`. It never revokes, as with O-18. Re-running is idempotent. |
| Existing Owner accounts | Keep every C-route capability (grants already present). Keep the `rbac:admin` and N-route capabilities **only** for the codes granted in the same release. **Before deploying, a read-only production inventory must confirm** that the production `super_admin` role really holds the expected grants. If it holds fewer, the Owner loses access on deploy (a fail-closed outcome, but disruptive). |
| System Admin (dev/test seeds only) | Unaffected: it never relied on M1 (it is not named `super_admin`). |
| O-15 / O-16 | Unaffected. O-16's appointment check is already canonical. O16-1 (nobody can appoint a PM in production) is independent and needs its own decision. |
| O-17 / O-18 | O-17 creation is already independent of M1. O-18 PM/Member baselines do not involve `super_admin`. Both are unchanged. |
| Legacy `users.role` | M1 never read it. Still read by M2/M3 (H4 / O-8). |
| `user_roles` / `role_permissions` | Become the only source of route authority for every role. |
| Test fixtures | The `createTenantUser()` default persona needs explicit grants for the routes each test exercises. Measure, then migrate, in H1 and H3. No assertion is changed. A test that expects a bypass must be reported to the Owner, not rewritten. |
| Seeded permissions | Possibly one new canonical code (e.g. `platform.operate`) **or** reuse of the existing, currently ungranted `system.admin`. The Owner chooses (O-17h-2). |

## 9. Required regression tests (design; not written)

**RED tests.** Each fails today and must pass after the fix:
1. **Route-table invariant (H1).** For every route with `rbac:<arg>`, `<arg>` must be an existing permission code. This kills class X: today 80 `rbac:admin` routes fail it.
2. **Zero-grant Owner invariant (H1/H3).** It uses the real global `super_admin` role with its grants detached inside the test, like the probe. For every C/N/Z/X route, the middleware alone returns 403 (exercised through a route-table-driven request with a terminating stub, so no controller runs).
3. **Confirmed-path tests.** E1, E2, E4, WF1, DOC1, DOC2, R3, R4, R5 and R6 for a zero-grant `super_admin`. Each asserts 403 and zero change to `user_roles`, `roles`, `permissions`, `change_requests.status`, `documents` and `audit_logs` (DOC1/DOC2 depend on H4 for the policy layer).
4. **N codes.** A `super_admin` without `task.assign` cannot `POST api/v1/task-assignments`.

**Guard tests.** These must stay green:
5. **The fully granted Owner** (`production:rbac` seeded) keeps:
   - R1–R7, W1, W2, D1, D2, E1, E2 and WF1, plus the approved `rbac:admin` routes;
   - admin staff management (`app/admin/users`) and StaffAccountService rules: one active Owner, no self-demotion.
6. **No access expansion.** A snapshot of the per-role effective grant sets (Owner, PM, Member, Finance, site_engineer) before and after the change. The only differences allowed are the Owner-approved codes on `super_admin`.
7. **Tenant isolation.** Probes XT1–XT7 stay 404/403 for every actor.
8. **Project ownership.** The UatRecovery04* and 06 suites stay unchanged and green.
9. **Role-assignment boundaries.**
   - Only a `role.assign` holder can assign roles.
   - Assigning `super_admin` to oneself is refused (proposed O-17h-4).
   - Cross-tenant assignment is refused (existing GAP-042 tests).
10. **The existing approved System Admin and O-16/O-18 tests stay unchanged** (UatRecovery04d, UatRecovery05).
11. **`legacy` actor.** `users.role = super_admin` without role rows stays denied at the middleware.

**Verification commands:**
- Recovery, Feature, Api, Architecture and Unit suites;
- PHPStan, SSOT lint, governance lint, cs;
- CI including `browser-tests`;
- an additional MySQL 8 run of the new Recovery tests (still a P2 merge blocker).

## 10. Proposed bounded slices

Each slice is its own commit under the allocated Work ID, with its own Owner gate.

| Slice | Content | Runtime change | Owner gate |
|---|---|---|---|
| **H0** | Read-only production inventory: the `super_admin` grant set; how many accounts hold `super_admin`; how many tenants exist; who holds `role.assign`. A query script for the Owner to run, or run with explicit Owner approval. | None | Approve the read-only run |
| **H1** | RED tests 1–4 plus guard tests 5–11 (the RED ones quarantined as expected-failing), plus a measurement of how many fixtures depend on M1 | None (tests only) | Approve the test-only commit |
| **H2** | Re-gate the 80 `rbac:admin` routes on the approved code. Add the approved grant(s) to `super_admin` in the additive seeder used by `production:rbac`. **M1 stays**, so this changes nothing in behavior. | Route declarations + seeder grant | O-17h-2, O-17h-3 |
| **H3** | Remove the M1 short-circuit. Migrate the fixtures from H1's measurement (no assertion changes). Un-quarantine RED 1–4. | Middleware, 1 line | O-17h-1 |
| **H4** | Remove the role-name fallback from the reachable policies (`DocumentPolicy`, `SidebarConfigPolicy::before`) and align `TaskPolicy::view()` | Policies | O-17h-5 (+ the existing `TaskPolicy::view()` blocker) |
| **H5** (optional) | Delete M5 dead code (unregistered middleware; shadowed `HasRoles::hasPermission` map) with guard tests | None reachable | O-17h-6 |

## 11. Owner decisions required

| ID | Decision | Recommendation |
|---|---|---|
| **O-17h-1** | Choose A, B or C | **B** |
| **O-17h-2** | What replaces `rbac:admin` (80 routes): (a) reuse the existing, currently ungranted `system.admin` code; (b) a new `platform.operate` code; (c) retire the platform/maintenance endpoints (much of it is mock or dev tooling), keeping only what the Owner names | (b) with a review of which endpoints to keep. If production may ever hold more than one tenant, these host-wide operations should not belong to a tenant Owner at all. |
| **O-17h-3** | Which of the 6 N codes the Owner role should hold explicitly: `task.assign`, `change_request.create`, `component.create`, `component.read`, `component.update`, `notification.manage_rules` | Grant only those the Owner actually uses. Each is additive and revocable. |
| **O-17h-4** | Protected-role assignment: should assigning `super_admin` require the actor to already be an Owner, forbid self-assignment, and write an audit row (as O-16a does for PMs)? | Yes, as part of H3 or its own slice |
| **O-17h-5** | Remove the `super_admin`/role-name fallback from reachable policies (H4)? | Yes, separate slice |
| **O-17h-6** | Delete M5 dead code (H5)? | Optional hygiene |
| **WORK-ID** | Allocate a governance Work ID for O-17h (not invented here) | Required before any slice |
| **Separate items** | BLK-1 (bulk user creation: cross-tenant `tenant_id` + legacy `role`), AUD-1 (no audit on role/permission/document changes), O8-1 (parameterless gate rejects production PM/Member/Finance), O16-1 (no one in production can appoint a PM) | Register each with its own Work ID. None is fixed here. |

**Not done here:**
- no runtime, policy, seeder or route change; no grant or revocation; no commit or push; no production or staging access;
- host-side `rbac:admin` write operations not executed;
- not probed: BLK-1, and the M3 fallbacks other than `DocumentPolicy`;
- MySQL 8 not used: the probes ran on SQLite only.
