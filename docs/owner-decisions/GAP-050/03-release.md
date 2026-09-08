---
work_id: GAP-050
gate: 3
gate_status: approved
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-09-06-gap-050-gate2-mysql-transaction-isolation-design.md
  plan: null
  branch: fix/GAP-050-gate3-mysql-invariants-process-isolation
  pr: "https://github.com/kha997/zenamanagephp/pull/305"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-08T00:00:00Z"
  owner_response_reference: "Owner Gate-3 approval, bound to PR #305 head f4acb0ad222037a63b57128c681246d48a6caa1f and implementation-tree digest 6584d237651c202cab9061c95f9e65f11a59b322404f12b64560396e5e5bc402; residual risk accepted low-to-medium; containment (not root-cause fix) approved per Gate 2 §G/§L primary recommendation."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-07T00:44:53Z"
  updated_at: "2026-09-08T00:00:00Z"
generated_by: agent
residual_risk_rating: low_to_medium
mandatory_technical_gate_summary: "GAP-050 Gate 3 implementation (per Owner Gate-2 approval, docs/owner-decisions/GAP-050/02-design.md, PR #304 head 214646b5ea275d40156105d7b525a846b3dfd62b), CORRECTED per Owner Gate-3 CHANGES REQUESTED (2026-09-08, see 'Owner Gate 3 — Correction 2' section below), is technically complete and verified against real MySQL 8.0. Candidate B (process isolation): scripts/ci/zena-invariants-mysql replaces the single 18-file/41-test PHPUnit process with one isolated PHPUnit process per file. File discovery was hardened in Correction 2: scripts/ci/zena-invariants-discover-files.php resolves group membership via PHPUnit's own --list-tests-xml metadata API (the same resolver PHPUnit itself uses, which reads both the doc-comment @group annotation and the #[Group] attribute into one concept) instead of a source-text grep, which could have silently dropped a file migrated to the attribute form (doc-comment metadata is deprecated, removal slated for PHPUnit 12) while the job kept reporting green. No hardcoded list, no batch-size tuning; fails closed on zero-selection, a vanished file, a lost exit status, a failed PHPUnit list-tests invocation, a missing --list-tests-xml artifact, or a testClass with no file attribute; running every file even after a failure so partial coverage is never reported as success. New regression test tests/Unit/ZenaInvariantsDiscoveryTest.php proves, against isolated fixtures, that both a doc-comment-declared group and an attribute-declared group are discovered (RED confirmed first: a plain grep only found the doc-comment fixture, missing the attribute one). Reconciled: still exactly the canonical 18 files / 41 tests, byte-identical to the prior grep-based inventory. Candidate C (fail-loud detection): tests/Support/RefreshDatabaseSelfHealingGuard.php, wired into tests/TestCase.php around parent::setUp(), throws immediately if Illuminate\\Foundation\\Testing\\RefreshDatabaseState::$migrated is observed flipping true->false mid-process (Gate 1/2's proven self-healing signature); gated on GAP050_SELF_HEALING_GUARD=1 (exported only by scripts/ci/zena-invariants-mysql), inert elsewhere — narrowed from the original, too-broad DB_CONNECTION=mysql gate after live CI caught it breaking two unrelated real-MySQL jobs (mysql-parity, ci-cd.yml's GAP-032 job), documented in the 'Live-CI correction' section below and, in Correction 2, also fixed in the guard class's own stale doc-comment which still described the old DB_CONNECTION=mysql scope after the code fix; verified in isolation (no DB) via tests/Unit/RefreshDatabaseSelfHealingGuardTest.php with RED/GREEN discipline. Evidence at subject_sha 040c025a921b008826072499881ed45e1894869b (Correction 2's head) from canonical main c4ccf0eed83065d453271a4defd3805131161c3a: RED — the unmodified script reproduces the exact Gate-1/Gate-2 symptom (TENANT_INVALID instead of E404.NOT_FOUND on ZenaApiContractPhase2InvariantTest::test_document_show_returns_not_found_for_scoped_cross_tenant_resource, 1 failed/40 passed). GREEN, pre-Correction-2 topology — 5 consecutive independent runs, each 18/18 files and 41/41 tests passing, zero guard trips; general-query-log capture on every run showed exactly 2 (not 1) create table `tenants` occurrences, both independently accounted for (the script's own pre-loop migrate:fresh, and ZenaInvariantsTransactionIsolationColdStartTest's own pre-existing, deliberate forced-cold-start mechanism unrelated to GAP-050) — acceptance criterion I.2 explicitly reinterpreted for the new topology per Gate 2 §I.5's own instruction. GREEN, post-Correction-2 (hardened discovery): one full real-MySQL 8.0 run at subject_sha 040c025a — 18/18 files, 41/41 tests, exit 0 — confirming the hardened discovery mechanism produces an identical, correctly-functioning per-file topology. ZenaApiContractPhase2InvariantTest's original, unweakened assertSame('E404.NOT_FOUND', ...) assertion passed in every real-MySQL run (criterion I.3). Guardrails remain green post-Correction-2: SQLite scripts/ci/zena-invariants (39 passed, 2 skipped), full Unit testsuite (926 tests — 2 more than pre-correction, the new discovery regression test's two cases — 0 failures/errors, 27 pre-existing unrelated skips), and scripts/ci/lint-mysql-claim-truthfulness.php (14 files scanned, PASS). One local-environment non-determinism was observed pre-Correction-2 and reported honestly (§D of the packet body): a separate ad hoc run of the OLD unmodified script did not reproduce the defect once in this session's differently-provisioned Docker environment, consistent with (not contradicting) Gate 2's own full-invocation/process-composition-state-dependence characterization; this does not weaken the RED evidence, which stands on its own successful reproduction. Root cause at the exact-line level remains unresolved per Gate 2 §D; this is documented containment, not a claimed fix. Tenant/RBAC/document/product semantics, the Sanctum Bearer-token fidelity defect, Treasury, and the mysql-parity job are untouched, per Owner's explicit Gate-2 scope instruction, unchanged by Correction 2. This packet requests Owner Gate 3 decision only; it does not request or imply Ready-for-review, merge, release, or deployment authorization."
technical_evidence:
  subject_sha: "040c025a921b008826072499881ed45e1894869b"
  implementation_tree_digest: "6584d237651c202cab9061c95f9e65f11a59b322404f12b64560396e5e5bc402"
  verified_pr_head_sha: "040c025a921b008826072499881ed45e1894869b"
  verified_at: "2026-09-08T00:00:00Z"
