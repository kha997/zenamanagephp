# UAT RECOVERY 02 — S1 + S2 local patch report

**FINAL DECISION: LOCAL_PATCH_VERIFIED** (updated after Owner acknowledgement, "Xác nhận", in chat). Positive evidence, no regression. The one deviation from O-1 — a same-transaction `user_roles` projection alongside the GAP-042 `system_user_roles` ledger, instead of moving the write target — was **acknowledged by the Owner** (decision L1). Not a deployment approval: decisions L2–L5 and the MySQL run remain open.

## A. Snapshot
HEAD `a0381f5e3d5abaa37dc2f21f7099a09e6234ab2d` (unchanged). Pre-existing untracked UAT/Recovery-01 artifacts preserved. Tracked files modified: 3 (below). Nothing committed/pushed. No DB other than PHPUnit's per-run temp sqlite was touched.

## B. Exact changes
| File | Change |
|---|---|
| `database/seeders/ZenaPermissionsSeeder.php` | + `CATALOG_ONLY_PERMISSIONS` (21 definitions, **granted to nobody**), + `SYSTEM_ADMIN_ONLY_PERMISSIONS` (`role.assign`), + `OWNER_DECISION_REQUIRED_ALIASES` (12 codes, **not created**), `catalogOnlyCodes()`; `run()` now `updateOrCreate`s the definitions (idempotent, keyed by `code`, `name=code`), `repairNullPermissionNames()` (metadata only, skips on name collision), `grantSystemAdminOnlyPermissions()` (additive `syncWithoutDetaching` to the role named exactly `System Admin` with scope=system, tenant_id NULL). `CANONICAL_PERMISSIONS` untouched, so the `ADMIN_ROLE_NAMES` alias roles (`Admin`, `super_admin`, `system_admin`) do **not** receive anything. |
| `database/seeders/PermissionSeeder.php` | System Admin "all permissions" `sync` now excludes `catalogOnlyCodes()` so re-seeding cannot silently grant the 21 catalog-only permissions. |
| `src/RBAC/Services/RBACManager.php` | `assignSystemRole()`: ledger row (`system_user_roles`) **and** `user_roles` row written in one transaction, idempotent, post-insert verification (MySQL `INSERT IGNORE` cannot hide a failure; both writes roll back, method returns `false`). `revokeRole()` system scope: removes both rows in one transaction **only if** a ledger row was actually revoked (an independent `user_roles` row is never deleted). Custom/project scopes untouched. |
| `src/RBAC/Services/SystemRolePivotBackfill.php` (new) | plan (read-only) / apply (insert-only) / rollback (exact manifest). Not wired to any migration/seeder. |
| `app/Console/Commands/RbacBackfillUserRoles.php` (new) | `rbac:backfill-user-roles` — **dry-run by default**; `--apply`, `--allow-privileged-role=<id>`, `--rollback=<manifest>`. Not executed anywhere. |
| `tests/Feature/Recovery/UatRecovery01IdentityRbacTest.php` | Only the two tests whose *precondition* was obsolete changed: D1b (removed the injected `role.assign` row — S1 seeds it now) and CONTROL-D1 (now: detach only `role.assign` ⇒ same request is denied). **No RED assertion was weakened.** |
| `tests/Feature/Recovery/UatRecovery02RbacFoundationTest.php` (new) | 19 tests: S1, integrated S1+S2, backfill fixtures. |
| evidence | `evidence/s1s2-regression-BEFORE.txt`, `…-AFTER.txt`, `s1s2-recovery-AFTER.txt`, `missing-route-permissions-BASELINE-34.txt` (current residual list is `missing-route-permissions.txt`). |

