# GAP-053 — Dashboard RBAC performance-fixture Gate-1 evidence

**Date:** 2026-09-15 (+07:00)

**Canonical base:** `adacc5cc5fb8a08353cc90576076724e45e6e8bc`

**Branch:** `docs/GAP-053-dashboard-rbac-performance-fixture-gate1`

**Scope:** Read-only root-cause investigation and Gate-1 documentation. No
application, RBAC, route, test, threshold, CI policy, GAP-041, migration, or
deployment change is included.

## Finding

**Classification: A — STALE TEST FIXTURE.** The application/security behavior
is correct. `DashboardPerformanceTest::it_can_handle_role_based_filtering_performance`
creates four users with only the legacy scalar `users.role`. It creates no
canonical `Role` record or assignment pivot for those loop users. Three scalar
values happen to be admitted by the middleware's compatibility path;
`client_rep` is not, because the canonical middleware role is `client`.

A controlled genuine-login reproduction proves that an otherwise equivalent
`client_rep` with canonical `client` assignments returns 200. Therefore this is
not an application/RBAC defect and the released expectation that
`client_rep` can access the retained dashboard routes remains valid.

## Candidate-ID and base audit

Before creating these documents:

- `origin/main` and `HEAD` both resolved to the requested canonical SHA;
- no `GAP-053` entry existed in `OPERATIONAL_GAP_REGISTER.md`,
  `docs/roadmap/backlog.yaml`, repository documentation, owner records, or git
  history;
- no remote branch, GitHub Issue, or PR contained `GAP-053`;
- the only matching branch was this session's pre-provisioned local branch,
  already clean and pinned to the canonical SHA.

## Complete request path

1. **Fixture:** `tests/Performance/DashboardPerformanceTest.php:658-685`
   loops over `project_manager`, `site_engineer`, `qc_inspector`, and
   `client_rep`, calling `User::create()` with `role` and `tenant_id` only.
   This differs from its own `setUp()` at lines 45-52, which uses
   `createTenantUserWithRbac()`.
2. **Authentication:** `AuthenticationTrait::apiAs()` logs in through
   `/api/auth/login`, sends a genuine Sanctum Bearer token plus `X-Tenant-ID`,
   and clears cached guards (`tests/Traits/AuthenticationTrait.php:70-113`).
   Authentication succeeds in the failing case; the 403 is later.
3. **Canonical assignment:** `User::roles()` reads `user_roles`, and
   `User::systemRoles()` reads `system_user_roles`
   (`app/Models/User.php:90-107`). `TenantUserFactoryTrait::createTenantUser()`
   attaches requested roles to both pivots (`tests/Traits/TenantUserFactoryTrait.php:14-52`).
   `FixtureFactory::createTenantUserWithRbac()` preserves the scalar app role
   while establishing the canonical RBAC role and both assignments
   (`tests/Support/SSOT/FixtureFactory.php:34-85`).
4. **Middleware:** `rbac` resolves to
   `RoleBasedAccessControlMiddleware` (`app/Http/Kernel.php:57`). General
   access accepts a defined RBAC role set and denies users lacking an allowed
   assignment with `RBAC_ACCESS_DENIED`
   (`app/Http/Middleware/RoleBasedAccessControlMiddleware.php:255-293`).
   `User::hasAnyRole()` also has a scalar compatibility shortcut
   (`app/Models/User.php:180-189`), explaining the asymmetric false controls.
5. **Route:** `/api/v1/dashboard/role-based/widgets` is under
   `auth:sanctum`, `tenant.isolation`, and `rbac`
   (`routes/api.php:787,902-913`). Runtime `route:list --json` confirmed the
   full middleware stack.
6. **Dashboard role:** only after middleware admission, the controller reads
   scalar `users.role` and asks the service for that dashboard configuration
   (`DashboardRoleBasedController.php:114-120`). The released validator
   explicitly supports `client_rep`
   (`app/Services/Dashboard/DashboardRoleValidator.php:9-24`). Dashboard-role
   selection and RBAC assignment are related but distinct identity dimensions.
7. **Released contract:** GAP-051 already recorded the exact mapping
   `client_rep` (business/dashboard role) to `client` (canonical middleware
   role) and classified missing pivot assignment as fixture debt
   (`docs/owner-decisions/GAP-051/03-release.md:211-221`). GAP-052 Gate 2
   retains `client_rep` among the seven exact dashboard roles and requires
   successful metadata responses/degraded unsupported-widget results, not an
   RBAC denial (`docs/owner-decisions/GAP-052/02-design-v2.md:39-58`). GAP-052
   is released on main via PR #312/merge `cf70123669573ba9aecad1817804365b9193951a`
   (`OPERATIONAL_GAP_REGISTER.md:60`).
