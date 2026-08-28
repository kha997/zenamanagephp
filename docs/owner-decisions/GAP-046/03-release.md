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
  recorded_at: "2026-08-29T00:00:00Z"
  owner_response_reference: "Owner Gate 3 decision, GAP-046, Correction Round 1 (relayed via coordinator session): 'OWNER GATE-3 DECISION: CORRECTION REQUESTED (Round 1)'. Reviewed exact state: PR #292 head 63f8636fa77bdfc73401cc9bcd25f52c759fd82f, subject_sha 037758fff502d738eac31e1a08a8e7a4e3701c2b, implementation_tree_digest 35d9d9c8d44a745aa284cbfaf77b03039801b091a90da2a069b535ad604a472b, canonical main 9944e1b50de515accb68bd5fd67347747620c6d3. DECISION: CORRECTION REQUESTED, not approved. Owner directed 7 substantive corrections before Gate 3 approval, all within the existing approved Gate-2 §11 boundary (no Gate-2 reopening, no new Work ID): (1) EnforcesServiceLineIntegrity must also reject a mismatch between the ACTING/CURRENT tenant context (app('tenant') / current_tenant_id / request attribute tenant_id, same precedence as TenantScope, TenantScope itself not modified) and the parent's true tenant, not only an explicitly-set conflicting child.tenant_id — with strict RED tests first for both Opportunity and Project sides proving zero rows persisted; a legitimate no-current-tenant-context CLI backfill write must remain permitted. (2) Integrity enforcement must cover updates as well as creates (service_line/provenance/parent-resolvability/tenant congruence), via a saving-equivalent hook, with RED tests first for invalid-service_line update, invalid-provenance update, and cross-tenant parent-reassignment update, both sides where applicable. (3) Acceptance J's test must be strengthened to a discriminating scenario: an Opportunity with a legacy mappable category whose converted_project_id points at a real, pre-existing Project — proving the backfill command creates the expected Opportunity-side row while creating zero rows for the linked historical Project. (4) Acceptance K's test must be strengthened: create a WON Opportunity that already carries a real canonical Service-Line membership row before conversion, run the real conversion endpoint, and prove that membership row survives unchanged on the Opportunity side while the new Project receives zero rows — proving no propagation even when canonical data exists to propagate, without modifying OpportunityController. (5) GAP-046 DB behavior (migration/table behavior, unique constraint, tenant-parent integrity, Opportunity backfill mapping, backfill idempotency) must be proven inside this repo's canonical @group mysql-parity live-CI mechanism, not only a local Docker harness, with live-log proof of the specific GAP-046 test names actually executing on real MySQL — 'migrations built successfully' alone is insufficient. (6) Add an index on (tenant_id, service_line) to both new migrations (explicit stable names, existing unique constraints retained) since parent_id currently sits between tenant_id and service_line in the unique index's B-tree order, inefficient for 'all X-tenant subjects with Service-Line Y' queries — verified on SQLite and real MySQL SHOW INDEX/SHOW CREATE TABLE. (7) Strict TDD required throughout (RED first, minimum fix, GREEN, preserved evidence). Remediation scope is explicitly bounded to EnforcesServiceLineIntegrity.php, the two new migrations, the three existing GAP-046 test files, optionally one new narrowly-focused MySQL-parity test file, and truthful updates to the implementation plan and this Gate-3 packet; OpportunityController, LeadController, CrmPageController, DesignItemPageController, BusinessKpiService, service_category behavior, any pre-existing migration, routes/**, resources/**, RBAC/policies, projects.tenant_id schema, CRM classification UX, Quote/Contract classification, Portfolio/OPPM/Control-Tower/Finance/Treasury, GAP-041/042/045/047, and .github/** remain explicitly forbidden — if any forbidden surface appears necessary, the agent must stop and report back rather than proceed. Because these corrections change implementation content, the old subject_sha/digest above become historical only and must not be reused as current Gate-3 evidence; a new subject_sha and freshly recomputed implementation_tree_digest are required for re-presentation. PR #292 must remain Draft throughout — no Ready-flip, merge, deploy, self-approval, or new Work ID authorized by this decision. This Correction Round 1 record is permanent and must never be erased or overwritten by the re-presented packet.'"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-28T09:18:14Z"
  updated_at: "2026-08-28T18:46:38Z"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "RE-PRESENTATION after Owner Gate 3 Correction Round 1 (full directive preserved verbatim in decision_provenance.owner_response_reference and in this file's 'Correction Round 1' body section). All 6 directed corrections are remediated and independently re-verified: (1) EnforcesServiceLineIntegrity now also rejects an acting/current-tenant-context mismatch (mirroring TenantScope's exact precedence: app('tenant') -> current_tenant_id -> request attribute tenant_id) even with no explicit child tenant_id set, proven by 3 new RED->GREEN tests per side; (2) the trait now hooks `saving` (not `creating`), enforcing canonical service_line/provenance/resolvable-parent/tenant-congruence on updates too, proven by 3 new RED->GREEN tests per side (invalid service_line update, invalid provenance update, cross-tenant parent-reassignment update); (3) acceptance J is now proven via a real Opportunity.converted_project_id link to a pre-existing Project (not an unrelated empty one); (4) acceptance K is now proven with a real canonical Service-Line row seeded on the Opportunity BEFORE conversion, surviving unchanged while the new Project stays at zero rows; (5) GAP-046 DB behavior now runs inside this repo's canonical @group mysql-parity live-CI mechanism via 5 new dedicated test methods (not replacing/removing any default-suite test) added to the two existing GAP-046 test files, matching the exact method-level-tag convention already established by tests/Feature/DatabaseConstraintsTest.php; live-log-verified via `gh run view --job=98944492511 --log` on PR #292's Zena RBAC/Tenant Invariants (MySQL parity) job -- all 5 GAP-046 test method names appear explicitly in that job's own log output with a final `Tests: 41 passed (1278 assertions)` summary and zero FAILURES/ERRORS; (6) both new migrations now carry an explicit (tenant_id, service_line) index (opp_service_lines_tenant_line_index / proj_service_lines_tenant_line_index), independently verified present via SQLite PRAGMA index_list AND real MySQL SHOW INDEX. Remediation touched exactly: app/Models/Concerns/EnforcesServiceLineIntegrity.php, both new migrations, and the three existing GAP-046 test files (36/36 default-suite GAP-046 tests pass, up from 24; +5 mysql-parity-only tests) -- confirmed by explicit diff grep that no forbidden surface (OpportunityController, LeadController, CrmPageController, DesignItemPageController, BusinessKpiService, any pre-existing migration, routes/**, resources/**, .github/**, GAP-041/042/045/047) was touched. One additional real defect was found and fixed during this remediation's own CI verification: PHPStan's nullsafe.neverNull on request()?->attributes in the new resolveActingTenantId() helper (request()'s return type resolves non-nullable; fixed by dropping the unnecessary ?->, zero behavior change). Full local regression suite re-run post-remediation: 2374 tests, 7 pre-existing failures byte-identical by name to the Round-0 set (all in Tests\\Feature\\Dashboard\\DashboardApiTest, unrelated to GAP-046), zero new failures. All 33 live CI checks green on exact current subject_sha/PR-head 829d275f1d9f68af9859db9a558404ed600f20c5 (Owner Governance Lint, test-routes-guardrails, Zena RBAC/Tenant Invariants MySQL parity, Unit/Feature/Integration/API Tests, all real-MySQL concurrency/constraint jobs, security/quality scans, browser-tests polled to genuine 16m33s completion, quality-gate) -- zero evidence-freshness timing-race reruns needed this round (unlike Round 0's two occurrences). deploy correctly shows 'skipping'; workflow success is not described as a deployment. This packet requests Owner Gate 3 decision only; it does not request or imply Ready-for-review, merge, release, or deployment authorization. No self-approval: owner_decision.value stays none, owner_decision_binding stays null."
