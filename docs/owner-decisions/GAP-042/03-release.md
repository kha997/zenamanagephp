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
  recorded_at: "2026-09-02T09:40:00Z"
  owner_response_reference: "GAP-042 Gate 3 Owner Round 2 (relayed via coordinator session, reviewed exact corrected implementation subject feaa320b98b31380c2cac7e74ce872616b63eca5 / implementation-tree digest 8512e0a5e22f0712d56bac6bf3e1a94f0c02ba8e2aa5356c0041638b62733295 and Gate-3 record/PR head 6b4ef1535456925748928b38e47af6b470c4eeef against canonical main 673855f69a3633b64c378e965ae409ed3a098c50): 'DECISION: CHANGES REQUESTED. Continue in the SAME GAP-042 implementation/Gate-3 session, SAME branch/worktree, and SAME Draft PR #299. Do not open a new session, create another implementation branch, use the abandoned duplicate local implementation attempt, mark Ready, merge, deploy, or start another Work ID. The major Round-1 corrections are accepted as directionally correct — do not reopen them. Round 2 is narrowly limited to: Correction 10 — Permission Matrix EventBus projectId semantics are still wrong. Round-1 Correction 6 explicitly required actorId = actual acting user or repository-established system fallback; projectId = actual project id for a genuine project event; for NON-PROJECT RBAC events, use the repository-established system convention; never label tenant id as project id. The corrected subject still violates that rule in the Permission Matrix execution path: PermissionMatrixController::export(), PermissionMatrixController::import(), PermissionMatrixService::importFromCSV() per-role event all use `projectId => (string) ($tenantId ?? system)`. These are NOT project-domain events — fix to the literal `projectId => system` convention. Keep actorId truthful, entityId truthful, no EventBus refactor, no changes outside the GAP-042-touched RBAC event surface. RED first: add a discriminating test proving no remaining Permission-Matrix pattern equivalent to `projectId => $tenantId` or `projectId => ($tenantId ?? ...)` for these non-project events — prefer capturing the actual emitted/audit payload if practical. Correction 11 — rejected Permission Matrix imports must not create global permission rows before authorization/role validation. PermissionMatrixService::importFromCSV() performs `Permission::firstOrCreate(...)` while parsing CSV rows, BEFORE resolving whether the target role is visible to the caller tenant, belongs to the caller tenant, or is global/system and therefore read-only — leaving an unauthorized side effect (the import route only requires rbac:permission.import; direct permission creation is separately protected by rbac:permission.create). RED tests first using a permission code that does NOT exist before the request: (A) cross-tenant target role — as tenant A, import CSV targeting a tenant-B-owned role using a brand-new permission code; after the request, tenant-B role permissions unchanged, no role_permissions row written, permissions table does NOT contain the new code. (B) global/system read-only target role — same proof for a genuine global/system role. Control: an own-tenant mutable role importing a genuinely new valid permission may continue to create the permission and sync it. Implementation requirement: do not perform permission-creation side effects until the relevant target role has passed visibility/ownership/global-read-only validation — prefer a parse/validate/resolve-before-write structure; do not redesign the whole CSV importer; if partial success across multiple valid/invalid roles is intentionally supported, preserve that behavior, but an invalid/global/cross-tenant ROLE row must produce ZERO permission-definition or role-permission writes. Correction 12 — make Gate-3 evidence exactly match the tests. The current packet claims BOTH effective-permissions and check-permission are proven fail-closed for both another tenant's user and another tenant's project, but the Round-1 correction test suite only explicitly exercises effective-permissions x cross-tenant user, check-permission x cross-tenant user, effective-permissions x cross-tenant project, and valid own-tenant controls — add the missing check-permission x cross-tenant project discriminator with the same non-disclosing failure behavior; this is preferable to weakening the packet wording. Also refresh the PR body before final Gate-3 re-presentation to remove stale pre-Round-1 evidence language. Do not rewrite historical Gate-3 decision records. Verification: strict RED->GREEN for Corrections 10-12; run the new Round-2 focused tests, both prior GAP-042 suites, RbacApiTest, genuine disposable MySQL 8.0 (clean migrate:fresh, canonical RBAC tables present, zena_roles/zena_permissions absent, all GAP-042 RBAC tests green), broader SQLite regression, PHPStan/Deptrac and applicable code-quality checks, route inventory (still exactly the authorized 29 RBAC routes, no new public API), and inspect the full diff from canonical main for scope creep. Then freeze a NEW implementation subject SHA, recompute the implementation-tree digest via the repository canonical Owner-Governance implementation-tree function, update this Gate-3 packet (preserve Round 1 CHANGES REQUESTED permanently, append/preserve this Round 2 CHANGES REQUESTED decision, update subject SHA and tree digest, refresh correction evidence truthfully, do not overwrite history, return to awaiting_owner/technical_readiness:ready only when repository governance permits it), push to the SAME Draft PR #299, wait for ALL mandatory CI checks on the FINAL exact head (Owner Governance Lint MUST be SUCCESS on that final exact head), then STOP for Owner Round-3 review. Do not reopen: Option A canonical schema convergence; custom_user_roles 3-layer decision/migration shape; tenant role visibility semantics; global-role read-only policy; assignment tenant checks already corrected; scope=system escalation protection; genuine-global assignSystemRole check; revoke identity validation; effective/check-permission tenant-target helper; restored project routes; route identity decision; unwired methods decision; RolePermission decision; roles.name uniqueness; getUserRoles exclusion; Compensation/RBACMiddleware exclusion; production deployment; GAP-041/GAP-045. No new public API. No Ready flip. No merge. No release. No deploy. No self-approval.'"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-02T06:20:00Z"
  updated_at: "2026-09-02T09:40:00Z"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-042 implements Option A exactly as approved through Gate 2 Round 5, corrected per Owner Gate-3 Round 1 (CHANGES REQUESTED) and Round 2 (CHANGES REQUESTED), full text of both in decision_provenance.owner_response_reference history (Round 1 text preserved verbatim in the Round-1 section below; Round 2 text in this frontmatter's current owner_response_reference). Round-1 corrections 1-7 (see 'Owner Decision History — Round 1' and 'Round-1 correction evidence' below) remain implemented and verified, unchanged this round per the Owner's explicit 'do not reopen' instruction. Round-2 corrections 10-12 (see 'Owner Decision History — Round 2' and 'Round-2 correction evidence' below for the complete, itemized RED->GREEN record) are all implemented and verified: (10) Permission Matrix EventBus non-project events (PermissionMatrixController::export()/import(), PermissionMatrixService::importFromCSV()'s per-role event) now publish the literal `'projectId' => 'system'` convention instead of `(string) ($tenantId ?? 'system')`, proven both by a captured-payload EventBus test (wildcard-listener subscription) and a static source-pattern assertion; (11) PermissionMatrixService::importFromCSV() restructured to a parse-then-validate-then-write flow — CSV rows are only parsed/queued per role name in a first pass, and Permission::firstOrCreate() only runs in a second pass after the target role has passed the pre-existing visibility/ownership/global-read-only checks, so an import aimed at a cross-tenant or global/read-only role now produces zero `permissions` or `role_permissions` writes (proven with brand-new permission codes that do not exist before the request, per the Owner's explicit RED-test requirement); (12) the missing check-permission x cross-tenant project discriminator added, completing the tenant-fail-closed security matrix (the existing shared targetsAreTenantScoped() helper already covered this path — this closes a test-coverage gap, not an implementation gap). Correction 9/Round-1's exact-head CI-before-awaiting_owner sequence is being followed again for this round: this packet's gate_status remains awaiting_owner only after this same session pushes this content and independently confirms Owner Governance Lint SUCCESS plus all other mandatory checks green on this exact new subject_sha, per the final CI-status section below."
technical_evidence:
  subject_sha: "13e9e64df3c9ceba29dd191494df8a4ee757b1f5"
  implementation_tree_digest: "6192e9e48ffba5d04b875baf16b8848ee2d9e069645f3284367a2a7de2e22917"
  verified_pr_head_sha: "13e9e64df3c9ceba29dd191494df8a4ee757b1f5"
  verified_at: "2026-09-02T09:40:00Z"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-042 — RBAC Production-Fidelity Restoration: Gate 3 Release Request

**Gate 3 packet status: `awaiting_owner`.** This is evidence/request only —
it is NOT Owner approval. No merge, no Ready-for-review flip, no release,
no deployment has occurred or is authorized by this packet.

## Owner Decision History — Round 1 — CHANGES REQUESTED (permanent record, never erased)

**Owner Gate-3 Round 1 decision: CHANGES REQUESTED.** Reviewed the prior
implementation subject `b04089268bdfe725d8d51db2f628fbfd99c8bd49` and
Gate-3 record head `bd3c0910a8555ab30195c0b3a15447cea608ea6f` against
canonical main `673855f69a3633b64c378e965ae409ed3a098c50`. Full verbatim
directive preserved in this file's frontmatter
`decision_provenance.owner_response_reference` above. Nine corrections
were required (7 load-bearing production/security defects under strict
RED→GREEN TDD, 1 Gate-3-evidence-truthfulness correction, 1 exact-head
CI-before-`awaiting_owner` sequencing requirement) — summarized in
"Round-1 correction evidence" below, full text in the frontmatter. This
round's correction is recorded in this same Gate-3 packet (not a new
gate) because no Owner Gate-3 approval/defer decision had yet been
rendered against `bd3c0910` — the packet returns to `awaiting_owner` for
a fresh Gate-3 decision against the corrected head below, per the Owner's
own explicit instruction. This record is preserved permanently and must
not be removed by any future revision.

