# OWN-2026-024 (O-17h) — H4 local implementation: policy authorization hardening

**Status: see §12.** H4 is **local and uncommitted**. **No checkpoint commit was created** (§9: Gate 9 STOP, the slices cannot be separated cleanly). Nothing is pushed. PR #422 stays Draft and MERGE_NO_GO at remote head `c94ff67c0`. There was no production or staging access. H0 was **not** executed. The original checkout (`a0381f5e3`, 39 entries) was not touched.

- **Owner decision (2026-11-25):** "H3_ACCEPTED / H4_LOCAL_IMPLEMENTATION_APPROVED". Local checkpoint commits are approved with restrictions. Push, merge and deploy are not approved. Production is **roll-forward-only**.
- **Prior documents:** 100 (assessment), 101 (H0/H1 design), 102 (H2a), 103 (H2b), 104 (H3).

## 1. Gate 0: baseline

| Check | Result |
|---|---|
| PR #422 | head `c94ff67c0`, Draft |
| H2a / H2b / H3 work | present in the worktree; H3 Recovery 211 tests OK before H4 |
| Original checkout | untouched, 39 entries |
| Evidence 100–104 | unchanged |

## 2. Complete policy inventory (Gate 1)

The full table is `evidence/o17h-h4-policy-inventory.tsv`: every policy and ability, with its call site, route gate, previous role-name condition, canonical replacement, tenant/object rule, legitimate and unauthorized access, decision and tests.

**Sweep.** I searched `app/`, `src/`, `routes/` and `resources/views` for every policy and Gate call form: `authorize`, `can`, `cannot`, `canAny`, `allows`, `denies`, `inspect`, `@can`, `can:` middleware, `Gate::forUser` and FormRequest `authorize()`.
- `Gate::before` hooks: **none**.
- `Policy::before`: **only `SidebarConfigPolicy`**.

**Reachable role-name shortcuts, all fixed in H4**

| # | Shortcut | Who passed it without a grant |
|---|---|---|
| P-1 | `DocumentPolicy` `view`/`update`/`download` OR-ed `hasRole(['super_admin','admin','pm','designer','engineer'])`. `hasRole(array)` also reads the legacy `users.role` column. | the role names, and `users.role` values, with no grant |
| P-2 | `SimpleDocumentController::destroy` had **no** policy call. `api/documents-simple/{id}` (an `apiResource`) is gated **only** by the parameterless role-name `rbac` gate. | **any** user holding one of its 12 legacy role names (e.g. the seeded `site_engineer`; `Finance` on case-insensitive MySQL collation) could delete any document of the tenant, files included |
| P-3 | `SimpleDocumentController::index`/`store` had no policy call (same `documents-simple` exposure) | the same population could list or create documents |
| P-4 | `SidebarConfigPolicy::before()` returned true for the role name `super_admin`; `viewAny`/`view` also admitted `admin` (role or `users.role`). Object abilities had no tenant check. | a zero-grant `super_admin`, cross-tenant |
| P-5 | Navigation visibility (`OperatorNavigationComposer::loadPermissionNames`) read `role_permissions` directly | revoked grants and ledger-less platform codes stayed visible (the routes still denied them) |

**Legitimate role checks kept (narrowing, not bypass)**
- **`delete` / `restore` / `forceDelete`** were always narrower than their route gate ("super_admin and admin only"). Now:
  - document.delete **and** canonical Owner authority (`RbacProtection::hasOwnerAuthority`: the Owner role **and** its revocable `admin.company.manage` grant);
  - a role that merely holds `document.delete` (e.g. a dev-seeded "System Admin" with every permission) still cannot delete. This is the existing UAT-03 guard row.
- **`assignApprover`** (GAP-033 §6.2): the project's own PM, or the tenant Owner, now via canonical Owner authority instead of a role name.
- **`TaskPolicy::update/delete/assign`:** already canonical (S4). Unchanged.

