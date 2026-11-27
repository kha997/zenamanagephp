# UAT RECOVERY 03 — S3 report (parameterless `rbac` gate + `DocumentPolicy`)

**DECISION: LOCAL_PATCH_VERIFIED for the bounded `DocumentPolicy` alignment. The parameterless-`rbac` part is `BLOCKED_OWNER_DECISION` and was deliberately NOT changed.** This is a verified *partial* S3: it must not be presented as complete S3 closure. Not a staging/production approval.

## A. Snapshot
Branch `main`, HEAD `a0381f5e3d5abaa37dc2f21f7099a09e6234ab2d` (unchanged). Tracked modifications now: S1/S2 (`PermissionSeeder`, `ZenaPermissionsSeeder`, `RBACManager`) + S3 (`app/Policies/DocumentPolicy.php`). Untracked (preserved): `docs/uat/`, `docs/audits/UAT-RECOVERY-01/`, `tests/Feature/Recovery/`, `src/RBAC/Services/SystemRolePivotBackfill.php`, `app/Console/Commands/RbacBackfillUserRoles.php`. No commit/push. Only PHPUnit's per-run temp SQLite (and a scratch SQLite for route listing) was touched. S1/S2 re-verified before starting (Recovery 01/02 suites unchanged: see §F).

## B. Parameterless `rbac` — inventory and verdict
Implementation: `RoleBasedAccessControlMiddleware::handle()` with no argument → `handleGeneralAccess()`: grants iff `$user->hasAnyRole([super_admin, admin, project_manager, team_member, client, viewer, designer, site_engineer, qc_engineer, qc_inspector, procurement, finance])`, where `hasAnyRole` = `users.role` string **or** a DB role *named exactly* one of those. Does **not** call `hasPermission()`, does not check tenant beyond the earlier `tenant.isolation` middleware, no project membership. Seeded DB role names (`System Admin`, `Project Manager`, `Project Member`) match none → DB-only users are denied (D2); users with a matching legacy `users.role` pass.

Inventory (`evidence/s3-parameterless-rbac-inventory.csv`, from `route:list` of current HEAD): **421** routes carry the parameterless gate; **312** of them have **no** other permission gate (**158 mutating**). Largest groups: `dashboard` 97, `integrations` 25, `performance` 25, `projects` 21, `templates` 12, `notifications` 10, `admin` 9, `drawings` 9, `tasks` 7, `users` 7, `components` 5, `task-assignments` 5, `documents-simple` 5, …
Evidence of impact (test `test_OWNER_DECISION_widening_the_parameterless_gate_would_expand_member_access`): a Member holding **no** `task.create` already reaches `POST /api/tasks` when it carries the legacy value `team_member`, because that route is protected only by this gate; a DB-only Member is denied. **Making the gate accept seeded DB roles would therefore hand ~158 mutating routes to every Project Member and every PM.** That is an access expansion, contradicts O-8 ("do not change the semantics until independently reviewed") and has no approved canonical semantics. → **BLOCKED_OWNER_DECISION; no runtime change.**
Minimum fail-closed design to propose (Owner decision O-8′): replace the parameterless gate **per route group** with explicit `rbac:<permission>` before ever widening it; keep today's behaviour until each of the 312 routes has a permission mapping; never make it "authenticated user" or "System Admin bypass".

