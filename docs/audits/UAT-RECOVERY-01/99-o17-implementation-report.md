# O-17 Option B + CB-3 / CB-4 — Local implementation report

**Status: see §10.**

- **Base:** the patch was built locally on top of PR #422 head `004a5774d`.
- **Not committed, not pushed.** No staging or production access was used.
- **No permission changes:** no grant, revocation, seed change or migration.

**Owner decision (2026-11-24): "APPROVED_FOR_BOUNDED_LOCAL_IMPLEMENTATION".** The approved contract is that task creation requires all of:
1. the actor is in the same tenant as the project;
2. the actor holds the canonical `task.create`, plus `template.apply` for templates;
3. the actor is the project's PM (`pm_id`) or an active `project_user_roles` participant.

There is no System Admin or `super_admin` bypass, and nothing is derived from `users.role`.

## 0. Gate 0
| Item | Value |
|---|---|
| Branch / worktree | `integration/uat-recovery-s1-s4d`, worktree `.claude/worktrees/uat-recovery-s1-s4d` |
| PR #422 head | `004a5774d31977a4ea55b4f25621a02269c47bac`, Draft, auto-merge off |
| CI on `004a5774d` | **35 / 35 pass** (final) |

## 1. Gate 1 — Pre-fix evidence (preserved)
- **Policy probe:** `98-o17-task-creation-decision-package.md` §2, with `evidence/o17-creation-probe.json` and `o17-creation-route-inventory.tsv`. These are unchanged.
- **RED run:** the new test class ran against the **unpatched** controllers. 22 tests: **20 failed**, and the 2 already-safe cases (Member denied, cross-tenant 422) passed. See `evidence/o17-impl-red-run.txt`.

**Erratum to doc 98, I1/I2.**
- `assignee_id` is **not** dropped. `App\Models\Task::setAssigneeIdAttribute` stores it in `assigned_to`.
- So before this patch, any user id from **any tenant** that passed the global `exists:users` rule was written to `tasks.assigned_to`.
- The probe only read the `assignee_id` column. Doc 98 is left as written; this report is the correction.

## 2. Changed files
**Production code:**
| File | Change |
|---|---|
| `app/Support/TaskCreationAuthority.php` (new) | The single decision point: `denialReason()` / `allows()`, `isProjectPm`, `isActiveParticipant`, `eligibleAssignee`, `taskOfProject`, `logDenial`, and the strict `audit()` / `auditIds()`. It has no role-name logic and never calls `isSuperAdmin()`. |
| `app/Http/Controllers/Api/TaskController.php` | `store`: validates the tenant-scoped `project_id`, then decides authority (403), then validates references. `assignee_id` and `watchers.*` must be eligible; `parent_id` and `dependencies.*` must be tasks of the same project. The audit row is written inside the transaction, replacing the best-effort audit after commit. |
| `src/CoreProject/Requests/StoreTaskRequest.php` | `authorize()` implements the contract and runs before validation (403 in the JSend shape). `assignments.*.user_id` must be eligible; `dependencies.*`, `component_id` and `phase_id` must be project-scoped. |
| `src/CoreProject/Controllers/TaskController.php` | `store`: the creation and its audit row are written in one `DB::transaction`. |
| `src/CoreProject/Services/TaskService.php` | `createTask` writes `tenant_id` = the project's tenant. **Defect found:** v1 tasks used to be written with an empty `tenant_id`. |
| `app/Http/Controllers/Api/BulkOperationsController.php` | CB-3 gate for both bulk routes, in this order: no `task.create` → 403; request `tenant_id` / `options.tenant_id` ≠ authenticated tenant → 422; missing, malformed, unknown or foreign `project_id` → 422; no PM or participant relationship → 403. Row `assignee_id` must be eligible (422). |
| `app/Services/BulkOperationsService.php` | The tenant always comes from `Auth::user()`, and a conflicting tenant throws. Each row gets a tenant-scoped project and the contract check. Row fields are whitelisted, so `tenant_id`, `project_id`, `created_by` and links are never mass-assigned. Each row is a savepoint holding the task and its audit row. |
| `app/Http/Controllers/Api/WorkTemplateController.php` | `applyTemplateToScope` (project apply, component apply, web preview and apply): the contract with `template.apply` is checked before preview or apply (403). One audit row is written per created task inside the transaction. A `user_id` assignee selector must be eligible; otherwise "unresolved assignment rule" (422). |
| `src/CoreProject/Controllers/WorkTemplateController.php` | Latent `api/work-templates/{id}/apply`: tenant-scoped `project_id`, then the contract (403), then project-scoped `component_id` and an eligible `default_assignee_id`. |
| `src/WorkTemplate/Controllers/TemplateController.php` | Latent `api/templates/{id}/apply`: the contract (403), then one transaction holding the created `project_tasks` rows and their audit rows. |
| `app/Http/Controllers/Api/ChangeRequestController.php` | `apply` (derived creation): an in-controller canonical `change-request.apply` check closes CB-4. The derived "CR delta" task is audited inside the transaction. Since the pre-push gate (§11, O-17i), apply also requires the full task-creation contract via `TaskCreationAuthority::denialReason`. |

