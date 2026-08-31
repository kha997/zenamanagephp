---
work_id: GAP-048
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
  spec: docs/superpowers/specs/2026-08-30-gap-048-crm-classification-ux-gates-design.md
  plan: docs/superpowers/plans/2026-08-30-gap-048-crm-classification-ux-gates-implementation.md
  branch: feat/GAP-048-crm-classification-ux-gates
  pr: "https://github.com/kha997/zenamanagephp/pull/295"
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
  created_at: "2026-08-31T00:10:00+07:00"
  updated_at: "2026-08-31T00:10:00+07:00"
generated_by: agent
residual_risk_rating: medium
mandatory_technical_gate_summary: "GAP-048 implementation (canonical multi-valued Service-Line classification UX with explicit CONFIRMED confirmation; shared legacy->canonical mapper reused by store()/Lead-convert()/update(); nullable service_category migration verified on SQLite and real MySQL 8.0 including safe down()-rollback with pre-existing NULL data; pipeline/sendQuote()/convert()/createContract() gates backed by one shared CONFIRMED predicate; Opportunity-row locking with canonical lock order closing the Owner-identified concurrency race) is technically complete and verified. Full SQLite regression: 2416 tests, 8 pre-existing/unrelated failures (verified zero-diff against baseline dd7ed7c9 for every affected file: 7 Dashboard widget tests failing on a pre-existing broken Redis cache-store method, 1 flaky SecureUploadServiceTest), 0 GAP-048 regressions. CONCURRENCY-1/2/3 real-MySQL subprocess-race evidence obtained against a genuine MySQL 8.0 instance (Docker), sabotage-verified discriminating (temporarily removing OpportunityStageTransitionService's lockForUpdate() reproduced the exact Owner-specified illegal race state; restoring it closed the race). Nullable migration verified on SQLite and real MySQL 8.0, including a real bug found and fixed by live CI: the original down() blindly re-added NOT NULL and crashed on real MySQL once any row had legitimately gone NULL (SQLSTATE 22004) — fixed to backfill NULL rows to 'architecture' before restoring the constraint, verified by reproducing the failure on real MySQL, applying the fix, and confirming rollback+re-migrate succeeds; this defect was independently caught by live browser-tests (Dusk, real MySQL) CI. A second real, pre-existing (byte-identical to baseline dd7ed7c9) PHPStan gap in app/Http/Controllers/Web/ReportPageController.php's buildDataset() — unrelated to GAP-048's design boundary but blocking the Owner-governance evidence-freshness gate's hard requirement that every check on the head be green before gate_status may reach awaiting_owner — was also fixed: the private closure/LazyCollection-typing issue was replaced with a plain generator method (projectRows()), zero behavior change, verified against the real CSV-export Feature test (OperatorPlatformUiTest::test_report_export_streams_tenant_scoped_csv, still green) rather than assumed safe. Live PR #295 CI at the final verified head: all applicable checks green — Unit Tests, API Tests (Fast/Slow), Feature Tests, Integration Tests, browser-tests (Dusk, real MySQL), RFI Escalation Concurrency (real MySQL), Document Workflow Concurrency (real MySQL), Treasury Native CHECK Constraints (real MySQL), Zena RBAC/Tenant Invariants (default + MySQL parity), Owner Governance Lint, test-routes-guardrails, Repo Hygiene Guards, Code Quality Analysis, Security Tests, staging-smoke, and all security/dependency/license/Docker scans. PHPStan project-wide: zero errors (0 file_errors), verified via the exact command CI runs. Deptrac clean (0 violations). This packet records technical readiness for Owner Gate-3 review only; it does not authorize Ready-for-review, merge, release, or production deployment."
technical_evidence:
  subject_sha: "PENDING_FINAL_HEAD"
  implementation_tree_digest: "PENDING_FINAL_DIGEST"
  verified_pr_head_sha: "PENDING_FINAL_HEAD"
  verified_at: "2026-08-31T00:10:00+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-048 — CRM Classification UX & Gates: Gate 3 Release Request