**Unreachable role-name policies. Separate Owner gate O17H-H4-G1, not changed.**
- **Policies:** `ChangeRequest`, `Component`, `Ncr`, `Rfi`, `QcPlan`, `QcInspection`, `Notification`, `Template`, `Invitation` and `User`, plus `TaskPolicy`'s legacy `view/create/comment/attachFiles/changeStatus/viewTimeTracking/editTimeTracking`.
  - They have **no caller today**. The independent review (§8) confirmed this, multi-line forms and FormRequests included.
  - Replacing them means choosing a canonical code per ability, which is a business decision. `TaskPolicy::view` is already pending as doc 96 P0 item 3.
- **Guard:** the 07e tripwire fails if any of them becomes reachable. It covers:
  - ability calls whose subject is the model class or any variable named after the model, multi-line included (mutation-tested);
  - `can:` middleware and `@can`.
  - **Known limit:** a variable not named after the model, or a variable ability, is not detected. The Owner gate is the real control.

**Other separate Owner gates found**
- **O17H-H4-G2:** who may manage **global** sidebar configs (`tenant_id` NULL, affecting every tenant). Tenant Owners are now denied; previously the role name `super_admin` could.
- **SB-1:** no migration creates `sidebar_configs`. The whole sidebar-config API fails after authorization in every environment, and nothing renders those configs (`SidebarService` is unwired).

## 3. Implementation (Gate 2)

| File | Change |
|---|---|
| `app/Policies/DocumentPolicy.php` | Rewritten. Canonical codes plus string-safe tenant check for every ability. The narrowing abilities use canonical Owner authority. No `hasRole`, no `users.role`. |
| `app/Policies/SidebarConfigPolicy.php` | `before()` removed. Every ability needs a tenant Owner (non-null tenant **and** Owner authority). Object abilities need `config.tenant_id == user.tenant_id`; global configs are denied. |
| `app/Http/Controllers/Api/SimpleDocumentController.php` | `authorize('viewAny')` in `index`, `authorize('create')` in `store`, `authorize('delete', $document)` in `destroy`, before any file or row is removed |
| `app/Models/User.php` | `grantedPermissionNames()`, with exactly `hasPermission()`'s rules (`user_roles`, durable revocations excluded, `platform.*` only for an active operator) |
| `app/Support/Navigation/OperatorNavigationComposer.php` | `loadPermissionNames()` uses it |

**Requirements check**
1. No automatic `super_admin` or `admin` approval.
2. Active canonical grants only (`hasPermission`), so durable revocations apply.
3. Tenant isolation on every object ability. Project/object rules kept: the `assignApprover` PM rule and the TaskPolicy S4 rules.
4. No System Admin bypass: delete needs Owner authority.
5. No `users.role` fallback.
6. No denial rule weakened.

**Behaviour alignment the Owner should note.** On routes whose only gate is the parameterless `rbac` (`documents-simple` show/update), `view`/`update` now equal the permission the canonical routes already require. A role holding `document.view`/`document.update` but outside the old role-name list now passes there, as it already did on `api/v1/documents`. Production roles are unaffected: the role-name list did not contain `Project Manager` or `Project Member`, and on the canonical routes those roles already had exactly this access.

## 4. Security tests and RED/GREEN (Gate 3)

New `tests/Feature/Recovery/UatRecovery07eO17hH4Test.php` (8 tests):