owner_decision_binding:
  implementation_tree_digest: "6584d237651c202cab9061c95f9e65f11a59b322404f12b64560396e5e5bc402"
  decision_recorded_at: "2026-09-08T00:00:00Z"
---

## Live-CI correction (2026-09-07, self-caught before Owner review)

The first pushed version of this implementation (subject_sha `d6bebe72`)
gated `RefreshDatabaseSelfHealingGuard::enabled()` on the broad
`DB_CONNECTION === 'mysql'` condition. Live CI on PR #305 caught what local
verification — which only ever ran GAP-050's own job in isolation — could
not: two **other** real-MySQL jobs (`test-routes-guardrails`'s
`--group=mysql-parity` step, and `ci-cd.yml`'s "Prove GAP-032 migrations on
MySQL 8.0") also run under `DB_CONNECTION=mysql`, run several files in
**one shared PHPUnit process** (unlike this job's new per-file topology),
and independently include a `ColdStart`-family test using the
pre-existing `GAP040ColdStartTransactionIsolationAssertions::forceGenuineColdStartForNextSetUp()`
mechanism, which *deliberately* resets `RefreshDatabaseState::$migrated`.
The guard correctly detected a `true→false` transition in both jobs — it
just couldn't distinguish "this job's own known-legitimate reset" from a
genuine self-healing bug, because in a *shared*-process job that
distinction requires knowing which job is running, not just which
connection driver.

**Fix** (subject_sha `0db80dbc`, superseding `d6bebe72`): the guard is now
gated on a new `GAP050_SELF_HEALING_GUARD=1` environment variable that
**only** `scripts/ci/zena-invariants-mysql` exports, instead of the broad
connection check. This keeps the guard's blast radius exactly at GAP-050's
own job. Re-verified: a 6th consecutive real-MySQL run of the target job
(18/18 files, 0 failed) and the guard's own isolated unit test remain
green after the fix. `mysql-parity`, Treasury, and every other real-MySQL
job are left byte-for-byte unaffected by this correction — no file outside
`scripts/ci/zena-invariants-mysql` and
`tests/Support/RefreshDatabaseSelfHealingGuard.php` changed. All 26 live CI
checks subsequently ran; see §H below for the final green state, recorded
after this correction landed.

This is recorded here, not silently squashed away, per this repository's
established convention of preserving correction history rather than
erasing it.

## Owner Gate 3 — Correction 2 (2026-09-08): hardened discovery + stale-claim fixes

**Owner decision: CHANGES REQUESTED, focused correction only** — the
prior packet's implementation was "accepted provisionally," with an
explicit instruction not to reopen root-cause investigation or change
tenant/RBAC/product semantics. Four corrections were required:

1. **Test-discovery false-green risk.** The prior discovery mechanism
   (`grep -rl '@group zena-invariants' tests/`) matches only the literal
   doc-comment annotation string. PHPUnit's doc-comment metadata is
   deprecated and will be removed in PHPUnit 12 (this repo's own live CI
   already warns about it on several files); a file gradually migrated to
   the `#[Group('zena-invariants')]` attribute form would silently stop
   matching that grep, removing it from this job's coverage while the job
   kept reporting green — the exact class of risk this Gate exists to
   close, now present in its own tooling.

   **Fix**: `scripts/ci/zena-invariants-discover-files.php`, a small PHP
   script that shells out to `vendor/bin/phpunit --list-tests-xml`, the
   same mechanism PHPUnit itself uses to resolve `--group` membership.
   PHPUnit's metadata resolver reads doc-comment annotations and
   attributes into one unified concept, so this discovery can never be
   fooled by which form a given file uses. Fails closed on: missing
   `--group`, a missing `--config`/binary, a non-zero PHPUnit exit, a
   missing or empty `--list-tests-xml` output file (never trust an
   artifact's mere existence-claim without checking it), unparseable XML,
   or a `<testClass>` entry with no `file` attribute. Zero-selection is
   still the *caller's* decision (unchanged fail-closed check in
   `scripts/ci/zena-invariants-mysql`), keeping this script a
   single-purpose primitive.

   **Proof, RED then GREEN**: a fixture pair —
   `scripts/ci/__fixtures__/zena-invariants-discovery/DocCommentGroupTest.php`
   (doc-comment `@group`) and `.../AttributeGroupTest.php` (`#[Group]`
   attribute), both tagged `zena-invariants-fixture` — plus an isolated
   fixture `phpunit.xml` (bootstraps only `vendor/autoload.php`, no
   Laravel app boot, never referenced by any real job). RED: a plain
   `grep -rl '@group zena-invariants-fixture' scripts/ci/__fixtures__/...`
   finds only `DocCommentGroupTest.php` — confirmed by direct command
   before writing the fix. GREEN:
   `tests/Unit/ZenaInvariantsDiscoveryTest.php` proves the new script
   finds both files, and separately proves a non-matching group returns
   an empty (not erroring) result.

   **Reconciliation**: `php scripts/ci/zena-invariants-discover-files.php --group=zena-invariants --config=phpunit.xml`
   against the real repository returns exactly the same 18 files the old
   `grep` did (`diff` against the grep-derived list: zero differences),
   totaling the same 41 tests — the hardening changes *how* the inventory
   is computed, not *what* it currently contains.

2. **Stale truth claims, corrected**:
   - `tests/Support/RefreshDatabaseSelfHealingGuard.php`'s class-level
     docblock still said `enabled()` was "gated on `DB_CONNECTION=mysql`"
     — a description of the *original*, too-broad gate from before the
     prior session's own live-CI-caught correction, which had already
     fixed the `enabled()` method itself to check
     `GAP050_SELF_HEALING_GUARD=1` but left the class comment describing
     the old behavior. Corrected to describe the actual current scope.
   - This packet's `mandatory_technical_gate_summary` and §B below both
     repeated the same stale `DB_CONNECTION=mysql` description; corrected.
   - The PR #305 body repeated both stale claims (the old grep-based
     discovery description and the old guard scope); corrected in the
     same push as this correction.
   - **The original broad-`DB_CONNECTION` implementation and its live-CI
     failure are preserved below in the "Live-CI correction (2026-09-07)"
     section, unmodified** — this Correction 2 fixes the *documentation*
     that had fallen out of sync with that already-corrected code, not
     the historical record of what happened.

3. **Evidence rebinding**: `technical_evidence.subject_sha` and
   `.implementation_tree_digest` above are recomputed at the correction's
   own commit, `040c025a921b008826072499881ed45e1894869b` (digest
   `6584d237651c202cab9061c95f9e65f11a59b322404f12b64560396e5e5bc402`,
   distinct from the prior packet's `0db80dbc.../5faad506...`). **SHA
   distinction, made explicit**: `040c025a` is the *implementation*
   subject SHA (code + fixtures + regression test — everything the digest
   covers). Any subsequent commit that only edits this
   `03-release.md` file itself (a "packet-only" commit, e.g. to link the
   final live-CI-verified PR head below) does **not** change the
   implementation-tree digest by construction — the digest computation
   excludes exactly this active Gate-3 packet file — so such a commit is
   *not* a new implementation subject SHA even though it does move the PR
   head. §H below states the PR head actually verified live, which may be
   later than `040c025a` for this reason; both are recorded, not
   conflated.

4. **Focused self-review, re-run for this correction** (§F below is the
   full, updated self-review; summarized here): coverage omission (now
   addressed by PHPUnit's own group resolution, not text matching);
   mixed doc-comment/attribute metadata (the entire point of the fixture
   proof above); shell exit propagation (the discovery script's exit
   code is captured under an explicit `set +e`/`set -e` bracket, the same
   pattern already used for the per-file test loop, before being checked);
   real per-file process isolation (unchanged — still one `php artisan
   test <file>` OS process per file; confirmed by a full real-MySQL rerun
   at `040c025a`); guard scope leakage into unrelated MySQL jobs (not
   reintroduced — this correction touches only the discovery mechanism
   and a docblock, not `enabled()`'s actual gating condition, which stays
   `GAP050_SELF_HEALING_GUARD=1`).

**Root-cause investigation was not reopened. No tenant/RBAC/product
semantics were changed.** This section is preserved permanently and must
not be removed by any future revision.

---

## Owner Summary

Per Owner Gate-2 Round-2 approval (`docs/owner-decisions/GAP-050/02-design.md`,
bound to PR #304 head `214646b5ea275d40156105d7b525a846b3dfd62b`), this Gate 3
implements the Gate-2 §G/§L **primary recommendation**: candidates **B
(process isolation) + C (fail-loud detection)** for the deterministic,
100%-reproducible-in-shape `Zena RBAC/Tenant Invariants (MySQL parity)` CI
failure. **This is documented containment, not a claimed root-cause fix** —
the exact PDO/driver-level statement that flips `PDO::inTransaction()` from
`true` to `false` remains unresolved at the exact-line level (Gate 2 §D),
and this packet makes no claim otherwise.

This is a **fresh implementation session**, started from clean canonical
`main` at `c4ccf0eed83065d453271a4defd3805131161c3a` (confirmed identical to
`origin/main` before any change), in a dedicated worktree/branch. No prior
Gate-1/Gate-2 diagnostic session was resumed; Gate 1's and Gate 2's own
epistemic boundaries (proven / strongly supported / unresolved, per §D) are
preserved unchanged in this packet.

### A. Process isolation (`scripts/ci/zena-invariants-mysql`)

The single long-lived `php artisan test --group=zena-invariants` PHPUnit
process (18 files / 41 tests against real MySQL, sharing one live
connection) is replaced with **one isolated PHPUnit process per file** —
the most conservative topology available without inventing an untested
batch size, per Gate 2 §L's finding that no reduced subset up to 88% of the
suite reproduced the defect and no specific safe smaller N was validated.

- File discovery is **dynamic and grounded in PHPUnit's own group
  semantics** (Correction 2, 2026-09-08 — see that section above for the
  full RED/GREEN proof): `scripts/ci/zena-invariants-discover-files.php`
  shells out to `vendor/bin/phpunit --group=zena-invariants
  --list-tests-xml=<tmp>`, the same metadata resolver PHPUnit itself uses
  to decide `--group` membership, then parses the resulting `<testClass
  file="...">` entries. This replaced an earlier `grep -rl '@group
  zena-invariants' tests/` (sorted via `LC_ALL=C sort`), which relied on
  matching the literal doc-comment annotation string — a risk given
  PHPUnit's doc-comment metadata is deprecated and slated for removal in
  PHPUnit 12, and a file migrated to the `#[Group]` attribute form would
  have silently stopped matching that grep while this job kept reporting
  green. No hardcoded list either way, so a future test addition/removal
  is still picked up automatically.
- **Fails closed**, not merely "select some subset and hope":
  - the discovery script itself fails (missing PHPUnit binary/config, a
    non-zero PHPUnit exit, a missing/empty `--list-tests-xml` artifact,
    unparseable XML, or a `<testClass>` with no `file` attribute) → hard
    error, script exits 1;
  - zero files discovered → hard error, script exits 1 (refuses to report
    a false-green empty run);
  - a discovered file no longer exists on disk when its turn comes → hard
    error, exits 1;
  - a per-file invocation's exit status cannot be captured
    (`${zena_invariant_exit+x}` unset check) → hard error, exits 1;
  - every discovered file is run even after an earlier one fails (no
    fail-fast short-circuit), so one bad run never hides coverage of the
    rest; the script only reports success if **all** discovered files
    actually ran **and** none failed.
- No magic batch-size tuning: the granularity is exactly "one file, one
  process," not a chosen N.
- The pre-loop `php artisan migrate:fresh --force` + `migrate:status` +
  `zena_mysql_ensure_connection` re-check are unchanged from the prior
  script (this is the one intentional, expected `create table \`tenants\``
  per run — see §C below).

### B. Fail-loud protection (`tests/Support/RefreshDatabaseSelfHealingGuard.php`, wired into `tests/TestCase.php`)

A small, test-infrastructure-only guard class tracks
`Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated` transitions
across tests within one PHPUnit process:

- `beforeRefresh()` is called immediately before `parent::setUp()` (i.e.
  before Laravel's own `refreshDatabase()` runs). If a migration has
  already been observed once in this process and the flag has since gone
  back to `false`, that is precisely the self-healing signature documented
  in Gate 1/Gate 2 (some earlier test's teardown reset it) — it throws a
  named `RuntimeException` immediately, at the exact test boundary, instead
  of letting it manifest as a confusing downstream symptom in an
  unrelated-looking test.
- `afterRefresh()` records that a genuine migration was observed, so a
  later unexpected reset can be detected by the next test.
- `enabled()` gates on `getenv('GAP050_SELF_HEALING_GUARD') === '1'`, an
  env var only `scripts/ci/zena-invariants-mysql` exports — inert for
  every other job (SQLite runs, and every other real-MySQL job:
  `mysql-parity`, Treasury, `rfi-escalation-concurrency-mysql`, etc.), and
  inherently inert in production since it lives under `tests/` and is
  never referenced from application code. (Narrowed from the original
  `getenv('DB_CONNECTION') === 'mysql'` gate after live CI caught it
  breaking two unrelated real-MySQL jobs — see "Live-CI correction
  (2026-09-07)" above; the class's own docblock had fallen out of sync
  with this already-corrected code until Correction 2 fixed it too.)
- Does **not** touch `vendor/`, does **not** introduce a second
  migration/reset mechanism, and does **not** weaken any assertion — it
  only observes a public static property Laravel's own framework already
  exposes and fails the current test loudly if the anomaly is seen.

Verified in isolation, without any database, via
`tests/Unit/RefreshDatabaseSelfHealingGuardTest.php` (5 tests): a stable
`$migrated` flag across many simulated tests does not trip the guard; a
simulated self-healing reset between two tests does trip it with the
expected message; `reset()` correctly clears state between simulated
"processes." RED/GREEN discipline was followed on this unit test itself
(temporarily neutering the guard's own condition reproduced the expected
single failure; restored before committing).

### C. TDD / verification (real MySQL 8.0, canonical `main` `c4ccf0eed83065d453271a4defd3805131161c3a`)

All runs used a disposable `mysql:8.0` Docker container (matching the CI
job's own image) and the exact `./scripts/ci/zena-invariants-mysql`
entrypoint, discarded/truncated (`mysql.general_log`) between runs.

1. **RED (pre-fix, unmodified script)**: the original single-process
   `--group=zena-invariants` invocation reproduced the exact Gate-1/Gate-2
   symptom — `ZenaApiContractPhase2InvariantTest::test_document_show_returns_not_found_for_scoped_cross_tenant_resource`
   asserted `E404.NOT_FOUND`, actual `TENANT_INVALID` (1 failed, 40
   passed, 1271 assertions). Log preserved at evidence path
   `/tmp/gap050-evidence/red-run1.log` (local, not committed — ephemeral
   per this Gate's own disposable-environment discipline, consistent with
   Gate 1/Gate 2's own methodology).
2. **Guard correctness, isolated**: `tests/Unit/RefreshDatabaseSelfHealingGuardTest.php`
   RED (guard's own condition neutered) → GREEN (restored), 5/5 passing,
   no database required.
3. **GREEN, new topology, 5 consecutive independent runs**: each run
   truncated `mysql.general_log`, ran `./scripts/ci/zena-invariants-mysql`
   against a persistent (not recreated between the 5 runs) real MySQL 8.0
   container, and re-queried the general log:

   | Run | Files ran | Tests | Result | `create table \`tenants\`` occurrences |
   |---|---|---|---|---|
   | 1 | 18/18 | 41/41 | **PASS**, exit 0 | 2 (both expected — see below) |
   | 2 | 18/18 | 41/41 | **PASS**, exit 0 | 2 |
   | 3 | 18/18 | 41/41 | **PASS**, exit 0 | 2 |
   | 4 | 18/18 | 41/41 | **PASS**, exit 0 | 2 |
   | 5 | 18/18 | 41/41 | **PASS**, exit 0 | 2 |

   No `RefreshDatabaseSelfHealingGuard` trip occurred in any of the 5 runs
   (a trip would have surfaced as an 18th "file" test failure with a named
   `RuntimeException`, distinct from any product-semantics assertion
   failure — none occurred).

4. **Acceptance criterion I.2, reinterpreted for the new topology** (per
   Gate 2 §I.5's own instruction: "if candidate B was pursued... criterion
   1 must be re-verified at the new, smaller N — not assumed safe by
   extrapolation"): the original criterion's "exactly one `create table
   \`tenants\`` occurrence" was written for the single-process topology.
   Under per-file isolation, **exactly 2** occurrences were observed per
   run, in every one of the 5 runs, both of which are independently
   accounted for as expected, not unscheduled:
   - **1**: the script's own intentional pre-loop `php artisan migrate:fresh
     --force`, unchanged from the prior script;
   - **1**: `ZenaInvariantsTransactionIsolationColdStartTest`'s own
     **pre-existing, deliberate** mechanism
     (`Tests\Support\GAP040ColdStartTransactionIsolationAssertions::forceGenuineColdStartForNextSetUp()`,
     line ~55, `RefreshDatabaseState::$migrated = false;`) — this test file
     exists specifically to prove genuine-cold-start behavior (GAP-040/044)
     and intentionally forces one real re-migration for its own first test,
     confirmed by direct timestamp correlation between the general-log
     event and this file's isolated process window in a diagnostic run (not
     committed). This is unrelated to GAP-050's defect and was not modified.

   No occurrence outside these two was observed in any of the 5 runs. This
   is treated as the reinterpreted, re-verified criterion for the new job
   boundaries, per §I.5.

5. `ZenaApiContractPhase2InvariantTest::test_document_show_returns_not_found_for_scoped_cross_tenant_resource`
   passed with its original, unweakened `assertSame('E404.NOT_FOUND', ...)`
   assertion in all 5 runs (criterion I.3) — tenant/RBAC/document semantics
   are unchanged; only the CI process topology and a new,
   observation-only test-infrastructure file were touched.
6. **Guardrails remain green**:
   - `scripts/ci/zena-invariants` (SQLite job, untouched): 39 passed, 2
     skipped, exit 0.
   - Full `Unit` testsuite (924 tests): 0 failures/errors, 27 pre-existing
     unrelated skips, exit 0 — confirms the `tests/TestCase.php` edit
     (guard wiring around `parent::setUp()`) introduces no regression
     outside the MySQL-gated guard path.
   - `php scripts/ci/lint-mysql-claim-truthfulness.php`: PASS (14 files
     scanned) — the rewritten script still makes no "success" claim it
     doesn't fail closed on.
7. **Correction 2 (2026-09-08), discovery hardening — evidence at
   subject_sha `040c025a921b008826072499881ed45e1894869b`**:
   - RED: `grep -rl '@group zena-invariants-fixture'
     scripts/ci/__fixtures__/zena-invariants-discovery/` finds only
     `DocCommentGroupTest.php`, missing `AttributeGroupTest.php` —
     confirmed by direct command before the fix existed.
   - GREEN: `tests/Unit/ZenaInvariantsDiscoveryTest.php` (2 tests) proves
     `scripts/ci/zena-invariants-discover-files.php` finds both fixture
     files, and separately that a non-matching group returns an empty
     result rather than erroring.
   - Reconciliation: `php scripts/ci/zena-invariants-discover-files.php
     --group=zena-invariants --config=phpunit.xml` against the real
     repository, `diff`'d against the prior `grep`-derived file list —
     zero differences; still exactly 18 files / 41 tests.
   - One additional full real-MySQL 8.0 run of the corrected
     `scripts/ci/zena-invariants-mysql` at subject_sha `040c025a`: 18/18
     files, 41/41 tests, exit 0 — confirms the hardened discovery
     produces an identical, correctly-functioning per-file topology (not
     re-run 5x at this step, since the underlying per-file execution
     mechanism (§A) is unchanged by Correction 2 — only *how the file
     list is computed* changed, and that is what items 1-3 above prove;
     §H below records the live-CI confirmation on top of this).
   - Full `Unit` testsuite re-run: 926 tests (2 more than pre-correction —
     the new discovery regression test's two cases), 0 failures/errors, 27
     pre-existing unrelated skips.
   - SQLite `scripts/ci/zena-invariants` re-run: 39 passed, 2 skipped,
     exit 0 (unchanged, confirming no regression from files this
     correction did not touch).
   - `php scripts/ci/lint-mysql-claim-truthfulness.php` and
     `bash scripts/ci/docs-lint.sh`: both PASS.

### D. Non-determinism observed, reported honestly

One full-invocation run of the **old, unmodified** single-process script
against the same disposable MySQL container did **not** reproduce the
defect (clean pass) on a later attempt in this session, alongside the one
that did (§C.1). This is consistent with — not contradictory to — Gate 2's
own characterization of the mechanism as full-invocation/process-composition
-state dependent rather than a hard, always-100%-in-every-environment
trigger; Gate 2's own 11/11 reproduction rate was measured in a different
disposable environment. This packet does not rely on the negative
(non-reproducing) run as evidence of anything; the RED evidence in §C.1
stands on its own reproduction, and the acceptance evidence in §C.3 is 5
independently clean runs of the **new** topology, not a claim that the old
topology never passes.

### E. Scope discipline

Not touched, changed, or reasoned about beyond what is recorded above:
tenant/RBAC/document/product semantics or behavior; the Sanctum
Bearer-token fidelity defect (Gate 2 §F) — still explicitly out of
GAP-050's scope, recommendation for a separate Work ID stands unchanged;
Treasury or the `mysql-parity` (5-file) job (Gate 2 §E blast-radius table)
— not independently reproduced or modified, per Owner's own Gate-2
instruction to track those separately; `vendor/`; any migration or reset
mechanism beyond the pre-existing `migrate:fresh`; test ordering, sleeps,
or retries (all explicitly rejected per Gate 2 §J, none used here either).

### F. Self-review

**Re-run for Correction 2 (2026-09-08)**, focused on exactly what the
Owner asked to be re-checked:

- **Current and future coverage omission**: closed by grounding discovery
  in PHPUnit's own `--list-tests-xml` group resolution instead of a
  source-text `grep` — a future file cannot fall out of coverage merely by
  changing which metadata form (`@group` doc-comment vs. `#[Group]`
  attribute) it uses, because PHPUnit resolves both into the same concept.
  Proved, not just asserted: the fixture RED/GREEN pair in "Correction 2"
  above, plus the reconciliation `diff` against the real repository's
  actual 18-file inventory (zero differences).
- **Mixed PHPUnit group metadata**: the entire point of the two-fixture
  proof (`DocCommentGroupTest.php` + `AttributeGroupTest.php`, one of
  each form, discovered together in one run) — this is what "mixed" means
  here and it is exactly what
  `tests/Unit/ZenaInvariantsDiscoveryTest.php::test_discovers_both_doc_comment_and_attribute_declared_groups`
  asserts.
- **Shell exit propagation**: the new discovery-script invocation is
  wrapped in its own explicit `set +e` / capture `$?` / `set -e` bracket
  (`scripts/ci/zena-invariants-mysql`), the same pattern already used for
  the per-file test loop, and its exit code is checked for non-zero
  before the returned file list is trusted at all — a discovery failure
  can never silently fall through to "zero files, therefore fail-closed
  on empty selection" (a different, less informative failure mode); it is
  its own explicit, named error.
- **Real per-file process isolation**: unchanged by this correction — the
  execution loop (§A) still spawns one `php artisan test <file>` OS
  process per discovered file; only *how the file list is computed*
  changed. Confirmed unchanged by re-running the full corrected script
  against real MySQL 8.0 at subject_sha `040c025a` (§C.7): 18/18 files,
  41/41 tests, exit 0.
- **Guard scope leakage into unrelated MySQL jobs**: not reintroduced —
  this correction touches `RefreshDatabaseSelfHealingGuard.php`'s
  class-level *docblock* only, not its `enabled()` method's actual gating
  condition, which remains `GAP050_SELF_HEALING_GUARD=1` (unchanged from
  the prior session's live-CI-caught fix). §H below's live-CI confirmation
  on the corrected head re-verifies `mysql-parity` and the `ci-cd.yml`
  GAP-032 job both still pass, i.e. still unaffected.

**Preserved from the prior review round** (still true, unchanged by
Correction 2):

- **False-green risk**: addressed by the fail-closed script design (§A) and
  by treating "all 18 files ran and none failed" as the only success
  condition, rather than trusting PHPUnit's own aggregate exit code from a
  single invocation (which no longer exists in this topology).
- **Shell quoting/path handling**: file paths are read via `IFS= read -r`
  (not `mapfile`, for bash 3.2 compatibility — macOS ships bash 3.2 without
  `mapfile`; GitHub Actions' `ubuntu-latest` ships bash 5.x where this
  would also have worked, but the portable form was adopted so local
  reproduction is possible on both) and always double-quoted in the loop
  and existence check.
- **Isolation is real, not cosmetic**: each file runs as a distinct OS
  process (`php artisan test <file>`, a genuinely new PHP process per Gate
  2 §B's own process-boundary reasoning), with its own fresh
  `RefreshDatabaseState` statics — confirmed empirically by the general-log
  timestamp correlation in §C.4, not merely asserted.
- Independent review: not obtained in this session; the packet is
  self-reviewed against the criteria above. The Owner may wish to
  request an independent focused review before approving.

### H. Live CI on PR #305

#### H.1 — Final state after the "Live-CI correction" (2026-09-07) above, superseded by H.2 below

At PR head `202dafb4cff986ca5f1631b1be548bcfa6fbff76`, **all 33 required
checks are green**, including — critically, since these are the ones the
live-CI correction directly fixed:

- `Zena RBAC/Tenant Invariants (MySQL parity)` — GAP-050's own target job,
  now running the new per-file topology: **pass**.
- `test-routes-guardrails` (`--group=mysql-parity`, 5-file shared-process
  job) — the first job the guard's original, too-broad
  `DB_CONNECTION=mysql` gate incorrectly broke: **pass** after the
  `GAP050_SELF_HEALING_GUARD=1` scoping fix.
- `test` (`ci-cd.yml`'s "Prove GAP-032 migrations on MySQL 8.0") — the
  second job the same over-broad gate incorrectly broke: **pass** after
  the fix.
- `Treasury Native CHECK Constraints (real MySQL)`,
  `RFI Escalation Concurrency (real MySQL)`,
  `Document Workflow Concurrency (real MySQL)`,
  `GAP-048 Service-Line Concurrency (real MySQL)` — every other real-MySQL
  job in the repository, confirming the scoping fix left them genuinely
  unaffected, not merely assumed unaffected: **pass**.
- `Zena RBAC/Tenant Invariants` (SQLite), `Owner Governance Lint`
  (`--enforce-gate-ordering` included), `Unit Tests`, `Feature Tests`,
  `Integration Tests`, `API Tests (Fast/Slow)`, `Security Tests`,
  `Security Vulnerability Scan`, `Dependency Vulnerability Scan`,
  `Docker Security Scan`, `License Compliance Scan`, `Code Quality
  Analysis`, `code-quality`, `Repo Hygiene Guards`, `button-inventory-check`,
  `Performance Tests` (both files), `Test Coverage Report`,
  `coverage-report`, `browser-tests`, `staging-smoke`, `quality-gate`,
  `security-tests`, `Trivy` — all **pass**.

`Owner Governance Lint`'s evidence-freshness check failed twice during this
session purely on its own documented timing race (it independently polls
up to 300s for every *other* check on the head to reach a terminal state
before evaluating; it ran while `browser-tests`/`test` were still mid-run)
— both were resolved by re-running the same job once the other checks had
actually finished, with no code or packet change required either time.
This is the same gotcha documented in prior GAP release packets in this
repository (evidence-freshness 300s timing race).

#### H.2 — After Correction 2 (2026-09-08): discovery hardening + stale-claim fixes

Pushed at implementation subject_sha `040c025a921b008826072499881ed45e1894869b`
(a packet-only commit updating this file to record H.2 itself may move the
PR head past this SHA without changing the implementation-tree digest —
see "Correction 2" §3 above for why those are tracked separately). Per
this task's explicit instruction, live CI was checked **once**, not
polled in a background loop; if any check was still running at that
single check, this section records exactly what was observed and stops
rather than waiting further.

Pushed to PR head `f414fad281c66b97997693f24c037c3bddafe62d`. `gh pr checks
305` was checked exactly once, immediately after the push: all 26 checks
were reported `pending` (the push had only just triggered them; none had
reached a terminal state yet). Per this task's explicit instruction not to
background-poll CI, this session stops here rather than waiting further —
live CI on this exact head has not yet been confirmed green and is not
claimed to be. The Owner (or a follow-up session) should re-check
`gh pr checks 305` — or the PR's Checks tab — once these have had time to
complete before treating H.2 as resolved; `technical_evidence` above
remains bound to the implementation subject_sha (`040c025a`), not to any
claim about this live-CI run's outcome.

#### H.3 — Owner Gate-3 approval, pre-mutation verification (2026-09-08)

Before recording the Owner's approval below, this session re-verified live
CI at the exact head the Owner's decision is bound to: PR #305 head
`f4acb0ad222037a63b57128c681246d48a6caa1f` (one packet-only commit past
`f414fad2`, recording the single live-CI checkpoint itself — does not
change the implementation-tree digest, per "Correction 2" §3 above).
`gh pr checks 305`, checked once: **all 33 required checks pass**,
including `Owner Governance Lint`, `Routes Guardrails`
(`test-routes-guardrails`), `Automated Testing` (`Unit Tests`/`Feature
Tests`/`Integration Tests`/API Tests), `Zena RBAC/Tenant Invariants (MySQL
parity)`, `CI/CD Pipeline` (`test`, `code-quality`), `Code Quality &
Security` (`Code Quality Analysis`, `Security Tests`, `Security
Vulnerability Scan`, `Dependency Vulnerability Scan`, `Docker Security
Scan`, `License Compliance Scan`, `Trivy`), `button-inventory-check`,
`browser-tests`, and `staging-smoke`. `git merge-base --is-ancestor
origin/main HEAD` confirmed canonical `main` (`c4ccf0eed83065d453271a4defd3805131161c3a`,
unchanged since this Gate 3's own base) has not drifted incompatibly
against the reviewed head. `owner_decision_binding.implementation_tree_digest`
below is bound to this exact head's implementation-tree digest,
recomputed locally via `scripts/ssot/owner_governance_lint.php`'s own
self-consistency check (`✅ owner-governance-lint PASS`), matching
`technical_evidence.implementation_tree_digest` unchanged since
Correction 2.

### G. Follow-ups (explicitly out of this Gate's scope, recorded per Owner Gate-2 direction)

1. **Sanctum Bearer-token fidelity** (Gate 2 §F): a new Work ID should be
   opened to investigate whether `@group zena-invariants` tests' Bearer-token
   requests are silently authenticating via a stale 'web' session guard
   instead of the token they carry.
2. **Treasury / `mysql-parity` blast radius** (Gate 2 §E): the 18-file
   Treasury job (currently non-gating) and the 5-file `mysql-parity` job
   share the same structural precondition (many `RefreshDatabase` tests in
   one PHPUnit process against real MySQL) and were not independently
   reproduced or remediated here, per Owner's explicit Gate-2 instruction.
   A future Work ID should evaluate whether the same per-file isolation
   pattern applies there.
3. **Root cause (candidate A)** remains open per Gate 2 §K/§G — this Gate
   3 does not close it, only contains the symptom.

## Owner Gate 3 — APPROVED (2026-09-08)

**Owner decision: approved.** Bound to PR #305 reviewed head
`f4acb0ad222037a63b57128c681246d48a6caa1f`, implementation subject
`040c025a921b008826072499881ed45e1894869b`, implementation-tree digest
`6584d237651c202cab9061c95f9e65f11a59b322404f12b64560396e5e5bc402`
(unchanged from Correction 2, confirmed by pre-mutation re-verification in
§H.3 above). Exact-head GitHub CI settled green across all 33 required
checks. Owner accepts residual risk low-to-medium.

**Binding interpretation**: this is approved *containment* — deterministic
per-file PHPUnit process isolation (candidate B) plus the job-scoped
fail-loud self-healing guard (candidate C), per Gate 2 §G/§L's primary
recommendation. PHPUnit-native group discovery hardening (Correction 2) is
part of the approved implementation. **The exact PDO/framework root cause
remains unresolved below the containment layer** (Gate 2 §D) — this
approval does not claim otherwise.

All prior CHANGES REQUESTED / correction history above (the "Live-CI
correction (2026-09-07)" section and "Owner Gate 3 — Correction 2
(2026-09-08)" section) is preserved permanently and must not be removed by
any future revision.

Proceeds to release integration: PR #305 marked ready, squash-merged. No
further GAP-050 implementation changes. Root-cause investigation not
reopened. Sanctum/Treasury follow-ups (§G above) not started in this
session.

## Quyết định Gate 3 cần Owner

`decision_requested: approve_or_correction_or_defer`. Đề nghị Owner xác
nhận: (a) containment B+C (per-file process isolation + fail-loud guard) là
đủ để coi Gate 3 hoàn tất theo khuyến nghị Gate 2 §G/§L đã được duyệt; (b)
việc diễn giải lại acceptance criterion I.2 cho topology mới (2 occurrences
dự kiến/run thay vì 1) là hợp lý và đã được chứng minh bằng bằng chứng; (c)
đồng ý mở PR nháp (Draft) và dừng tại `gate_status: awaiting_owner`, không
merge, không deploy, theo đúng chỉ đạo của phiên làm việc này.

Không có thay đổi hành vi tenant/RBAC/product nào. Không đóng root cause ở
mức chính xác từng dòng (vẫn "unresolved" theo Gate 2 §D). Dừng tại Gate 3
chờ Owner xem xét; không merge.
