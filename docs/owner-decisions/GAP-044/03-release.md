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
  updated_at: "2026-08-23T00:00:00+07:00"
generated_by: agent
technical_evidence:
  subject_sha: "4361c5f59cbba548664a68d0b84fb440c9b54da3"
  implementation_tree_digest: "716ea9cf50e4ab5ccbe478bd3a6ccf63aab2043e6dbd069db5a2b850eddf3d28"
  verified_pr_head_sha: "4361c5f59cbba548664a68d0b84fb440c9b54da3"
  verified_at: "2026-08-23T00:00:00+07:00"
---

# GAP-044 — TestCase Transaction Isolation + Permission Lookup Identity: Gate 3 Release Request

**This packet is PREPARED, not submitted for a release decision. `gate_status: awaiting_owner`, `owner_decision.value: none`. PR #286 is not marked ready. No merge, release, or deployment is authorized by this document — it awaits a separate, explicit Owner Gate-3 review and decision.**

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

Functional fix surface: exactly `tests/TestCase.php` (Surface 1) and `tests/Traits/TenantUserFactoryTrait.php` (Surface 2), matching the Owner-approved boundary. The remaining 8 files are the permanent regression-test/support files explicitly authorized and required by the Owner's Gate-2 scope clarification. No production/application code, no migration, no seeder, no workflow file.

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

Confirmed via the same GREEN run above: `assertMarkerDisappearedViaRollbackNotMigrateFresh()` (new, per Owner Gate-2 §G requirement) passed as part of the 4-test/32-assertion PASS — meaning `RefreshDatabaseState::$migrated === true` and the marker was still independently visible immediately before the verifier's own `parent::setUp()` ran, so the marker's later absence is attributable to the writer's own `RefreshDatabase` rollback specifically, not a `migrate:fresh` schema wipe. This is structurally the same false-green-resistant design used in GAP-044 Gate 1's own §H1 investigation (which is what caught GAP-040's original false-green) — reused here as the permanent regression proof.

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

## CI run/job inventory (exact-head `4361c5f59cbba548664a68d0b84fb440c9b54da3`, PR #286)

All checks green except the one pre-existing/unrelated E2E job and (at packet-preparation time) `browser-tests`, still in progress:

- Owner Governance Lint — PASS (run `32604805504`, job `97108220553`).
- Routes Guardrails (`test-routes-guardrails`) — PASS (run `32604805365`, job `97108220067`).
- Automated Testing (push event) — all jobs PASS (run `32604803549`): Zena RBAC/Tenant Invariants (MySQL parity) `97108215990`, Zena RBAC/Tenant Invariants `97108216091`, Treasury Native CHECK Constraints (real MySQL) `97108216073`, Document Workflow Concurrency (real MySQL) `97108216015`, RFI Escalation Concurrency (real MySQL) `97108216117`, Performance Tests (monitoring) `97108216107`, Performance Tests (dashboard) `97108216178`, Unit/Feature/Integration/API Tests (Fast/Slow)/Security Tests/Repo Hygiene Guards — all PASS.
- CI/CD Pipeline — `code-quality` PASS, `test` PASS (run `32604805512`, job `97108220583`, 7m55s), `deploy` skipped by design (not merged).
- Code Quality & Security — all scans PASS.
- Staging Smoke — PASS.
- Button Test Suite (`button-inventory-check`, `feature-tests`, `security-tests`) — PASS. `browser-tests` — in progress at packet-preparation time; result to be confirmed and reported truthfully before any Gate-3 Owner decision is requested to act on this packet.
- `a11y-perf-testing.yml` (manually dispatched, run `32605264549`): `E2E Tests` job conclusion `failure` — attributable entirely to the pre-existing, unrelated `CriticalUserFlowsE2ETest` failure (§ above); our discriminating proof step within that job passed.

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
- **`browser-tests` job status pending at packet-preparation time** — not one of the 5 GAP-040 surfaces or part of the discriminating acceptance contract, but a normally-triggered CI check on this PR; its result should be confirmed before any Owner Gate-3 action, per the instruction to report every failure truthfully.
- **The narrow Gate-1 §I1 uncertainty** (exact reason the seeded `permissions` row's `name` is `NULL` rather than absent) was independently reconciled by Owner at Gate-1 approval (`RoleSeeder` → `PermissionSeeder` → `name=NULL`, matching AUD-28) and is not re-litigated here; the fix (Surface 2) is correct regardless of that provenance detail, since it operates on the actual unique constraint (`code`), not on any assumption about how the row came to exist.
- **One pre-existing, unrelated Treasury FK-constraint test failure** and **one pre-existing, unrelated `CriticalUserFlowsE2ETest` failure** were both re-observed during this implementation's CI runs, identical in nature to what GAP-040's own Gate 3 evidence already documented as non-gating/informational and unrelated — not newly introduced, not investigated further here (out of GAP-044's scope).
- **A1-style generalization of the isolated-connection mechanism was not attempted** — the A2-style direct-reuse implementation (calling the existing `zenaRbacBootstrapSchema()` method as-is) was sufficient and is the smaller, Owner-preferred diff; no `TestCase.php` mechanism rename or extraction was needed.

## What the Owner is NOT being asked to decide by this packet's preparation

Preparing this document does not request a decision. `gate_status` remains `awaiting_owner`; `owner_decision.value` remains `none`. PR #286 is not marked ready, not merged. This packet awaits a separate, explicit Owner Gate-3 review and decision before any release action proceeds.