| Gate 3 item | Evidence |
|---|---|
| Zero-grant `super_admin` / `admin` denied | 07e role-names test: role `admin` with zero grants; `users.role` `admin`/`super_admin`/`pm`; a zero-grant custom role; the Owner role with grants detached. 0 document and 0 sidebar abilities allowed. Also H1 policy tests. |
| Real grants permit intended actions | 07e: Owner deletes via `documents-simple`, lists documents, manages own-tenant sidebar; seeded PM approves |
| Revoked grants deny | 07e: `rbac:permission revoke document.delete`, then `production:rbac`. Policy `delete`/`forceDelete` denied; `update` still allowed; the code disappears from `grantedPermissionNames()` and from the navigation. |
| Cross-tenant document operations denied | 07e: foreign-tenant Owner `DELETE` fails, document intact; foreign PM approve denied; policy `sameTenant` everywhere |
| Unauthorized approval denied | 07e: Member (with `users.role` `pm`) and foreign PM denied |
| Unauthorized deletion denied | 07e: `site_engineer`, `Project Manager` and `users.role` admin on `DELETE api/documents-simple/{id}` all fail with **nothing deleted**. Mutation-tested: removing the new `authorize('delete')` turns the test red. |
| Sidebar / navigation visibility follows real permissions | 07e: Owner sees the company item but not the host console. A leaked Operator role without a ledger sees nothing. A provisioned operator sees the host console but not tenant admin. |
| `Policy::before` cannot override revocations | 07e: no `before()` on `SidebarConfigPolicy`; Gate `beforeCallbacks` is empty |
| Platform authority independent | 07e: an operator gets no sidebar or document authority; the Owner gets no platform authority |
| Owner management operational | 07e: Owner authority holds; appointing another Owner works; H3/H2b tests |
| Task creation protected by O-17 | Recovery `UatRecovery06…` (O-17) classes green on SQLite and MariaDB; TaskPolicy untouched |

**RED tests released** (assertions unchanged):
- `test_document_policy_does_not_bypass_canonical_grants`;
- `test_sidebar_config_policy_does_not_bypass_canonical_grants`.

**The quarantine is now empty:**
- `phpunit.xml` no longer excludes the group (identical to HEAD);
- `UatRecovery07O17hQuarantineGuardTest` enforces that the quarantine stays empty, that `phpunit.xml` doesn't exclude the group, and that all 10 released tests still exist and are recorded;
- `QUARANTINE-O17H.md` updated;
- `--group uat-recovery-07-o17h-red` executes nothing.

**Superseded assertions (they asserted the bypass itself). Owner review requested.** The originals are verbatim in `evidence/o17h-h4-superseded-originals.php.txt`. Each replacement keeps a positive canonical case.

| Test | Original assertion | Replacement |
|---|---|---|
| `UatRecovery03…::matrix` rows "Legacy admin value" / "Legacy designer value" | a `users.role` value alone gave view/download/update (+ assignApprover/delete for admin) | all abilities false |
| `DocumentPolicyTest::test_user_can_delete_document_with_admin_role` | role named `admin` deletes | admin denied; `document.delete` without Owner authority denied; canonical Owner allowed |
| `DocumentPolicyTest::test_super_admin_can_perform_all_actions` | role named `super_admin` holding only `document.approve` passes 7 abilities | the bare role name is denied the 6 it has no grant for; the provisioned Owner passes all 7 |
| `DocumentPolicySimpleTest::test_user_can_view…` / `…create…` | `users.role = 'admin'` alone | column alone denied, then allowed after a canonical grant |
| `PolicyTest::document_policy_allows_proper_access` | `users.role`-only matrix | no `users.role` value authorizes; canonical grants do |
| `DocumentAssignApproverPolicyTest::test_admin_role_may_assign_approver…` | role named `admin` | role name denied; canonical Owner allowed |

**Fixture migrations (assertion unchanged, grant added)**
- `DocumentPolicyTest` view/create/update/download: the role gets exactly the code.
- `PolicyTest::tenant_isolation_prevents_cross_tenant_access`: both users get the same canonical grants. The test is about tenants.

## 5. Browser tests (Gate 4)

CI runs only `tests/Browser/Projects/` and `tests/Browser/Crm/` (the `browser-tests` job).

| File | Persona | Relies on `super_admin`? | Effect of H3/H4 |
|---|---|---|---|
| `Projects/ProjectCreateCanaryTest` | zero-role user (canary page load) | no | none |
| `Projects/ProjectCreateTest` | custom role with only `project.create` | no | none |
| `Projects/WorkTemplateApplyBrowserTest` | custom PM role with `project.view`, `template.view`, `template.apply`, `task.create`; project PM | no | none expected; navigation visibility now uses `grantedPermissionNames()` (same result without revocations) |
| `Crm/PipelineDragDropTest` | custom role with `crm.manage`, `crm.view` | no | none |

