---
work_id: GAP-046
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
  spec: docs/superpowers/specs/2026-08-25-gap-046-service-line-foundation-design.md
  plan: docs/superpowers/plans/2026-08-28-gap-046-service-line-foundation-implementation.md
  branch: feat/GAP-046-service-line-foundation
  pr: "https://github.com/kha997/zenamanagephp/pull/292"
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
  created_at: "2026-08-28T09:18:14Z"
  updated_at: "2026-08-28T16:26:22Z"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-046 implementation (Canonical Service-Line Foundation: ServiceLine/ServiceLineProvenance constants; opportunity_service_lines + project_service_lines migrations, Option B; OpportunityServiceLine/ProjectServiceLine models sharing EnforcesServiceLineIntegrity for value validation + parent-derived tenant_id enforcement; Opportunity::serviceLines()/Project::serviceLines() relations; service-lines:backfill-opportunities idempotent Opportunity-side-only backfill) is technically complete, strictly within the approved Gate-2 §11 boundary, and verified. Diff against canonical baseline 9944e1b50de515accb68bd5fd67347747620c6d3 is exactly 15 files, 1583 insertions, 0 deletions, 0 modifications to any pre-existing production file's behavior beyond the two additive relation methods on Opportunity.php/Project.php (12/13 lines each, doc comment + method only) — confirmed by explicit grep across the diff for OpportunityController, LeadController, CrmPageController, DesignItemPageController, BusinessKpiService, and every pre-existing migration filename: zero matches. TDD followed strictly: every behavior slice (constants, migrations, models+relations, backfill command, no-propagation regression) was committed only after its test was observed RED for the expected reason (class/method/command not found) and then GREEN. 24 focused GAP-046 tests pass on SQLite; the same 24 were independently re-run against a real MySQL 8.0 container (same image used by this repo's CI) with identical results, and the two new migrations' up()/down() round-trip was verified separately on both SQLite and that real MySQL instance, confirming SHOW CREATE TABLE output carries genuine DB-level FK constraints (opp_service_lines_tenant_id_foreign, opp_service_lines_opportunity_id_foreign, proj_service_lines_tenant_id_foreign, proj_service_lines_project_id_foreign, both created_by FKs) and the (tenant_id, opportunity_id, service_line) / (tenant_id, project_id, service_line) unique constraints — not merely SQLite-level or application-level claims. All acceptance criteria A-K independently proven by name (see full report). All 33 live CI checks on exact PR #292 head 037758fff502d738eac31e1a08a8e7a4e3701c2b are green (Owner Governance Lint, test-routes-guardrails, Unit/Feature/Integration/API Tests, Zena RBAC/Tenant Invariants incl. MySQL parity, Document Workflow/RFI Escalation/Treasury Native CHECK Constraints real-MySQL jobs, Security/Code-Quality/Dependency/License/Docker scans, Performance Tests, browser-tests, staging-smoke, coverage-report, quality-gate); deploy correctly shows 'skipping' (no production deployment occurred or is implied, consistent with this repo's established pattern when deploy secrets are not configured for a Draft PR). One real defect was found and fixed during CI verification, not silently absorbed: PHPStan flagged missingType.generics on the four new relation methods (no Larastan in this repo, so HasMany/BelongsTo generics must be declared explicitly) — fixed with the exact @return <Type><Generic> PHPDoc convention already used by Project::designItems(), annotation-only, zero behavior change, re-verified green both locally (all 24 GAP-046 tests still pass) and on the next live CI run. This packet requests Owner Gate 3 decision only; it does not request or imply Ready-for-review, merge, release, or deployment authorization."
technical_evidence:
  subject_sha: "037758fff502d738eac31e1a08a8e7a4e3701c2b"
  implementation_tree_digest: "35d9d9c8d44a745aa284cbfaf77b03039801b091a90da2a069b535ad604a472b"
  verified_pr_head_sha: "037758fff502d738eac31e1a08a8e7a4e3701c2b"
  verified_at: "2026-08-28T09:18:14Z"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-046 — Canonical Service-Line Foundation: Gate 3 Release Request

## Status: awaiting_owner — technical readiness complete, Owner Gate 3 decision requested