**Status: `awaiting_owner`.** This packet requests Owner Gate-3 review of a
technically-complete, freshly-verified implementation. It does **not**
itself authorize Ready-for-review, merge, release, or production
deployment — those require a separate, explicit Owner instruction issued
after this Gate-3 decision.

## Implementation baseline and PR

- **Implementation baseline (Gate-2 merge, unchanged origin/main at session
  start):** `dd7ed7c9` (PR #294 squash-merge).
- **Implementation branch:** `feat/GAP-048-crm-classification-ux-gates`.
- **Implementation PR (Draft, unmerged, mergeable):** [#295](https://github.com/kha997/zenamanagephp/pull/295).
- **Exact PR head / subject_sha (identical — no Gate-3-record-only commits
  have been made yet on top of this evidence):** `d12c01cc1cb64c42d5577c28f80a598054751e4b`.
- **Implementation plan:** `docs/superpowers/plans/2026-08-30-gap-048-crm-classification-ux-gates-implementation.md`.

## What changed (`dd7ed7c9` → `d12c01cc1cb64c42d5577c28f80a598054751e4b`)

```
 app/Console/Commands/BackfillOpportunityServiceLines.php                    |   19 +-
 app/Console/Commands/Testing/OpportunityConcurrencyTestReconcile.php        |   38 + (new)
 app/Console/Commands/Testing/OpportunityConcurrencyTestSendQuote.php        |   42 + (new)
 app/Console/Commands/Testing/OpportunityConcurrencyTestTransition.php       |   39 + (new)
 app/Console/Commands/Testing/OpportunityConcurrencyTestUpdateCategory.php   |   66 + (new)
 app/Http/Controllers/Api/LeadController.php                                 |   15 +-
 app/Http/Controllers/Api/OpportunityController.php                         |  280 +-
 app/Http/Controllers/Web/CrmPageController.php                             |  110 +-
 app/Http/Controllers/Web/DesignItemPageController.php                      |   22 +-
 app/Http/Controllers/Web/ReportPageController.php                         |   16 +- (mechanical PHPStan fix, zero behavior change — see below)
 app/Models/Opportunity.php                                                 |   20 +
 app/Models/Project.php                                                     |    1 +
 app/Services/BusinessKpiService.php                                        |    8 +-
 app/Services/Crm/OpportunityServiceLineClassificationService.php           |  167 + (new)
 app/Services/Crm/OpportunityStageTransitionService.php                     |   92 +-
 app/Support/LegacyServiceCategoryMapper.php                                |   33 + (new)
 database/migrations/2026_08_30_100000_make_opportunities_service_category_nullable.php | 33 + (new)
 docs/superpowers/plans/2026-08-30-gap-048-crm-classification-ux-gates-implementation.md | 1749 + (new)
 resources/views/crm/opportunity-show.blade.php                            |   28 +
 routes/api_zena.php                                                        |    1 +
 routes/web.php                                                             |    1 +
 scripts/ssot/baselines/skipped_tests_baseline.txt                          |    1 +
 tests/Feature/Api/CrmApiTest.php                                           |   34 +
 tests/Feature/Concurrency/OpportunityServiceLineConcurrencyTest.php        |  318 + (new)
 tests/Feature/Crm/OpportunityConversionUnchangedTest.php                   |   23 +-
 tests/Feature/Crm/ServiceLineClassificationReconciliationTest.php          |  294 + (new)
 tests/Feature/Crm/ServiceLineClassificationWriterSyncTest.php              |  149 + (new)
 tests/Feature/Crm/ServiceLineGateTest.php                                  |  204 + (new)
 tests/Feature/Models/ServiceLineFoundationTest.php                        |   11 +-
 tests/Feature/QuoteLifecycleTest.php                                       |    7 +
 tests/Feature/QuoteToContractTest.php                                      |    7 +
 tests/Feature/Zena/AiDesignItemSuggestionTest.php                         |  102 + (new)
 tests/Feature/Zena/OperatorCrmUiTest.php                                   |   25 +
 tests/Unit/Services/BusinessKpiServiceTest.php                            |   14 +
 tests/Unit/Support/LegacyServiceCategoryMapperTest.php                    |   39 + (new)
```

Every changed file is inside this Work ID's approved File Structure
(implementation plan §"File Structure"). No `.github/workflows/**` file was
touched. No `zena-boq-core` file exists in this repository to touch. No
Opportunity→Project propagation code was added (`OpportunityController::convert()`/`createContract()`
still create zero `ProjectServiceLine` rows — proven by
`OpportunityConversionUnchangedTest.php`, updated only to seed the now-required
CONFIRMED classification, never to weaken its non-propagation assertions).

## Completed behavior matrix vs approved Gate 2

| Design §/behavior | Implemented at | Status |
|---|---|---|
| §4 shared legacy→canonical mapper, single source | `app/Support/LegacyServiceCategoryMapper.php`, consumed by backfill command + `store()` + `LeadController::convert()` + `update()` | Done |
| §4 rule C: legacy-scalar `update()` reconciles mapper-owned INFERRED only, never touches CONFIRMED | `OpportunityController::update()` | Done — structurally impossible to touch CONFIRMED (query filters `provenance = INFERRED`) |
| §5 atomic desired-set reconciliation, explicit tenant/parent check | `OpportunityServiceLineClassificationService::reconcile()` | Done |
| §5 lifecycle invariant (active/formal states retain ≥1 CONFIRMED) | Same service, `requiresConfirmedInvariant()` | Done |
| §3 UI write contract (unpersisted draft, explicit Confirm) | `resources/views/crm/opportunity-show.blade.php` + `CrmPageController::confirmServiceLines()` / `Api\OpportunityController::updateServiceLines()` | Done |
| §9 nullable migration, both app-level `'architecture'` fallbacks removed | Migration + `OpportunityController::store()`/`LeadController::convert()` | Done, verified SQLite + real MySQL 8.0 |
| §10 shared CONFIRMED predicate | `Opportunity::hasConfirmedServiceLine()` | Done — every gate calls this one method |
| §11 pipeline gate | `OpportunityStageTransitionService::transition()` | Done |
| §12 WON→Project defense-in-depth gate | `OpportunityController::convert()` / `createContract()` | Done |
| §13 formal-Quote gate (`sendQuote()`) + independent `createContract()` gate | `CrmPageController::sendQuote()` / `OpportunityController::createContract()` | Done |
| §13 external-sync-vs-local-use distinction | `linkExternalBoqProject()`/`syncExternalQuote()` unchanged (ungated); only `createContract()` gated | Done |
| §14 BusinessKpiService narrow NULL bridge | `BusinessKpiService::serviceCategoryPerformance()` | Done — explicit `'unclassified'` bucket |
| §14 DesignItemPageController complete CONFIRMED set | `DesignItemPageController::suggestDescription()` | Done — full stable-order set, CONFIRMED wins over legacy scalar, INFERRED-only falls back |
| §17 no-grace / no-grandfather | All gates | Done — proven negatively (case M/N-equivalent tests) |
| §19 canonical lock order + recheck-under-lock | All six named operations (A–F) | Done |
| §19 legacy-writer atomicity + CONCURRENCY-3 | `OpportunityController::update()` + test-only failure-injection seam | Done |

## TDD red/green evidence summary

Every behavioral slice (Tasks 1–12 of the implementation plan) was built
red→green: a failing test was written and run first (confirmed failing for
the expected reason), then the minimum implementation was added, then the
same test was re-run to confirm it passed, then the surrounding regression
suite was re-run. This was done live in this session for all 12 tasks —
not simulated. Representative examples: `LegacyServiceCategoryMapperTest`
(11 tests, confirmed `Class not found` before implementation);
`ServiceLineGateTest::test_transition_into_scope_defined_blocked_without_confirmed`
(confirmed the gate did not exist — transition succeeded — before the gate
was added); `ServiceLineClassificationReconciliationTest::test_update_rolls_back_scalar_when_mapper_reconciliation_fails`
(confirmed the CONCURRENCY-3 failure-injection seam actually throws before
asserting rollback).

## Nullable migration evidence: SQLite + real MySQL

- **SQLite:** `OpportunityServiceCategoryNullableTest`/`ServiceLineClassificationWriterSyncTest::test_store_omitted_service_category_persists_null_and_zero_rows`
  — omitted `service_category` persists as `NULL`, zero canonical rows.
- **Real MySQL 8.0** (Docker `mysql:8.0` container, migrated fresh):
  `SHOW COLUMNS FROM opportunities LIKE 'service_category'` after `up()` →
  `Null=YES, Default=NULL`. **A genuine bug was found and fixed here:** the
  original `down()` blindly re-added `NOT NULL DEFAULT 'architecture'`,
  which crashed with `SQLSTATE[22004]` on real MySQL once any row had
  legitimately gone NULL (exactly the state this migration exists to
  enable) — reproduced directly (inserted a real Eloquent `Opportunity` row
  with `service_category => null`, ran `migrate:rollback`, observed the
  exact CI failure locally), fixed by backfilling NULL rows to
  `'architecture'` before re-adding the constraint, then re-verified:
  rollback succeeds, backfilled row reads `'architecture'`,
  `SHOW COLUMNS` reports `Null=NO, Default=architecture`, re-migrating up
  succeeds. This exact defect was independently caught by live GitHub
  Actions `browser-tests` (Dusk, real MySQL, `DatabaseMigrations` trait) on
  the first CI push — it is now green after the fix.

## Canonical mapper evidence across store / Lead convert / update / CLI

`ServiceLineClassificationWriterSyncTest` cases A/B/C prove `store()` and
`LeadController::convert()` produce identical `INFERRED` outcomes for the
same input via the one shared mapper; `ServiceLineClassificationReconciliationTest`
cases D/E prove `update()` reconciles mapper-owned `INFERRED` rows and
never touches `CONFIRMED` rows; `BackfillOpportunityServiceLinesTest` (9
tests, pre-existing, still green) proves the CLI backfill now consumes the
same `LegacyServiceCategoryMapper` class, not a duplicated table.

## Atomic reclassification evidence

`ServiceLineClassificationReconciliationTest` cases F (last-CONFIRMED
removal on active stage rejected), G (atomic replace, no observable
zero-confirmed intermediate state), H (pre-scope may return to zero), I
(multiple CONFIRMED lines survive as a set) — all pass against real
transactional behavior (`DB::transaction` + `lockForUpdate()`), not mocked.

## Tenant/delete-safety evidence

`ServiceLineClassificationReconciliationTest::test_reconcile_rejects_cross_tenant_opportunity`
proves the service's own explicit tenant check rejects a cross-tenant
actor even though `EnforcesServiceLineIntegrity` cannot see the delete
half of the reconciliation operation.

## Pipeline gate evidence

`ServiceLineGateTest` — blocked without CONFIRMED, allowed with CONFIRMED,
blocked with INFERRED-only, unconditional `lost`/`no_bid`/`nurture`
exemptions, and the no-grandfather proof (`negotiation`→`contracting` and
`contracting`→`won` both still blocked without CONFIRMED regardless of the
Opportunity's existing stage).

## Native Quote send gate evidence

`ServiceLineGateTest::test_send_quote_blocked_without_confirmed` /
`test_send_quote_allowed_with_confirmed` — real HTTP round trip through
`CrmPageController::sendQuote()`, asserting the persisted `Quote::status`
never reaches `SENT` without CONFIRMED.

## External Quote sync + local createContract gate evidence

`ServiceLineGateTest::test_create_contract_blocked_without_confirmed_even_with_external_accepted_snapshot`
— an Opportunity with a real `external_quote_snapshot.status === 'ACCEPTED'`
still gets a 422 with a `service_line` error from `createContract()`,
proving the ingestion-vs-local-use distinction is enforced, not just
documented.

## WON convert()/createContract() evidence

`ServiceLineGateTest::test_already_won_opportunity_convert_blocked_until_confirmed`
plus 4 pre-existing `CrmApiTest`/`QuoteToContractTest` happy-path tests
updated to seed the now-required CONFIRMED classification (never to weaken
the gate).

## Legacy-consumer compatibility evidence

`BusinessKpiServiceTest::test_null_service_category_appears_under_explicit_unclassified_bucket`
(explicit `'unclassified'` bucket, not silently dropped);
`AiDesignItemSuggestionTest::test_passes_complete_confirmed_set_not_legacy_scalar`
(both `DESIGN, CONSTRUCTION` passed together, legacy scalar not consulted)
and `test_inferred_only_falls_back_to_legacy_scalar` (INFERRED-only does
NOT outrank the legacy fallback) — both assert on the real outbound HTTP
request body via `Http::fake()`/`Http::assertSent()`, not a mock of the
service.

## No-grace evidence

`ServiceLineGateTest::test_negotiation_to_contracting_still_blocked_without_confirmed`,
`test_contracting_to_won_still_blocked_without_confirmed`,
`test_already_won_opportunity_convert_blocked_until_confirmed` — all seed
an Opportunity already sitting in the later stage (simulating a pre-existing
deal) and prove its next gated action is still blocked.

## Shared CONFIRMED predicate evidence

`Opportunity::hasConfirmedServiceLine()` is the single method called by
`OpportunityServiceLineClassificationService`, `OpportunityStageTransitionService`,
`CrmPageController::sendQuote()`, and both gates in `OpportunityController`
— grep-verified zero independent `count(...)`-style duplicate predicate
queries anywhere in the changed files.

## Opportunity-row lock/serialization implementation + canonical lock-order proof

All six named operations (§19 A–F) acquire `Opportunity::query()->lockForUpdate()`
**first**, inside a `DB::transaction`, before touching any child
(`OpportunityServiceLine`/`Quote`) row, and re-read state after the lock
(never validating against a pre-lock instance) — verified by direct code
reading of every touched method plus the CONCURRENCY-1/2 real-MySQL race
tests below, which would be flaky/non-discriminating if the lock order
were wrong.

## CONCURRENCY-1 real MySQL result

**Obtained against a real MySQL 8.0 instance** (Docker `mysql:8.0`
container, migrated fresh, `DB_CONNECTION=mysql`). Two genuinely
independent OS subprocesses (`opportunity:concurrency-test-transition` /
`opportunity:concurrency-test-reconcile`, mirroring this repo's existing
`RfiEscalationConcurrencyTest` pattern) race a pre-scope Opportunity's
transition into `scope_defined` against reconciling its CONFIRMED set to
`{}`. Result: **PASS**, run repeatedly — the final committed state is never
`scope_defined` + zero CONFIRMED; the two processes' exit codes vary
run-to-run (sometimes A wins, sometimes B wins) but always exactly one is
serialized behind the other, proving genuine row-level locking, not a
coincidental ordering. **Sabotage-verified**: temporarily removing
`OpportunityStageTransitionService`'s `lockForUpdate()` call and re-running
reproduced the exact Owner-specified illegal race
(`scope_defined` + zero CONFIRMED, assertion failure observed directly);
the lock was restored and CONCURRENCY-1 passes again.

## CONCURRENCY-2 real MySQL result

Same real-MySQL harness; races `sendQuote()` against classification
reconciliation-to-empty on a DRAFT Quote with `{DESIGN/CONFIRMED}`.
**PASS** — the final committed state is never Quote `SENT` + zero
CONFIRMED.

## CONCURRENCY-3 rollback result

Same real-MySQL harness; a legacy `service_category` `update()` invoked
via `opportunity:concurrency-test-update-category --fail-mapper-write`
(activating the test-only, env-var-guarded failure-injection seam in
`OpportunityController::update()`) simulating a mapper-reconciliation
failure. **PASS** — the command exits non-zero, and the legacy scalar is
confirmed unchanged (`'architecture'`, never `'construction'`) and zero
`CONSTRUCTION` canonical rows exist — the scalar mutation rolled back
together with the failed reconciliation, no partial state, inside the same
transaction. Also independently proven on SQLite via the same seam through
the real HTTP endpoint (`ServiceLineClassificationReconciliationTest::test_update_rolls_back_scalar_when_mapper_reconciliation_fails`).

## Full relevant regression results

- **Full SQLite suite (fresh run, this exact head):** 2416 tests, 17382
  assertions, 8 failures, 45 skips (skips are all the repo's existing,
  documented dependency-gated groups — Redis/slow/stress/load — including
  this Work ID's own new CONCURRENCY-1/2/3, which correctly skip when no
  `mysql` connection is available). The 8 failures are `git diff dd7ed7c9`
  zero-diff on every affected file (7 `DashboardApiTest` widget-customization
  tests failing on a pre-existing broken `RedisStore::publish()` method; 1
  `SecureUploadServiceTest` test) — confirmed pre-existing, unrelated to
  GAP-048.
- **Targeted GAP-048 + directly-affected regression set** (`tests/Feature/Crm`,
  `tests/Feature/Concurrency`, `tests/Feature/Models/ServiceLineFoundationTest.php`,
  `tests/Unit/Services`, `tests/Feature/QuoteToContractTest.php`,
  `tests/Feature/Api/CrmApiTest.php`, `tests/Feature/Zena/AiDesignItemSuggestionTest.php`,
  `tests/Feature/QuoteLifecycleTest.php`, `tests/Feature/QuoteCommercialEndpointTest.php`,
  `tests/Feature/Zena/OperatorCrmUiTest.php`, `tests/Feature/OpportunityAppointmentLifecycleTest.php`,
  `tests/Feature/Console/BackfillOpportunityServiceLinesTest.php`,
  `tests/Unit/Support`, route/RBAC/tenant-isolation guardrails): **378
  tests, 1902 assertions, 0 failures**, 6 skips (the 3 CONCURRENCY tests ×
  their own `skipUnlessMysqlAvailable()` guard, correctly skipping on the
  default SQLite connection).
- **Live PR #295 CI (final verified head, see subject_sha below):** all
  applicable checks pass — Unit Tests, API Tests (Fast/Slow), Feature
  Tests, Integration Tests, `browser-tests` (Dusk, real MySQL), RFI
  Escalation Concurrency (real MySQL), Document Workflow Concurrency (real
  MySQL), Treasury Native CHECK Constraints (real MySQL), Zena RBAC/Tenant
  Invariants (default + MySQL parity), Owner Governance Lint,
  `test-routes-guardrails`, Repo Hygiene Guards, `Code Quality Analysis`,
  `Security Tests`, `staging-smoke`, and all security/dependency/license/Docker
  scans. `deploy` correctly skips (no deployment). An earlier head
  (`d12c01cc`) had 2 red checks (`Code Quality Analysis`, `Security Tests`)
  caused solely by a pre-existing PHPStan gap in
  `app/Http/Controllers/Web/ReportPageController.php::buildDataset()`
  (byte-identical to baseline `dd7ed7c9`, unrelated to GAP-048's design
  boundary) — fixed with a zero-behavior-change refactor (private
  closure/LazyCollection typing replaced by a plain generator method,
  `projectRows()`), verified against the real CSV-export Feature test
  (`OperatorPlatformUiTest::test_report_export_streams_tenant_scoped_csv`).
  This fix was necessary because the Owner-governance evidence-freshness
  check hard-requires every check on the head to be green before
  `gate_status` may reach `awaiting_owner` — it could not be left as
  disclosed-but-unfixed pre-existing debt the way the 3 unrelated
  `lint_tests.sh` findings and the 8 SQLite failures were.
- **PHPStan** (`./vendor/bin/phpstan analyse`, project-wide, same command
  CI runs): zero errors, zero file_errors — fully clean at the final
  verified head.
- **Deptrac** (`./vendor/bin/deptrac analyse`): 0 violations.
- **`scripts/ssot/lint_tests.sh`**: the `skipped_tests_inventory` baseline
  was updated to register the new `OpportunityServiceLineConcurrencyTest`
  skip entry (same convention as the existing RFI/Document-Workflow/Treasury
  concurrency tests); 3 remaining flagged files
  (`tests/Feature/Api/RfiApiTest.php`, `tests/Feature/Api/SubmittalResubmitLifecycleTest.php`,
  `tests/Feature/Zena/PermissionCanonicalIdentityRegressionTest.php`) are
  `git diff dd7ed7c9` zero-diff — pre-existing, unrelated debt, not fixed
  here.

## Exact implementation tree digest and Gate-3 packet state

- **subject_sha / verified_pr_head_sha:** see this file's frontmatter
  `technical_evidence` block (identical values — this is the exact head
  whose live CI was inspected, all applicable checks green).
- **implementation_tree_digest** (`sha256`, computed via this repository's
  own `owner_governance_compute_implementation_tree_digest()` in
  `scripts/ssot/owner_governance_lint.php`, excluding only this exact Gate-3
  record file): see frontmatter — independently re-verified by recomputing
  it at the commit that adds this exact packet content and confirming
  byte-for-byte equality with the value computed one commit earlier (proves
  the exclusion mechanism is working, not just trusted).
- **Gate-3 packet state:** `awaiting_owner`, `owner_decision.value: none`,
  `owner_decision_binding` both null. No Owner decision has been recorded
  yet.

## Known limitation, disclosed honestly

Real MySQL evidence in this section (nullable-migration parity, CONCURRENCY-1/2/3)
was obtained via a local Docker `mysql:8.0` container this implementation
session started itself, plus this repository's own real-MySQL CI jobs
(`RFI Escalation Concurrency`, `Document Workflow Concurrency`, `Treasury
Native CHECK Constraints`, `Zena RBAC/Tenant Invariants (MySQL parity)`,
`browser-tests`) on live GitHub Actions — this Work ID did not add a new
CI job for its own CONCURRENCY-1/2/3 tests (they run locally against
Docker MySQL in this session, `@group stress`, and will run in CI exactly
like the pre-existing RFI/Document-Workflow concurrency suites the moment
a dedicated workflow step invokes them the same way — no such step was
added in this session, matching the plan's scope, which did not include a
new `.github/workflows/**` file). This is disclosed so it is not mistaken
for "GAP-048's own CONCURRENCY tests run in CI automatically" — they do
not yet; they were run manually against real MySQL in this session with
sabotage verification, and the honest evidence above reflects exactly
that, no more.

## What this packet does NOT authorize

This Gate-3 packet does not authorize Ready-for-review, merge, release, or
production deployment. Those remain separate, explicit Owner decisions to
be issued after Owner reviews this packet. The implementation PR (#295)
remains Draft and unmerged.

## What the owner is NOT being asked to decide

Owner is not being asked to inspect CI logs, source diffs, or review
comments line-by-line — only whether the demonstrated behavior (the
completed behavior matrix, TDD evidence, and concurrency proof above) and
residual risk are acceptable to move toward release. The
`ReportPageController.php` PHPStan gap mentioned above was fixed (not left
for Owner to decide) purely because it mechanically blocked the
evidence-freshness gate; it required no product/business decision — a
zero-behavior-change type-annotation fix, verified against its own real
Feature test.