The other 8 browser files are not run by CI: `Authentication`, `Dashboard`, `DocumentManagement`, `ProjectManagement`, `TaskManagement`, `SubmittalResubmitDirtyState`, and `Buttons/*`. They use legacy helpers, and `SubmittalResubmitDirtyStateTest` uses role `admin`. They may already be stale; they are recorded, not changed.

No fixture privilege was inflated. **A CI browser run is required** before any merge (no chromedriver locally).

## 6. Rollback safety: roll-forward-only (Gate 5, prepared, nothing executed)

1. **Expand migration order:**
   - deploy the code;
   - `php artisan migrate` (only `2026_11_24_200000_own_2026_024_h2b_…`, additive: two tables and one nullable column; no backfill);
   - `php artisan production:rbac`;
   - restart the workers.
2. **Permission inventory prerequisites (from H0):**
   - the Owner role holds every code its workflows need: the 223 including `admin.company.manage`, `document.*`, `role.*`;
   - every `rbac:<code>` route literal exists as a permission (`test_every_rbac_argument_is_a_real_permission_code`);
   - no role holds `platform.*`.
3. **Operator provisioning:** none exists by default, so host routes deny everyone (fail closed). Provision only via `php artisan platform:operator grant --user=… --reason=… --approval=<Owner ref> --yes` on the server console, after a separate Owner approval naming the person.
4. **Fail-closed deployment checks (read-only, after migrate):**
   - migration row present;
   - `role_permission_revocations` and `platform_operators` exist;
   - `SELECT COUNT(*) … role_permissions` joined to `platform.*` = 0;
   - Owner role grant count ≥ the H0 value;
   - one Owner smoke login: `/app/admin/roles` 200, a host route 403.
5. **Owner lock-out prevention:**
   - H0 first;
   - `production:rbac` in the same deploy;
   - the `revoke()` refusal for `admin.company.manage` on the Owner role;
   - the role-sync guard (H3);
   - last-active-Owner protection;
   - global roles are read-only over HTTP.
6. **Safe roll-forward correction:**
   - a wrong revocation → `php artisan rbac:permission regrant --role … --code … --reason … --approved-by <Owner>` (audited);
   - a missing grant on a tenant role → the governed role-permission sync;
   - a code defect → fix forward in a new governed PR.

   Never re-enable the bypass.
7. **Emergency manual rollback gate (exceptional, Owner-only):**
   - a code rollback past H3 restores the role-name bypass, and an old `production:rbac` re-adds revoked grants (104 RB-1). So no automated rollback.
   - Prerequisites: a written Owner approval naming the target release; `php artisan rbac:permission list` exported to the incident record; `production:rbac` **disabled** on the old code; revocations re-applied by hand after re-deploying forward.
   - The migration `down()` refuses while any active revocation or operator record exists.
8. **Revocation history preserved:**
   - revocation rows are superseded, never deleted (`superseded_*` fields plus audit rows);
   - `down()` refuses with active records;
   - **never drop `role_permission_revocations`**;
   - backups must include both new tables.

## 7. H0 production read-only preflight (Gate 6, NOT executed)

Checklist for the operator before running `evidence/o17h-h0-readonly-inventory.sql`:

| # | Check | How |
|---|---|---|
| 1 | Least-privilege account | dedicated MySQL user with only `SELECT` on the app schema; no `SUPER`, `PROCESS`, `FILE` or write grants (`SHOW GRANTS` reviewed by a second person) |
| 2 | Session read-only | the script begins `SET SESSION TRANSACTION READ ONLY; START TRANSACTION READ ONLY;` and ends with `ROLLBACK`. First verify that an `INSERT` in that session is refused (on a scratch table under a non-production schema, **not** production). |
| 3 | Timeouts | `SET SESSION MAX_EXECUTION_TIME=10000;` (MySQL 8) and client `--connect-timeout=10`; run outside peak hours |
| 4 | Exactly the 12 approved aggregate SELECTs | diff the file against the reviewed copy (hash); no ad-hoc queries |
| 5 | No PII | outputs are counts, role names, codes and tenant ordinals only; no names, e-mails or ids |
| 6 | No `production:rbac` | not run during the inventory |
| 7 | No mutation | read-only transaction plus a `SELECT`-only account; stop on any privilege or schema error |
| 8 | Independent SQL review | D7 condition: a second reviewer signs off on the exact SQL before execution |

