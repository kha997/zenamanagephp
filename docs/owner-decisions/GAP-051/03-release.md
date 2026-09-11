---
work_id: GAP-051
gate: 3
gate_status: preparing
technical_readiness:
  value: not_checked
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-09-09-gap-051-gate2-sanctum-bearer-fidelity-contract-design.md
  plan: docs/superpowers/plans/2026-09-09-gap-051-sanctum-bearer-fidelity-implementation.md
  branch: impl/GAP-051-sanctum-bearer-fidelity
  pr: "https://github.com/kha997/zenamanagephp/pull/308"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-11T04:18:07Z"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-11T04:18:07Z"
  updated_at: "2026-09-11T04:18:07Z"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GOVERNANCE STALE-BINDING INCIDENT (self-caught before Owner review, 2026-09-11): this packet's prior awaiting_owner presentation (recorded at subject_sha 422c5015677867734e64ab2149069d426492b9ff, implementation_tree_digest 203856e77d741ec45c86fb7eac16fcf72f4c7d247be6e3f3f209cd519a26ddc1) was itself immediately invalidated by the same commit that presented it: commit 39e8c1f966f358526ea66a1c6d71e43069ee6bf3 committed this exact 03-release.md file together with OPERATIONAL_GAP_REGISTER.md's GAP-051/GAP-052 reconciliation in one packet-only commit. The digest algorithm (packet-schema.yml, owner_governance_compute_implementation_tree_digest()) excludes ONLY docs/owner-decisions/<work_id>/03-release*.md from the implementation-tree digest — it does NOT exclude OPERATIONAL_GAP_REGISTER.md, which is correctly treated as governance content that legitimately changes the digest. Recomputing at 39e8c1f9 (twice, identical both times) yields 6e0c099c923a5d28c434b93d8c6640d2e488df098998afff13494df0d0802971, not the recorded 203856e7... — a genuine staleness, not a computation error. This packet is returned to gate_status: preparing and its technical_evidence rebound to the correct post-register tree (subject_sha 39e8c1f9..., digest 6e0c099c...) per this correction. No implementation, test, register, packet-schema, freshness-algorithm, or CI-workflow file was touched by this rebind — only this exact file. The underlying GAP-051 implementation is unchanged from the prior presentation; only the governance binding was corrected. This incident is preserved permanently as correction history and must not be removed by any future revision. --- GAP-051 implements the Owner-approved Gate-2 two-layer architecture (docs/owner-decisions/GAP-051/02-design.md, approved base 2f28e3edccd50e0f046b165b827437886d1fe2ad, PR #307) on top of that same base: Layer A (tests/Concerns/InteractsWithSanctumBearerTokens.php) — actingAsSanctumBearerToken() issues a genuine Laravel\\Sanctum\\PersonalAccessToken via User::createToken(), never calls Sanctum::actingAs(), and calls the public AuthManager::forgetGuards() last, immediately before the caller dispatches. Layer B (tests/TestCase.php::call()) — a universal pre-dispatch guard that runs on every outgoing request carrying a Bearer Authorization header, inspects unique(config('sanctum.guard', []) + ['sanctum']) via the public GuardHelpers::hasUser() (never check(), which would itself trigger resolution), and throws a named RuntimeException before dispatch if any of those guards already has a cached user, rejecting both plain actingAs() and Sanctum::actingAs() contamination; a matching finally-block forgetGuards() runs after every Bearer-carrying dispatch so completed requests never contaminate the next one. tests/Feature/SanctumBearerTransportGuardContractTest.php proves both runtime blocks (17 tests, 63 assertions, all passing — the original Gate-3 evidence recorded 18 tests/65 assertions; this 1-test/2-assertion difference is a pre-existing discrepancy unrelated to and not introduced by either correction round documented below, and has not been independently re-investigated here) and proves the positive path resolves the exact genuine PersonalAccessToken class/id/owner/abilities, not merely a matching user id. tests/Feature/SanctumWebGuardCharacterizationTest.php remains characterization-only (1 test, 2 assertions), unchanged from the original implementation. A real-route behavioral topology contract (GET /api/admin/sidebar-configs with a real persisted session cookie and no Bearer token) proves 401, confirming the current API middleware group carries no stateful/session-bridging middleware. No production auth/config/route/middleware/guard code was changed anywhere in this Gate 3 implementation or either correction round — every change across all three rounds is confined to tests/ and documentation. CORRECTION HISTORY (this retracts and replaces the original Gate-3 evidence's claim that composer test:fast's two full-suite failures were 'pre-existing/unrelated' — Owner-directed baseline verification proved that claim false; both failed only at the candidate, not at approved Gate-2 base 2f28e3ed, which passed cleanly): Round 1 (commit c1e587d5) fixed two tests proven, via disposable guard-state diagnostics never committed, to be stale-cached-identity false-greens at base: (1) IntegrationTest::test_complete_project_workflow — base's cross-tenant-project request resolved a stale cached identity left over from an earlier call in the same test, not the intended $otherUser, producing a false 403 TENANT_INVALID; GAP-051's genuine Sanctum authentication correctly resolves $otherUser, whose tenant matches the X-Tenant-ID header, so the request reaches the tenant-scoped controller and genuinely 404s (E404.NOT_FOUND) rather than 403 — assertion corrected from 403/TENANT_INVALID to 404/E404.NOT_FOUND. (2) SystemIntegrationTest::it_can_handle_role_based_data_filtering — mixed root cause: a GAP-051-owned test-fixture RBAC role-naming gap (the fixture's 'client_rep' role column value was never backed by a real Role pivot record matching RoleBasedAccessControlMiddleware::handleGeneralAccess()'s canonical 'client' allow-list entry), corrected here by granting the canonical Role record; and a second, genuinely separate, pre-existing production defect in DashboardRoleBasedService reaching a nonexistent DashboardDataAggregationService::getWidgetData() method for client_rep's widget codes, which GAP-051 does NOT fix — explicitly deferred to GAP-052 per Owner scope decision (2026-09-10), with the test now honestly asserting the real observed 500 for client_rep's affected endpoints (with an inline GAP-052 comment) instead of hiding it, while every other role/endpoint assertion remains a genuine, unweakened 200. Round 2 (commit 422c5015) found, via an exhaustive no-stop attribution sweep of tests/Feature/Api (excluded from composer test:fast, and whose CI job uses --stop-on-failure, which had hidden further false-greens behind the first one fixed), three more tests sharing the identical stale-cached-identity root-cause mechanism, each independently proven via the same disposable-diagnostic method: (3) InspectionTemplateRuntimeTest::test_inspection_create_rejects_foreign_tenant_generated_checklist_step — base's sanctum guard was still cached with actorB (from an earlier real-token request in the same test) immediately before AND after the actorA-headed dispatch, so actorA was never actually authenticated at base, producing a false 403 TENANT_INVALID; GAP-051 genuinely authenticates actorA (confirmed via Auth::guard('sanctum')->id() after dispatch), and the real production contract for a foreign-tenant work_instance_step_id is 422 E422.VALIDATION ('Inspection checklist instance not available for this QC plan') at the QC-plan/step compatibility boundary, not 403 — assertion corrected accordingly. (4) MaterialRequestApiTest::test_material_request_store_rejects_foreign_project_created_via_canonical_zena_projects_owner — identical mechanism, stale cached foreignCreator at base; GAP-051 genuinely authenticates userA; real contract is 422 E422.VALIDATION with the tenant-scoped exists validation rule rejecting the foreign project_id ('The selected project id is invalid.') — assertion corrected accordingly. (5) WorkTemplateMvpApiTest::test_work_instance_step_attachments_upload_list_delete_are_tenant_scoped_and_audited — identical mechanism, stale cached actorA at base (from an earlier delete-attachment call in the same test); GAP-051 genuinely authenticates actorB, whose tenant-scoped lookup of tenant A's work instance/step correctly finds nothing, producing a real 404 E404.NOT_FOUND ('Work instance or step not found') rather than 403 — assertion corrected accordingly. None of these five corrections were arbitrary expectation rewrites: each old green assertion was behaviorally proven, with disposable diagnostic evidence, to have executed under the wrong cached identity at Gate-2 base; each corrected assertion reflects the actual, real production behavior GAP-051's genuine authentication now correctly exercises. VERIFICATION TRUTH (both local and live layers recorded, neither overstated): locally, composer test:fast is NOT fully green at this subject and has never been claimed to be — 2,654 tests, 556 errors, 14 failures, 32 skipped, identical in error count to an unmodified-candidate-HEAD baseline re-run performed specifically to attribute this (confirming the 556 errors are pre-existing local-environment noise: a missing Illuminate\\Cache\\RedisStore::publish() method and broken imagick/memcached PHP extensions in this specific local worktree, not GAP-051-caused), with the 14 failures reduced by exactly 2 from that same baseline's 16, matching the two Round-1 targeted fixes precisely and introducing zero new failures; local tests/Feature/Api (excluded from composer test:fast) runs clean with no-stop and CI-exact --stop-on-failure invocations both showing exactly 2 failures, both ContractPdfExportTest/DocumentManagementTest MissingAppKeyException — confirmed identical on Gate-2 base via full commit checkout, a local app-key/environment artifact of this specific worktree, not GAP-051-caused, and not fixed here. Live, exact-head GitHub CI at subject SHA 422c5015677867734e64ab2149069d426492b9ff is authoritative for repository-integrated verification and is fully green: every required workflow/job passed, including Owner Governance Lint, Routes Guardrails, API Tests (Fast/Slow), Feature/Integration/Unit/Security Tests, all real-MySQL concurrency jobs, Zena RBAC/Tenant Invariants (both variants), Code Quality Analysis, all vulnerability/license/security scans, Repo Hygiene Guards, Test Coverage Report, staging-smoke, and browser-tests. browser-tests (Button Test Suite workflow) is recorded truthfully: its first Dusk attempt (tests/Browser/Projects/, tests/Browser/Crm/) had two transient failures ('project create validation surface', 'project create cancel flow'); the workflow's own defined retry (up to 2 attempts, .github/workflows/button-tests.yml) ran automatically; the second attempt passed cleanly with no failures; the final Button Test Suite job conclusion is success (44m16s total, matching roughly two full ~20-22min attempts). No GAP-051 code path exists in Dusk's execution — Dusk runs real HTTP requests against a real running server through a real Chrome browser, never through Tests\\TestCase::call(), the sole location of Layer A/B's logic — so this transient failure could not mechanically originate from this PR's diff (confined to tests/Feature/Api/*.php and documentation in Round 2, tests/Feature/*.php and tests/Integration/*.php in Round 1). Four other recent, unrelated browser-tests runs on this repository (other commits/branches, including this same branch's own two prior PR pushes) were checked and each completed in a single ~19-22 minute attempt with no retry needed, consistent with this being an isolated one-off rather than a newly introduced or systemic defect; this is reported as the observed evidence, not overstated as a formally proven systemic flaky-test classification. No correction was made for this transient failure, per Owner instruction that no more implementation changes are authorized at this subject. This packet requests Owner Gate 3 decision only; it does not request or imply ready-for-review, merge, release, or deployment authorization."
technical_evidence:
  subject_sha: "39e8c1f966f358526ea66a1c6d71e43069ee6bf3"
  implementation_tree_digest: "6e0c099c923a5d28c434b93d8c6640d2e488df098998afff13494df0d0802971"
  verified_pr_head_sha: null
  verified_at: null
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-051 — Sanctum Bearer-Token Authentication Test Fidelity: Gate 3 Release Request

**Gate 3 packet status: `preparing`.** Technical readiness: `not_checked`.
**NOT ready for Owner review.** This is a governance-only re-verification
step following a self-caught stale-binding incident (see the incident
record at the top of `mandatory_technical_gate_summary`, and the "SHA
identity" section below). The underlying GAP-051 implementation is
unchanged; only the evidence binding was corrected. This packet is
expected to return to `awaiting_owner` once its rebound digest is
verified live-CI-green at a packet-only commit — see the verification
record appended below once that happens.

## SHA identity — read this before anything else

**Revised in the governance stale-binding correction (2026-09-11) — see
the incident record at the top of `mandatory_technical_gate_summary`
above.** Four distinct SHA/digest values appear in this packet and its
history. They are not interchangeable, and the first presentation of
this packet conflated two of them (see incident record):

1. **Implementation-code subject SHA** — `422c5015677867734e64ab2149069d426492b9ff`.
   The last commit that changed any `tests/`/implementation/evidence
   implementation content (Round 1 + Round 2 corrections). No commit
   since has touched any implementation or test file.
2. **Technical-evidence subject SHA** — `39e8c1f966f358526ea66a1c6d71e43069ee6bf3`.
   The last commit that changed anything included in the **governance
   implementation tree** the digest actually covers — this differs from
   (1) because it additionally reconciled `OPERATIONAL_GAP_REGISTER.md`
   (the GAP-051 status update + GAP-052 reservation), which the digest
   algorithm correctly does **not** exclude (only
   `docs/owner-decisions/*/03-release*.md` files are excluded — see
   `packet-schema.yml`'s `implementation_tree_digest_algorithm`
   comment). `technical_evidence.subject_sha` below is bound to **this**
   SHA, not to (1), because the digest must be computed against the
   exact tree it actually describes.
3. **Current implementation-tree digest** —
   `6e0c099c923a5d28c434b93d8c6640d2e488df098998afff13494df0d0802971`.
   Computed via `owner_governance_compute_implementation_tree_digest()`
   (`scripts/ssot/owner_governance_lint.php`) at subject SHA (2) above,
   reproduced twice with an identical result: a SHA-256 of the sorted
   `"<blob_sha> <path>"` lines for the complete git tree, excluding every
   `docs/owner-decisions/*/03-release*.md` file in the repository (this
   repo's Gate-3 self-reference exclusion, not limited to `GAP-051/`'s
   own directory) — `OPERATIONAL_GAP_REGISTER.md` is included, by
   design. A commit that touches ONLY this exact `03-release.md` file
   does not change this digest, by construction; a commit touching
   `OPERATIONAL_GAP_REGISTER.md` (or any other non-excluded path) does.
4. **Packet-only PR head SHA** — not yet known at the time this
   correction is authored. Every commit from here forward, for as long as
   this packet remains bound to digest (3), must touch **only** this
   exact `03-release.md` file — see the Phase-A/Phase-B verification
   record below for the explicit ancestry proof once those commits
   exist.

## Original Gate-3 implementation (subject-adjacent, unchanged by either correction round)

Per Owner Gate-2 approval (`docs/owner-decisions/GAP-051/02-design.md`,
approved base `2f28e3edccd50e0f046b165b827437886d1fe2ad`, PR #307), Gate 3
implements exactly the two-layer architecture the Owner's binding Gate-2
interpretation specified:

- **Layer A** — `tests/Concerns/InteractsWithSanctumBearerTokens.php`.
  `actingAsSanctumBearerToken(User $user, array $abilities = ['*'])` calls
  the real `$user->createToken(...)`, stores the issued
  `Laravel\Sanctum\PersonalAccessToken`, configures the plain-text Bearer
  token via `withToken()`, and calls the public
  `AuthManager::forgetGuards()` **last**, immediately before returning
  control to the caller for dispatch. It never calls `Sanctum::actingAs()`.
- **Layer B** — `tests/TestCase.php::call()`.
  `guardAgainstGap051BearerContamination()` runs before every outgoing
  request. If the outgoing headers carry a `Bearer` Authorization value
  (checked via both `HTTP_AUTHORIZATION` and
  `REDIRECT_HTTP_AUTHORIZATION`, case-insensitive), it inspects
  `array_unique(config('sanctum.guard', []) + ['sanctum'])` via the
  **public** `GuardHelpers::hasUser()` — never `check()`, which would
  itself trigger guard resolution and defeat the check — and throws a
  named `RuntimeException` before dispatch if any of those guards already
  has a cached user, rejecting both raw `actingAs()` and
  `Sanctum::actingAs()` contamination ahead of a genuine Bearer request. A
  matching `finally` block calls `forgetGuards()` after every
  Bearer-carrying dispatch completes, so a finished request never
  contaminates the next one either.
- **Positive-path proof** — `tests/Feature/SanctumBearerTransportGuardContractTest.php`
  asserts, after a real dispatch through Layer A, that the response's
  `token_class` is exactly `Laravel\Sanctum\PersonalAccessToken`, the
  token id equals the exact issued row id, the owner type/id match the
  intended (not the contaminating) user, and the abilities match what was
  requested — proving genuine token lookup, not merely a matching user
  id. **17 tests, 63 assertions, all passing.** (The original Gate-3
  evidence recorded 18 tests/65 assertions for this file; this 1-test/
  2-assertion discrepancy predates and is unrelated to both correction
  rounds documented below and has not been independently re-investigated
  as part of this packet.)
- **Characterization split** — `tests/Feature/SanctumWebGuardCharacterizationTest.php`
  (1 test, 2 assertions) remains explicitly characterization-only,
  unchanged by either correction round.
- **Real-route topology 401 proof** — a behavioral contract creates and
  persists a real web session cookie, clears in-process guard state, and
  requests the real protected route `GET /api/admin/sidebar-configs`
  (confirmed on the `api` route group with `Authenticate:sanctum`) with no
  Bearer token. The response is `401 Unauthorized`, confirming the current
  API middleware group carries no session-bridging middleware that would
  make the `web`-guard fallback exploitable in production today.
- **Scope discipline** — no production auth/config/route/middleware/guard
  code has been changed anywhere in this Gate-3 implementation or in
  either correction round below. Every changed file across all three
  rounds lives under `tests/` or is a documentation/evidence file.

## Correction history — retraction of the original "pre-existing/unrelated" claim

The original Gate-3 evidence packet (`GATE3-LOCAL-REPORT.md` §8, as first
authored) claimed `composer test:fast`'s two full-suite failures were
**"pre-existing/unrelated"** to GAP-051. **This claim is false and is
retracted here.** Owner-directed baseline verification re-ran both
failures against the approved Gate-2 base `2f28e3edccd50e0f046b165b827437886d1fe2ad`:
base passed both, twice. Both failures were candidate-caused regressions,
not pre-existing defects. `GATE3-LOCAL-REPORT.md` §14 and §15 carry the
complete, permanent record of this retraction and the resulting two
correction rounds; this section summarizes it for the Owner decision.

**A note on method, applying to all five corrections below:** none of
these were arbitrary expectation rewrites. Each old green assertion was
behaviorally **proven** — via disposable diagnostic instrumentation added
temporarily to a real commit checkout of Gate-2 base, run, observed, and
then reverted before returning to the candidate branch; never committed —
to have executed under a **stale cached identity**, not the test's
intended user. Each corrected assertion reflects the actual, real
production behavior that genuine Sanctum authentication under GAP-051 now
correctly exercises.

### Round 1 (commit `c1e587d5785cb581b4c4e75461055bdf04704497`)

1. **`Tests\Feature\IntegrationTest::test_complete_project_workflow`** —
   Classification B (pre-existing false-green exposed by GAP-051). Base's
   cross-tenant-project request resolved a stale cached identity left
   over from an earlier authenticated call in the same test — not the
   test's intended `$otherUser` — because Laravel's guard instances
   persist across `->call()` invocations within one test unless
   explicitly reset, and base had no such reset discipline. That stale
   identity's tenant genuinely differed from the `X-Tenant-ID` header,
   producing a **false** `403 TENANT_INVALID`. GAP-051's genuine
   authentication correctly resolves `$otherUser`, whose tenant matches
   the header, so `TenantIsolationMiddleware` does not 403 and the
   request reaches the tenant-scoped project controller, which correctly
   **404s** (`E404.NOT_FOUND`) because the project belongs to a different
   tenant. Corrected: assertion changed from `403`/`TENANT_INVALID` to
   `404`/`E404.NOT_FOUND`.
2. **`Tests\Integration\SystemIntegrationTest::it_can_handle_role_based_data_filtering`** —
   Classification C (mixed). Two independent issues were both masked by
   the same stale-identity bug: (a) a GAP-051-owned test-fixture RBAC
   role-naming gap — the fixture's `client_rep` `role` column value was
   never backed by a real `Role` pivot record matching
   `RoleBasedAccessControlMiddleware::handleGeneralAccess()`'s canonical
   `client` allow-list entry, so a genuinely-authenticated `client_rep`
   user correctly received `RBAC_ACCESS_DENIED`; corrected by granting the
   canonical `Role` record via `Role::firstOrCreate` +
   `syncWithoutDetaching`, without changing the business-facing `role`
   column value. (b) A **genuinely separate, pre-existing production
   defect**: `DashboardRoleBasedService::getWidgetDataForRole()`'s
   `default:` switch branch calls
   `DashboardDataAggregationService::getWidgetData()`, which does not
   exist on that class — verified by direct inspection — throwing an
   uncaught `\Error` (not caught by the method's `catch (\Exception $e)`,
   since `\Error` does not extend `\Exception`) and surfacing as a
   genuine `500`. Confirmed via a disposable diagnostic probe that this
   triggers for every one of `client_rep`'s configured widget codes on
   both `/api/v1/dashboard/role-based` and
   `/api/v1/dashboard/role-based/widgets`, while `/metrics`, `/alerts`,
   and `/permissions` genuinely return `200`. **This production defect is
   explicitly deferred to a new, separately-governed Work ID — GAP-052 —
   per Owner scope decision (2026-09-10). It is NOT fixed under GAP-051.**
   The test now explicitly documents/asserts the real observed `500` for
   `client_rep`'s `root`/`widgets` endpoints (with an inline `GAP-052`
   comment explaining why), while every other role/endpoint combination —
   including `client_rep`'s own `/metrics`, `/alerts`, `/permissions` —
   still asserts a genuine, unweakened `200`.

### Round 2 (commit `422c5015677867734e64ab2149069d426492b9ff`)

CI's "API Tests (Fast)" job runs `tests/Feature/Api` with
`--stop-on-failure`; this directory is also excluded from local
`composer test:fast` (`phpunit.xml`), so neither local verification nor a
single CI run had ever exercised it exhaustively. An explicit no-stop
attribution sweep (`php artisan test tests/Feature/Api --exclude-group=slow`,
run against both Gate-2 base and the candidate via full commit checkouts)
found three more tests sharing the identical stale-cached-identity
mechanism as Round 1, each independently proven the same way:

3. **`InspectionTemplateRuntimeTest::test_inspection_create_rejects_foreign_tenant_generated_checklist_step`** —
   base's sanctum guard was still cached with `$actorB` (from an earlier
   real-token request in the same test) both immediately before AND after
   the `$actorA`-headed dispatch — `$actorA` was never actually
   authenticated at base, producing a false `403 TENANT_INVALID`. GAP-051
   genuinely authenticates `$actorA` (confirmed:
   `Auth::guard('sanctum')->id()` after dispatch equals `$actorA`'s exact
   id). The real production contract for a foreign-tenant
   `work_instance_step_id` is `422 E422.VALIDATION`
   (`"Inspection checklist instance not available for this QC plan"`) at
   the QC-plan/step compatibility boundary — not a `403`. Corrected
   accordingly, with `assertDatabaseMissing` retained.
4. **`MaterialRequestApiTest::test_material_request_store_rejects_foreign_project_created_via_canonical_zena_projects_owner`** —
   identical mechanism: base's sanctum guard stayed cached with
   `$foreignCreator` through the `$userA`-headed dispatch. GAP-051
   genuinely authenticates `userA`. The real production contract is
   `422 E422.VALIDATION`, with the tenant-scoped `exists` validation rule
   on `project_id` rejecting the foreign-tenant project
   (`"The selected project id is invalid."`) — not a `403`. Corrected
   accordingly.
5. **`WorkTemplateMvpApiTest::test_work_instance_step_attachments_upload_list_delete_are_tenant_scoped_and_audited`** —
   identical mechanism: base's sanctum guard stayed cached with
   `$actorA` (from an earlier delete-attachment call in the same test)
   through the final `$actorB`-headed dispatch. GAP-051 genuinely
   authenticates `$actorB`, whose tenant-scoped lookup of tenant A's work
   instance/step correctly finds nothing, producing a genuine
   `404 E404.NOT_FOUND` (`"Work instance or step not found"`) — not a
   `403`. Corrected accordingly.

No test in either round exposed an ambiguous or ""INVESTIGATE""-class
root cause, and no new Work ID beyond the already-reserved GAP-052 was
opened during either correction round.

## Verification truth — both layers recorded, neither overstated

**Local verification is NOT fully clean at this subject, and this packet
does not claim it is.**

- `composer test:fast`: **2,654 tests, 16,362 assertions, 556 errors, 14
  failures, 32 skipped.** The 556 errors are confirmed pre-existing
  local-environment noise (this specific worktree's missing
  `Illuminate\Cache\RedisStore::publish()` method and broken
  `imagick`/`memcached` PHP extension `.so` files) — verified identical
  in count via a dedicated baseline re-run at unmodified candidate HEAD.
  The 14 failures are exactly 2 fewer than that same baseline's 16,
  matching Round 1's two targeted fixes precisely, with zero new failures
  introduced by either round.
- `tests/Feature/Api` (excluded from `composer test:fast`; CI's own
  invocation): both the no-stop and the CI-exact `--stop-on-failure`
  invocations show exactly 2 failures — `ContractPdfExportTest` and
  `DocumentManagementTest`, both `MissingAppKeyException` — confirmed
  identical on Gate-2 base via a full commit checkout; a local
  app-key/environment artifact of this specific worktree, not
  GAP-051-caused, and not fixed here.
- Focused GAP-051 suites, all corrected test files in full, and the
  changed-file population all pass cleanly locally — see
  `GATE3-LOCAL-REPORT.md` §14.3 and §15.4 for exact counts.

**Live, exact-head GitHub CI at subject SHA
`422c5015677867734e64ab2149069d426492b9ff` is authoritative for
repository-integrated verification, and is fully green.** Every required
workflow/job on this exact head passed: Owner Governance Lint, Routes
Guardrails, API Tests (Fast/Slow), Feature Tests, Integration Tests, Unit
Tests, Security Tests, all real-MySQL concurrency jobs (Document Workflow,
GAP-048 Service-Line, RFI Escalation, Treasury Native CHECK Constraints),
Zena RBAC/Tenant Invariants (both the plain and MySQL-parity variants),
Code Quality Analysis, Dependency/License/Security Vulnerability Scans,
Docker Security Scan, Trivy, Repo Hygiene Guards, Test Coverage Report,
staging-smoke, button-inventory-check, test-routes-guardrails, and
browser-tests.

**`browser-tests` (Button Test Suite workflow), recorded truthfully, not
overstated:** its first Dusk attempt (`tests/Browser/Projects/`,
`tests/Browser/Crm/`) had two transient failures — `"project create
validation surface"` and `"project create cancel flow"`. The workflow's
own defined retry (`.github/workflows/button-tests.yml`, up to 2 attempts)
ran automatically. The second attempt passed cleanly with zero failures.
The final `browser-tests` job conclusion is `success` (44m16s total,
consistent with two full ~20-22 minute attempts). **No GAP-051 code path
exists in Dusk's execution** — Dusk dispatches real HTTP requests against
a real running Laravel server through a real Chrome browser, never through
`Tests\TestCase::call()`, the sole location of Layer A/B's logic — so this
transient failure could not mechanically originate from this PR's diff
(confined to `tests/Feature/*.php` and `tests/Integration/*.php` in Round
1, `tests/Feature/Api/*.php` and documentation in Round 2). Four other
recent, unrelated `browser-tests` runs on this repository (including this
same branch's own two earlier PR pushes) were checked and each completed
cleanly in a single ~19-22 minute attempt with no retry needed —
consistent with this being an isolated one-off, not a newly introduced or
proven systemic defect. This is reported as the evidence observed, not
overstated as a formally proven flaky-test classification. **No correction
was made for this transient failure**, per Owner instruction that no
further implementation changes are authorized at this subject.

## Gói quyết định phát hành

**1. Vấn đề đã xảy ra là gì?** Bộ test xác thực Sanctum Bearer-token
(dùng để kiểm tra API) có thể "xanh giả" — pass không phải vì code đúng,
mà vì test dùng nhầm một identity (danh tính người dùng) còn sót lại từ
một request trước đó trong cùng test, thay vì identity thật của request
đang kiểm tra.

**2. Người dùng nào bị ảnh hưởng?** Không có người dùng thật nào bị ảnh
hưởng trực tiếp — đây là lỗ hổng trong chính bộ test (test giả mạo khả
năng bảo vệ mà nó tưởng đang kiểm tra), không phải lỗ hổng bảo mật thật
trong sản phẩm đang chạy. Route API hiện tại không có middleware
session/stateful nên "khe hở" web-guard-fallback mà audit gốc phát hiện
là bất hoạt trong topology hiện tại (đã kiểm chứng bằng hợp đồng 401 thật
ở route sống).

**3. Bây giờ người dùng có thể làm gì?** Không có gì thay đổi với người
dùng cuối. Thay đổi này chỉ khiến đội kỹ thuật tin tưởng đúng vào kết quả
test — khi test pass, nghĩa là hành vi thật đã được kiểm tra đúng identity,
không còn "xanh giả."

**4. Rủi ro nào đã được đóng lại?** Rủi ro rằng một lỗ hổng phân quyền/
cách ly tenant thật có thể lẩn tránh test mãi mãi vì test tự nhiễm identity
sai — đã đóng bằng 2 lớp bảo vệ (Layer A/B) + 5 test cụ thể được sửa để
phản ánh đúng hành vi production thật.

**5. Đã kiểm thử những gì?** Toàn bộ 5 test bị lộ sai đã được sửa và pass
lại với hành vi thật; bộ test tập trung của GAP-051 pass; toàn bộ file đã
sửa pass; CI thật ở đúng head này xanh toàn bộ (kể cả browser-tests, sau
khi tự retry theo cơ chế có sẵn của workflow). Một số lỗi cục bộ trên máy
làm việc (không liên quan code) được ghi nhận trung thực, không giấu.

**6. Điều gì KHÔNG nằm trong phạm vi lần này?** Một lỗi sản phẩm thật,
độc lập, mới lộ ra (dashboard vai trò client_rep gọi một hàm không tồn
tại, trả về lỗi 500) — được giữ nguyên, KHÔNG sửa ở đây, dành riêng một
Work ID mới (GAP-052) theo đúng quy trình quản trị.

**7. Vì sao các gap liên quan vẫn để riêng?** GAP-052 là một lỗi sản
phẩm thật, khác hẳn về bản chất với GAP-051 (GAP-051 là lỗi độ tin cậy
của test; GAP-052 là lỗi code sản phẩm thật). Gộp chung sẽ mở rộng phạm
vi ngoài những gì Gate 2 đã duyệt và cần đánh giá rủi ro/ưu tiên riêng.

**8. Rủi ro còn lại là gì?** Thấp. Không có thay đổi code sản xuất nào.
Một lỗi transient (không lặp lại) ở browser-tests đã tự pass ở lần chạy
lại theo đúng cơ chế retry có sẵn của repo, không liên quan về mặt kỹ
thuật tới thay đổi này.

**9. Có thể hoàn tác không?** Có — toàn bộ thay đổi chỉ nằm trong thư mục
test và tài liệu, không đụng route/middleware/config sản xuất, revert đơn
giản qua git nếu cần.

**10. Đề xuất của đội kỹ thuật:** Đủ điều kiện kỹ thuật để Owner ra quyết
định Gate 3. Khuyến nghị: phê duyệt, hoặc yêu cầu chỉnh sửa nếu Owner muốn
xử lý GAP-052 khác đi.

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide

The Owner is not being asked to inspect CI logs, source code, diffs, or
review comments directly — only whether the demonstrated behavior
(genuine per-request Bearer authentication, the five corrected test
contracts now matching real production behavior, and the explicitly
deferred GAP-052 production defect) and the residual risk are acceptable
to release. The Owner is also not being asked to approve, scope, or
prioritize GAP-052 itself here — only to acknowledge that it exists and is
correctly out of this gate's scope; GAP-052 will be presented through its
own governed Gate-1 request when work on it begins.