## C. Permission catalog before → after
- Route-referenced but unseeded: **34 → 12**. Created 22: `role.assign` (granted to System Admin only) + 21 catalog-only (`role.view/create/edit/delete`, `permission.view/create/edit/delete/export/import`, `audit.view`, `baseline.view/create/edit/delete`, `compensation.view/edit/preview/apply/sync/stats`).
- **OWNER_DECISION_REQUIRED (12, not created, each has an existing equivalent or a family-naming question):** `task.edit`→`task.update`; `project.edit`→`project.update`; `component.edit/view`→`component.update/read`; `user.view`→`user.read`; `change_request.view/edit/delete/submit` (equivalents `change-request.*` and `change_request.read/update`); `change_request.stats`; `template.create`; `template.edit`→`template.edit_draft`. Creating them would add duplicate aliases or decide business naming.
- Grants: System Admin +1 (`role.assign`). Project Manager 42 and Project Member 15 — unchanged (asserted). No wildcard, no `super_admin`, no alias-role grant.
- Metadata: `project.read`, `user.manage` had `name=NULL` (invisible to `hasPermission()`); now `name=code`, ids unchanged. **Side effect to acknowledge:** the existing Member/PM `project.read` grants become effective (it is consumed only by `SidebarConfig` navigation entries).