## Round-1 correction evidence

Each item below maps directly to the Owner's numbered corrections (full
text in `decision_provenance.owner_response_reference`).

1. **PermissionController EventBus mutation-then-500.** `store()`'s
   `rbac.permission.created` event was missing validator-required
   `entityId`/`projectId` and used `$request->get('user_id')` (client-
   suppliable) for `actorId`. Fixed: `entityId`/`projectId` present
   (`projectId` = `'system'`, permissions have no real project concept);
   `actorId` = `(string) ($request->user()?->id ?? 'system')`. Audited
   every other live `PermissionController` `EventBus::publish()` call
   (`update()`, `destroy()`) — both had the same client-suppliable-actorId
   defect, fixed identically.
2. **Permission Matrix live routes.** Root cause found on inspection:
   `RBACServiceProvider` resolved `PermissionMatrixService` with
   `new PermissionMatrixService()` — zero args — against a constructor
   requiring `EventBus $eventBus`, an `ArgumentCountError` on every
   resolution, making `permission-matrix/export`/`import`/`validate`/
   `template` completely unreachable regardless of any EventBus-payload
   fix. Fixed the binding. Then fixed: `rbac.permission.matrix.exported`/
   `imported` (4-segment, invalid) → `rbac.permissionMatrix.exported`/
   `imported` (valid, with `entityId`/`projectId`); `rbac.role.permissions.imported`
   (4-segment, invalid, fires mid-loop AFTER each role's `sync()` mutation)
   → `rbac.role.permissionsImported`; the `FIELD(scope, 'system', 'custom', 'project')`
   MySQL-only ordering clause (breaks on SQLite, where this exact code
   path is also exercised by tests) → portable PHP `sortBy()`; `actorId`
   parameter widened from `int` (silently truncating any real ULID actor
   id to 0) to `string`. No EventBus surface outside this exact execution
   path was touched.
3. **Tenant role scope escalation.** `RoleController::update()` now
   rejects `scope=system` in the same way `store()` already did, closing
   the indirect "create as custom, then PUT scope=system" path.
   Defense-in-depth: `RBACManager::assignSystemRole()`'s role lookup now
   requires `whereNull('tenant_id')` in addition to `scope=system`, and a
   dedicated `isGenuineSystemRole()` check gates the actual assignment —
   a service-level test manufactures a malformed row (`scope=system`,
   non-null `tenant_id`, unreachable via the fixed API surface) and proves
   `assignSystemRole()` returns `false` with zero `system_user_roles`
   rows written; a control test proves a genuine global role (`tenant_id
   IS NULL`) still assigns successfully.
4. **`revokeRole()`/project DELETE incomplete identity validation.** Added
   `revokeRoleIdentitiesValid()`, mirroring the `assign*Role()` methods'
   exact per-scope checks (system: genuine global role; custom: role
   scope=custom AND role tenant_id = caller tenant; project: role
   scope=project AND role tenant_id = caller tenant AND project tenant_id
   = caller tenant), called before any DELETE. Discriminating tests seed a
   `project_user_roles` row directly for (a) a tenant-A user + tenant-B
   project + tenant-A-labeled role and (b) a tenant-A user + tenant-A
   project + tenant-B-owned role, then call the live DELETE route as
   tenant A — the row survives untouched in both cases; a control test
   proves a genuinely own-tenant row still deletes successfully.
5. **Cross-tenant effective-permissions/check-permission disclosure.**
   Both `RBACController` routes now call a new
   `targetsAreTenantScoped()` check (target user must belong to the
   caller's tenant; if `project_id` is supplied, it must too) before
   calling `RBACManager`, returning a generic non-disclosing 404
   otherwise. Discriminating tests prove both routes fail closed for
   another tenant's user id and for another tenant's project id; a
   control test proves the caller's own tenant/user/project still
   succeeds (200).
6. **Truthful EventBus audit identity.** Every occurrence of
   `'actorId' => $tenantId` across `RBACManager`'s 4 assignment/revoke
   event publishes replaced with `AuthHelper::idOrSystem()` (this
   repository's existing established convention for "real acting user, or
   `'system'` if none," already used elsewhere in the codebase — e.g.
   `InteractionLogService`). Every occurrence of `'projectId' =>
   $tenantId` (or a tenant-id fallback) for a non-project RBAC event
   (system/custom-scope assignment/revoke events; `RoleController`'s
   created/updated/deleted/permissionsSynced events;
   `RBACController::bulkAssignRoles()`'s event) replaced with the literal
   `'system'` convention — tenant id is never mislabelled as an actor or
   a project again anywhere this Work ID touches. Project-scope events
   (`assignProjectRole`, project-scope `revokeRole`) correctly keep the
   real project id, unchanged. A static discriminating test asserts the
   literal defect string `'actorId' => $tenantId` no longer appears
   anywhere in `RBACManager.php`.
7. **Migration silent-success guard.** `custom_user_roles`'s
   `if (Schema::hasTable('custom_user_roles')) { return; }` removed —
   `Schema::create()` now throws (fails closed) if an unexpected
   conflicting table already exists, rather than silently marking the
   migration "applied" over an unverified schema. Confirmed on real MySQL
   8.0: `migrate:fresh` still succeeds cleanly (no pre-existing table),
   `custom_user_roles` created with the exact approved §2b shape.

## Correction 8 — Gate-3 evidence truthfulness, applied

- **(A) Corrected claim:** this packet does NOT claim "all 20 acceptance
  items have a dedicated passing test in the new test file." Truthful
  statement: all 20 approved Gate-2 acceptance items (§10) have evidence.
  `tests/Feature/GAP042RbacProductionFidelityTest.php` contains 18
  automated PHP test methods (one item — 16 — is covered by assertions
  embedded across items 10-15's own tests, per the design's own item-16
  wording "covered across items 10-15," not a separate test method).
  Items 1, 2, and 9 additionally rely on genuine MySQL 8.0 procedural
  evidence (disposable container, `migrate:fresh`, direct
  `Schema::hasTable()`/`getColumnListing()` inspection — see "Real MySQL
  8.0 evidence" below), not solely a PHP test assertion.
  `tests/Feature/GAP042Gate3Round1CorrectionsTest.php` (this round) adds
  20 further automated test methods for corrections 1-7, none double-
  counted against the original 20-item matrix.
- **(B) Corrected claim, item 19:** the immediately-prior packet described
  item 19 as "sabotage/revert-verified" based on removing the explicit
  closure from `Role::scopeTenantVisible()` — but that specific sabotage
  attempt did NOT fail (Eloquent's `Builder::callScope()` auto-groups any
  `where`s added during a local-scope call, so the test passed regardless
  of whether the scope body used an explicit closure). Describing that
  as a completed sabotage-verification was not accurate — the mechanism
  is real and worth documenting, but it does not itself constitute
  discriminating RED evidence. **This round's genuinely discriminating
  sabotage:** `RoleController::show()`'s `Role::tenantVisible($tenantId)`
  call was temporarily replaced with the literal raw ungrouped
  `Role::whereNull('tenant_id')->orWhere('tenant_id', $tenantId)->with(...)->whereKey($id)->first()`
  form the Owner specified — bypassing the Eloquent-scope auto-grouping
  protection entirely by not going through a scope at all. Re-running
  item 19's test (`test_grouped_tenant_visibility_predicate_discriminates`)
  against this sabotage: **FAILED** — `GET /roles/{tenantB_role_id}` as
  tenant A returned HTTP 200 (leaking a *different*, global role's data)
  instead of the expected 404, exactly the OR-precedence leakage
  mechanism the design describes (the ungrouped `tenant_id IS NULL`
  branch matches unconditionally regardless of the `id` filter, so
  `first()` returns the first global row in the table rather than
  correctly finding nothing for the requested id). Sabotage reverted;
  test re-confirmed GREEN. This is now genuine, discriminating RED→GREEN
  evidence for item 19, not merely a structural observation.

## Owner Decision History — Round 2 — CHANGES REQUESTED (permanent record, never erased)

**Owner Gate-3 Round 2 decision: CHANGES REQUESTED.** Reviewed the
Round-1-corrected implementation subject
`feaa320b98b31380c2cac7e74ce872616b63eca5` (implementation-tree digest
`8512e0a5e22f0712d56bac6bf3e1a94f0c02ba8e2aa5356c0041638b62733295`) and
Gate-3 record/PR head `6b4ef1535456925748928b38e47af6b470c4eeef` against
canonical main `673855f69a3633b64c378e965ae409ed3a098c50`. Full verbatim
directive preserved in this file's frontmatter
`decision_provenance.owner_response_reference` above (current value —
the Round-1 verbatim directive remains preserved in git history at the
`6b4ef153` packet revision and is summarized, unaltered, in the
"Owner Decision History — Round 1" section above). The major Round-1
corrections were explicitly accepted as directionally correct and not
reopened. Three narrowly-scoped corrections were required (Corrections
10-12) — summarized in "Round-2 correction evidence" below, full text in
the frontmatter. This round's correction is recorded in this same Gate-3
packet (not a new gate) for the same reason as Round 1: no Owner Gate-3
approval/defer decision had yet been rendered against the reviewed head —
the packet returns to `awaiting_owner` for a fresh Gate-3 decision against
the corrected head below. This record is preserved permanently and must
not be removed by any future revision.

## Round-2 correction evidence

Each item below maps directly to the Owner's numbered Round-2 corrections
(full text in `decision_provenance.owner_response_reference`). New
discriminating tests for all three live in
`tests/Feature/GAP042Gate3Round2CorrectionsTest.php` (7 test methods).

10. **Permission Matrix EventBus projectId semantics.** Round-1
    Correction 6 fixed `actorId` truthfulness but left three Permission
    Matrix event publishes using
    `'projectId' => (string) ($tenantId ?? 'system')` — mislabelling the
    caller's tenant id as a projectId for events that have no project
    concept: `PermissionMatrixController::export()`,
    `PermissionMatrixController::import()`, and
    `PermissionMatrixService::importFromCSV()`'s per-role
    `rbac.role.permissionsImported` event. Fixed all three to the literal
    `'projectId' => 'system'` convention already established elsewhere in
    this Work ID for non-project RBAC events. RED evidence: two tests
    subscribe an `EventBus::subscribe('*', ...)` wildcard listener and
    capture the actual emitted payload for a live `export`/`import` HTTP
    call — before the fix, `payload['projectId']` equalled the caller's
    tenant id; after the fix it equals the literal string `'system'` and
    is asserted `assertNotSame($tenantId, ...)`. A third test statically
    asserts neither the exact defect pattern
    `'projectId' => (string) ($tenantId ?? 'system')` nor the bare
    `'projectId' => $tenantId` pattern remains in either file. All three
    tests failed on the pre-fix subject and pass after.
11. **Permission Matrix import permission-creation ordering.**
    `PermissionMatrixService::importFromCSV()` called
    `Permission::firstOrCreate()` while parsing each CSV row, before the
    per-role loop resolved whether the target role is visible/owned/
    global-read-only — an import aimed at an inaccessible role could still
    manufacture a new global `permissions` row via the
    `rbac:permission.import`-only route, a privilege the route does not
    grant (`rbac:permission.create` is the separate, dedicated gate for
    that). Restructured to a two-pass parse-then-validate-then-write flow:
    the first pass only parses/validates CSV rows and queues
    `{code, module, action}` per role name — no database write. The
    second pass resolves each role (existing visibility/ownership
    lookup, unchanged) and only then, for a role that has passed the
    existing global-read-only check, calls `Permission::firstOrCreate()`
    for that role's queued codes before `sync()`. RED evidence, using
    permission codes guaranteed not to pre-exist
    (`gap042.round2crosstenantnew`, `gap042.round2globalreadonlynew`):
    (A) a tenant-A caller importing against a genuine tenant-B-owned
    role — after the request, `permissions` does NOT contain the new
    code and zero `role_permissions` rows exist for that role (failed
    pre-fix: the code was being created before role resolution ever ran).
    (B) the same proof for a genuine global/system role. Control:
    `test_correction11_control_own_tenant_role_new_permission_still_created_and_synced`
    proves a genuinely-owned tenant role importing a new valid code still
    creates the permission and syncs it — unaffected by the reordering.
12. **check-permission × cross-tenant project test-coverage gap.** The
    implementation already fails closed for this exact case — both
    `getUserEffectivePermissions()` and `checkUserPermission()` share the
    same `targetsAreTenantScoped()` helper, which already validates
    `project_id` (added under Round-1 Correction 5) — but no test
    exercised `checkUserPermission()` with a cross-tenant `project_id`
    specifically (only cross-tenant user was exercised for that route).
    Added `test_correction12_check_permission_cross_tenant_project_fails_closed`:
    tenant A's own user, tenant B's project, `POST .../check-permission`
    — asserts non-200 and specifically `404` with no `has_permission` key
    in the response body. This is a coverage completion, not a code fix;
    it passed immediately because the shared helper already covered it,
    confirming the packet's prior claim was accurate for this exact case
    once the discriminator is actually present.

## Implementation baseline and PR

- **Implementation baseline (Gate-2 merge, confirmed zero drift at session
  start):** `origin/main @ 673855f69a3633b64c378e965ae409ed3a098c50`.
- **Implementation branch:** `fix/GAP-042-rbac-production-fidelity`.
- **Implementation PR (Draft, unmerged):** [#299](https://github.com/kha997/zenamanagephp/pull/299).
- **subject_sha (Round-2 correction, current; supersedes
  `feaa320b98b31380c2cac7e74ce872616b63eca5`, which itself superseded
  `b04089268bdfe725d8d51db2f628fbfd99c8bd49`):**
  `13e9e64df3c9ceba29dd191494df8a4ee757b1f5`.
- **Implementation plan:** `docs/superpowers/plans/2026-09-02-gap-042-rbac-production-fidelity-implementation.md`.

## What changed (`feaa320b` → `13e9e64d`, Round-2 corrections only)

```
 src/RBAC/Controllers/PermissionMatrixController.php |  9 ++--  (Correction 10)
 src/RBAC/Services/PermissionMatrixService.php        | 82 ++++++++++++++-------  (Corrections 10, 11)
 tests/Feature/GAP042Gate3Round2CorrectionsTest.php   | 246 + (new, 7 tests)
 3 files changed, 267 insertions(+), 29 deletions(-)
```

Every changed file is inside the already-approved GAP-042 boundary and,
more narrowly, inside the exact Permission Matrix execution path the
Owner named this round. No `.github/workflows/**` file touched. No new
public route added. No file outside `PermissionMatrixController.php`,
`PermissionMatrixService.php`, and the one new test file was modified.

## Scope discipline confirmed

Per the Owner's explicit Round-1 AND Round-2 "do not reopen" lists, none
of the following was reopened or touched in either round: Option A
canonical-table convergence; the `custom_user_roles` 3-layer decision;
the global-role read-only policy; route-parameter-authoritative identity
(§2e); the three restored project-assignment routes; the unwired
`assignSystem`/`assignCustom`/`assignProject`/`getEffectivePermissions`
decision (§2d); the exclusion of `AssignmentController::getUserRoles()`;
the exclusion of `CompensationController`/`Src\RBAC\Middleware\RBACMiddleware`;
the `RolePermission`-not-required decision (§15); `roles.name` global
uniqueness; GAP-041/GAP-045; production deployment configuration;
tenant role visibility semantics; assignment tenant checks; scope=system
escalation protection; genuine-global `assignSystemRole` check; revoke
identity validation; the effective/check-permission tenant-target helper
itself (only its test coverage was completed, not its logic); the route
identity decision; the unwired-methods decision.

## Completed behavior matrix vs approved Gate 2 (all 20 acceptance items, §10)

Unchanged from the prior packet's mapping — Round-1 corrections did not
alter which test proves which item, only fixed defects the corrected
items' own tests (and the new Round-1 correction tests) now catch. See
the prior packet's table (preserved in git history at `bd3c0910`) for the
full 20-row mapping; item 19's evidence is corrected above (Correction 8B).

## Real MySQL 8.0 evidence (Round-1 corrected head — unchanged, preserved)

Fresh disposable `mysql:8.0` Docker container (port 33062),
`migrate:fresh --force` against real migrations only (235 migrations,
including the Correction-7-fixed `custom_user_roles` migration, which
still succeeds cleanly with the silent-success guard removed since no
conflicting table exists):

- Table inventory: `roles`, `permissions`, `role_permissions`,
  `user_roles`, `system_user_roles`, `project_user_roles`,
  `custom_user_roles` — all `PRESENT`; `zena_roles` — `ABSENT`.
- `GAP042RbacProductionFidelityTest` (18) + `RbacApiTest` (9) +
  `GAP042Gate3Round1CorrectionsTest` (20) = **47/47 pass** against
  `phpunit.mysql.xml` with `DB_CONNECTION=mysql`/`ZENA_INVARIANTS_DB=mysql`.
- Container torn down after evidence capture (disposable, per repo
  convention).

## Real MySQL 8.0 evidence (Round-2 corrected head)

Fresh disposable `mysql:8.0` Docker container (port 33063, distinct from
the Round-1 container to avoid any collision with a concurrently-running
session), `migrate:fresh --force` against real migrations only — same
migration set as Round-1 (Round-2 added no new migration; the last
migration to run is still the Correction-7-fixed
`2026_09_02_000000_create_custom_user_roles_table`), completed cleanly
with zero errors:

- Table inventory re-confirmed: `roles`, `permissions`,
  `role_permissions`, `user_roles`, `system_user_roles`,
  `project_user_roles`, `custom_user_roles` — all `PRESENT`;
  `zena_roles` and `zena_permissions` — both `ABSENT`
  (`SHOW TABLES LIKE 'zena_roles'` / `'zena_permissions'` both empty).
- `GAP042RbacProductionFidelityTest` (18) + `GAP042Gate3Round1CorrectionsTest`
  (20) + `GAP042Gate3Round2CorrectionsTest` (7) + `RbacApiTest` (9) =
  **54/54 pass** against `phpunit.mysql.xml` with
  `DB_CONNECTION=mysql`/`ZENA_INVARIANTS_DB=mysql` (env vars set inline,
  `DB_HOST=127.0.0.1 DB_PORT=33063 DB_DATABASE=zenamanage_test
  DB_USERNAME=root DB_PASSWORD=root`).
- Container torn down after evidence capture (disposable, per repo
  convention).

## Regression results (Round-2 corrected head)

- **Full SQLite regression** (`vendor/bin/phpunit --exclude-group=stress,performance,browser`):
  **2446 tests** (2439 Round-1 baseline + 7 new Round-2 correction tests),
  17330 assertions, **7 failures — all pre-existing, unrelated,
  independently documented** (`Tests\Feature\Dashboard\DashboardApiTest`'s
  7 widget-customization tests, `Call to undefined method
  Illuminate\Cache\RedisStore::publish()`, zero RBAC/tenant files
  involved; this exact failure set is documented as pre-existing in
  `docs/owner-decisions/GAP-048/03-release.md`'s own regression section,
  merged to `main` before this PR's baseline — identical failure count
  and identical named tests as the Round-1 packet's own regression run,
  confirming Round-2 introduced zero new regressions). **0 GAP-042
  regressions** introduced by the Round-2 corrections.
- **Targeted focused regression**
  (`GAP042RbacProductionFidelityTest` + `GAP042Gate3Round1CorrectionsTest`
  + `GAP042Gate3Round2CorrectionsTest` + `RbacApiTest`): 54 tests, 282
  assertions, 0 failures, both on SQLite and on real MySQL 8.0 (see
  above).

## Route inventory check (unchanged, no unauthorized new public surface)

`php artisan route:list --path=rbac` at `13e9e64d`: 29 routes, byte-
identical count/paths to the Round-1 inventory (Round-2 corrections only
changed internal EventBus payload construction and CSV-import write
ordering — no route registration was added, removed, or renamed).

## Static analysis / PHPStan

Same documented, pre-existing local environment limitation as the prior
packet (this worktree's ad hoc vendor copy+symlink construction produces
a duplicate-autoloader-class fatal error when invoking `vendor/bin/phpstan`
directly, and `composer dump-autoload` was needed just to make
`vendor/bin/phpunit` resolve `Tests\TestCase` in this worktree — see the
accompanying session report). Relies on the live `Code Quality Analysis`
CI check — see "CI status" below.

## CI status (exact head `13e9e64d`)

To be finalized against this exact head before this packet is treated as
truthfully `awaiting_owner`, per Correction 9's explicit sequencing
requirement (still binding this round) — see the accompanying session
report for the live, exact-head CI snapshot, including explicit
confirmation that `Owner Governance Lint` is `SUCCESS` on this exact head
(not merely a prior head's rerun).

## Known limitations, disclosed honestly

Unchanged from the prior packet — see `assignSystem`/`assignCustom`/
`assignProject`/`getEffectivePermissions` (§2d, deliberately unwired,
untouched); `AssignmentController::getUserRoles()` and the
`CompensationController`/`RBACMiddleware` constructor-wiring defect
(both explicitly excluded, untouched); `Src\RBAC\Models\RolePermission`
(not reactivated, standard `belongsToMany()` used throughout).

## What this packet does NOT authorize

This Gate-3 packet does not authorize Ready-for-review, merge, release, or
production deployment. Those remain separate, explicit Owner decisions to
be issued after Owner reviews this packet. The implementation PR (#299)
remains Draft and unmerged.

## What the owner is NOT being asked to decide

Owner is not being asked to inspect CI logs, source diffs, or review
comments line-by-line — only whether the demonstrated behavior (the
completed 20-item acceptance matrix, the 9 Round-1 corrections' RED→GREEN
evidence, real-MySQL proof, and residual risk) is acceptable to move
toward release.