This packet is prepared following the approved GAP-046 Gate 2 design
(`docs/owner-decisions/GAP-046/02-design.md`, Owner Round 2 FINAL APPROVAL,
squash-merged to main at `9944e1b50de515accb68bd5fd67347747620c6d3`) and the
implementation plan `docs/superpowers/plans/2026-08-28-gap-046-service-line-foundation-implementation.md`.
It records technical readiness for Owner review only. **It does not
authorize Ready-for-review, merge, release, or production deployment** —
those require a separate, explicit Owner instruction after Gate 3 is
decided.

## Three SHAs — do not conflate

1. **Canonical implementation baseline:** `9944e1b50de515accb68bd5fd67347747620c6d3`
   (main, the approved Gate-2 PR #288 squash-merge SHA; verified identical
   to `origin/main` at the start of this implementation session — zero
   drift).
2. **Implementation subject_sha / current PR head (identical in this
   case):** `037758fff502d738eac31e1a08a8e7a4e3701c2b` — the last commit on
   PR #292 before this Gate-3 record; what `technical_evidence.implementation_tree_digest`
   is computed against.
3. This packet's own commit (adding this file) is excluded from the
   implementation-tree digest by construction
   (`owner_governance_compute_implementation_tree_digest()` excludes exactly
   `docs/owner-decisions/GAP-046/03-release*.md`), so the digest is
   unchanged whether recomputed before or after this file's own commit —
   only a change to any OTHER file would move it.

## What changed (`9944e1b50de515accb68bd5fd67347747620c6d3` → `037758fff502d738eac31e1a08a8e7a4e3701c2b`)

```
 app/Console/Commands/BackfillOpportunityServiceLines.php                          | 122 ++
 app/Models/Concerns/EnforcesServiceLineIntegrity.php                              |  64 ++
 app/Models/Opportunity.php                                                        |  12 +
 app/Models/OpportunityServiceLine.php                                             |  60 ++
 app/Models/Project.php                                                            |  13 +
 app/Models/ProjectServiceLine.php                                                 |  63 ++
 app/Support/ServiceLine.php                                                       |  20 ++
 app/Support/ServiceLineProvenance.php                                             |  24 ++
 database/migrations/2026_08_28_120000_create_opportunity_service_lines_table.php  |  43 ++
 database/migrations/2026_08_28_120001_create_project_service_lines_table.php      |  46 ++
 docs/superpowers/plans/2026-08-28-gap-046-service-line-foundation-implementation.md | 542 ++
 tests/Feature/Console/BackfillOpportunityServiceLinesTest.php                     | 191 ++
 tests/Feature/Crm/OpportunityConversionUnchangedTest.php                          |  86 ++
 tests/Feature/Models/ServiceLineFoundationTest.php                                | 268 ++
 tests/Unit/Support/ServiceLineTest.php                                            |  29 ++
 15 files changed, 1583 insertions(+)
```

Exactly the substantive scope §11 of the approved Gate-2 design authorized:
2 migrations, shared value constants, 2 thin models + shared integrity
trait, 2 relations (12/13-line additive diffs on `Opportunity.php`/`Project.php`
— doc comment + method only, no other line touched), 1 backfill command,
focused tests, the implementation plan, and (this commit) the Gate-3
packet. No unrelated file. Explicit grep across the full diff for
`OpportunityController`, `LeadController`, `CrmPageController`,
`DesignItemPageController`, `BusinessKpiService`, and every pre-existing
migration filename: **zero matches**.

## Acceptance matrix (Gate 2 §12) — proof by test