## D. Role pivot before → after
Before: write → `system_user_roles` (GAP-042 ledger); authorization read → `user_roles`; an API-assigned role was never effective.
After: one transactional write path puts both rows; authorization reads unchanged (`user_roles`); revoke symmetric. **Deviation from O-1 (blocker recorded):** GAP-042 Gate-3 (released PR #299, design §item 10/16, tests `GAP042Gate3Round1CorrectionsTest`, `GAP042RbacProductionFidelityTest`, `RbacApiTest`) requires system assignments to be provable in `system_user_roles`. Switching the write target would contradict that Owner-approved contract and break those PASS tests, so the ledger write was **kept** and `user_roles` is a verified, same-transaction projection — controlled, not independent dual-write. Alternatives for the Owner (see §L): (a) accept projection; (b) supersede GAP-042 and move the ledger; (c) union-read (rejected: instantly activates unknown legacy ledger rows).

## E. Backfill / conflict handling
Classification per active ledger row: `INSERT`, `DUPLICATE`, `CONFLICT` with reasons `USER_MISSING`, `USER_TENANT_UNKNOWN`, `ROLE_MISSING`, `ROLE_NOT_GLOBAL_SYSTEM`, `ROLE_INACTIVE`, `PRIVILEGED_ROLE_REQUIRES_EXPLICIT_APPROVAL` (System Admin or any role holding `role.assign`; allowed only by explicit role id). Revoked (soft-deleted) ledger rows are ignored. Idempotent, insert-only, rollback deletes exactly the manifest rows. Tested fixtures: empty DB; row already in `user_roles`; ledger-only; duplicates; conflicts (missing user/role/tenant, tenant-bound custom role, privileged); two tenants. **Production execution not performed and not authorized.**

## F. Tests — exact counts
| Suite | Before | After |
|---|---|---|
| `UatRecovery01IdentityRbacTest` | 15 tests / 46 assertions — 6 PASS, **9 FAIL (RED)** | 15 / 48 — 9 PASS, **6 RED** |
| `UatRecovery02RbacFoundationTest` (new) | n/a | 19 / 143 — **19 PASS** |
| GAP042Gate3Round1 / Round2 | 20 / 7 OK | 20 / 7 OK |
| GAP042RbacProductionFidelityTest | 18 OK | 18 OK |
| RbacApiTest | 9 OK | 9 OK |
| tests/Feature/Zena (incl. SeedParity/Wiring/Canonical identity) | 290 OK (2 skipped) | 290 OK (2 skipped) |
| Treasury role-permission seeder, Seeders, Unit/Seeders, TeamPolicy, ProjectStoreRbac, ProjectWorkTemplateApply, WorkTemplateBaselineSeeder, InspectionTemplateRuntime, ProductionBootstrapCommand | all OK | identical |
| Audit 13, Documents 29 (2 skipped), RfiWorkflow 10, ChangeRequestApi 24, ChangeRequestWorkflow 6, Api/SecurityTest 15, UlidModels 8 | all OK | identical |
| SSOT test lint | pass | pass |

Turned GREEN: `D1_seed_contains_role_assign_permission`, `D1_system_admin_can_assign…`, `D1b_role_assigned_through_api_takes_effect…`. **Still RED (expected, out of scope):** `D3_every_rbac_route_permission_exists…` (12 OWNER_DECISION aliases), `D3_project_member_can_open_my_work`, `D2` ×3, `TASK_OWNERSHIP`. Mutation checks: reverting S2 fails 3 tests; reverting S1 fails 8; dropping only the PermissionSeeder exclusion fails the re-seed test — the tests discriminate. Total new+old Recovery: 34 tests, 191 assertions, 28 PASS, 6 RED, 0 BLOCKED.

## G. Security regression assessment
No regression found. Verified: PM/Member (even with a legacy value that passes the generic gate) get 403 on assignment and on self-escalation to System Admin, with **zero rows** in either store; cross-tenant assign denied with zero rows and cross-tenant revoke cannot strip another tenant's row; custom/tenant-bound/unknown roles rejected with zero rows; revoke removes effective access; denied request mutates nothing; bulk route aligned; reseeding does not duplicate or widen System Admin; no new alias role received `role.assign`.

## H. DB compatibility
SQLite (PHPUnit temp DB): verified. **MYSQL_NOT_VERIFIED** — no disposable MySQL instance was confirmed safe (`.env.testing` points at a local `laravel` DB that may hold dev data; not touched). Code is written for MySQL semantics (explicit post-insert verification for `INSERT IGNORE`, no sqlite-only SQL, composite PK idempotency), but must be run on the GAP-040/044 MySQL harness before release.

## I. Remaining RED / unresolved
D2 (generic gate vs `DocumentPolicy` vs DB roles — O-2/O-8), D3 residual 12 aliases (Owner), `task.view` not effective for Member, task ownership (O-4), project membership (O-3). **New finding S1-F1 (pre-existing seeder-order drift):** `PermissionSeeder` grants Member `task.view, crm.view, material.view, vendor.view, boq.view, contract.view, event-record.view, design-item.view, site_diary.view, material-receipt.view` but runs before those permissions exist, so a fresh `DatabaseSeeder` silently drops them while a second run adds them (characterized by `test_CHAR_member_grants_depend_on_seeder_ordering_pre_existing`). Not changed — it would grant Member 10 permissions (O-5). Other unresolved: prod/staging counts of `system_user_roles`; `RolePermissionSeeder`/`TestDatabaseSeeder`/`ZenaRbacSeeder` still give `Permission::all()` to admin roles if ever run (not in `DatabaseSeeder`).

## J. Independent adversarial review
| Concern | Result |
|---|---|
| Privilege escalation | Only System Admin holds `role.assign`; assigners can assign System Admin to anyone in-tenant (inherent to admin; recommend a "role ⊄ assigner" guard if `role.assign` is ever delegated). **Medium (future), not a current gap.** |
| Cross-tenant | Target-user tenant check retained; tested both directions. |
| Stale relations | `hasPermission()` queries live; RBACManager cache cleared on assign/revoke. |
| Drift between stores | Pre-patch rows with ledger≠`user_roles` remain until backfill; backfill/dry-run provides the report; new writes cannot diverge (tests + verification). A direct DB/`HasRoles::assignRole` writer still writes both by itself. |
| Fixture masking | New tests use the real seeder and inject no permission; CONTROL tests are explicitly labelled and change one variable. |
| Re-seed detaching | `PermissionSeeder` still uses `sync` (detach): a future Owner grant of a catalog-only permission to System Admin must be added in code, not by hand, or the next seed will revoke it. **Low.** |
| System Admin bypass | None added; `isSuperAdmin()` still false for seeded roles. |
| Name-repair side effect | Activates already-assigned `project.read` (navigation only). **Low, disclosed.** |
No Critical/High issue open in S1/S2.

## K. Next bounded slice (proposal)
**S3 — O-2/O-8 gate alignment** (after Owner answers): generic `rbac` gate and `DocumentPolicy::update` accept the seeded DB roles through one alias map; targets the 3 D2 RED tests. Separate slice for the 12 aliases once naming is decided, and a Member-grant slice for S1-F1.

## L. Owner decisions before any deployment
1. **Acknowledge the projection** (ledger + `user_roles`, one transaction) as the implementation of O-1, or choose to supersede GAP-042 item 10/16 — *Recommended: acknowledge.* **RESOLVED — acknowledged by Owner.**
2. Naming policy for the 12 OWNER_DECISION aliases (single canonical spelling per module).
3. Whether Member should receive the 10 intended-but-dropped grants (S1-F1).
4. Approve running the backfill on staging first: read-only counts via `rbac:backfill-user-roles` (dry-run) and review conflicts; privileged roles by explicit id only.
5. MySQL harness run as release precondition.