## C. DocumentPolicy operation matrix (real seeded roles; before → after)
Route gate = the `rbac:<perm>` middleware the route already has.
| Operation | Route gate | Policy before | Policy after | Effect |
|---|---|---|---|---|
| Create | `document.create` (Member, PM, Admin hold it) | not invoked | unchanged | none |
| View / download | `document.view` (**only System Admin** holds it) | legacy roles only | `hasPermission('document.view')` **or** legacy | System Admin with DB role only now passes; PM/Member unchanged (do not hold it) |
| Update / upload revision / submit / publish / archive / reopen / reactivate / link | `document.update` (System Admin, PM) | legacy roles only (PM blocked ⇒ UAT D2) | `hasPermission('document.update')` **or** legacy | PM and DB-only System Admin now pass **exactly where the route gate already says they may**; Member still denied |
| Review | — | no review permission/entity exists | unchanged | OWNER_DECISION (no `document.review*`) |
| Reject / approve | `document.approve` (System Admin, PM) | `hasPermission('document.approve')` | unchanged | none |
| Assign approver | `document.update` + policy | `admin`/`super_admin` legacy **or** project `pm_id` | unchanged | no admin alias introduced (test asserts DB System Admin is still denied) |
| Delete / restore / forceDelete | `document.delete` | legacy admin only | unchanged | out of scope |
Root cause of the PM conflict: `DocumentPolicy` still used the pre-RBAC vocabulary (`pm`, `designer`, `admin`, `super_admin`, `engineer`) through `User::hasRole`, which matches `users.role` or a DB role *named* that; no seeded role is named so and `UserManagementService::createUser` leaves `users.role` empty.

## D. Architecture before / after
Before: route gate (`document.*` permission, DB) **AND** policy (legacy role names) — two independent decisions that disagree for seeded PM/System Admin.
After (for `view`, `download`, `update`): `tenant match AND (hasPermission(<same code as the route gate>) OR unchanged legacy fallback)`. `user_roles` remains the pivot read by `hasPermission()`; `system_user_roles` is never consulted. Tenant check, state-machine guards, designated-approver rule, project rule: untouched. **The legacy fallback is kept on purpose**: removing it would *narrow* routes where the policy is the only object-level gate behind the legacy parameterless gate (`api/documents-simple`), changing previously working behaviour. It is recorded as a remaining legacy dependency (§J) and a retirement decision for the Owner.

## E. Exact code changes
Only `app/Policies/DocumentPolicy.php` (+10/−5): `view`, `update`, `download` gain `hasPermission('document.view'|'document.update'|'document.view') ||` in front of the unchanged legacy `hasRole([...])`. No change to middleware, seeders, services, routes, state machine, grants, or `users.role`.

## F. Tests
New: `tests/Feature/Recovery/UatRecovery03DocumentPolicyAlignmentTest.php` — 25 tests / data sets, real `DatabaseSeeder`, no injected permission, arrange-by-service only for states.
| Run | Result |
|---|---|
| S3 file **before** the patch (`evidence/s3-BEFORE.txt`, 23 tests then) | 7 FAIL = 6 RED defects (System Admin matrix, PM matrix, PM submit/publish/reopen, PM reopen-rejected, System Admin submit, System Admin zena-API read) + 1 OWNER_DECISION test whose precondition needs the same fix; 16 PASS |
| S3 file **after** (`s3-AFTER.txt` + 2 added adversarial tests) | **25 tests PASS**, 0 FAIL, 0 SKIPPED, 0 BLOCKED |
| Added adversarial pair, before/after | `documents_simple_update_does_not_widen_to_members` PASS/PASS; `…reaches_permission_holders_only` FAIL → PASS |
| Mutation check (policy `update` ⇒ `return true`) | 3 guard tests fail → tests discriminate |
OWNER_DECISION_REQUIRED tests (pin current behaviour, make no decision): parameterless-gate widening (§B); submitter may approve own submission and non-designated approver may approve (no such rule exists; GAP-033 designation is advisory); no `document.review/submit/publish` permissions; publishing a never-approved draft is allowed by `DocumentLifecycleService` (finding S3-F1).

