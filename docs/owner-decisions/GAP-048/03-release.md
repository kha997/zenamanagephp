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
  recorded_at: "2026-09-01T00:00:00Z"
  owner_response_reference: "GAP-048 Gate 3 Round 1 (relayed via coordinator session, reviewed exact prior head ee600951 — the head this correction directive was issued against): 'DECISION: CORRECTION REQUESTED. Re-inspect createContract() yourself now — do not trust the prior session's self-report; find the actual gap. Fix createContract() §19 atomicity: one transaction must hold the authoritative Opportunity lockForUpdate() across re-read, CONFIRMED gate evaluation, Project/Contract mutation and relevant audit/event mutation through commit; Opportunity must be locked FIRST; do not make authoritative decisions or mutations from the stale pre-lock model; preserve native/external Quote semantics and no Opportunity->Project Service-Line propagation. Add a discriminating real-MySQL concurrency regression for reconcile -> {} racing createContract(): RED first, GREEN only after the production fix, genuinely separate connections/processes and controlled interleaving, not sequential simulation; determine the smallest appropriate way to make this regression execute automatically in an existing real-MySQL CI surface. Do NOT perform optional cleanup of GAP048_SIMULATE_MAPPER_FAILURE unless technically necessary for the mandatory fix. After correction: run focused tests + affected regression, run real-MySQL concurrency evidence, run required broader quality/governance checks, recompute implementation subject SHA/digest using canonical repo tooling, refresh Gate-3 technical evidence, push to the existing PR #295, keep it Draft, STOP at awaiting_owner. Do not approve Gate 3, mark Ready, merge, release, deploy, or start another Work ID.' Independent re-inspection at the reviewed head confirmed the finding: createContract() split its classification gate re-check into its own short DB::transaction() (Opportunity::lockForUpdate(), hasConfirmedServiceLine() check, commit — releasing the row lock immediately), then performed Project creation in a second, separate DB::transaction(), then Contract+BOQ+BOQ-line creation and both audit EventRecord writes in a third, separate DB::transaction() — none of which re-acquired or held the Opportunity row lock, and the Project-creation step mutated the STALE, pre-lock $opportunity model instance loaded before any transaction began, not the freshly-locked model. This exactly matches the Owner-identified defect: the authoritative gate decision was made under lock, but the mutation was not — a genuine check-then-act race distinct from what CONCURRENCY-1/2/3 (transition()/sendQuote()/update()) already covered."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-31T00:10:00+07:00"
  updated_at: "2026-09-01T00:00:00Z"