technical_evidence:
  subject_sha: "829d275f1d9f68af9859db9a558404ed600f20c5"
  implementation_tree_digest: "acb7c8dac62a2b750711a0462991c0cfe0527d4698f0c4a9392f21a9e69ced2c"
  verified_pr_head_sha: "829d275f1d9f68af9859db9a558404ed600f20c5"
  verified_at: "2026-08-28T18:46:38Z"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-046 — Canonical Service-Line Foundation: Gate 3 Release Request

## Correction Round 1 — Owner Gate 3 Review (permanent record — never erased)

**Owner Gate 3 decision: CORRECTION REQUESTED.** Reviewed exact state: PR
#292 head `63f8636fa77bdfc73401cc9bcd25f52c759fd82f`, subject_sha
`037758fff502d738eac31e1a08a8e7a4e3701c2b`, implementation_tree_digest
`35d9d9c8d44a745aa284cbfaf77b03039801b091a90da2a069b535ad604a472b`,
canonical main `9944e1b50de515accb68bd5fd67347747620c6d3`. The full verbatim
directive is recorded in this file's frontmatter
`decision_provenance.owner_response_reference` field above. Summary of the
7 directed corrections, all within the existing approved Gate-2 §11
boundary (no Gate-2 reopening, no new Work ID):