**Tests:**
- `tests/Feature/Recovery/UatRecovery06O17TaskCreationAuthorityTest.php` (new): 23 tests, listed in §5.
- Fixture-only adaptations are listed in §6.

## 3. Route and service inventory (after the patch)
| # | Route | Decision point | Result |
|---|---|---|---|
| 1 | `POST api/zena/tasks` | `Api\TaskController::store` | contract enforced; references checked; audited atomically |
| 2 | `POST api/tasks` (parameterless gate) | same action | same |
| 3 | `POST api/v1/tasks` | `StoreTaskRequest::authorize` | same; `tenant_id` now written |
| 4 | `POST app/tasks`, `POST tasks` | delegate to #1 | same; web shows the existing error flash |
| 5 | `POST operator/schedule/tasks` | delegates to #1 | same |
| 6 | CAPA routes (api and operator) | delegate to #1 | same |
| 7 | `POST api/auth/bulk/tasks/create`, `POST api/auth/bulk/import/tasks` | controller gate **and** service (each row) | CB-3 closed; audited per row |
| 8 | `POST api/zena/projects/{id}/apply-template`, `POST api/zena/components/{id}/apply-template`, `POST app/projects/{p}/work-templates/{preview,apply}` | `applyTemplateToScope` | contract + `template.apply`; audited per task |
| 9 | `POST api/zena/change-requests/{id}/apply` | controller | canonical grant (CB-4); audited; full task-creation contract since the pre-push gate (O-17i, §11) |
| 10 | `POST api/work-templates/{id}/apply` | controller | contract; non-functional (schema, doc 98 §1) |
| 11 | `POST api/templates/{id}/apply` | controller | contract + atomic audit; `project_tasks` has **no migration**, so this route is non-functional outside the test harness |
| 12 | `POST api/templates/apply` (ProjectTemplate) | — | creates nothing (`ProjectTemplate::applyToProject` only returns data) |
| — | `v1/work-template/templates/{id}/apply` | — | routes to a method that does not exist; cannot create |
| — | `ChangeRequestEventListener` (`src/ChangeRequest`) | system event | not user-reachable; no `tenant_id` and no audit (pre-existing, §8) |

Root `App\Http\Controllers\TaskController`, `Api\App\TaskController` and `Web\TaskBulkController` are unrouted (doc 98 §1).

## 4. CB-3 and CB-4

### CB-3 — bulk creation crossed tenants (Critical where more than one tenant exists)
**Exploit path (before the patch):**
- An `auth.bulk.manage` holder (seeded: System Admin) posted `{"tenant_id": B, "project_id": <project of B>}` to `api/auth/bulk/tasks/create`.
- `BulkOperationsService::createTask` wrote `tenant_id` = B, with no project validation and no audit row (doc 98, K1).
- Rows could also mass-assign any `tasks` column.

**Fix:**
- The tenant comes only from the authenticated user, and a conflicting `tenant_id` or `options.tenant_id` gets a 422.
- `project_id` must exist in that tenant: missing, malformed, unknown or foreign ids get a 422.
- The O-17 contract is then applied (403), and rows are whitelisted.
- The service repeats every check, so no caller can bypass the controller.