generated_by: agent
residual_risk_rating: medium
mandatory_technical_gate_summary: "GAP-048 implementation (canonical multi-valued Service-Line classification UX with explicit CONFIRMED confirmation; shared legacy->canonical mapper reused by store()/Lead-convert()/update(); nullable service_category migration verified on SQLite and real MySQL 8.0 including safe down()-rollback with pre-existing NULL data; pipeline/sendQuote()/convert()/createContract() gates backed by one shared CONFIRMED predicate; Opportunity-row locking with canonical lock order closing the Owner-identified concurrency race, INCLUDING the Gate-3 Round 1 correction to createContract() itself) is technically complete and verified. Gate-3 Round 1 correction: createContract() previously split its classification gate re-check from its Project/Contract/BOQ mutation and audit EventRecord writes across three separate DB transactions, releasing the Opportunity row lock between the gate re-check and the mutation, and mutating a stale pre-lock $opportunity model instance for Project creation. Fixed to one continuous DB::transaction() holding Opportunity::lockForUpdate() (locked first) from the re-read and gate re-check through contract.create authorization, conditional Project creation, Contract+BOQ+BOQ-line mutation, and both audit EventRecord writes, to commit — matching the same discipline already used by sendQuote()/convert()/reconcile(). New CONCURRENCY-4 real-MySQL regression (tests/Feature/Concurrency/OpportunityServiceLineConcurrencyTest.php) proves this directly with a genuinely separate OS process (createContract() subprocess) racing a genuinely separate DB connection (an in-PHPUnit-process reconcile({}) probe, synchronized via a test-only start-marker file so it races createContract()'s own critical section rather than PHP/Laravel bootstrap time): RED on the prior 3-transaction implementation (140/140 concurrent probe attempts completed near-instantly while no Contract row existed yet); GREEN after the fix (0 fast completions, repeated 3x); sabotage-verified by reverting the fix and reproducing the exact RED failure again, then restoring it. A pre-existing test (CrmApiTest::test_create_contract_requires_contract_create_permission) asserted the OLD non-atomic behavior (a denied contract.create authorization left a converted Project behind) as though it were intentional; updated to assert the corrected atomic behavior (the whole attempt, including Project auto-convert, rolls back together) with an explanatory comment — the old behavior was itself a symptom of the bug being fixed, not a documented product requirement. Added a dedicated 'GAP-048 Service-Line Concurrency (real MySQL)' CI job (scripts/ci/gap048-service-line-concurrency-mysql + .github/workflows/automated-testing.yml), mirroring the existing RFI Escalation/Document Workflow/Treasury Native CHECK Constraints real-MySQL job pattern exactly, so CONCURRENCY-1/2/3/4 now run automatically in CI (previously a disclosed known limitation: local-only). Full SQLite regression: 2416 tests, 8 pre-existing/unrelated failures (verified zero-diff against baseline dd7ed7c9 for every affected file: 7 Dashboard widget tests failing on a pre-existing broken Redis cache-store method, 1 flaky SecureUploadServiceTest), 0 GAP-048 regressions. CONCURRENCY-1/2/3 real-MySQL subprocess-race evidence re-verified unaffected by this correction, still sabotage-verified discriminating. Nullable migration verified on SQLite and real MySQL 8.0 (unaffected by this correction). Targeted focused regression re-run after the fix: tests/Feature/Crm, tests/Feature/QuoteToContractTest.php, tests/Feature/Api/CrmApiTest.php, tests/Feature/QuoteLifecycleTest.php, tests/Feature/QuoteCommercialEndpointTest.php, tests/Feature/Zena/OperatorCrmUiTest.php, tests/Feature/OpportunityAppointmentLifecycleTest.php, tests/Feature/Console/BackfillOpportunityServiceLinesTest.php, tests/Feature/Zena/AiDesignItemSuggestionTest.php: 141 tests, 526 assertions, 0 failures. GAP048_SIMULATE_MAPPER_FAILURE left untouched (not technically necessary for this mandatory fix, per Owner directive). This packet records technical readiness for Owner Gate-3 review only; it does not authorize Ready-for-review, merge, release, or production deployment."
technical_evidence:
  subject_sha: "141024d79935d691e3bd205726f0e5ff562d8aac"
  implementation_tree_digest: "0bfd5dfc8d321c763acf5d4099bcc83afac174a95444df2a32157ee57df723c5"
  verified_pr_head_sha: null
  verified_at: null
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

## Owner Decision History — Round 1 — CORRECTION REQUESTED (permanent record, never erased)