| # | Criterion | Proof |
|---|---|---|
| A | `service_line` accepts exactly DESIGN/CONSTRUCTION/INSPECTION | `ServiceLineFoundationTest::test_service_line_accepts_exactly_the_three_canonical_values` |
| B | Invalid `service_line`/`provenance` rejected | `test_invalid_service_line_is_rejected`, `test_invalid_provenance_is_rejected` |
| C | Legacy architecture family → DESIGN/INFERRED only | `BackfillOpportunityServiceLinesTest::test_architecture_family_creates_only_design_inferred_rows` |
| D | Legacy construction → CONSTRUCTION/INFERRED only | `test_construction_creates_only_construction_inferred_row` |
| E | inspection/consulting/combined_package → zero rows | `test_inspection_consulting_combined_package_create_zero_rows` |
| F | null/unrecognized → zero rows | `test_unrecognized_creates_zero_rows` (unrecognized string proxy — `service_category` is NOT NULL at the DB level, so a literal null is not representable; documented in the test) |
| G | Backfill never CONFIRMED | `test_backfill_never_creates_confirmed_provenance` |
| H | Backfill idempotent | `test_backfill_is_idempotent` |
| I | Cross-tenant write rejected, both sides | `ServiceLineFoundationTest::test_cross_tenant_write_is_rejected_for_opportunity`, `test_cross_tenant_write_is_rejected_for_project` |
| J | Project-side backfill count exactly zero | `test_project_service_lines_table_has_zero_rows_by_default` (+ no Project-side backfill mechanism exists anywhere in the diff) |
| K | No runtime Opportunity→Project propagation | `OpportunityConversionUnchangedTest::test_won_to_project_conversion_creates_zero_service_line_rows` |

Also proven: tenant-scoped visibility (`test_tenant_scoped_visibility`),
unique-constraint duplicate rejection via real DB exception
(`test_duplicate_membership_is_rejected_by_unique_constraint`), tenant_id
derivation from parent (`test_tenant_id_is_derived_from_opportunity_parent_not_caller_input`),
both relations' seeded-row behavior, migration up/down round-trip
(`test_migration_round_trip_leaves_no_trace`), dry-run writes nothing,
`service_category` never modified by backfill.

## MySQL vs SQLite vs application-layer — explicit distinction

- **SQLite:** `php artisan migrate:fresh --env=testing` succeeds (full
  existing chain + the 2 new migrations); `migrate:rollback --step=2`
  removes exactly `opportunity_service_lines`/`project_service_lines`,
  confirmed via `Schema::hasTable()`, with `opportunities`/`projects`
  confirmed still present and unaltered.
- **Real MySQL 8.0** (local Docker `mysql:8.0`, the same image this repo's
  CI uses as a service container): identical migrate/rollback round-trip
  verified independently; `SHOW CREATE TABLE opportunity_service_lines` /
  `project_service_lines` inspected directly, confirming genuine DB-level
  `CONSTRAINT ... FOREIGN KEY` clauses (not merely declared in the
  migration) for `tenant_id`, `opportunity_id`/`project_id`, and
  `created_by`, plus a genuine `UNIQUE KEY` on
  `(tenant_id, opportunity_id|project_id, service_line)`; all 24 focused
  GAP-046 tests re-run against this real MySQL instance with identical
  pass results to SQLite, including the unique-constraint-violation test
  (raises a real MySQL duplicate-key `QueryException`, not a SQLite-only
  behavior).
- **Live CI real MySQL:** PR #292's `test-routes-guardrails`,
  `Document Workflow Concurrency (real MySQL)`, `RFI Escalation
  Concurrency (real MySQL)`, and `Treasury Native CHECK Constraints (real
  MySQL)` jobs all independently ran `php artisan migrate:fresh` (building
  the complete schema including the two new tables) against a real
  `mysql:8.0` GitHub Actions service container and passed — corroborating
  the local Docker evidence above in the exact CI environment. The two new
  GAP-046 test files are not tagged `@group mysql-parity` and so do not run
  inside CI's dedicated `mysql-parity`-filtered step specifically; the
  local real-MySQL run above is the evidence source for GAP-046's own
  test-level MySQL behavior, corroborated by CI's independent proof that
  the migrations build cleanly on the exact same MySQL image/version.
