---
work_id: GAP-042
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_correction_or_defer"
references:
  spec: docs/superpowers/specs/2026-09-01-gap-042-rbac-model-consolidation-design.md
  plan: docs/superpowers/plans/2026-09-02-gap-042-rbac-production-fidelity-implementation.md
  branch: fix/GAP-042-rbac-production-fidelity
  pr: "https://github.com/kha997/zenamanagephp/pull/299"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-02T06:20:00Z"
  updated_at: "2026-09-02T06:20:00Z"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-042 implements Option A exactly as approved through Gate 2 Round 5 (docs/owner-decisions/GAP-042/02-design.md): Src\\RBAC\\Models\\Role/Permission repointed from zena_roles/zena_permissions to the canonical roles/permissions/role_permissions/user_roles tables; new custom_user_roles migration (§2b); RBACController::getUserEffectivePermissions()/checkUserPermission() fixed to call RBACManager's real method contracts (§2a #1-#2); bulkAssignRoles() argument-order fix + real boolean-return check eliminating false-success (§2a #3); AssignmentController route-parameter-authoritative user identity for assignments/users/{user}/roles with HTTP 400 + zero writes on a conflicting body user_id (§2e); the 3 already-live, previously-500ing project-assignment routes restored (§2a #4-#6); RBACManager assignment/revoke methods widened to require $tenantId and enforce §6a's fail-closed per-identity checks (target user/role/project tenant ownership) before any write, at both controller and service layer; grouped tenant-visibility predicate (Role::scopeTenantVisible, an Eloquent local scope whose wheres Laravel's Builder::callScope() auto-groups) plus §6's global-role read-only policy; tests/TestCase.php's ensureSqliteZenaRbacTables() RBAC-schema shim removed. All 20 approved Gate-2 acceptance items (§10) have a dedicated, passing test in the new tests/Feature/GAP042RbacProductionFidelityTest.php. 27/27 tests (18 new + 9 pre-existing RbacApiTest, updated for the converged tables) pass on SQLite AND on genuine MySQL 8.0 (disposable Docker container, real migrate:fresh, no PHPUnit schema shim) — deterministic across repeat runs. Sabotage/revert-verified for items 15 and 19. Full SQLite regression (2419 tests): 7 pre-existing, unrelated Dashboard-widget failures (documented pre-existing in GAP-048's own Gate-3 packet, a broken Redis cache-store method, zero RBAC/tenant files involved) plus 1 genuine GAP-042-caused test-fragility regression in ServiceLineFoundationTest (a hardcoded migrate:rollback --step count shifted by GAP-042's new migration) — found and fixed properly, not dismissed, re-verified green. Three implementation-time factual corrections to the Gate-2 route/defect inventory were found and fixed under the same already-approved remediation pattern (§2a/§13a): PermissionController was missing show/update/destroy despite live routes targeting them; RoleController::syncPermissions()/PermissionMatrixService::importFromCSV() passed permission codes (not ids) into BelongsToMany::sync(); two route-ordering bugs (roles/by-scope, permissions/hierarchy swallowed by their own {id}-wildcard siblings registered earlier). None reopens any Gate-2 business/security decision."
technical_evidence:
  subject_sha: "b04089268bdfe725d8d51db2f628fbfd99c8bd49"
  implementation_tree_digest: "ba4674877f8c1394553bc2b8da54b0db5e040e12bc843b25a122047a59b85774"
  verified_pr_head_sha: "b04089268bdfe725d8d51db2f628fbfd99c8bd49"
  verified_at: "2026-09-02T06:20:00Z"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-042 — RBAC Production-Fidelity Restoration: Gate 3 Release Request

**Gate 3 packet status: `awaiting_owner`.** This is evidence/request only —
it is NOT Owner approval. No merge, no Ready-for-review flip, no release,
no deployment has occurred or is authorized by this packet.

## Implementation baseline and PR

- **Implementation baseline (Gate-2 merge, confirmed zero drift at session
  start):** `origin/main @ 673855f69a3633b64c378e965ae409ed3a098c50`.
- **Implementation branch:** `fix/GAP-042-rbac-production-fidelity`.
- **Implementation PR (Draft, unmerged):** [#299](https://github.com/kha997/zenamanagephp/pull/299).
- **subject_sha (last content-changing commit; what
  `implementation_tree_digest` is computed against):**
  `b04089268bdfe725d8d51db2f628fbfd99c8bd49`.
- **Implementation plan:** `docs/superpowers/plans/2026-09-02-gap-042-rbac-production-fidelity-implementation.md`.

## What changed (`673855f6` → `b0408926`)

```
 database/migrations/2026_09_02_000000_create_custom_user_roles_table.php          |  46 ++ (new)
 docs/superpowers/plans/2026-09-02-gap-042-rbac-production-fidelity-implementation.md | 151 ++ (new)
 src/RBAC/Controllers/AssignmentController.php                                     | 167 +++++-
 src/RBAC/Controllers/PermissionController.php                                     | 102 ++++ (show/update/destroy added)
 src/RBAC/Controllers/PermissionMatrixController.php                               |   6 +-
 src/RBAC/Controllers/RBACController.php                                          |  62 ++-
 src/RBAC/Controllers/RoleController.php                                          |  77 ++-
 src/RBAC/Models/Permission.php                                                    |   4 +-
 src/RBAC/Models/Role.php                                                          |  45 +-
 src/RBAC/Services/PermissionMatrixService.php                                    |  43 +-
 src/RBAC/Services/RBACManager.php                                                |  94 +++-
 src/RBAC/routes/api.php                                                          |  12 +-
 tests/Feature/GAP042RbacProductionFidelityTest.php                               | 603 ++ (new)
 tests/Feature/Models/ServiceLineFoundationTest.php                               |  24 +- (unrelated regression fix, see below)
 tests/Feature/RbacApiTest.php                                                    |  34 +-
 tests/Support/GAP040ColdStartTransactionIsolationAssertions.php                  |  53 +-
 tests/TestCase.php                                                               |  67 --- (ensureSqliteZenaRbacTables() removed)
 17 files changed, 1357 insertions(+), 233 deletions(-)
```

Every changed file is inside the approved boundary (Gate 1: `Src\RBAC\Models\Role`/`Permission`
and their direct consumers — `RBACManager` and the 5 controllers in
`src/RBAC/Controllers/`) plus the mandated test-infrastructure cleanup
(`tests/TestCase.php`), test updates for the converged table set
(`tests/Feature/RbacApiTest.php`), the new acceptance-matrix test file, and
one unrelated test-fragility fix genuinely caused by this PR's new
migration (`tests/Feature/Models/ServiceLineFoundationTest.php` — see
"Regression results" below). No `.github/workflows/**` file was touched.

## Implementation-time rulings (logged, not scope reinterpretation)

Recorded in full in the implementation plan §0; summarized here:

1. **`PermissionController` was missing `show`/`update`/`destroy`** despite
   `GET|PUT|DELETE permissions/{permission}` being live, already-wired
   routes (Gate 2's §2c inventory asserted these existed — a Gate-2
   inventory error, discovered on direct inspection). Fixed using the
   identical, already-approved remediation pattern §2a established for
   `AssignmentController`'s analogous missing methods — same 5-controller
   approved boundary, same defect class (undefined-method fatal on an
   already-live route), not a new business decision.
2. **`RoleController::syncPermissions()`/`PermissionMatrixService::importFromCSV()`
   passed permission codes into `BelongsToMany::sync()`**, which requires
   the related model's primary keys (`permissions.id`), not `code`
   strings — a pre-existing latent bug that would have silently no-op'd or
   errored the moment either method was actually reachable (both were
   blocked by the table-rename defect until this PR). Fixed within the same
   already-in-scope methods.
3. **Two route-ordering bugs found and fixed:** `roles/by-scope` and
   `permissions/hierarchy` were registered AFTER their own `{id}`-wildcard
   sibling routes (`roles/{role}`, `permissions/{permission}`), so Laravel's
   in-order route matching swallowed them (`GET /roles/by-scope` matched
   `{role}="by-scope"`, calling `RoleController::show()` with a lookup that
   always 404s). Fixed by reordering registration in `src/RBAC/routes/api.php`
   (comment added explaining why the order matters) — no route
   signature/verb/path changed, matching §2a's established remediation
   pattern for already-live-but-broken routes.
4. **`RBACController::getUserEffectivePermissions()`/`checkUserPermission()`
   declared `int $userId`** under `declare(strict_types=1)` — user ids in
   this codebase are ULID strings, so any real call would throw an uncaught
   `TypeError` at the argument-binding boundary, before the method's own
   `try/catch` could run — a second, independent failure mode alongside
   §2a #1-#2's wrong-method-name defect. Fixed to `string $userId` as part
   of the same already-approved method-contract correction.
5. **Several `RBACManager`/`RoleController`/`PermissionController`
   `EventBus::publish()` calls used an invalid 4-segment event name (e.g.
   `rbac.role.permissions.synced`, `EventBus`'s own validator only accepts
   `Domain.Entity.Action`) or omitted validator-required fields
   (`entityId`/`projectId`/`actorId`)** — dormant because the RBAC surface
   never successfully reached these calls before this fix (blocked earlier
   by the table-rename defect or by the sync()-codes-not-ids bug above).
   Corrected to valid 3-segment event names with the required fields
   populated; zero behavior change to the events' actual business content.
6. **`tests/TestCase.php::zenaRbacBootstrapSchema()` is shared,
   non-RBAC-specific infrastructure** — `ensureInteractionLogsTable()`,
   `ensureProjectPhasesTable()`, and `ensureProjectTasksTable()` (unrelated
   tables, the GAP-040/GAP-044 second-connection DDL technique) also call
   it. Per the mandate to remove the RBAC-schema-manufacturing shim: removed
   only `ensureSqliteZenaRbacTables()` (the method that actually creates
   `zena_roles`/`zena_permissions`/`zena_role_permissions`/`zena_user_roles`)
   and its call site — kept `zenaRbacBootstrapSchema()` itself, since
   removing it would have broken those three unrelated, out-of-boundary
   helpers. `tests/Support/GAP040ColdStartTransactionIsolationAssertions.php`
   updated to drop its dependency on the deleted RBAC-bootstrap-specific
   probe keys; the underlying GAP-040/GAP-044 implicit-commit invariant
   remains proven via the three still-live sibling helpers, unaffected by
   this removal.

None of the above reopens any Gate-2 business/security decision — all are
narrow, logged, factual corrections/completions of an already-approved
remediation pattern, or (for #6) a scoped adaptation of an approved
instruction to avoid an unauthorized, unrelated regression.

## Completed behavior matrix vs approved Gate 2 (all 20 acceptance items, §10)

| # | Acceptance item (abbreviated) | Evidence |
|---|---|---|
| 1 | Clean genuine MySQL 8.0 bootstrap, no PHPUnit schema shim | `migrate:fresh` on disposable Docker `mysql:8.0`, all expected tables present (see "Real MySQL 8.0 evidence" below) |
| 2 | Application does not require `zena_roles`/`zena_permissions` | `Schema::hasTable('zena_roles'/'zena_permissions')` both `false` on the same clean MySQL schema |
| 3 | Authenticated + authorized real HTTP `roles`/`permissions` succeed | `test_authenticated_authorized_roles_and_permissions_endpoints_succeed` — 200 on both SQLite and MySQL |
| 4 | Denied access remains denied | `test_denied_access_without_permission_still_403` |
| 5 | Tenant A cannot list/show/update/delete/sync tenant B's role; global role stays visible to both | `test_tenant_a_cannot_read_or_write_tenant_bs_role` |
| 6 | Role creation binds to server-derived tenant, not client-supplied | `test_role_creation_binds_server_derived_tenant` |
| 7 | `effective-permissions`/`check-permission` live routes return 200 | `test_effective_permissions_and_check_permission_routes_return_200` |
| 8 | 3-layer permission computation (system/custom/project) | `test_three_layer_permission_computation_system_custom_project` |
| 9 | `custom_user_roles` proven on genuine MySQL at service level | `test_custom_user_roles_service_level_write_read` — green on MySQL |
| 10 | Assignment paths write the correct canonical table, independently verified | `test_assignment_paths_write_canonical_tables` |
| 11 | `bulkAssignRoles` project-scope no longer false-success | `test_bulk_assign_project_scope_no_false_success` — row verified present |
| 12 | Tenant A cannot assign tenant B's role | `test_tenant_a_cannot_assign_tenant_bs_role` — 0 rows in all 3 assignment tables |
| 13 | Tenant A cannot assign a role to tenant B's user | `test_tenant_a_cannot_assign_role_to_tenant_bs_user` |
| 14 | Project-role assignment cannot target tenant B's project | `test_project_role_assignment_cannot_target_tenant_bs_project` |
| 15 | Route user identity not overridable by conflicting body | `test_route_user_identity_not_overridable_by_body` — HTTP 400, 0 rows; sabotage-verified discriminating |
| 16 | Success verified by correct-table row; failure verified by absence in all 3 tables | Covered across items 10-15's own assertions |
| 17 | The 3 already-live `assignments/projects/*` routes restored | `test_project_assignment_routes_restored` + `test_project_assignment_route_rejects_tenant_bs_project` |
| 18 | No test-only shim can manufacture production-impossible schema | `test_shim_removed_from_test_case` (reflection: `ensureSqliteZenaRbacTables()` absent from `Tests\TestCase`) |
| 19 | Grouped tenant-visibility predicate proven under OR-precedence pressure | `test_grouped_tenant_visibility_predicate_discriminates`; sabotage-verified (see below) |
| 20 | Global roles readable but not writable through tenant surface, with controls | `test_global_role_readonly_through_tenant_surface` (a-e) |

## TDD RED → GREEN evidence

- **RED (pre-fix, against unmodified baseline code, shim still active):**
  the new 18-test acceptance file run against baseline `673855f6` code
  (models still pointed at `zena_roles`, no tenant checks, no route
  restoration) failed 15/18 for the exact documented reasons (500s from
  `int $userId` TypeError, undefined-method `Error`s on the 3
  project-assignment routes, false-success on `bulkAssignRoles`, missing
  tenant scoping allowing/denying the wrong rows, `ensureSqliteZenaRbacTables()`
  still present).
- **GREEN (post-fix):** all 18 new tests + the updated 9-test `RbacApiTest.php`
  = 27/27 pass, deterministic across 5 repeat SQLite runs (a random-scope
  factory default in 2 `RbacApiTest` cases was pinned to `scope=system` to
  remove non-determinism unrelated to GAP-042's fix — the underlying
  §6a system-scope-role check this exposed is itself a genuine, intentional
  GAP-042 security fix, not a test bug).
- **Sabotage/revert verification (Owner-instructed, for the highest-risk
  regressions):**
  - Item 15: temporarily reverted `AssignmentController::assignUserRoles()`'s
    route/body identity check (body `user_id` silently wins again) →
    `test_route_user_identity_not_overridable_by_body` failed exactly as
    expected (200 instead of 400) → fix restored, test passes again.
  - Item 19: temporarily reverted `Role::scopeTenantVisible()`'s grouped
    closure to a bare `whereNull()->orWhere()` chain. **Finding:** the test
    still passed — because `scopeTenantVisible` is implemented as an
    Eloquent **local scope**, and Laravel's `Eloquent\Builder::callScope()`
    automatically wraps any `where`s added during a scope call in a group
    (`addNewWheresWithinGroup()`), specifically to prevent exactly this
    OR-precedence class of bug — regardless of whether the scope body
    itself uses an explicit closure. Verified directly:
    `DB::table('roles')->whereNull(...)->orWhere(...)->where(...)->toSql()`
    (no Eloquent scope involved) DOES produce the unsafe, ungrouped SQL the
    design warns about, confirming the underlying concern is real; but
    `Role::tenantVisible(...)->whereKey(...)->toSql()` produces the safe,
    grouped SQL `(tenant_id IS NULL OR tenant_id = ?) AND id = ?` even with
    the closure removed, because Eloquent's own scope machinery groups it.
    This means the implementation is structurally protected against this
    vulnerability class (every RBAC tenant-visibility call site uses
    `->tenantVisible()`, never a raw ad hoc chain) — a stronger guarantee
    than the literal closure pattern alone. The closure was restored anyway
    for code-reader clarity/defense-in-depth. §6 of the Gate-2 design
    explicitly permits reusing an existing repo scope/trait mechanism
    instead of hand-rolling the closure, "the requirement is the resulting
    SQL semantics, not this exact syntax" — satisfied.

## Real MySQL 8.0 evidence

Disposable `mysql:8.0` Docker container (`MYSQL_ALLOW_EMPTY_PASSWORD=yes`,
port 33061), `zena_mysql_resolve_env`/`zena_mysql_ensure_connection`/
`zena_mysql_preflight_connection` from this repo's own
`scripts/ci/lib/mysql-fail-closed.sh`, then `php artisan migrate:fresh --force`
using ONLY the real committed migrations (`ensureSqliteZenaRbacTables()`
already deleted from `tests/TestCase.php` at this point — irrelevant to
`migrate:fresh` regardless, since that command never touches PHPUnit's
`TestCase`, but confirming the shim's removal here too for completeness).

- `migrate:fresh --force`: succeeded, all 235 migrations ran including the
  new `2026_09_02_000000_create_custom_user_roles_table`.
- Table inventory: `roles`, `permissions`, `role_permissions`, `user_roles`,
  `system_user_roles`, `project_user_roles`, `custom_user_roles` — all
  `PRESENT`.
- Negative-space proof: `zena_roles`/`zena_permissions` — both `ABSENT`.
- Real column shapes confirmed via `Schema::getColumnListing()`, e.g.
  `roles: id,name,scope,allow_override,description,is_active,tenant_id,created_at,updated_at`;
  `custom_user_roles: id,user_id,role_id,created_at,updated_at,deleted_at`
  (exact §2b shape).
- `GAP042RbacProductionFidelityTest` (18) + `RbacApiTest` (9) run against
  `phpunit.mysql.xml` with `DB_CONNECTION=mysql`/`ZENA_INVARIANTS_DB=mysql`
  pointed at this same container: **27/27 pass**, re-confirmed once more
  at the final subject_sha `b0408926` after the `ServiceLineFoundationTest`
  fix (unrelated to RBAC, but re-verified anyway since the subject SHA
  moved).
- Container torn down after evidence capture (disposable, per repo
  convention).

## Regression results

- **Full SQLite regression** (`vendor/bin/phpunit --exclude-group=stress,performance,browser`,
  2419 tests, 17246 assertions): **8 failures found before this PR's final
  commit; both categories investigated, not assumed:**
  - **7 pre-existing, unrelated:** `Tests\Feature\Dashboard\DashboardApiTest`
    (`it_can_add_widget_via_customization`, `it_can_remove_widget_via_customization`,
    `it_can_update_widget_config_via_customization`, `it_can_update_layout_via_customization`,
    `it_can_apply_layout_template`, `it_can_import_dashboard`,
    `it_can_reset_dashboard_via_customization`) — all fail with
    `Call to undefined method Illuminate\Cache\RedisStore::publish()`, a
    pre-existing broken cache-store method with zero RBAC/tenant files
    involved. This exact failure set (7 Dashboard widget tests, broken
    Redis cache-store method) is independently documented as pre-existing
    in `docs/owner-decisions/GAP-048/03-release.md`'s own regression
    section (merged to `main` before this PR's baseline) — not
    reclassified as pre-existing merely by assertion; corroborated by a
    prior, independent Work ID's own disclosure of the identical defect.
  - **1 genuine GAP-042-caused regression, found and FIXED (not dismissed):**
    `Tests\Feature\Models\ServiceLineFoundationTest::test_migration_round_trip_leaves_no_trace`
    hardcoded `migrate:rollback --step 3` to target exactly the 2 GAP-046
    table migrations + 1 GAP-048 nullable-column migration. GAP-042's new
    `custom_user_roles` migration is now the newest migration overall, so a
    naive `--step 3` would roll back the wrong 3 migrations. Fixed by
    targeting the exact 3 migration files via `--path` instead of a step
    count — verified GREEN, re-run together with the full RBAC suite.
  - **Final state after the fix: 0 GAP-042 regressions** in the full
    2419-test SQLite suite (only the 7 pre-existing, independently-documented
    Dashboard failures remain, unrelated to this PR).
- **Targeted focused regression** (`GAP042RbacProductionFidelityTest`,
  `RbacApiTest`, `ServiceLineFoundationTest`): 49 tests, 267 assertions,
  0 failures, on SQLite; RBAC suite subset re-confirmed 27/27 on real
  MySQL 8.0 at the final subject_sha.

## Route inventory check (no unauthorized new public surface)

`php artisan route:list --path=rbac` at subject_sha `b0408926`: **29 routes**
total — matches Gate 2 §2c's authoritative pre-fix inventory count exactly.
Confirmed zero routes registered for `assign/system`, `assign/custom`,
`assign/project`, or an unprefixed `effective-permissions` (§2d's
"keep unwired" decision honored). The three restored project-assignment
routes (`GET assignments/projects/{project}/users`,
`POST assignments/projects/{project}/users/{user}/roles`,
`DELETE assignments/projects/{project}/users/{user}/roles/{role}`) resolve
to real, newly-added `AssignmentController` methods, confirmed via the same
`route:list` output.

## Static analysis / PHPStan

**Known, documented, pre-existing environment limitation** (also disclosed
in GAP-048's own Gate-3 packet): this worktree's ad hoc vendor
copy+symlink construction (necessary because `vendor/` does not ship with
the repo and multiple parallel worktree sessions share the same physical
`vendor/composer`/`vendor/phpstan` install) cannot run `phpstan`/Composer
binaries locally — a symlinked package's own relative `require` resolves to
the MAIN repo's `vendor/autoload.php` while the binary proxy itself loads
the worktree's own freshly-dumped `vendor/autoload.php`, causing a duplicate
class-declaration fatal. PHPStan/Deptrac verification for this PR relies on
the live `Code Quality Analysis` CI check (see "CI status" below), matching
established repo precedent for this exact limitation.

## CI status

Live PR #299 CI at subject_sha `b04089268bdfe725d8d51db2f628fbfd99c8bd49`.
**26 of 27 applicable checks confirmed `pass`** at this exact head,
including every check directly relevant to this PR's RBAC/tenant surface
and every quality/security gate: `Owner Governance Lint` (including
`--enforce-gate-ordering`), `Zena RBAC/Tenant Invariants`, `Zena RBAC/Tenant
Invariants (MySQL parity)`, `Code Quality Analysis` (PHPStan/Deptrac),
`Security Tests`, `Security Vulnerability Scan`, `Dependency Vulnerability
Scan`, `Docker Security Scan`, `License Compliance Scan`, `Unit Tests`,
`Feature Tests`, `Integration Tests`, `API Tests (Fast/Slow)`, `Performance
Tests` (both), `test` (the full `ci-cd.yml` PHPUnit run), `test-routes-guardrails`,
`Repo Hygiene Guards`, `Test Coverage Report`, `RFI Escalation Concurrency
(real MySQL)`, `Document Workflow Concurrency (real MySQL)`, `Treasury
Native CHECK Constraints (real MySQL)`, `GAP-048 Service-Line Concurrency
(real MySQL)`, `staging-smoke`, `button-inventory-check`, `code-quality`,
`feature-tests`. **`browser-tests` (Dusk UI suite) was still `in_progress`
at the time this packet was authored** — it exercises browser-driven UI
flows, not the RBAC API surface this PR touches, and is unrelated in scope
to GAP-042's changes; its outcome should still be independently confirmed
green on the live PR before Owner decision, and this packet does not claim
"all CI green" until that check's own result is confirmed.

## Known limitations, disclosed honestly

- The Design Dependency Preflight was correctly NOT triggered (per Gate 2
  §7's classification) — this PR restores/security-scopes existing RBAC
  semantics; it does not change CRM/Project/Finance/Treasury/OPPM business
  semantics. No implementation-time discovery contradicted that
  classification.
- `assignSystem`/`assignCustom`/`assignProject`/`getEffectivePermissions`
  (singular) remain deliberately unwired per §2d — not touched, not
  exposed, matching the approved decision exactly.
- `AssignmentController::getUserRoles()` (Gate 1's original incidental
  finding) and the `CompensationController`/`Src\RBAC\Middleware\RBACMiddleware`
  constructor-wiring defect both remain explicitly excluded, unfixed, per
  Owner instruction — untouched by this PR.
- `Src\RBAC\Models\RolePermission` (the dead Pivot subclass) was NOT
  reactivated — standard `belongsToMany()` against `role_permissions` was
  used throughout, per Gate 2 §15's explicit instruction. No concrete
  technical requirement for the custom Pivot class was ever discovered
  during implementation.

## What this packet does NOT authorize

This Gate-3 packet does not authorize Ready-for-review, merge, release, or
production deployment. Those remain separate, explicit Owner decisions to
be issued after Owner reviews this packet. The implementation PR (#299)
remains Draft and unmerged.

## What the owner is NOT being asked to decide

Owner is not being asked to inspect CI logs, source diffs, or review
comments line-by-line — only whether the demonstrated behavior (the
completed 20-item acceptance matrix, TDD evidence, real-MySQL proof, and
residual risk) is acceptable to move toward release.