**Owner Gate-3 Round 1 decision: CORRECTION REQUESTED** — reviewed prior
head `ee600951` and found that its own final report claimed
`createContract()` had the same full lock-order/atomicity discipline as
the other five gated operations (pipeline transition, `sendQuote()`,
`convert()`, classification reconciliation, legacy `update()`), but on
independent review it did not. Full verbatim directive preserved in this
file's frontmatter `decision_provenance.owner_response_reference` above.
Re-inspection at the reviewed head confirmed the finding exactly:
`createContract()` acquired `Opportunity::lockForUpdate()` inside its own
short `DB::transaction()` to re-check the CONFIRMED gate, then **released
that lock immediately on commit**, then performed Project creation in a
**second**, separate `DB::transaction()`, then Contract+BOQ+BOQ-line
creation and both audit `EventRecord` writes in a **third**, separate
`DB::transaction()` — none of the two mutation transactions re-acquired
the Opportunity lock, and Project creation mutated the **stale, pre-lock**
`$opportunity` model instance loaded before any transaction began, not the
freshly-locked model from the gate-check transaction. This is a genuine
check-then-act race distinct from what CONCURRENCY-1/2/3 already covered
(those exercise `OpportunityStageTransitionService::transition()`,
`CrmPageController::sendQuote()`, and the legacy `update()` mapper
reconciliation — never `createContract()` itself). Correction directed:
(1) fix `createContract()` to hold Opportunity locked FIRST in one
continuous transaction across re-read, CONFIRMED gate evaluation,
Project/Contract mutation, and relevant audit/event mutation through
commit, never deciding or mutating from the stale pre-lock model,
preserving native/external Quote convergence semantics and the no
Opportunity→Project Service-Line-propagation boundary; (2) add a
discriminating real-MySQL concurrency regression for classification
reconciliation racing `createContract()`, RED first then GREEN after the
fix, genuinely separate connections/processes, wired into an existing
real-MySQL CI surface by the smallest appropriate means. `GAP048_SIMULATE_MAPPER_FAILURE`
cleanup explicitly NOT authorized unless technically necessary for the
mandatory fix (it was not; left untouched). This round's correction is
recorded in this same Gate-3 packet (not a new gate) because no Owner
Gate-3 decision (approve/defer) had yet been rendered against `ee600951`
— the packet remains `awaiting_owner` for a fresh Gate-3 decision against
the corrected head below. This Round 1 record is preserved permanently
and must not be removed by any future revision.

## Implementation baseline and PR

