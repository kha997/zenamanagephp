---
work_id: GAP-044
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/superpowers/specs/2026-08-22-gap-044-testcase-transaction-and-permission-lookup-design.md
  plan: docs/superpowers/plans/2026-08-23-gap-044-testcase-transaction-and-permission-lookup-implementation.md
  branch: feature/GAP-044-testcase-transaction-and-permission-lookup
  pr: "https://github.com/kha997/zenamanagephp/pull/286"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-23T00:00:00+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-23T00:00:00+07:00"
  updated_at: "2026-08-24T00:00:00+07:00"
generated_by: agent
technical_evidence:
  subject_sha: "4361c5f59cbba548664a68d0b84fb440c9b54da3"
  implementation_tree_digest: "716ea9cf50e4ab5ccbe478bd3a6ccf63aab2043e6dbd069db5a2b850eddf3d28"
  verified_pr_head_sha: "4361c5f59cbba548664a68d0b84fb440c9b54da3"
  verified_at: "2026-08-23T00:00:00+07:00"
residual_risk_rating: low
mandatory_technical_gate_summary: "Both Owner Gate-2-approved surfaces (Surface 1: TestCase.php transaction isolation; Surface 2: TenantUserFactoryTrait.php permission lookup identity) implemented and verified GREEN with RED-first TDD evidence. Discriminating rollback-vs-migrate:fresh proof (the exact mechanism that caught GAP-040's own false-green) re-verified PASS on all 5 GAP-040-approved real-MySQL surfaces at subject SHA 4361c5f5: immediately before the verifier's own parent::setUp(), RefreshDatabaseState::$migrated===true (no migrate:fresh pending) AND the marker is already absent via independent PDO — so its disappearance is attributable to the writer's own teardown rollback, not a verifier-side schema wipe. Authoritative seeded PerformanceMonitoringTest (10/10 passed, 45 assertions) and DashboardPerformanceTest (19/19 passed, 157 assertions) pipelines both show zero SAVEPOINT-1305 and zero duplicate-permission-1062 failures, obtained via a disposable never-merged GAP-041 selector overlay since GAP-041 itself remains separately open/unfixed on main. Full local SQLite regression: 2309/2309 passed, 0 failures. RoleSeeder/PermissionSeeder/migrations/register/workflows and all GAP-040/041/042/043/045 artifacts confirmed untouched (zero-diff). Two pre-existing, unrelated failures re-observed (1 Treasury FK-constraint test, 1 CriticalUserFlowsE2ETest) — both already documented as non-gating at GAP-040 Gate 3, not attributable to GAP-044. All current normal PR #286 checks are green as of this correction, including browser-tests (run 32606356420, job 97112063351, SUCCESS) and Owner Governance Lint (run 32606356397, rerun SUCCESS after the evidence-freshness timing gotcha). This is a prepared packet only: gate_status remains awaiting_owner, owner_decision.value remains none, no Owner decision has been recorded, no merge/release/deployment is authorized."
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-044 — TestCase Transaction Isolation + Permission Lookup Identity: Gate 3 Release Request

**This packet is PREPARED, not submitted for a release decision. `gate_status: awaiting_owner`, `owner_decision.value: none`. PR #286 is not marked ready. No merge, release, or deployment is authorized by this document — it awaits a separate, explicit Owner Gate-3 review and decision.**

**Revision note:** Owner reviewed the first submission of this packet (live PR head `a0577deb`) and returned **DECISION: CHANGES REQUIRED, scoped to Gate-3 evidence/documentation only** — the implementation itself (Surface 1, Surface 2, all regression tests) was explicitly accepted as-is, no code change authorized or required. This revision corrects: (1) the discriminating-rollback-proof prose, which previously stated the marker was "still independently visible immediately before the verifier's `parent::setUp()`" — logically incorrect and inconsistent with the permanent regression test, corrected below to the actual proven discriminator (marker already *absent* at that boundary, combined with `$migrated === true` proving no `migrate:fresh` was pending); (2) the subject-file classification, corrected from an undifferentiated "8 remaining files" to the precise 2 functional + 7 regression/support + 1 implementation-plan breakdown; (3) stale CI status (`browser-tests` was pending at first submission, now confirmed SUCCESS, along with a rerun-confirmed Owner Governance Lint). Implementation subject SHA (`4361c5f59cbba548664a68d0b84fb440c9b54da3`) and implementation-tree digest (`716ea9cf50e4ab5ccbe478bd3a6ccf63aab2043e6dbd069db5a2b850eddf3d28`) are unchanged — this correction touches only this one document.