1. **Current/acting-tenant enforcement** — `EnforcesServiceLineIntegrity`
   must also reject a mismatch between the acting/current tenant context
   (same precedence as `TenantScope`: `app('tenant')` →
   `current_tenant_id` → request attribute `tenant_id`; `TenantScope`
   itself not modified) and the parent's true tenant — not only an
   explicitly-set conflicting `child.tenant_id`. A no-current-tenant-context
   CLI backfill write must remain permitted.
2. **Update-path integrity** — the same invariants (canonical
   `service_line`, canonical `provenance`, resolvable parent, tenant
   congruence) must also be enforced on `update`, not only `create`.
3. **Strengthen acceptance J** — prove the backfill command creates zero
   rows for a Project that is the real, linked `converted_project_id`
   target of a legacy-mappable Opportunity, not just an unrelated empty
   Project.
4. **Strengthen acceptance K** — prove that an Opportunity carrying a real
   canonical Service-Line row *before* WON conversion still shows zero
   propagation to the new Project (and its own row survives unchanged),
   not just an Opportunity with no rows to propagate in the first place.
5. **Canonical MySQL-parity CI coverage** — GAP-046 DB behavior must run
   inside this repo's `@group mysql-parity` live-CI mechanism with
   log-verified proof of the specific test names executing on real MySQL,
   not only a local Docker harness.
6. **Set-membership index** — add `(tenant_id, service_line)` indexes to
   both new migrations (existing unique constraints retained), since
   `parent_id` currently sits between `tenant_id` and `service_line` in
   the unique index's B-tree order.
7. **Strict TDD** required throughout for every correction above.

Remediation scope: `app/Models/Concerns/EnforcesServiceLineIntegrity.php`,
the two new migrations, the three existing GAP-046 test files, optionally
one new narrowly-focused MySQL-parity test file, and truthful updates to
the implementation plan and this packet. Everything else (including
`OpportunityController`, `LeadController`, any pre-existing migration,
`.github/**`) remains explicitly forbidden. Old `subject_sha`/digest above
are historical only as of this correction — superseded by a fresh
subject_sha/digest once remediation completes. This Round 1 record is
preserved permanently and must not be removed by any future revision.

---

## Original Submission (Round 0) — historical, superseded by remediation below

The original submission (subject_sha `037758fff502d738eac31e1a08a8e7a4e3701c2b`,
digest `35d9d9c8d44a745aa284cbfaf77b03039801b091a90da2a069b535ad604a472b`)
was reviewed and returned **CORRECTION REQUESTED** above. Its full original
evidence body (three-SHAs table, diff stat, acceptance-matrix table,
MySQL-evidence section, local/CI verification summaries, and two
evidence-freshness timing-race notes on the now-superseded intermediate
heads `a4da050b`/`54be709e`) is preserved verbatim in this file's git
history (commits `a4da050b` through `63f8636f`) and is **not** repeated
here to avoid stale "current PR head" claims, per the Owner's explicit
instruction. That evidence is historical only — do not cite it as current
Gate-3 evidence. The remediated, current evidence is below.