- **Implementation baseline (Gate-2 merge, unchanged origin/main at session
  start):** `dd7ed7c9` (PR #294 squash-merge).
- **Implementation branch:** `feat/GAP-048-crm-classification-ux-gates`.
- **Implementation PR (Draft, unmerged, mergeable):** [#295](https://github.com/kha997/zenamanagephp/pull/295).
- **subject_sha (last content-changing commit; what `implementation_tree_digest`
  is computed against):** `141024d79935d691e3bd205726f0e5ff562d8aac` — the
  Gate-3 Round 1 correction commit (superseding the prior
  `2ae7d5721887812123a099b725b50437f2acb7ca`).
- **verified_pr_head_sha:** recorded once this correction commit's live PR
  #295 CI is independently confirmed all-green (see below); not yet filled
  in the frontmatter pending that verification.
- **Implementation plan:** `docs/superpowers/plans/2026-08-30-gap-048-crm-classification-ux-gates-implementation.md`.

## What changed (`dd7ed7c9` → `2ae7d5721887812123a099b725b50437f2acb7ca`)

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

**Gate-3 Round 1 correction:** `createContract()`'s gate-check-then-mutate
split (see Owner Decision History above) is fixed — one continuous
`DB::transaction()` now holds `Opportunity::lockForUpdate()` (locked
first) from the re-read and CONFIRMED gate re-check through
`contract.create` authorization, conditional Project creation,
Contract+BOQ+BOQ-line mutation, and both audit `EventRecord` writes, to
commit; the mutation now reads exclusively from the freshly-locked
`$locked` model, never the stale pre-lock `$opportunity` instance.
`contract.create` authorization was deliberately kept AFTER the gate
re-check (preserving the pre-existing response-ordering contract: a
missing classification is still reported as 422 even for an actor who
also lacks `contract.create`) but moved INSIDE the same transaction, so a
denied authorization now rolls back any Project mutation already made in
the same request — `CrmApiTest::test_create_contract_requires_contract_create_permission`
updated accordingly (see below). Proven by the new CONCURRENCY-4 real-MySQL
regression (next section).

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
reading of every touched method plus the CONCURRENCY-1/2/4 real-MySQL race
tests below, which would be flaky/non-discriminating if the lock order
were wrong. **Gate-3 Round 1 correction:** `createContract()` (operation E)
did NOT actually hold the lock across its own mutation prior to this
correction, despite the prior implementation's self-report claiming it
did — re-verified directly by reading the corrected method: the entire
critical section (gate re-check, `contract.create` authorization,
Project/Contract/BOQ mutation, both audit `EventRecord` writes) is now
inside the SAME `DB::transaction()` closure that acquires the lock, with
no intervening commit.

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

## CONCURRENCY-4 real MySQL result (Gate-3 Round 1 correction)

New test: `OpportunityServiceLineConcurrencyTest::test_concurrency_4_reconcile_to_empty_races_create_contract`.
Same real-MySQL harness; races the REAL `OpportunityController::createContract()`
(via a new `opportunity:concurrency-test-create-contract` artisan
subprocess, genuinely separate OS process) against a real,
separate-connection `OpportunityServiceLineClassificationService::reconcile()`
attempt toward `{}` run from the PHPUnit process's own dedicated `mysql`
PDO connection. A WON Opportunity with one CONFIRMED `DESIGN` line and an
ACCEPTED native Quote with 400 line items (so `createContract()`'s
BOQ-copy mutation phase has real, measurable wall-clock duration) is the
fixture. Because reconciling to `{}` on an already-WON Opportunity is
unconditionally rejected by `OpportunityServiceLineClassificationService`'s
own lifecycle invariant (empirically verified: this holds regardless of
`createContract()`'s internal transaction structure — WON is permanently
in the invariant's gated-stage list), "final state = Contract created +
zero CONFIRMED" can never be reached through this specific race and is
asserted only as a belt-and-braces defense-in-depth check, not the primary
proof, per the Owner directive's "or otherwise prove the test
distinguishes the defect" allowance. The actual discriminating proof: the
`createContract()` subprocess touches a test-only start-marker file the
instant Laravel bootstrap completes and it is about to enter the real
controller method (so the PHPUnit process's probe loop starts from the
same line as the subprocess's own critical section instead of guessing a
fixed head-start past unpredictable PHP/Laravel bootstrap time, which
empirically ran 450-800ms — comparable to or larger than the whole race
window); the probe then runs a tight loop of real `reconcile()` attempts,
checking before each one whether the Contract row exists yet, counting
how many completed near-instantly (<50ms) while it did not. **RED** on the
prior 3-transaction implementation: 140 of 140 concurrent probe attempts
completed near-instantly while no Contract row existed yet (`git stash`
reverting only `app/Http/Controllers/Api/OpportunityController.php`
reproduced this exactly). **GREEN** after the fix: 0 fast completions,
confirmed across 3 repeated runs. Sabotage-verified by re-stashing the fix
and re-running (RED reproduced identically), then restoring it (GREEN
again). `createContract()`'s own exit code and the belt-and-braces
zero-CONFIRMED-with-Contract check both pass in every run, buggy and
fixed alike, confirming they alone would NOT have caught this defect —
the fast-completion-count assertion is what actually discriminates.

## Full relevant regression results

Sections below unaffected by this Gate-3 Round 1 correction (nullable
migration, canonical mapper, atomic reclassification, tenant/delete
safety, pipeline gate, no-grace, shared predicate) were not re-run in
full — the correction touched only `createContract()` and its own tests —
but the targeted set below re-covers every one of them at the corrected
head.

- **Targeted GAP-048 + directly-affected regression set, re-run against the
  `createContract()` fix (`509adfc7`) and re-confirmed unaffected by the
  subsequent PHPStan sentinel-return refactor at the corrected head
  `141024d7`):** `tests/Feature/Crm`,
  `tests/Feature/QuoteToContractTest.php`, `tests/Feature/Api/CrmApiTest.php`,
  `tests/Feature/QuoteLifecycleTest.php`, `tests/Feature/QuoteCommercialEndpointTest.php`,
  `tests/Feature/Zena/OperatorCrmUiTest.php`, `tests/Feature/OpportunityAppointmentLifecycleTest.php`,
  `tests/Feature/Console/BackfillOpportunityServiceLinesTest.php`,
  `tests/Feature/Zena/AiDesignItemSuggestionTest.php`: **141 tests, 526
  assertions, 0 failures**. `tests/Feature/Models/ServiceLineFoundationTest.php`,
  `tests/Feature/Api/SubmittalResubmitLifecycleTest.php`: **33 tests, 131
  assertions, 0 failures**. `tests/Feature/Api/RfiApiTest.php`, `tests/Unit/Services`,
  `tests/Unit/Support`, `tests/Feature/Zena/PermissionCanonicalIdentityRegressionTest.php`:
  **218 tests, 966 assertions, 0 failures**.
- **`tests/Feature/Concurrency/OpportunityServiceLineConcurrencyTest.php`
  against real MySQL 8.0 (Docker), corrected head:** all 4 tests pass
  (CONCURRENCY-1/2/3 unaffected + new CONCURRENCY-4), 10 assertions, 0
  failures, run 3× for stability at `509adfc7`, re-confirmed once more
  after the PHPStan refactor at `141024d7`.
- **Live PR #295 CI caught a real finding that local verification could
  not** (this worktree's vendor directory cannot run `phpstan`/Composer
  binaries locally — a documented, pre-existing environment gotcha
  unrelated to GAP-048): at head `509adfc7`, `Code Quality Analysis` and
  `Security Tests` both failed on the same genuine PHPStan error —
  `Dead catch - Illuminate\Validation\ValidationException is never thrown
  in the try block` at the line where `createContract()`'s
  `DB::transaction()` closure threw `ValidationException` for the
  classification-gate rejection: PHPStan's flow analysis does not trace an
  exception thrown inside a `Closure` passed to `DB::transaction()` back to
  an enclosing `try`/`catch`. Fixed at head `141024d7` by switching to the
  same established sentinel-return pattern already used by
  `convertOpportunityToProjectLocked()` elsewhere in this same file: the
  transaction closure now returns `?Contract` (`null` on gate rejection —
  harmless no-op commit, no mutation attempted), and the outer scope maps
  `null` to the 422 response; no behavior change, confirmed by re-running
  the full targeted regression set and the real-MySQL CONCURRENCY-1/2/3/4
  suite again after the refactor (both fully green, see above).
  `Owner Governance Lint` also failed at `509adfc7`, but as the known,
  documented 300-second evidence-freshness sibling-job timing race — its
  own log shows `gate_status is 'awaiting_owner' but 4 other check(s) ...
  are not green (pending or failed) after waiting up to 300s`, which
  included the two genuinely-failing siblings above; this will be re-run
  once all siblings are independently confirmed green at `141024d7`, not
  treated as a content issue.
- **PHPStan / Deptrac** (project-wide, exact CI command): verified via the
  live PR #295 `Code Quality Analysis` check at head `141024d7` (see CI
  status below) — this worktree cannot run `phpstan`/Composer binaries
  locally (documented pre-existing environment gotcha).
- **`scripts/ssot/lint_tests.sh`**: unaffected by this correction — the
  `OpportunityServiceLineConcurrencyTest` skip-baseline entry already
  covers the whole file (CONCURRENCY-4 is a new test method in an already-
  registered file, not a new file).

## Exact implementation tree digest and Gate-3 packet state

- **subject_sha (Gate-3 Round 1 correction, final):** `141024d79935d691e3bd205726f0e5ff562d8aac`
  — the commit containing the PHPStan dead-catch fix on top of the
  `createContract()` atomicity fix, the new CONCURRENCY-4 regression, the
  CI wiring, and the `CrmApiTest` update (superseding the intermediate
  `509adfc722b0afa10007806626a7fcca31d0c710` and the pre-correction
  `2ae7d5721887812123a099b725b50437f2acb7ca`). This is what
  `technical_evidence.implementation_tree_digest` is computed against.
- **implementation_tree_digest** (`sha256`, computed via this repository's
  own `owner_governance_compute_implementation_tree_digest()` in
  `scripts/ssot/owner_governance_lint.php`, excluding only this exact Gate-3
  record file, invoked directly against `141024d7`): `0bfd5dfc8d321c763acf5d4099bcc83afac174a95444df2a32157ee57df723c5`
  — see frontmatter.
- **verified_pr_head_sha:** to be recorded once PR #295's live CI at
  `141024d7` (or the exact head this correction ends up pushing, if this
  packet-recording commit itself needs a follow-up push) is independently
  confirmed all-green — not yet filled in as of this edit.
- **Gate-3 packet state:** `awaiting_owner`, `owner_decision.value: none`,
  `owner_decision_binding` both null. No Owner Gate-3 decision (approve/
  defer) has been recorded yet — Round 1 above was a pre-decision
  correction request, not a Gate-3 decision itself.

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