8. **Current tests:** `GAP052DashboardWidgetContractTest` creates every
   dashboard role through `createTenantUser(..., ['admin'])` and asserts both
   retained routes return 200 (`tests/Integration/GAP052DashboardWidgetContractTest.php:27-86`).
   `SystemIntegrationTest` uses the narrower explicit mapping
   `client_rep -> client` before its 200 assertions
   (`tests/Integration/SystemIntegrationTest.php:689-759`).

## Controlled reproduction

All probes ran on the canonical base with genuine API login. The diagnostic
test was temporary, untracked, and deleted immediately after execution; no
committed source was changed.

| Identity construction | `users.role` | `user_roles` | `system_user_roles` | Result |
|---|---|---|---|---|
| performance-test baseline | `client_rep` | `[]` | `[]` | `403 RBAC_ACCESS_DENIED` |
| canonical helper/pattern | `client_rep` | `[client]` | `[client]` | `200` |
| known-working scalar control | `project_manager` | `[]` | `[]` | `200` |
| canonical working control | `project_manager` | `[project_manager]` | `[project_manager]` | `200` |

The unchanged performance method reproduced the complete observed sequence:
`project_manager` 200, `site_engineer` 200, `qc_inspector` 200, then
`client_rep` 403 at line 681. The controlled probe passed 8 assertions. The
released GAP-052 integration suite separately passed 6 tests / 153 assertions,
including both routes for all seven dashboard roles.

Local PHP emitted unrelated optional-extension startup warnings for `imagick`
and `memcached`; they did not prevent either reproduction.

## Hypothesis disposition

- **A — confirmed.** The loop fixture omits canonical RBAC assignment.
- **B — rejected.** A canonically assigned `client_rep`/`client` reaches the
  endpoint and returns 200.
- **C — rejected.** Released GAP-052 explicitly supports `client_rep` and its
  executable contract passes on current main.
- **D — no alternative root cause found.** Authentication, tenant isolation,
  routing, dashboard-role validation, and widget handling all occur as designed
  once identity construction is canonical.

## Blast radius and remediation boundary

The immediate blocker is test-only: one iteration in
`DashboardPerformanceTest::it_can_handle_role_based_filtering_performance`,
and therefore PR #316's truthful Dashboard performance matrix leg. No
application endpoint, RBAC policy, tenant boundary, dashboard contract, or
security behavior needs to change.

Repository inspection also found scalar-only role loops in
`DashboardE2ETest.php:640-688` and `FinalSystemTest.php:983-1006`. They are not
the PR #316 failing matrix target and were not changed or used to expand this
Gate-1 remediation scope; they are recorded as adjacent test-fixture debt for
future verification when those surfaces are made executable.

Minimal safe options:

1. **Recommended:** in the affected performance loop only, replace direct
   `User::create()` with the already-used `createTenantUserWithRbac()` helper,
   preserve each scalar dashboard role, and map only `client_rep` to canonical
   RBAC role `client` (other roles map to themselves). This changes test
   identity construction only and exercises least-privilege representative
   roles.
2. Use `createTenantUser()` with the same explicit mapping. This is smaller in
   behavior but less aligned with the performance class's existing SSOT helper.
3. Assign blanket `admin`, as GAP-052's broad contract test does. This would
   produce 200 but is not recommended for a role-filtering performance test
   because it overstates the tested user's authority.

Do not add `client_rep` to middleware, weaken the guard, accept scalar identity
as canonical, fake permissions, or change the expected 200.

## Relationship to GAP-041 PR #316

PR #316 is OPEN, Draft, unmerged, and based on the same canonical SHA. Its
approved GAP-041 changes make the performance matrix execute truthfully; they
do not edit this test, RBAC, application behavior, GAP-045 thresholds, or
evidence-freshness policy. Its only failing current-head check is the Dashboard
performance leg exposing this pre-existing fixture defect.

After a separately approved and released GAP-053 test-only correction, GAP-041
can resume with its authored implementation unchanged. PR #316 will need only
normal base integration/revalidation against the new main; no GAP-041 design,
workflow logic, threshold, policy, or application change is indicated.