## Round 1 Remediation Complete — Re-Presentation

This packet is prepared following the approved GAP-046 Gate 2 design
(`docs/owner-decisions/GAP-046/02-design.md`, Owner Round 2 FINAL APPROVAL,
squash-merged to main at `9944e1b50de515accb68bd5fd67347747620c6d3`), the
implementation plan `docs/superpowers/plans/2026-08-28-gap-046-service-line-foundation-implementation.md`
(see its "Addendum — Gate 3 Correction Round 1 remediation" section), and
the Correction Round 1 directive recorded permanently above. It records
technical readiness for Owner review only. **It does not authorize
Ready-for-review, merge, release, or production deployment** — those
require a separate, explicit Owner instruction after Gate 3 is decided.

### Three SHAs — do not conflate

1. **Canonical implementation baseline:** `9944e1b50de515accb68bd5fd67347747620c6d3`
   (main, the approved Gate-2 PR #288 squash-merge SHA — unchanged since
   Round 0, zero drift).
2. **Implementation subject_sha (current, superseding Round 0's
   `037758ff`):** `829d275f1d9f68af9859db9a558404ed600f20c5` — the last
   commit on PR #292 changing non-Gate-3-record content; what
   `technical_evidence.implementation_tree_digest` below is computed
   against.
3. **Current PR head (identical to subject_sha as of this packet's
   commit):** `829d275f1d9f68af9859db9a558404ed600f20c5`. This packet's
   own commit (adding/updating this file) is excluded from the
   implementation-tree digest by construction
   (`owner_governance_compute_implementation_tree_digest()` excludes
   exactly `docs/owner-decisions/GAP-046/03-release*.md`), so pushing this
   commit alone will not move the digest away from the value recorded
   below — only a change to any OTHER file would.

### Remediation diff (`63f8636f` Round-0 head → `829d275f` current subject_sha)

```
 app/Models/Concerns/EnforcesServiceLineIntegrity.php                              |  60 ++--
 database/migrations/2026_08_28_120000_create_opportunity_service_lines_table.php  |   6 +
 database/migrations/2026_08_28_120001_create_project_service_lines_table.php      |   6 +
 docs/superpowers/plans/2026-08-28-gap-046-service-line-foundation-implementation.md |  72 ++
 tests/Feature/Console/BackfillOpportunityServiceLinesTest.php                     | 105 ++
 tests/Feature/Crm/OpportunityConversionUnchangedTest.php                          |  76 ++
 tests/Feature/Models/ServiceLineFoundationTest.php                                | 305 ++
 7 files changed (across 5 commits: a1706725, a828d126, 530f6549, cdeb1e5f, 829d275f)
```

Exactly the remediation scope the Correction Round 1 directive authorized:
`EnforcesServiceLineIntegrity.php`, both new migrations, the three existing
GAP-046 test files, and truthful plan/packet updates. No new test file was
needed (5 new `@group mysql-parity` methods were added to the two existing
test files instead, per the directive's own preference for tagging
existing files). Explicit grep across the full remediation diff for
`OpportunityController`, `LeadController`, `CrmPageController`,
`DesignItemPageController`, `BusinessKpiService`, any pre-existing
migration, `routes/`, `resources/`, `.github/`, and GAP-041/042/045/047:
**zero matches**.

### Acceptance matrix (Gate 2 §12) — proof by test, updated