### F2. Regression execution (isolated SQLite; evidence in `evidence/s3-full-feature-AFTER.txt`, `s3-api-AFTER.txt`, `s3-unrelated-failures-*.txt`)
| Run (patched tree = S1+S2+S3) | Exact result |
|---|---|
| `--testsuite Feature` | 1797 tests, 42980 assertions: **29 FAIL/ERROR**, 21 SKIPPED, 1 risky |
| `tests/Feature/Api` (separate suite) | 798 tests, 5166 assertions: **3 FAIL/ERROR**, 0 skipped |
| S3 file | 25/25 PASS |
| Recovery 01 file | 15 tests: 9 PASS, **6 RED (expected, unchanged since Recovery 02)** — one of the 6 (`D2_seeded_project_manager_can_submit_a_draft_document`) is now PASS after S3, so 5 RED remain in the full run: `D3_every_rbac_route…` (12 OWNER aliases), `D2_user_with_only_seeded_PM_role_passes_api_gate`, `D3_project_member_can_open_my_work`, `TASK_OWNERSHIP…`, `D2_no_seeded_user_can_create_users…` |
| Recovery 02 file | 19/19 PASS |
| GAP042 (3 files), RbacApiTest, Zena seed/wiring invariants, Documents, RFI, CR, Audit | all PASS (inside the full Feature run; no failure in any of them) |
Attribution of the remaining failures (no aggregate claim of success): the **5 Recovery-01 RED** are the documented, out-of-scope defects. Every other failing file was re-run **alone**, on the patched tree and on a clean stash of HEAD:
- identical failure at baseline HEAD and patched (pre-existing, unrelated): `BackupRetentionTest` (1), `BadgeApiRetiredTest` (1 error), `DashboardApiTest` (7), `GAP059/ConfigureSmtpWritesEnvSafelyTest` (7) — **16 tests, same counts both sides**;
- `BackupContentsSafetyTest`: failed inside the concurrent full-suite run, then **PASSED on the patched tree** when re-run alone (scans repository files; interference from files being written during the run) — flaky, not a regression;
- `OperatorCrmUiTest`, `DocumentTemplateRenderTest`, `OperatorDesignItemUiTest` (2), `OperatorInspectionUiTest`, `Api/ExportTenantIsolationTest`, `Api/LegacyCsvExportSafetyTest` (2): fail only inside the full run, **PASS alone at both baseline and patched** — order-dependent; I did **not** run the whole suite on a clean HEAD, so their full-suite status at baseline is UNVERIFIED (classified: not attributable to S1–S3, evidence = isolated runs).
SKIPPED 21 are pre-existing skips. BLOCKED: none. OWNER_DECISION_REQUIRED tests: 5 (all PASS, they only pin current behaviour).


## G. Security review (adversarial)
- Effective-privilege diff, exhaustive by construction: access expands **only** for holders of `document.update` / `document.view` that previously failed the policy because they lacked a legacy value: seeded PM (update), seeded System Admin (update, view, download). Both already pass the route-level permission gate for the same operation. Project Member, role-less users, cross-tenant users: unchanged denied (matrix + zero-mutation guard test incl. state, events, API and web).
- Legacy `designer` stays unable to approve (permission-based, test). Legacy `admin` is not aliased to System Admin and vice-versa (matrix rows both directions).
- Stale relations: `hasPermission()` queries `user_roles` live each call; no caching added.
- `api/documents-simple` (policy is the only object gate): PM with the legacy gate value now updates (holds `document.update`); legacy-valued Member still denied, no mutation.
- No Critical/High issue. Medium (pre-existing, not fixed): legacy fallback still lets `users.role='designer'|'pm'|'engineer'` act without any DB permission on `documents-simple`; **S3-F1** publish needs no approval; submitter self-approval and non-designated approval allowed.

## H. Unresolved permission aliases (unchanged, not approved)
`task.edit`, `project.edit`, `component.edit/view`, `user.view`, `change_request.view/edit/delete/submit/stats`, `template.create/edit` (12; see S1 report).

## I. Unresolved Member grants (unchanged, not approved)
`task.view, crm.view, material.view, vendor.view, boq.view, contract.view, event-record.view, design-item.view, site_diary.view, material-receipt.view` (S1-F1 seeder-order drift). S3 deliberately did not grant any.

## J. Remaining legacy-role dependencies
`handleGeneralAccess` allow-list (312 routes without permission gate); `DocumentPolicy` legacy fallback and `assignApprover`/`delete`/`restore`/`forceDelete`; 11 other gen-1 policies (`TaskPolicy`, `RfiPolicy`, `ChangeRequestPolicy`, `ComponentPolicy`, `NcrPolicy`, `QcPlan/QcInspectionPolicy`, `UserPolicy`, `TemplatePolicy`, `InvitationPolicy`, `SidebarConfigPolicy`, `NotificationPolicy`); ~264 `hasRole` call sites in total.