**H4-specific amendment request (O17H-H4-G3, needs Owner approval because D7 allows only the 12 approved SELECTs).** H4 removes the `users.role` fallback from document authorization, so the deploy decision needs the distribution of `users.role` values among users **without** a canonical role. This is an aggregate, with no PII:

```sql
SELECT u.role, COUNT(*) FROM users u WHERE NOT EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id) GROUP BY u.role;
```

Without it, users relying only on `users.role` (`admin`/`pm`/`designer`/`engineer`) would silently lose document access.

## 8. Regression and independent review (Gates 7–8)

Results are in §10. The independent reviewer had fresh context and was read-only. **Verdict: no Critical or High findings; nothing blocks H4 locally verified.**

| Finding | Severity | Disposition |
|---|---|---|
| `Api\DocumentController` (many `Document::find` without `authorize`) and `Web\DocumentController@destroy/download` have no authorization | Medium latent, pre-existing | **Unrouted today.** Added to the 07e tripwire: it fails if any of them is ever routed. Fixing or retiring them is a separate gate (DOC-DEAD-1). |
| Tripwire easily evaded | Medium | **Hardened.** Any variable containing the model name, multi-line calls, Gate facade forms, case-insensitive `can:`. Mutation-tested. Residual limit documented in §2. |
| Sidebar class-level abilities did not require a tenant; a tenant-less Owner could reach the controller's request-supplied `tenant_id` fallback | Low, pre-existing | **Fixed:** `tenantOwner()` requires a non-null tenant |
| `documents-simple` `view`/`update` now align with the canonical route permission | Note | Recorded in §3 |
| `RbacProtection::protectedCodesOfRole` reads `permissions.code`; checks read `name` | Low, pre-existing | Consistent while code = name; reported |
| Superseded assertions are genuine bypass assertions with positive replacements | — | Confirmed |
| Scope | — | No unrelated behavioural change |

## 9. Local checkpoint commits (Gate 9): STOP, proposed plan

No commit was created. The H2a, H2b and H3 changes **cannot be separated cleanly**:
- The work exists only as one uncommitted tree. There is no snapshot of the tree at the H2a/H2b or H2b/H3 boundary.
- **25 files carry edits from two or more slices**, from a scan of each file's diff for the slice markers. Examples:
  - `RBACManager`, `RoleController`, `PermissionController`, `PermissionMatrixService`, `ProductionRbacCommand`, `ZenaPermissionsSeeder`, `routes/web.php`, `AuthenticationTrait`: H2a+H2b. H2b rewrote lines H2a had written.
  - `RoleController`, `PlatformPermissions`, `07b`: H2a+H2b+H3.
  - The H1 test, guard and manifest: H1 through H4.
- An H2a-only state would have to be **re-created**, not selected, and it was never tested.
- **H1 itself is uncommitted** and is not one of the three approved commits.
- The H4 edits share 5 files with H1–H3 (`User.php`, guard, manifest, H1 test, `phpunit.xml`).

**Proposed plan (Owner decision O17H-H4-G4):**

| Option | Commits | How | Verification |
|---|---|---|---|
| **A (recommended)** | 2 local commits: (1) **H1+H2a+H2b**, the pre-H3 tree; (2) **H3** | Start from the current tree. Reverse only the H4 hunks and the H3 hunks; both are small and recorded in 104 §2 and this document. Stage commit 1; re-apply H3 and stage commit 2. H4 stays uncommitted. | Full SQLite suites at **each** commit, compared with the recorded H2b (103 §8) and H3 (104 §8) numbers. A commit is kept only if they match exactly. |
| B | 1 commit **H1–H3** (current tree minus H4) | Reverse only the H4 hunks | One full run |
| C | 1 commit **H1–H4** | as is | already verified |