**Verified:**
- `test_bulk_create_never_trusts_request_tenant_and_rejects_foreign_malformed_or_missing_projects` covers 7 cases, each with **zero** task, assignment, audit, watcher, dependency or work-instance rows, and 0 rows in tenant B.
- `test_bulk_create_for_authorised_actor_writes_only_into_the_authorised_project_and_audits_each_task`: smuggled row-level `tenant_id`, `project_id` and `created_by` are ignored.
- `test_bulk_import_never_trusts_request_tenant_and_enforces_project_authority` covers 4 denial cases.

### CB-4 — `super_admin` role-name bypass (High)
**Exploit path:** `RoleBasedAccessControlMiddleware::checkAccess()` returns `true` for `isSuperAdmin()`, i.e. any DB role named `super_admin`, before any permission check. Routes with no in-controller check therefore created tasks with **zero** permissions:
- `api/v1/tasks`;
- bulk (`api/auth/bulk/...`), including writes into tenant B;
- change-request apply.

**Fix (no middleware change):** every creation entry point decides authority in-controller through `User::hasPermission` (canonical rows only; `User::hasPermission` overrides the `HasRoles` trait method, which contains a latent `'super_admin' => ['*']` map).

**Verified:**
- `test_super_admin_role_without_canonical_grant_is_denied_on_every_creation_path`: 5 direct routes, bulk (own tenant and tenant B), and template apply (api and web), even with a recorded participation row.
- `test_change_request_apply_needs_the_canonical_grant_and_audits_its_delta_task`.

**Test-suite evidence of CB-4 reach:**
- `Tests\Feature\IntegrationTest` created tasks only through this bypass.
- The default test persona (`createTenantUser`) holds a `super_admin` role and no `task.create`.
- `ChangeRequestApiTest` applied change requests only through this bypass. Its grants sit in `system_user_roles`, which no canonical check reads.

**Not fixed here (O-17h, needs its own Work ID):**
- the middleware short-circuit itself, which still admits a `super_admin` role on every **non-creation** `rbac:` route;
- the latent `HasRoles::hasPermission` map.

## 5. RED / GREEN evidence (`UatRecovery06O17TaskCreationAuthorityTest`)
**Run counts:**
| Run | Result |
|---|---|
| RED, unpatched controllers (22 tests at that point) | 20 failed / 2 passed (`evidence/o17-impl-red-run.txt`) |
| GREEN, SQLite | **OK, 23 tests, 469 assertions** |
| GREEN, isolated MariaDB 10.4.28 | **OK** (`evidence/o17-impl-verification.txt`) |

**Tests in the class:**
- **Allowed cases:**
  - the owning PM on all 5 direct routes, each creation audited once with actor, tenant and project;
  - an active participant with `task.create`;
  - System Admin **only** once it is a participant;
  - a participant of a project with no PM.
- **Denied cases:**
  - a participant without `task.create`;
  - a PM of another project;
  - a same-tenant holder with no relationship;
  - a participant of P1 trying P2;
  - System Admin as a non-member;
  - a zero-permission `super_admin`;
  - revoked participation (`deleted_at` set);
  - a participation row pointing a foreign-tenant user at the project (422);
  - cross-tenant (422);
  - `template.apply` alone, on apply and preview;
  - a PM on another PM's project, via template;
  - `task.create` without `template.apply`.
- **Reference checks:**
  - 9 reference cases on `api/zena` and 3 on v1 each return 422 with nothing persisted: foreign or ineligible assignee and watcher, foreign or other-project parent and dependency, unknown ids, v1 `assignments[].user_id`.
  - Eligible assignee, parent and dependency are accepted.
  - An unauthorised actor gets 403, never a reference 422 (so no existence oracle).