## K. MySQL
**MYSQL_NOT_VERIFIED.** No disposable MySQL confirmed safe. The change is a pure PHP boolean on an existing query (`roles()->whereHas('permissions')`), no SQL dialect surface.

## L. Readiness
`DocumentPolicy` alignment: LOCAL_PATCH_VERIFIED (scoped, no expansion beyond the route-level permission boundary, tests discriminate). Parameterless gate: **BLOCKED_OWNER_DECISION**. S3 as a whole is **not closed**: D2's API-gate half (PM/System Admin DB-only on `/api/projects`) and `D3 my-work` remain RED by design.

## M. Next smallest safe slice (proposal)
**S3b — permission-map the parameterless-only routes, one group at a time**, starting with the read-only/mutating groups whose controllers already perform a permission check (`projects`, 21 routes: `Api\ProjectController` calls `hasPermission` already), converting `rbac` → `rbac:<existing code>` with a before/after route-access test per group. Needs Owner approval of the mapping table (O-8′). Separate small slices afterwards: retire the legacy fallback in `DocumentPolicy` (after staging counts of `users.role`), the 12 aliases, S1-F1 grants.

## N. S3b (project write routes) — APPLIED after Owner decision O-9
Owner approved the mapping, then (O-9) approved amending the pinned assertions. Final state: **applied**.
Mapping (derived only from permissions `Api\ProjectController` already enforces; read routes untouched, O-8): `POST api/projects`→`project.create`; `PUT|PATCH api/projects/{project}`, `POST …/status`, `POST …/recalculate-progress`, `POST …/recalculate-actual-cost`→`project.update`; `DELETE api/projects/{project}`→`project.delete`. Implemented in `routes/api.php` with `Route::withoutMiddleware(['rbac'])` + `rbac:<permission>`; route names, authentication and tenant isolation preserved (patch copy: `evidence/s3b-routes-api.patch`).
Amended pinned assertions (only these, all with an `O-9` comment): `tests/Architecture/RouteNameCollisionInvariantTest.php` (3 snapshot entries: `projects.store/update/destroy` gain `rbac:<perm>`; `gatherMiddleware()` still lists the raw group `rbac`, the router excludes it at resolution — proven by the behaviour tests), `tests/Feature/Api/ProjectApiTest.php` (3 tests: denial envelope `E403.AUTHORIZATION` instead of `RBAC_ACCESS_DENIED`; HTTP 403 and "project unchanged" assertions kept).
Evidence: `UatRecovery03bProjectWriteRouteGateTest` 14/14 PASS (3 RED before); seeded System Admin reaches all 7 routes; seeded PM create/update OK, delete 403 (no grant added); Member / role-less / legacy-valued-without-permission / cross-tenant denied with zero mutation; read routes keep parameterless-gate semantics.
**Final-tree regression** (S1+S2+S3+S3b; `evidence/final-full-feature.txt`, `final-api.txt`): Feature 1811 tests, 97465 assertions, 1 error + 19 failures, 21 skipped; Api 798 tests, 5171 assertions, **0 failures**. Against the S3 run: **no new failing test**; the failures that remain are the 5 documented Recovery-01 RED tests plus the pre-existing unrelated ones attributed in §F2 (BackupRetention, BadgeApiRetired, DashboardApiTest ×7, GAP059 ConfigureSmtp ×7); the order-dependent/flaky ones from the S3 run did not fail this time. MYSQL_NOT_VERIFIED.
Effective-access change of S3b (disclosed): holders of `project.create/update/delete` who carry **no** legacy `users.role` value (e.g. seeded PM/System Admin) can now reach those 7 API routes; everyone who was denied by the controller before is still denied (now at the route, with `E403.AUTHORIZATION`).