Separating H2a from H2b is **not proposed**. It would fabricate an untested intermediate state.

## 10. Results (final tree)

**SQLite (sequential, final tree; Backup classes excluded as in CI)**

| Suite | H3 baseline (104) | H4 |
|---|---|---|
| Feature (incl. Architecture and Recovery) | 2292, 0 failures | **2302, 0 failures** (33 skipped); +10 = 8 new 07e tests + the 2 released RED tests |
| Api (`tests/Feature/Api`) | 825, 0 | **825, 0** |
| Unit | 960, 5 pre-existing (`EvidenceFreshnessLiveCheck`) | **960, the same 5** |
| Integration | 31, 0 | **31, 0** |
| RED group `uat-recovery-07-o17h-red` | 2 failing (H4) | **no tests executed (quarantine empty)** |
| Document/policy tests changed in H4 (UAT-03 25, DocumentPolicyTest 13, DocumentPolicySimpleTest 5, PolicyTest 5, DocumentAssignApprover 4, SidebarConfigTest 5) | — | all pass |

**Static checks**

| Check | Result |
|---|---|
| PHPStan | OK, no errors (2 type findings in new H4 code fixed) |
| SSOT lint | pass |
| `owner-governance-lint` and `--enforce-gate-ordering` | pass |
| `lint-mysql-claim-truthfulness` | pass |
| php-cs-fixer, every H4-touched file | clean |

**Mutation checks**

| Mutation | Caught by |
|---|---|
| Remove `authorize('delete')` from `destroy` | 07e delete test |
| A controller wiring `ChangeRequestPolicy` (single-line) | tripwire |
| `Gate::allows(...)` on a differently named `$cr…ChangeRequest` variable (multi-line) | tripwire |

All mutations were reverted.

**Isolated MariaDB 10.4.28** (scratch server; `ZENA_INVARIANTS_DB=mysql`, `migrate:fresh`, `-c phpunit.mysql.xml`; now shut down)

| Run | Result |
|---|---|
| Security and policy files (07e, 07d, 07c, 07b, H1, guard, UAT-03, DocumentPolicyTest, DocumentPolicySimpleTest, PolicyTest, DocumentAssignApprover) | **105 / 105 OK** |
| H4 files plus sidebar tests, rerun after the final sidebar and tripwire edits | **32 / 32 OK** |
| Recovery, with ordinary CI exclusions | 221 tests, **2 failures**: the known `split_percent` defect in 04b, the same as H2b/H3 |
| All 10 non-Recovery Feature files that hit document routes | **145 / 145 OK** |

Evidence: `evidence/o17h-h4-verification.txt`.

**MySQL 8:** not verified. No parity is claimed from MariaDB 10.4.

**Tracked separately (not H4):**
- `task_assignments.split_percent` (2 Recovery 04b failures, MariaDB only);
- MDB-1 `ProjectPhase::getEstimatedDurationAttribute` int/string on MySQL (104);
- 5 pre-existing Unit `EvidenceFreshnessLiveCheck` failures;
- the 2 pre-existing `uat-recovery-01-quarantine` tests (O-2/O-8).

## 11. Deployment sequence and controlled CI integration plan (not executed)

**Deployment (after Owner approval of commits, Gate 3 and the release):**
1. H0 under D7, plus amendment G3 if approved; review.
2. Provision nothing beyond the Owner's existing grants.
3. Deploy, then migrate, then `production:rbac`, then restart workers.
4. The fail-closed checks (§6.4).
5. Owner smoke test.
6. Platform Operator provisioning only on a separate approval.

Roll-forward only.

**CI integration:**
1. The Owner approves the commit plan (§9).
2. Local commits.
3. **Push only on a later explicit approval.** CI on PR #422 (still Draft) must show:
   - all 35 checks, including `browser-tests` (Dusk) and the MySQL jobs;
   - no Auto-fix, no auto-merge.