| # | Criterion | Proof |
|---|---|---|
| A | `service_line` accepts exactly DESIGN/CONSTRUCTION/INSPECTION | `ServiceLineFoundationTest::test_service_line_accepts_exactly_the_three_canonical_values` |
| B | Invalid `service_line`/`provenance` rejected (create AND update) | `test_invalid_service_line_is_rejected`, `test_invalid_provenance_is_rejected`, `test_update_to_invalid_service_line_is_rejected_for_opportunity`, `test_update_to_invalid_provenance_is_rejected_for_opportunity`, `test_update_to_invalid_service_line_is_rejected_for_project`, `test_update_to_invalid_provenance_is_rejected_for_project` |
| C | Legacy architecture family → DESIGN/INFERRED only | `BackfillOpportunityServiceLinesTest::test_architecture_family_creates_only_design_inferred_rows` |
| D | Legacy construction → CONSTRUCTION/INFERRED only | `test_construction_creates_only_construction_inferred_row` |
| E | inspection/consulting/combined_package → zero rows | `test_inspection_consulting_combined_package_create_zero_rows` |
| F | null/unrecognized → zero rows | `test_unrecognized_creates_zero_rows` (unrecognized-string proxy — `service_category` is NOT NULL at the DB level; documented in the test) |
| G | Backfill never CONFIRMED | `test_backfill_never_creates_confirmed_provenance` |
| H | Backfill idempotent | `test_backfill_is_idempotent` (+ `test_backfill_is_idempotent_on_real_mysql`) |
| I | Cross-tenant write rejected, both sides, both explicit-child-tenant-id AND acting/current-tenant-context mismatch, both create AND update | `test_cross_tenant_write_is_rejected_for_opportunity`, `test_cross_tenant_write_is_rejected_for_project`, `test_acting_tenant_mismatch_is_rejected_for_opportunity_even_without_explicit_child_tenant_id`, `test_acting_tenant_mismatch_is_rejected_for_project_even_without_explicit_child_tenant_id`, `test_acting_tenant_matching_parent_tenant_is_permitted`, `test_update_reassigning_parent_to_different_tenant_is_rejected_for_opportunity`, `test_update_reassigning_parent_to_different_tenant_is_rejected_for_project` (+ `test_cross_tenant_writes_are_rejected_on_real_mysql`) |
| J | Project historical-backfill count exactly zero, proven against a REAL linked converted_project_id | `test_backfill_creates_zero_rows_for_the_linked_converted_project` (strengthened per Correction Round 1 item 3; supersedes the original `test_project_service_lines_table_has_zero_rows_by_default`, which remains as an additional, weaker check) |
| K | No runtime Opportunity→Project propagation, proven even when the Opportunity carries a REAL canonical membership row before conversion | `test_won_to_project_conversion_does_not_propagate_existing_canonical_membership` (strengthened per Correction Round 1 item 4; supersedes the original `test_won_to_project_conversion_creates_zero_service_line_rows`, which remains as an additional, weaker check) |

Also proven: tenant-scoped visibility, unique-constraint duplicate
rejection via real DB exception (SQLite AND real MySQL), tenant_id
derivation from parent, both relations' seeded-row behavior, migration
up/down round-trip, dry-run writes nothing, `service_category` never
modified by backfill, `(tenant_id, service_line)` index exists on both
tables (SQLite `PRAGMA index_list` AND real MySQL `SHOW INDEX`).

### MySQL vs SQLite vs application-layer — explicit distinction, updated

- **SQLite:** migrate/rollback round-trip re-verified clean after
  remediation (only the two GAP-046 tables affected).
- **Real MySQL 8.0** (local Docker `mysql:8.0`, same image as CI): migrate/
  rollback round-trip re-verified clean; `SHOW INDEX FROM opportunity_service_lines`/
  `project_service_lines` independently confirms the new
  `opp_service_lines_tenant_line_index`/`proj_service_lines_tenant_line_index`
  indexes exist with the expected `(tenant_id, service_line)` column
  order; all 36 default-suite GAP-046 tests plus all 5 new
  `@group mysql-parity` tests re-run against this instance, 41/41 pass.