- **Template assignee:** a non-participant `user_id` selector gives 422 with nothing persisted; a participant `user_id` gives 201.
- **Atomicity:** a simulated failure of the `audit_logs` insert, raised from a `QueryExecuted` listener so it works on any driver, rolls back the creation on `api/zena`, v1, web, bulk (row reported failed) and template apply.
- **Latent path:** CoreProject apply denies a foreign project, a non-member and `super_admin`.

**Tenant-boundary proof:** every denial asserts identical counts in `tasks`, `task_assignments`, `audit_logs`, `task_watchers`, `task_dependencies` and `work_instances`, and 0 tasks in tenant B.

**Audit proof:**
- Every successful path writes exactly one `zena.task.created` row per task, with `entity_type` `task` (`project_task` for #11), actor, tenant, project and route.
- `meta.source` names the path.
- The row is written inside the creating transaction.

## 6. Existing tests: adaptations (fixture only unless stated)
| Test | Why | Change |
|---|---|---|
| `Recovery/UatRecovery04cSurfaceAndPmTest::test_OWNER_DECISION_task_creation_has_no_project_ownership_constraint_today` | characterisation of the pre-O-17 behaviour that the Owner has now decided | **inverted** into `test_O17_task_creation_requires_a_project_relationship` (403, no row), as was done for O-18 |
| `…::test_creating_a_task_never_assigns_it_so_creation_is_not_an_assignment_bypass` | memberB is ineligible, and pm2 has no relationship | **assertion changed and strengthened.** Before: the task was created with the assignee ignored. Now: pm1 gets 422 and pm2 gets 403, with no task and no assignment |
| `Recovery/UatRecovery04cTaskCreateVerificationTest::test_authorised_creator_succeeds_on_the_legacy_gate_route` | the creator was not the PM | fixture: `pm_id` = creator |
| `Api/AuthSensitiveRoutesProtectionTest` (bulk tasks create and import, 200 case) | posted the nonexistent project id `p1`, which pinned the CB-3 behaviour | fixture: a real project, the user is its PM, plus `task.create`; the 200 assertion is unchanged |
| `BulkOperationsTest::test_can_bulk_create_tasks` | the service now requires an authorised actor | fixture: acting user with `task.create` who is PM |
| `IntegrationTest::test_complete_project_workflow` | passed only via the CB-4 bypass; the creator is not PM | fixture: `task.create` + creator set as PM |
| `TaskTest`, `Legacy/LegacyTaskCreationPersistsTest`, `TemplateControllerTest` ×2 | creator not PM | fixture: `pm_id` (+ `task.create` in `TemplateControllerTest`) |
| `Web/ProjectWorkTemplateApplyTest` ×2, `WorkTemplates/WorkTemplateAuthoringPageTest` ×2 | template appliers lacked `task.create` | fixture: + `task.create` |
| `ChangeRequestApiTest` ×3 (apply) | the persona's `change-request.apply` was granted only through `system_user_roles`, which `User::hasPermission` never reads, so apply succeeded only through the CB-4 `super_admin` bypass | fixture: the canonical grant via `apiActingAsTenantAdmin(..., ['change-request.apply'])` |
| `Api/TaskCreatedPipelineTest` ×2 | the named assignee was not a participant (O-17d) | fixture: the assignee gets an active `project_user_roles` row |
| `Api/WorkTemplateMvpApiTest` ×9, `Api/WorkTemplateBaselineSeederTest` ×2, `Api/InspectionTemplateRuntimeTest` ×2 | template appliers (already the PM) lacked `task.create` | fixture: + `task.create` wherever `template.apply` was granted |
| `Api/ChangeRequestApiTest::test_can_implement_change_request` | the actor's only role was a zero-permission `super_admin` (CB-4) | fixture: a test-owned role with the canonical `change-request.apply` |
| `Api/IntegrationTest` ×2 | creator not PM; change-request apply relied on CB-4 | fixture: creator set as PM; + `change-request.apply` |
| `Api/TaskApiTest::test_can_create_task_with_dependencies` | creator not PM | fixture: `pm_id` |

`BulkOperationsSimpleTest` (group `performance`, excluded from the default run and from CI) was not changed. It would now fail, because its actor has no grants.

Original bodies of the two changed 04c tests:
```php
    public function test_creating_a_task_never_assigns_it_so_creation_is_not_an_assignment_bypass(): void
    {
        foreach ([$this->pm1, $this->pm2] as $who) {
            $this->api($who, 'POST', '/api/zena/tasks', ['project_id' => $this->projectX->id, 'name' => 'created-by-' . $who->id, 'assignee_id' => $this->memberB->id]);
            $row = DB::table('tasks')->where('name', 'created-by-' . $who->id)->first();
            $this->assertNotNull($row);
            $this->assertNull($row->assignee_id, 'assignee_id is not honoured at creation');
            $this->assertSame(0, DB::table('task_assignments')->where('task_id', $row->id)->count());
        }
    }

    /** OWNER_DECISION_REQUIRED: task CREATION has no project constraint (any task.create holder, any project of the tenant; O-3 tenant-visible). */
    #[Group('uat-recovery-04c-o15')]
    public function test_OWNER_DECISION_task_creation_has_no_project_ownership_constraint_today(): void
    {
        $this->api($this->pm2, 'POST', '/api/zena/tasks', ['project_id' => $this->projectX->id, 'name' => 'foreign-project-create']);

        $this->assertSame($this->projectX->id, DB::table('tasks')->where('name', 'foreign-project-create')->value('project_id'));
    }
```

## 7. Verification
See `evidence/o17-impl-verification.txt`. In every run, the Backup classes that write to the real `storage/backups` were excluded.

| Check | Result |
|---|---|
| New class, SQLite | OK, 23 tests |
| Recovery suite, SQLite | **OK, 167 tests, 1427 assertions** (144 before + 23 new) |
| Feature + Architecture, SQLite (final) | **OK, 2248 tests, 0 failures** (33 skipped) |
| `tests/Feature/Api` (excluded from the Feature testsuite), SQLite (final) | **OK, 825 tests, 0 failures** |
| Unit, SQLite (final) | 948 tests, **7 failures, the same as before the patch** (EvidenceFreshnessLiveCheck ×5, ZenaInvariantsDiscovery ×2) |
| Recovery suite, **isolated MariaDB 10.4.28** (scratch server, now stopped and deleted) | **OK, 167 tests.** Run before the final two PHPStan type guards, which were re-verified on SQLite only. The two 04b `split_percent` tests that failed on MariaDB in the O-18 run passed here; `split_percent` was not investigated and is **not** claimed fixed |
| PHPStan (project config, `app` / `routes` / `database`) | no errors, after one fix (a nullable route in `TaskCreationAuthority`) |
| php-cs-fixer (changed files) | clean |
| **MySQL 8** | **MYSQL_NOT_VERIFIED.** There is no MySQL 8 locally and Docker is not running. MariaDB 10.4 is the closest server available; CI (MySQL 8) has not run on this patch, because nothing is pushed |

**Regression comparison:**
- Baseline: `004a5774d`, CI 35 / 35, plus the O-18 local run (Feature 0 failures; Unit 7 pre-existing).
- The first patched runs had 14 Feature and 17 Api failures. All of them are the fixture adaptations listed in §6, plus one transient architecture failure caused by a file being edited mid-run, which passed on rerun.
- The final runs have 0 new failures.

## 8. Remaining security defects and gaps (not fixed in this slice)
| ID | Severity | Item |
|---|---|---|
| O-17h | High | Middleware `isSuperAdmin()` short-circuit on every **non-creation** `rbac:` route; latent `HasRoles::hasPermission` `super_admin => *` map. Needs its own Work ID and an Owner gate. |
| W-1 | Medium (availability) | Any **valid** `watchers` at creation returns 500: `task_watchers.id` is NOT NULL and never generated. Invalid watchers now get 422 before reaching it. Pre-existing. |
| split_percent | Medium | v1 creation with `assignments[]` fails on MySQL (column missing). Out of scope by instruction; invalid assignment users now get 422 before reaching it. |
| IMP-1 | Low | `api/auth/bulk/import/tasks` cannot import anything: `ImportExportService` references an un-imported `CsvReader` class (500 after authority passes). Pre-existing; it was therefore not a practical CB-3 vector before the patch. |
| PRE-1 | Medium | `POST api/zena/work-templates/{id}/preview` (`template.view`) previews against any project of the tenant, and the payload includes resolved PM and participant ids. It creates nothing. Recommend the same relationship check, as a follow-up. |
| TPL-1 | Low | The `Template::find` / `WorkTemplate::findOrFail` lookups on latent routes #10 and #11 are not tenant-scoped. `ApplyTemplateRequest` keeps a global `exists` on `phase_mapping.*.project_phase_id`. These routes are non-functional in real schemas. |
| CRL-1 | Low | `src/ChangeRequest/Listeners/ChangeRequestEventListener` creates tasks without `tenant_id` or audit. It is not user-reachable. |
| UI-1 | UX | The create forms, the schedule page and the template picker still list projects where the user will get 403 (doc 98 §5 item 9 / T-8, not implemented). |
| BULK-1 | Low | Bulk row `status` and `priority` are not validated; database errors are returned in `errors[]`. Pre-existing. |

## 9. O-17 sub-decision mapping
| ID | Owner instruction (2026-11-24) | Implemented as | Conflict / open |
|---|---|---|---|
| O-17 | Option B | §2 | — |
| O-17a | "active project participant recorded in project_user_roles" | `deleted_at IS NULL`; the participant must be a non-deleted user of the project's tenant | The table **can** distinguish active from inactive (`deleted_at`). It has no tenant column, so the tenant boundary is enforced by joining `users.tenant_id` = project tenant. The membership role's scope and tenant are not checked at decision time; the only writer enforces them. No status field was invented. |
| O-17b | implied by the contract (PM or participant) | a project with no PM accepts participants only | — |
| O-17c | "No implicit System Admin or super_admin bypass" | no override permission created | — |
| O-17d | assignee, watcher and assignment eligibility | 422 on direct, v1 and bulk routes | **Deviation from doc 98's proposal:** an ineligible template `user_id` selector returns the existing 422 "unresolved assignment rule" instead of "left unassigned with a warning". **Confirmed by the Owner in the pre-push gate** ("Template assignee outside project: 422"). |
| O-17e | fix CB-3 / CB-4 in this slice | §4 | — |
| O-17f | "No new grants" | none | — |
| O-17g | keep the System Admin membership API; PM-managed participation deferred to S5 | unchanged | — |
| O-17h | "Do not globally rewrite middleware" | not changed | **Open:** separate Work ID / Owner gate |
| **O-17i (new)** | **Decided 2026-11-24** in the pre-push gate (§11): apply requires `change-request.apply` + `task.create` + same tenant + PM or active participant | §11: enforced in `ChangeRequestController::apply` via `TaskCreationAuthority` | Workflow effect: the only seeded `change-request.apply` holder, System Admin, can apply only on projects where it is PM or participant (see §11). |
| **O-17j (new)** | — | an eligible `assignee_id` at creation is stored (`assigned_to`) without a `task.assign` check | **Open:** should creation with an assignee also require `task.assign`? Today both seeded `task.create` holders also hold `task.assign`, so there is no current effect. |
| **O-17k (new, workflow)** | — | a project creator (`project.create`) who does not name themself PM cannot add tasks until made PM or participant | Owner to confirm this is intended |

## 10. Final result
**O17_LOCAL_PATCH_VERIFIED.** This applies to the local patch, verified on SQLite and on isolated MariaDB 10.4.

The verification is conditional on two things:
- **MYSQL_NOT_VERIFIED:** CI on MySQL 8 runs only after an Owner-approved push.
- The open sub-decisions O-17h, O-17i, O-17j and O-17k (§9).

Nothing is committed, pushed, merged or deployed. PR #422 stays Draft and MERGE_NO_GO.

**Proposed next integration boundary:**
1. The Owner reviews this patch.
2. Commit on `integration/uat-recovery-s1-s4d` as one commit, then push fast-forward with no force.
3. CI on MySQL 8.
4. Separate Work IDs for O-17h (middleware `super_admin`), W-1 (`task_watchers.id`), IMP-1 (import), `split_percent`, and PRE-1 (preview).
5. Read-only production check: who holds `super_admin` and `auth.bulk.manage`, whether more than one tenant exists, and whether any `tasks` rows have an empty `tenant_id` (v1 defect) or a foreign `assigned_to` (I1 erratum).

## 11. Pre-push security gate (Owner: "O17_APPROVED_FOR_CONDITIONAL_COMMIT_AND_PUSH", 2026-11-24)

**Remote check.** `integration/uat-recovery-s1-s4d` was at `004a5774d31977a4ea55b4f25621a02269c47bac`, as expected, and PR #422 was Draft.

**Gate cases for change-request apply.** The Owner's rule: apply creates a task, so the actor must hold `change-request.apply` and `task.create`, belong to the project's tenant, and be its PM or an active participant.

- **RED.** On the patch as delivered in §1–§10, a System Admin holding `change-request.apply` and `task.create`, and not a participant, applied a change request on another project of the same tenant. The call returned 200, and the change request became `implemented` with a delta task. The earlier patch only checked the canonical grant (O-17i was left open).
- **Fix.** `ChangeRequestController::apply` now resolves the change request's project, scoped to the tenant, and calls `TaskCreationAuthority::denialReason` before any write. A denial returns 403 and is logged with `logDenial`. No new exception or permission was added.
- **GREEN.** `UatRecovery06O17TaskCreationAuthorityTest::test_change_request_apply_requires_the_full_task_creation_contract` covers:
  - a non-participant holding `change-request.apply`, on another same-tenant project: 403;
  - `change-request.apply` without `task.create`, as a participant: 403;
  - `task.create` participant without `change-request.apply`: 403;
  - a zero-permission `super_admin` participant: 403;
  - a cross-tenant change request: 403/404;
  - in each denied case: no task, assignment, audit row, CR link or baseline, the change request is still `approved`, and `task_id` is null;
  - the PM holding both grants: 200, with the delta task in the right project and exactly one `zena.task.created` audit row.
- The earlier test (`…needs_the_canonical_grant…`) now makes the System Admin a participant before its success case.

**Fixtures adapted by this gate.** Their assertions are unchanged; each actor that applies is given `task.create` plus PM or participant status:

| Test | Change |
|---|---|
| `ChangeRequestApiTest` (Feature) | `task.create` added; persona set as PM |
| `Api/ChangeRequestApiTest` | test-owned role gains `task.create`; persona set as PM |
| `Api/ChangeRequestStakeholderNotificationTest` (3 tests) | approver gains `task.create` and active participation, staying distinct from the PM so the two-recipient assertions keep their meaning |
| `Api/IntegrationTest::test_change_request_workflow_integration` | persona set as PM |
| `Notifications/BusinessNotificationsTest::test_change_request_apply_posts_once…` | applier gains `task.create` and participation |
| `Zena/ZenaAuditInvariantTest::test_change_request_workflow_mutations_write_audit_logs` | `task.create` added; persona set as PM |

`ChangeRequestApiTest::test_apply_requires_approved_status` (400) works again once its persona has the relationship. The authority check now runs before the status check, so an unauthorised caller cannot learn a change request's status.

**Owner confirmations in this gate:**
- O-17i is decided as above.
- A template assignee outside the project returns 422 (O-17d).
- Not to be implemented in this commit: a global `super_admin` middleware rewrite (O-17h), a `task.assign` requirement (O-17j), automatic PM assignment (O-17k), `split_percent`, the `task_watchers.id` repair (W-1) and `CsvReader` (IMP-1).

**CI and MySQL 8, from inspecting the workflows.** No CI job runs `tests/Feature/Recovery` on MySQL 8:
- `ci-cd.yml` → `test` uses MySQL 8.0 only for the two GAP-032 document tests and runs the suite on SQLite.
- `automated-testing.yml` → `feature-tests` and the Api jobs run on SQLite.
- The `*-mysql` jobs run only named invariant, concurrency and performance files.

The CI run therefore cannot supply MySQL 8 evidence for this patch. The only MySQL-family evidence is the local isolated MariaDB 10.4 run in §7.