4. Add the Recovery suite to the MySQL CI job, or accept the gap; Recovery has never run on CI MySQL.
5. Gate 3 review package with the CI evidence.
6. Merge and deploy only on separate Owner approvals.

**Open independent blockers:**
- BLK-1, CMP-1, AUD-1, O8-1, O16-1, NTF-1, R-1;
- RB-1 (now covered by the roll-forward-only decision plus the manual gate);
- SB-1, DOC-DEAD-1, MDB-1, `split_percent`;
- Owner gates O17H-H4-G1…G4.

## 12. Result

**O17H_H4_LOCAL_VERIFIED.** This covers the H4 policy hardening on SQLite full suites plus isolated MariaDB 10.4. **MySQL 8 is not verified**, and Dusk was not run locally.

**Gate 9 local checkpoint commits: not created (STOP).** The slices cannot be separated cleanly; the proposed plan is §9, Owner decision O17H-H4-G4.

**Owner decisions requested**
- **G1:** canonical rules for the unreachable role-name policies and TaskPolicy legacy abilities.
- **G2:** global sidebar-config management.
- **G3:** the H0 `users.role` aggregate amendment.
- **G4:** the commit plan.
- The superseded assertions in §4.

Nothing is committed or pushed. PR #422 remains Draft and MERGE_NO_GO.

## 13. Pre-commit review (Owner: "COMMIT PLAN C / PLAN_C_APPROVED / LOCAL_COMMIT_ONLY", 2026-11-25)

**The whole H1–H4 tree goes into ONE local commit, as decided** (`fix(security): enforce explicit RBAC and platform isolation`). No intermediate slice history is reconstructed. The commit SHA is recorded outside the commit (it cannot contain itself): in the session report and the decision register on the next documentation change.

**Staged inventory:** 99 files (42 added, 57 modified), all OWN-2026-024.
- **Additive migration:** two new tables and one nullable column, no backfill, classified `expand`.
- **Governance:** packets are Gate 1 and Gate 2 only; no Gate 3 approval exists.
- **Secrets / PII scan:** no secrets, local paths or personal data. The only hit is the public production domain, already present in 9 committed files.

**Independent pre-commit review** (fresh context, staged index only, read-only). **Verdict: OK_TO_COMMIT; no Critical or High.** Recorded follow-ups, none blocking, none implemented here:

| # | Severity | Finding | Disposition |
|---|---|---|---|
| 1 | Medium | `ZenaRbacSeeder`, `RolePermissionSeeder` and `TestDatabaseSeeder` still use `sync()` outside `RbacSeedGrants`. They are not run by `production:rbac` or `DatabaseSeeder`, and authorization-time revocation still denies. | SEED-1 (104 §6): fence them or route them through `RbacSeedGrants` in a separate gate |
| 2 | Medium | `rbac:permission` on a GLOBAL role accepts any tenant's Owner as approver (console-only; governance/audit attribution, not escalation) | new item RBAC-CLI-1: require a dedicated approval for global roles |
| 3 | Medium | `production:rbac` is a manual deploy step (`production.yml` runs only `deploy:migrate`); stale production grants would lock the Owner out | §6 and §11: H0 first; `production:rbac` plus the fail-closed checks are mandatory in the deploy runbook; automating it is a separate deploy gate |
| 4 | Medium | The parameterless `rbac` role-name allow-list (O-8), the PM-dashboard shortcut, and an unreachable `users.role` branch remain in the middleware | O-8 / M6 (104 §6) |
| 5–9 | Low | `down()` drops superseded history once nothing is active (`audit_logs` keeps the trail); unrouted `RoleController@sync` and `PermissionMatrixService` copies; `StaffAccountService::canonicalRole` matches by name only; OP-1 (operator ledger ignores `users.is_active`); the `createRbacAdminUser` test persona holds all 5 platform codes | recorded; OP-1 and M5 already listed |
| 10 | Info | `cache/clear` is not behind `platform.guarded` (it still needs an operator) | consistent with the approved 8-route scope |