- **Application-layer tenant congruence (explicitly not a DB-level
  guarantee, per Gate 2 §5's stated limitation):** the cross-tenant-write
  rejection (criterion I) is enforced by `EnforcesServiceLineIntegrity`'s
  `creating` hook at the Eloquent model layer, not by a portable composite
  DB-level `(tenant_id, opportunity_id)`/`(tenant_id, project_id)` FK —
  `projects.tenant_id`'s legacy `string` (non-`ulid`) typing makes a
  portable such constraint on the Project side impractical within this
  Work ID's scope, exactly as Gate 2 §5 anticipated and explicitly
  permitted this design not to attempt. This is stated here truthfully,
  not implied to be a DB-level guarantee.

## Local verification summary

- `tests/Unit/Support/ServiceLineTest.php`: 3/3 pass.
- `tests/Feature/Models/ServiceLineFoundationTest.php`: 12/12 pass.
- `tests/Feature/Console/BackfillOpportunityServiceLinesTest.php`: 8/8 pass.
- `tests/Feature/Crm/OpportunityConversionUnchangedTest.php`: 1/1 pass.
- Total focused: **24/24 pass**, on both SQLite and real MySQL 8.0.
- `tests/Unit/Services/BusinessKpiServiceTest.php`,
  `tests/Feature/Zena/AiDesignItemSuggestionTest.php`,
  `tests/Feature/Models/TenantScopedCrmModelsTest.php`,
  `tests/Feature/Models/OpportunityAppointmentModelTest.php`: 24/24 pass,
  unmodified — zero regression in the two documented `service_category`
  consumers or the broader CRM tenant-scoping guard.
- Full local suite (`--testsuite=Unit,Feature,Integration`, SQLite, with
  the complete GAP-046 diff present): 2353 tests, 2346 pass, 7 pre-existing
  failures, all in `Tests\Feature\Dashboard\DashboardApiTest` (dashboard
  widget customization — an area with no code path touched by this diff;
  consistent with previously-documented Redis-cache-store debt unrelated to
  CRM/Opportunity/Project). 42 skipped (pre-existing, unrelated).
- `php scripts/ssot/owner_governance_lint.php`: PASS (95 files scanned, 0
  violations).
- `php scripts/ssot/owner_governance_lint.php --enforce-gate-ordering`:
  PASS (0 violations) — required one fix during this session: the
  implementation plan initially lacked `owner_governance_version`/
  `owner_gate_2_record` frontmatter fields required by
  `docs/owner-governance/GOVERNED_DOCUMENT_FRONTMATTER.md`; corrected in
  commit `907bd847`, re-verified PASS.
- `php scripts/ci/lint-mysql-claim-truthfulness.php`: PASS (15 files
  scanned).

## Live CI — exact PR #292 head `037758fff502d738eac31e1a08a8e7a4e3701c2b`

All 33 checks green (`gh pr checks 292`, independently re-verified after
full settlement, including a job — `browser-tests` — that took ~19 minutes
and was polled to completion rather than assumed):

API Tests (Fast), API Tests (Slow), Code Quality Analysis, Dependency
Vulnerability Scan, Docker Security Scan, Document Workflow Concurrency
(real MySQL), Feature Tests, Integration Tests, License Compliance Scan,
**Owner Governance Lint**, Performance Tests (DashboardPerformanceTest.php),
Performance Tests (PerformanceMonitoringTest.php), RFI Escalation
Concurrency (real MySQL), Repo Hygiene Guards, Security Tests, Security
Vulnerability Scan, Test Coverage Report, Treasury Native CHECK Constraints
(real MySQL), Trivy, Unit Tests, Zena RBAC/Tenant Invariants, Zena
RBAC/Tenant Invariants (MySQL parity), browser-tests, button-inventory-check,
code-quality, coverage-report, feature-tests, security-tests, staging-smoke,
**test-routes-guardrails**, test, quality-gate — all `pass`. `deploy`:
`skipping` (no deployment occurred or is implied).

One genuine defect was found by live CI and fixed within approved scope: an
earlier head (`e420b192`) failed the `Security Tests` job's PHPStan step
with 4 `missingType.generics` errors on the 4 new relation methods (this
repo has no Larastan, so `HasMany`/`BelongsTo` generics require explicit
`@return` PHPDoc — confirmed via the existing `Project::designItems()`
convention). Fixed in commit `037758ff`, annotation-only, zero behavior
change, re-verified green both locally (24/24 GAP-046 tests) and on the
next live CI run.

A second, non-code CI-timing artifact was observed and resolved on this
Gate-3-record commit (`a4da050b`), the same class of artifact previously
documented in GAP-039/GAP-043/GAP-047: `Owner Governance Lint`'s
evidence-freshness step ran early in the check matrix and, per its
designed fail-closed behavior, correctly failed because `browser-tests`
(a ~17-19 minute job) and its downstream `coverage-report`/`quality-gate`
jobs had not yet reached a terminal state within evidence-freshness's
300-second wait window — `gate_status is 'awaiting_owner' but 2 other
check(s) on PR #292's current head are not green (pending or failed) after
waiting up to 300s`. This is the check working as designed, not a defect:
once `browser-tests`/`coverage-report`/`quality-gate` genuinely finished
green, `gh run rerun 33158919014 --failed` was run and `Owner Governance
Lint` passed clean (27s) with zero code/content changes. All 34 checks on
the Gate-3-record head `a4da050b7fc08ec46fa9d2dd786425da2f536098`
(one commit past `subject_sha` — only this Gate-3 record file differs,
excluded from the digest by construction) were green, independently
re-verified via `gh pr checks 292` after full settlement.

Because every push (including a Gate-3-record-only push) re-triggers this
repository's full CI matrix, committing the paragraph above (at head
`54be709e38b6087a22471f8678163e159add6aa3`, also a Gate-3-record-only
commit, digest/subject_sha still unchanged) triggered a fresh CI run that
hit the identical evidence-freshness timing race a second time, for the
identical structural reason (`browser-tests` ~18 minutes,
`coverage-report`/`quality-gate` downstream of it, evidence-freshness's
300s window elapsing first). Resolved identically:
`gh run rerun 33160398473 --failed` after independently confirming, via a
direct synchronous `gh pr checks 292` call, that every other check
(including `browser-tests` at 17m55s and `test` at 8m58s) had already
reached a genuine terminal `pass` state — `Owner Governance Lint` then
passed clean (35s). Final direct verification: `gh pr checks 292` exits
0, all 33 checks `pass` plus `deploy: skipping`, at exact head
`54be709e38b6087a22471f8678163e159add6aa3` (`gh pr view 292` confirms this
is the current PR head, state OPEN, Draft, mergeable). This two-for-two
pattern (timing race → identical one-line targeted rerun → clean pass)
confirms the mechanism is a structural property of this repo's CI
matrix interacting with Gate-3-packet-only commits, not a GAP-046 defect;
any further Gate-3-record-only push should be expected to need the same
treatment.