- **Live CI canonical `@group mysql-parity` mechanism (Correction Round 1
  item 5 — the specific gap the Owner identified):** PR #292's
  `Zena RBAC/Tenant Invariants (MySQL parity)` job (run `33199324422`,
  job `98944492511`) log independently inspected via
  `gh run view --job=98944492511 --log`: explicitly names all 5 new
  GAP-046 test methods executing (`Tests\Feature\Console\BackfillOpportunityServiceLinesTest::test_opportunity_backfill_mapping_on_real_mysql`,
  `::test_backfill_is_idempotent_on_real_mysql`,
  `Tests\Feature\Models\ServiceLineFoundationTest::test_migration_creates_expected_tables_and_columns_on_real_mysql`,
  `::test_duplicate_membership_is_rejected_by_unique_constraint_on_real_mysql`,
  `::test_cross_tenant_writes_are_rejected_on_real_mysql` — each appears
  by name in that job's own log output, not inferred), with the job's
  final summary line `Tests: 41 passed (1278 assertions)` and zero
  FAILURES/ERRORS anywhere in the log. This directly satisfies the Owner's
  instruction that "migrations built successfully" is not sufficient —
  this is test-name-level, log-verified proof of GAP-046 behavior
  executing on real MySQL inside the canonical CI mechanism.
- **Application-layer tenant congruence (explicitly not a DB-level
  guarantee, per Gate 2 §5's stated limitation — unchanged by remediation,
  still truthfully stated):** `projects.tenant_id`'s legacy `string`
  (non-`ulid`) typing still makes a portable composite DB-level FK
  impractical on the Project side within this Work ID's scope. The
  remediation strengthens the *application-layer* enforcement (acting-tenant
  check, update-path check) but does not and was not directed to change
  this stated DB-level limitation.

### Local verification summary (post-remediation)

- All four GAP-046 test files, default suite: **36/36 pass** (was 24 at
  Round 0; +9 new integrity tests, +1 new J test, +1 new K test, +1 index
  test = +12, netting 36; two Round-0 tests were superseded-but-retained
  rather than deleted).
- The same 36, re-run against real MySQL 8.0: **36/36 pass**, identical
  results to SQLite.
- The 5 new `@group mysql-parity`-tagged tests: **5/5 pass** locally
  against real MySQL, confirmed excluded from the default run (`--list-tests`
  and `--exclude-group mysql-parity` both show exactly the pre-existing 22
  in `ServiceLineFoundationTest`/9 in `BackfillOpportunityServiceLinesTest`
  visible by default; `--group mysql-parity` shows exactly the 5 new ones).
- Regression suite (`BusinessKpiServiceTest`, `AiDesignItemSuggestionTest`,
  `TenantScopedCrmModelsTest`, `OpportunityAppointmentModelTest`): 24/24
  pass, unmodified.
- Combined focused + regression: **60/60 pass**.
- Full local suite (`--testsuite=Unit,Feature,Integration`, SQLite, full
  post-remediation diff present): 2374 tests, 2367 pass, **7 pre-existing
  failures — byte-identical to the exact same 7 `Tests\Feature\Dashboard\DashboardApiTest`
  methods reported at Round 0** (confirmed by name-for-name comparison),
  42 skipped (pre-existing, unrelated). Zero new failures from remediation.
- `php scripts/ssot/owner_governance_lint.php`: PASS (96 files, 0
  violations).
- `php scripts/ssot/owner_governance_lint.php --enforce-gate-ordering`:
  PASS (0 violations).
- `php scripts/ci/lint-mysql-claim-truthfulness.php`: PASS.
- Migration round-trip (SQLite AND real MySQL): re-verified clean after
  remediation, both directions, only the two GAP-046 tables affected.

### Live CI — exact current subject_sha / PR head `829d275f1d9f68af9859db9a558404ed600f20c5`

All 33 checks green, independently re-verified via direct synchronous
`gh pr checks 292` calls after full settlement (exit code 0, zero
reruns needed this round — unlike Round 0, no evidence-freshness timing
race occurred on this head):

API Tests (Fast), API Tests (Slow), Code Quality Analysis, Dependency
Vulnerability Scan, Docker Security Scan, Document Workflow Concurrency
(real MySQL), Feature Tests, Integration Tests, License Compliance Scan,
**Owner Governance Lint**, Performance Tests (DashboardPerformanceTest.php),
Performance Tests (PerformanceMonitoringTest.php), RFI Escalation
Concurrency (real MySQL), Repo Hygiene Guards, Security Tests, Security
Vulnerability Scan, Test Coverage Report, Treasury Native CHECK Constraints
(real MySQL), Trivy, Unit Tests, Zena RBAC/Tenant Invariants, **Zena
RBAC/Tenant Invariants (MySQL parity)** (log-inspected, see above),
browser-tests (16m33s, polled to genuine completion), button-inventory-check,
code-quality, coverage-report, feature-tests, security-tests, staging-smoke,
**test-routes-guardrails**, test, quality-gate — all `pass`. `deploy`:
`skipping` (no deployment occurred or is implied; workflow success is not
described as a deployment).

Two genuine defects were found by live CI during remediation and fixed
within approved scope, not silently absorbed:

1. An intermediate head (`cdeb1e5f`) failed `Code Quality Analysis`/
   `Security Tests`' PHPStan step with `nullsafe.neverNull` on
   `EnforcesServiceLineIntegrity::resolveActingTenantId()` —
   `request()?->attributes` flagged because PHPStan resolves `request()`'s
   return type as non-nullable. Fixed in commit `829d275f`: dropped the
   unnecessary `?->` (the `function_exists('request')` guard already
   covers the helper-undefined case). Annotation/syntax-only, zero
   behavior change.
2. (Carried forward from Round 0, already fixed before Correction Round 1:
   the 4 `missingType.generics` PHPStan findings on the new relation
   methods, fixed in commit `037758ff` — not reintroduced by remediation.)

Both fixes re-verified green locally and on the immediately following live
CI run, with zero test regressions.

### Residual risks / known limitations (stated truthfully, not blocking)

1. **Application-layer-only tenant congruence on the Project side** —
   unchanged Gate-2 §5 stated limitation; the remediation strengthens
   application-layer enforcement (acting-tenant, update-path) but a
   portable DB-level composite FK on the Project side remains out of scope
   per Gate 2 §5 and per Correction Round 1 (which did not direct
   `projects.tenant_id` schema work).
2. **`null`/unrecognized backfill case F is tested via an
   "unrecognized string" proxy, not a literal NULL** — `service_category`
   is `NOT NULL` at the DB level; documented in the test; behaviorally
   equivalent.
3. **7 pre-existing `DashboardApiTest` failures** remain in the local full
   suite, unrelated to GAP-046, byte-identical to the Round-0 set —
   flagged for completeness, not attributed to this Work ID.
4. **The two Round-0 "weaker" J/K tests were retained, not deleted**, per
   general test-hygiene practice (broader coverage, no conflict with the
   stronger versions) — both continue to pass and are listed in the
   acceptance matrix above alongside their stronger replacements.

### Explicit confirmation — excluded slices and forbidden surfaces untouched

No CRM classification UX, stage gates, Quote Scope Snapshot, Contract
Service-Line classification, Portfolio membership behavior, Project OPPM,
Operations Control Tower, Finance/Treasury, historical Project backfill,
`projects.tenant_id` schema normalization, runtime review/remediation UI,
or unrelated refactor was built or touched, in either the original
submission or the Round 1 remediation. `Opportunity.service_category`'s
default and validation are unmodified. `OpportunityController`,
`LeadController`, `CrmPageController`, `DesignItemPageController`,
`BusinessKpiService`, any pre-existing migration, `routes/**`,
`resources/**`, RBAC/policies, `.github/**`, and GAP-041/042/045/047 were
not touched by the remediation — confirmed by explicit diff grep across
the full remediation commit range. WON→Project propagation remains absent
(proven, strengthened, by `test_won_to_project_conversion_does_not_propagate_existing_canonical_membership`).

### What this packet does NOT authorize

This Gate 3 packet requests an Owner Gate 3 decision only. It does not
authorize Ready-for-review, merge, release, or production deployment —
those remain separate, explicit Owner decisions pending a Gate 3 approval
and a subsequent, separate release instruction, per this repository's
established convention (see e.g. GAP-043's and GAP-047's Gate 3 records).
PR #292 remains Draft/unmerged throughout Correction Round 1 and this
re-presentation.
