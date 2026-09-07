---
work_id: GAP-050
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_correction_or_defer
references:
  spec: docs/superpowers/specs/2026-09-06-gap-050-gate2-mysql-transaction-isolation-design.md
  plan: null
  branch: fix/GAP-050-gate3-mysql-invariants-process-isolation
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-07T00:44:53Z"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-07T00:44:53Z"
  updated_at: "2026-09-07T00:44:53Z"
generated_by: agent
residual_risk_rating: low_to_medium
mandatory_technical_gate_summary: "GAP-050 Gate 3 implementation (per Owner Gate-2 approval, docs/owner-decisions/GAP-050/02-design.md, PR #304 head 214646b5ea275d40156105d7b525a846b3dfd62b) is technically complete and verified against real MySQL 8.0. Candidate B (process isolation): scripts/ci/zena-invariants-mysql replaces the single 18-file/41-test PHPUnit process with one isolated PHPUnit process per file, discovered dynamically via grep -rl '@group zena-invariants' tests/ (no hardcoded list, no batch-size tuning), failing closed on zero-selection, a vanished file, or a lost exit status, and running every file even after a failure so partial coverage is never reported as success. Candidate C (fail-loud detection): tests/Support/RefreshDatabaseSelfHealingGuard.php, wired into tests/TestCase.php around parent::setUp(), throws immediately if Illuminate\\Foundation\\Testing\\RefreshDatabaseState::$migrated is observed flipping true->false mid-process (Gate 1/2's proven self-healing signature); gated on DB_CONNECTION=mysql, inert elsewhere; verified in isolation (no DB) via tests/Unit/RefreshDatabaseSelfHealingGuardTest.php with RED/GREEN discipline on the guard's own logic. Evidence at subject_sha d6bebe72df720056d08634727033705e03f8b942 from canonical main c4ccf0eed83065d453271a4defd3805131161c3a: RED — the unmodified script reproduces the exact Gate-1/Gate-2 symptom (TENANT_INVALID instead of E404.NOT_FOUND on ZenaApiContractPhase2InvariantTest::test_document_show_returns_not_found_for_scoped_cross_tenant_resource, 1 failed/40 passed). GREEN — 5 consecutive independent runs of the new per-file topology against a real mysql:8.0 Docker container, each 18/18 files and 41/41 tests passing, zero RefreshDatabaseSelfHealingGuard trips; general-query-log capture on every run shows exactly 2 (not 1) create table `tenants` occurrences, both independently accounted for as expected (the script's own pre-loop migrate:fresh, and ZenaInvariantsTransactionIsolationColdStartTest's own pre-existing, deliberate forced-cold-start mechanism unrelated to GAP-050) — acceptance criterion I.2 is explicitly reinterpreted for the new topology per Gate 2 §I.5's own instruction, not silently redefined. ZenaApiContractPhase2InvariantTest's original, unweakened assertSame('E404.NOT_FOUND', ...) assertion passed in all 5 runs (criterion I.3). Guardrails remain green: SQLite scripts/ci/zena-invariants (39 passed, 2 skipped), full Unit testsuite (924 tests, 0 failures/errors, 27 pre-existing unrelated skips), and scripts/ci/lint-mysql-claim-truthfulness.php (14 files scanned, PASS). One local-environment non-determinism was observed and reported honestly (§D of the packet body): a separate ad hoc run of the OLD unmodified script did not reproduce the defect once in this session's differently-provisioned Docker environment, consistent with (not contradicting) Gate 2's own full-invocation/process-composition-state-dependence characterization rather than a hard threshold; this does not weaken the RED evidence, which stands on its own successful reproduction, nor the 5/5 GREEN acceptance evidence on the new topology. Root cause at the exact-line level remains unresolved per Gate 2 §D; this is documented containment, not a claimed fix. Tenant/RBAC/document/product semantics, the Sanctum Bearer-token fidelity defect, Treasury, and the mysql-parity job are untouched, per Owner's explicit Gate-2 scope instruction. This packet requests Owner Gate 3 decision only; it does not request or imply Ready-for-review, merge, release, or deployment authorization."
technical_evidence:
  subject_sha: "d6bebe72df720056d08634727033705e03f8b942"
  implementation_tree_digest: "fa2b638320873a5de0b53aa6e3e7117a8963dea16aee8d40fb5f17cc76d540ea"
  verified_pr_head_sha: "d6bebe72df720056d08634727033705e03f8b942"
  verified_at: "2026-09-07T00:44:53Z"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
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

- File discovery is **dynamic**: `grep -rl '@group zena-invariants' tests/`,
  sorted deterministically (`LC_ALL=C sort`) — no hardcoded list, so a
  future test addition/removal is picked up automatically and cannot
  silently drift out of coverage.
- **Fails closed**, not merely "select some subset and hope":
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
- `enabled()` gates on `getenv('DB_CONNECTION') === 'mysql'` — inert for
  every SQLite-based test run, and inherently inert in production since it
  lives under `tests/` and is never referenced from application code.
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

- **False-green risk**: addressed by the fail-closed script design (§A) and
  by treating "all 18 files ran and none failed" as the only success
  condition, rather than trusting PHPUnit's own aggregate exit code from a
  single invocation (which no longer exists in this topology).
- **Test-selection loss**: addressed by dynamic discovery (`grep`) rather
  than a hardcoded list, and the zero-selection / missing-file fail-closed
  checks; all 5 runs confirmed 18/18 files and 41/41 tests, matching Gate
  2 §L.1's own file/test inventory exactly.
- **Shell quoting/path handling**: file paths are read via `IFS= read -r`
  (not `mapfile`, for bash 3.2 compatibility — macOS ships bash 3.2 without
  `mapfile`; GitHub Actions' `ubuntu-latest` ships bash 5.x where this
  would also have worked, but the portable form was adopted so local
  reproduction is possible on both) and always double-quoted in the loop
  and existence check.
- **Exit-code propagation**: `set +e`/`set -e` bracket exactly the one
  `php artisan test` invocation per iteration so a per-file test failure
  does not abort the loop under `set -euo pipefail`, and the captured exit
  code is explicitly checked for "unset" (lost) before being trusted.
- **Isolation is real, not cosmetic**: each file runs as a distinct OS
  process (`php artisan test <file>`, a genuinely new PHP process per Gate
  2 §B's own process-boundary reasoning), with its own fresh
  `RefreshDatabaseState` statics — confirmed empirically by the general-log
  timestamp correlation in §C.4, not merely asserted.
- Independent review: not obtained in this session; the packet is
  self-reviewed against the criteria above. The Owner may wish to
  request an independent focused review before approving, given this task
  explicitly permitted "if useful."

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