## Summary

Implements Owner-approved Gate 2 Option A (complete test-infrastructure remediation) for the compound defect GAP-044 Gate 1 confirmed:

- **Surface 1 (transaction isolation):** `tests/TestCase.php`'s `ensureInteractionLogsTable()`/`ensureProjectPhasesTable()`/`ensureProjectTasksTable()` now route their DDL through the existing GAP-040 isolated `zena_ddl_bootstrap` connection (A2-style direct reuse — no new bootstrap architecture, no rename).
- **Surface 2 (permission fixture identity):** `tests/Traits/TenantUserFactoryTrait.php::ensurePermissionAttached()` now looks up `Permission` by canonical `code` instead of `name`.
- **Regression coverage:** the shared `tests/Support/GAP040ColdStartTransactionIsolationAssertions.php` proof is permanently strengthened with a rollback-vs-`migrate:fresh` discriminator (explicitly authorized/required by Owner Gate-2 approval), wired into all 5 GAP-040-approved consuming test classes; one new SQLite regression test (`tests/Feature/Zena/PermissionCanonicalIdentityRegressionTest.php`) proves the exact seeded shape (`code='project.read'`, `name=NULL`) is handled correctly.

## Three SHAs — do not conflate

1. **Gate-1 approval-record head:** `8ee9ec256f86aa291335768bd74abe0e1703f072` (PR #283, `docs/GAP-044-gate1-investigation`).
2. **Gate-2 approval-record head:** `2bfc7db5ebd028e8a2d8ca2e5ead2418a41a4b11` (PR #285, `docs/GAP-044-gate2-design`) — this implementation branch was cut from this exact commit.
3. **Implementation subject SHA (this is what would merge):** `4361c5f59cbba548664a68d0b84fb440c9b54da3` (PR #286, `feature/GAP-044-testcase-transaction-and-permission-lookup`, Draft).

Two disposable, never-merged evidence-harness branches were used during implementation for CI-only verification and have been deleted:
- `investigate/GAP-044-impl-seeded-performance-overlay` (Task 7 — GAP-041's already-Owner-approved selector overlay, to obtain truthful seeded-pipeline evidence; GAP-041 itself remains open and unfixed).

## Implementation-tree digest

`716ea9cf50e4ab5ccbe478bd3a6ccf63aab2043e6dbd069db5a2b850eddf3d28`, computed by the repository's own `owner_governance_compute_implementation_tree_digest()` (`scripts/ssot/owner_governance_lint.php`) against commit `4361c5f59cbba548664a68d0b84fb440c9b54da3`, invoked directly (not reimplemented) to avoid transcription error. This digest excludes only the active `docs/owner-decisions/GAP-044/03-release*.md` packet file itself (none existed in the tree at this subject SHA), so recording/updating this document does not change it.

## Exact changed-file inventory (`git diff 2bfc7db5...4361c5f5 --stat`)

```
 docs/superpowers/plans/2026-08-23-gap-044-testcase-transaction-and-permission-lookup-implementation.md | 993 +++++++
 tests/E2E/TransactionIsolationColdStartTest.php                                          |   5 +
 tests/Feature/Documents/TransactionIsolationColdStartTest.php                            |   5 +
 tests/Feature/Zena/PermissionCanonicalIdentityRegressionTest.php                         |  71 ++
 tests/Feature/Zena/ZenaInvariantsTransactionIsolationColdStartTest.php                   |   5 +
 tests/Feature/Zena/ZenaTransactionIsolationColdStartTest.php                             |   5 +
 tests/Support/GAP040ColdStartTransactionIsolationAssertions.php                          | 172 +++-
 tests/TestCase.php                                                                        |  69 +-
 tests/Traits/TenantUserFactoryTrait.php                                                   |   4 +-
 tests/Unit/Migrations/Treasury/TreasuryTransactionIsolationColdStartTest.php             |   5 +
 10 files changed, 1296 insertions(+), 38 deletions(-)
```

Of these 10 subject files:
- **2 functional implementation files** (the Owner-approved fix boundary): `tests/TestCase.php` (Surface 1) and `tests/Traits/TenantUserFactoryTrait.php` (Surface 2).
- **7 permanent regression/test-support files**, explicitly authorized and required by the Owner's Gate-2 scope clarification: `tests/Support/GAP040ColdStartTransactionIsolationAssertions.php` and the 5 consuming test classes (`tests/Feature/Zena/ZenaTransactionIsolationColdStartTest.php`, `tests/Feature/Zena/ZenaInvariantsTransactionIsolationColdStartTest.php`, `tests/Unit/Migrations/Treasury/TreasuryTransactionIsolationColdStartTest.php`, `tests/E2E/TransactionIsolationColdStartTest.php`, `tests/Feature/Documents/TransactionIsolationColdStartTest.php`), plus the new `tests/Feature/Zena/PermissionCanonicalIdentityRegressionTest.php`.
- **1 mandatory implementation-plan document**: `docs/superpowers/plans/2026-08-23-gap-044-testcase-transaction-and-permission-lookup-implementation.md`.

No production/application code, no migration, no seeder, no workflow file. (This 10-file count is the implementation subject's own diff, `git diff 2bfc7db5...4361c5f5` — distinct from PR #286's current total changed-file count, which additionally includes the Gate-1/Gate-2 approved governance artifacts carried forward byte-identically and this Gate-3 packet itself; see the PR body for that total.)

## RED evidence

**Task 1 (Surface 2, local SQLite, commit `28a1f41a`):** `PermissionCanonicalIdentityRegressionTest` failed with `Illuminate\Database\UniqueConstraintViolationException: SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: permissions.code`, thrown from `TenantUserFactoryTrait.php:59` (the pre-fix `firstOrCreate(['name' => ...])` lookup missing the seeded `name=NULL` row and attempting a duplicate insert) — confirmed the exact real bug, for the right reason.

**Task 4 (Surface 1 discriminator, genuine MySQL, commit `10e73532`, GitHub Actions run `32604436994`, job `97107353023`, `test-routes-guardrails`):**
```
[GAP-040/GAP-044 probe] {... "helpers":{"interaction_logs":{"pdo_in_transaction_before":true,"pdo_in_transaction_after":false}, ...}}
FAILED  Tests\Feature\Zena\ZenaTransactionIsolationColdStartTest > a writ…
  141▕                 $probe['helpers'][$helperTable]['pdo_in_transaction_after'],
Tests:    1 failed, 1 skipped, 2 passed (21 assertions)
```
Confirmed the new per-helper discriminating assertion catches `interaction_logs`'s implicit commit — RED for exactly the right reason, before Surface 1 was fixed.

## GREEN evidence

**Task 2 (Surface 2, local SQLite, commit `99f5be61`):** `PermissionCanonicalIdentityRegressionTest` passes (3 assertions). Full `tests/Feature/Zena` suite: 288 tests, 2403 assertions, 2 skipped (pre-existing), 0 failures.

**Task 5 (Surface 1, genuine MySQL, commit `c2baedd1` then `4361c5f5` after one interim fix):**
- First attempt (`c2baedd1`, GitHub Actions run `32604642524`, job `97107840797`) showed all 3 helpers' `pdo_in_transaction_after: true` (Surface 1 mechanism confirmed working) but surfaced a genuine bug in the new discriminator itself: `BindingResolutionException: Target class [config] does not exist` at `tests/Support/GAP040ColdStartTransactionIsolationAssertions.php:172/235` — `captureDiscriminatingStateBeforeVerifierSetUp()` calling `config()` before `parent::setUp()` boots the app container. Fixed (commit `4361c5f5`) by switching `independentPdoSeesTenant()` to `getenv()`, matching the established pattern already used by `forceGenuineColdStartForNextSetUp()` for the identical reason.
- Confirmed GREEN after the fix (GitHub Actions run `32604805365`, job `97108220067`, `test-routes-guardrails`):
```
[GAP-040/GAP-044 probe] {..."helpers":{"interaction_logs":{"pdo_in_transaction_before":true,"pdo_in_transaction_after":true},"project_phases":{"pdo_in_transaction_before":true,"pdo_in_transaction_after":true},"project_tasks":{"pdo_in_transaction_before":true,"pdo_in_transaction_after":true}}}
PASS  Tests\Feature\Zena\ZenaTransactionIsolationColdStartTest
Tests:    4 passed (32 assertions)
```

## Discriminating rollback proof — distinguishes rollback from migrate:fresh

Confirmed via the same GREEN run above: `assertMarkerDisappearedViaRollbackNotMigrateFresh()` (new, per Owner Gate-2 §G requirement) passed as part of the 4-test/32-assertion PASS. The permanent proof is implemented in `tests/Support/GAP040ColdStartTransactionIsolationAssertions.php`, specifically `captureDiscriminatingStateBeforeVerifierSetUp()` (captures state) and `assertMarkerDisappearedViaRollbackNotMigrateFresh()` (asserts on it). The actual proven discriminator, immediately **before** the verifier's own `parent::setUp()` runs:

1. `RefreshDatabaseState::$migrated === true` — therefore the verifier's own `parent::setUp()` is **not** about to perform a `migrate:fresh`.
2. The marker is **already absent** via the independent PDO check (`markerVisibleBeforeVerifierSetUp === false`) — therefore the row disappeared **before** the verifier's setup could have affected the database at all.
3. Combined with the writer having held a genuine, live `RefreshDatabase` transaction, this attributes the disappearance to the writer's own teardown rollback, rather than to verifier-side `migrate:fresh`.

This is **not** a claim that an independent PDO connection saw an uncommitted marker while the writer's transaction was still open — that would contradict normal transaction isolation and is not what this proof asserts. The marker is written and (in the false-green scenario) would remain visible past teardown only if removed by something other than rollback; this discriminator instead confirms it is *already gone* by the earliest point a `migrate:fresh` alternative explanation could apply, while `$migrated` confirms no such `migrate:fresh` was even pending at that point — jointly ruling out the false-green mechanism GAP-044 Gate 1 §H1 found in GAP-040's own proof, without asserting anything inconsistent with isolation.

## All 5 GAP-040-approved MySQL surfaces — re-verified with the new discriminating proof

| # | Surface | Run/Job | Result |
|---|---|---|---|
| 1 | `routes-guardrails.yml` `--group=mysql-parity` | run `32604805365`, job `97108220067` | **PASS** — 4 tests, 32 assertions, all 3 helpers `pdo_in_transaction_after: true`, discriminator passed |
| 2 | `automated-testing.yml` `zena-invariants-mysql` | run `32604803549`, job `97108215990` | **PASS** — 41 tests, 1278 assertions, all 3 helpers `pdo_in_transaction_after: true`, discriminator passed |
| 3 | `automated-testing.yml` `treasury-check-constraints-mysql` | run `32604803549`, job `97108216073` | **PASS** (job conclusion `success`) — all 3 helpers `pdo_in_transaction_after: true`, discriminator passed; step 3's full-suite run shows 1 pre-existing, unrelated Treasury FK-constraint failure (documented as non-gating/informational at GAP-040 Gate 3 — not attributable to GAP-044, not newly introduced) |
| 4 | `a11y-perf-testing.yml` `e2e-tests` | run `32605264549`, job `97109293977` | **Discriminating proof step PASS** — 2 tests, 22 assertions, all 3 helpers `pdo_in_transaction_after: true`. **Job's overall conclusion is `failure`**, caused entirely by a separate, pre-existing, unrelated failure (`CriticalUserFlowsE2ETest > complete user authentication…`, isolated in its own `if: always()` step per GAP-040's own established pattern) — not attributable to GAP-044, not newly introduced by this implementation. Reported truthfully, not glossed over. |
| 5 | `ci-cd.yml` `test` job (GAP-032 MySQL step) | run `32604805512`, job `97108220583` | **PASS** (`success`, 7m55s) |

## Authoritative seeded PerformanceMonitoringTest pipeline result

`automated-testing.yml`'s `performance-tests` job, as currently committed on `main`, still lacks GAP-041's selector fix and silently reports `No tests found.` (confirmed on this implementation's own head, run `32604803549`/`32605284894` pre-overlay: `INFO No tests found.`) — this is GAP-041's own separately-tracked, unfixed defect, not a GAP-044 regression.

Truthful evidence obtained via a disposable, never-merged overlay (GAP-041's already-Owner-approved `--group=performance --fail-on-empty-test-suite` selector, identical precedent to GAP-043's Gate 3 and GAP-044's own Gate 1; branch `investigate/GAP-044-impl-seeded-performance-overlay`, cut from this exact implementation head `4361c5f5`, workflow-dispatched, deleted immediately after capture):

- **Environment:** genuine MySQL 8.0 service container, `php artisan migrate` + `php artisan db:seed --env=testing --force`, `ZENA_INVARIANTS_DB=mysql`.
- **Command:** `php artisan test "tests/Performance/PerformanceMonitoringTest.php" --group=performance --fail-on-empty-test-suite`.
- **Run/job:** `32605284894` / `97109342547`.
- **Result:** `Tests: 10 passed (45 assertions)`. **`test_api_performance_budgets` — PASS.** Zero `SAVEPOINT trans2 does not exist` (1305). Zero duplicate-permission `UniqueConstraintViolationException` (1062) attributable to GAP-044.

## Authoritative seeded DashboardPerformanceTest pipeline result

Same overlay, same run, job `97109342500`:
- **Command:** `php artisan test "tests/Performance/DashboardPerformanceTest.php" --group=performance --fail-on-empty-test-suite`.
- **Result:** `Tests: 19 passed (157 assertions)`. **`it_can_load_dashboard_with_large_dataset_quickly` — PASS.** Zero 1305, zero 1062.
- **GAP-045 boundary (explicit, per Owner instruction):** `it_can_load_alerts_with_large_dataset_quickly`'s separate latency-budget assertion (`assertLessThan($latencyBudgetMs, $medianMs)`, GAP-045's own tracked concern) also passed on this run. This is reported as an observation only — **the 450ms threshold was not modified, and this single passing run is not claimed as proof GAP-045 is resolved** (GAP-045 remains a separate, open, unfixed work item with its own governance lifecycle; latency assertions can vary run-to-run). GAP-044's own acceptance boundary (no 1305, no 1062) is fully met independent of GAP-045's result.

## SQLite regression suite (local, full default suite)

`./vendor/bin/phpunit --testsuite=Unit,Feature,Integration`: **2309 tests, 17192 assertions, 0 failures, 0 errors, 42 skipped** (pre-existing, unrelated to this branch), 501 deprecation notices (pre-existing PHPUnit 12 doc-comment-metadata warnings, unrelated).

## CI run/job inventory

**Current normal PR #286 checks, at exact-head `a0577deb7ddf2c96e485f29714e06795a53dd2cb` (implementation subject unchanged at `4361c5f59cbba548664a68d0b84fb440c9b54da3`): all SUCCESS.**

- Owner Governance Lint — SUCCESS (run `32606356397`; the initial attempt on this head hit the known evidence-freshness timing gotcha — it requires all other PR checks green before confirming `technical_readiness: ready` — and was rerun after `browser-tests` completed; the rerun is SUCCESS).
- Routes Guardrails (`test-routes-guardrails`) — SUCCESS (run `32606356418`).
- Button Test Suite `browser-tests` — SUCCESS (run `32606356420`, job `97112063351`).
- Automated Testing — SUCCESS (all jobs, including Zena RBAC/Tenant Invariants (MySQL parity), Zena RBAC/Tenant Invariants, Treasury Native CHECK Constraints (real MySQL), Document Workflow Concurrency (real MySQL), RFI Escalation Concurrency (real MySQL), Performance Tests (monitoring/dashboard), Unit/Feature/Integration/API Tests (Fast/Slow)/Security Tests/Repo Hygiene Guards).
- CI/CD Pipeline — SUCCESS (`code-quality`, `test`; `deploy` skipped by design, not merged).
- Code Quality & Security — SUCCESS (all scans).
- Staging Smoke — SUCCESS.

**Distinct from the above: the historical/manual GAP-044 acceptance-evidence surface, not a normal PR-triggered check.** `a11y-perf-testing.yml`, manually dispatched at implementation-subject head `4361c5f5` (run `32605264549`) specifically to exercise the E2E surface (one of GAP-040's 5 approved MySQL surfaces, which does not run automatically on push/PR): the `E2E Tests` job's **GAP-044 discriminator step PASSED** (2 tests, 22 assertions, all 3 helpers `pdo_in_transaction_after: true`); the **job's overall conclusion remains `failure`**, caused entirely by the separate, pre-existing, unrelated `CriticalUserFlowsE2ETest` failure (§ "All 5 GAP-040-approved MySQL surfaces" above). This historical run and its documented red/pass split are not rewritten or reinterpreted by this correction — they are preserved exactly as originally recorded, since that distinction (discriminator PASS vs. job-level pre-existing failure) is itself part of the truthful evidence.

The earlier PerformanceMonitoringTest/DashboardPerformanceTest seeded-pipeline evidence (§ above) used a separate, now-deleted disposable overlay branch cut from this same implementation-subject head `4361c5f5` — unaffected by this correction, not rerun.

## Confirmation: RoleSeeder / AUD-28 untouched

```
git diff 2bfc7db5...4361c5f5 -- database/seeders/RoleSeeder.php database/seeders/PermissionSeeder.php database/migrations OPERATIONAL_GAP_REGISTER.md .github/workflows
```
Empty. `RoleSeeder.php`'s `name=NULL` seeding behavior (the AUD-28-matching condition) is unchanged — Surface 2's fix makes the test fixture correctly tolerate it, per the approved design; `RoleSeeder` itself was never modified.

## Confirmation: GAP-040/041/042/043/045 artifacts untouched

```
git diff 2bfc7db5...4361c5f5 -- "docs/owner-decisions/GAP-040/**" "docs/owner-decisions/GAP-041/**" "docs/owner-decisions/GAP-042/**" "docs/owner-decisions/GAP-043/**" "docs/owner-decisions/GAP-045/**"
```
Empty. GAP-041 remains open and unfixed (its selector defect was only ever overlaid transiently, unmerged, on two disposable branches now deleted, exactly as GAP-043's own precedent established). GAP-040's Gate-3 record is unedited; the false-green finding from GAP-044 Gate 1 §H1 remains reported there as new evidence only, not as an edit to GAP-040's historical record.

## Confirmation: Gate-1/Gate-2 artifacts did not drift

```
git diff docs/GAP-044-gate1-investigation -- docs/audits/2026-08-22-gap-044-savepoint-trans2-root-cause-evidence.md docs/owner-decisions/GAP-044/01-request.md
git diff docs/GAP-044-gate2-design -- docs/superpowers/specs/2026-08-22-gap-044-testcase-transaction-and-permission-lookup-design.md docs/owner-decisions/GAP-044/02-design.md
```
Both empty — byte-identical to their approved states.

## Implementation risks / residual uncertainties

- **GAP-041 remains open.** `automated-testing.yml`'s `performance-tests` job, as committed on `main`, still silently selects 0 tests. The authoritative seeded evidence above required a disposable overlay to obtain truthfully; the same false-green-by-omission would recur for anyone re-running the plain `main`-committed job until GAP-041 is separately fixed. This is not a GAP-044 defect and is explicitly not fixed here.
- **`browser-tests` — resolved.** Not one of the 5 GAP-040 surfaces or part of the discriminating acceptance contract, but a normally-triggered CI check on this PR; confirmed SUCCESS (run `32606356420`, job `97112063351`) as of this correction.
- **The narrow Gate-1 §I1 uncertainty** (exact reason the seeded `permissions` row's `name` is `NULL` rather than absent) was independently reconciled by Owner at Gate-1 approval (`RoleSeeder` → `PermissionSeeder` → `name=NULL`, matching AUD-28) and is not re-litigated here; the fix (Surface 2) is correct regardless of that provenance detail, since it operates on the actual unique constraint (`code`), not on any assumption about how the row came to exist.
- **One pre-existing, unrelated Treasury FK-constraint test failure** and **one pre-existing, unrelated `CriticalUserFlowsE2ETest` failure** were both re-observed during this implementation's CI runs, identical in nature to what GAP-040's own Gate 3 evidence already documented as non-gating/informational and unrelated — not newly introduced, not investigated further here (out of GAP-044's scope).
- **A1-style generalization of the isolated-connection mechanism was not attempted** — the A2-style direct-reuse implementation (calling the existing `zenaRbacBootstrapSchema()` method as-is) was sufficient and is the smaller, Owner-preferred diff; no `TestCase.php` mechanism rename or extraction was needed.

## What the Owner is NOT being asked to decide by this packet's preparation

Preparing this document does not request a decision. `gate_status` remains `awaiting_owner`; `owner_decision.value` remains `none`. PR #286 is not marked ready, not merged. This packet awaits a separate, explicit Owner Gate-3 review and decision before any release action proceeds.