## Residual risks / known limitations (stated truthfully, not blocking)

1. **Application-layer-only tenant congruence on the Project side** — see
   "MySQL vs SQLite vs application-layer" above. This is a stated Gate-2 §5
   design limitation, not a defect introduced by this implementation.
2. **`null`/unrecognized backfill case F is tested via an "unrecognized
   string" proxy, not a literal NULL** — `opportunities.service_category`
   is `NOT NULL` at the DB level, so a literal null cannot be seeded; the
   test documents this explicitly. Behaviorally equivalent (the backfill's
   `MAP` lookup returns `null` for both an unrecognized string and — were
   it possible — an actual NULL, since neither is a map key).
3. **GAP-046 test files are not tagged `@group mysql-parity`**, so they run
   under a local real-MySQL harness (this session) and are corroborated
   by, but not literally inside, CI's dedicated mysql-parity-filtered
   step. The migrations themselves ARE proven live in CI via the
   `migrate:fresh` step shared by every real-MySQL CI job.
4. **7 pre-existing `DashboardApiTest` failures** remain in the local full
   suite, unrelated to GAP-046 (Dashboard widget customization, no code
   path shared with this diff) — flagged here for completeness, not
   attributed to this Work ID.

## Explicit confirmation — excluded slices untouched

No CRM classification UX, stage gates, Quote Scope Snapshot, Contract
Service-Line classification, Portfolio membership behavior, Project OPPM,
Operations Control Tower, Finance/Treasury, historical Project backfill,
`projects.tenant_id` schema normalization, runtime review/remediation UI,
or unrelated refactor was built or touched. `Opportunity.service_category`'s
default and validation are unmodified.
`OpportunityController::convert()`/`createContract()`, `LeadController`,
WON→Project propagation are unmodified (proven by
`OpportunityConversionUnchangedTest`, which exercises the unmodified
conversion path and asserts zero Service-Line propagation).

## What this packet does NOT authorize

This Gate 3 packet requests an Owner Gate 3 decision only. It does not
authorize Ready-for-review, merge, release, or production deployment —
those remain separate, explicit Owner decisions pending a Gate 3 approval
and a subsequent, separate release instruction, per this repository's
established convention (see e.g. GAP-043's and GAP-047's Gate 3 records).
PR #292 remains Draft/unmerged.
