---
work_id: GAP-041
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-041/02-design.md
---

# GAP-041 — CI Test-Selection Truthfulness: Gate 2 Engineering Design (v3)

**Status:** Gate 1 approved (`docs/owner-decisions/GAP-041/01-request.md`, PR #276, Owner APPROVE 2026-08-21). Gate 2 design v3 — Owner requested changes on both v1 (Option A withdrawn) and v2 (see `docs/owner-decisions/GAP-041/02-design.md` decision_provenance for both verbatim decisions); this version corrects: (1) a false "no downstream consumer" claim about `performance-budget`/`performance-heavy` — `test-summary` genuinely references both (§1.3); (2) the missing-script blast-radius carried forward from Gate 1, now re-verified against exact current code (§6); (3) expands Option D's implementation surface to include the consequential `test-summary` cleanup (§3); (4) adds a narrow Gate-1 epistemic correction (§9) without reopening the approved Gate-1 decision. Gate 3 not started; implementation, merge, and release are not authorized. No workflow, test, application, or migration code has been changed to produce this document — it is design only.

**Objective:** every CI job that claims to validate a defined performance test set must either execute at least one test belonging to that intended set, or fail clearly and non-zero when the intended selection unexpectedly resolves to zero tests. This document additionally requires — per Owner's round-1 correction — that a job's *name/claim* match what it actually tests; changing only a PHPUnit `--group` selector while leaving a misleading job name/description in place does not satisfy this.

**Owner decision surface:** see `docs/owner-decisions/GAP-041/02-design.md` — this document is engineering evidence and design mechanics supporting that packet's recommendation, not itself a decision surface.

---

## 1. Reconstructed semantics of each job (unchanged from v1, carried forward)

### 1.1 `automated-testing.yml` → `performance-tests`

- **Origin**: matrix-driven over explicit file paths (`matrix.perf_file: tests/Performance/PerformanceMonitoringTest.php` / `DashboardPerformanceTest.php`) since the job's earliest history (`git log --follow -p` on `automated-testing.yml`) — never group-name-driven.
- **Intended population**: every test defined in the matrix-selected file.
- **What breaks it**: `phpunit.xml`'s global `<groups><exclude><group>performance</group>...</exclude></groups>` default-excludes `@group performance`-tagged tests unless the CLI passes `--group performance` to cancel that specific exclusion (PHPUnit 11.5 `Merger.php:712-725` — `--group` only cancels the exclude for the group(s) actually passed). Both target classes carry only `@group performance`; the CLI (`php artisan test "${perf_file}"`) never passes `--group`. This is a selector bug: the file-matrix intent and the group-exclusion default were never reconciled.
- **Conclusion**: fix belongs in the CLI invocation (add `--group performance`), not in test annotations, which are already correct and consistent with every other real `@group performance` consumer in the repo.

### 1.2 `a11y-perf-testing.yml` → `performance-budget` / `performance-heavy`

- **Origin**: `--group performance_budget` / `--group performance_heavy` have been present, unchanged, since commit `cd60e4f8` ("ci: split perf workflow into budget/heavy and run nightly only", 2026-01-13) — a deliberate design split, not an accident. `performance-heavy` additionally sets `PERF_ITERATIONS=1 PERF_SEED_COUNT=10`, implying an intended fast-threshold tier versus a load/seed-scale tier.
- **What actually exists**: an exhaustive repo-wide search finds **zero** tests, ever, in the repository's entire git history, carrying `@group performance_budget` or `@group performance_heavy`. `PERF_ITERATIONS`/`PERF_SEED_COUNT` are **never read** anywhere in `tests/`, `app/`, or `src/` — the env-var wiring is itself unconsumed scaffolding.
- **Conclusion (revised per Owner round-1 correction)**: this is a job split that was designed but never followed through with real tests, and **no fix that leaves these two jobs presented as "Performance Budget Tests"/"Performance Heavy Tests" while running the generic `performance` group is a truthfulness fix** — it merely trades one falsehood (a claimed tier selecting zero tests) for another (a claimed tier running tests that do not implement that tier's claimed distinction). The only way to make these two job *claims* truthful without inventing test semantics under this gap is to retire the claims themselves.

### 1.3 `test-summary` — corrected consumer analysis (new in v3, per Owner round-2 Finding 1)

v2 incorrectly stated (§4) that no workflow consumer depends on `performance-budget`/`performance-heavy`. **This was wrong.** Exact-baseline `a11y-perf-testing.yml` contains:

```yaml
test-summary:
  name: Test Summary
  runs-on: ubuntu-latest
  needs: [accessibility-tests, performance-budget, performance-heavy, lighthouse-ci, e2e-tests]
  if: always()
```

and its "Generate test summary" step emits, verbatim:

```
echo "### Performance Budget Tests" >> $GITHUB_STEP_SUMMARY
echo "- Status: ${{ needs.performance-budget.result }}" >> $GITHUB_STEP_SUMMARY
...
echo "### Performance Heavy Tests" >> $GITHUB_STEP_SUMMARY
echo "- Status: ${{ needs.performance-heavy.result }}" >> $GITHUB_STEP_SUMMARY
```

(`.github/workflows/a11y-perf-testing.yml:427-448`, verified on exact canonical baseline `0b777475`, byte-identical to the working tree — confirmed no drift before citing line numbers.)

**Correct classification, per Owner's own distinction:** this is a **reporting/reference consumer**, not a **true downstream dependency**. `test-summary` does not gate merge, does not feed any other job's `needs:`, and does not itself fail if `performance-budget`/`performance-heavy` fail or are absent (`if: always()`, and GitHub Actions treats a `needs:` entry on a *deleted* job as a hard workflow-validation error at parse time — not a soft runtime skip). That means it is **not** a reason to preserve the two jobs; it **is** a reason Option D cannot be "delete two job blocks and stop" — the workflow would fail to parse/validate the moment `performance-budget`/`performance-heavy` no longer exist while `test-summary.needs` still names them. The correction belongs entirely in `test-summary`'s own definition (remove the two IDs from `needs:` and remove the two corresponding summary-body sections), not in preserving the phantom jobs.

## 2. Current-state matrix (unchanged from v1)

| Surface | Intended selector (reconstructed) | Actual selector | Existing group annotations found | Selected test count today | Live reachability today | Current exit behavior on 0 selected |
|---|---|---|---|---|---|---|
| `performance-tests` (both matrix legs) | Run every test in the matrix-selected file | `php artisan test "${perf_file}"` (no `--group`) | Both classes: `@group performance` only | **0** (live-confirmed) | Reached every PR/push to `main` (not a required check) | Exit 0, job **succeeds** — false-green, live-proven |
| `performance-budget` | A fast/threshold-focused performance tier (never implemented) | `--group performance_budget` | None anywhere in repo history | **0** (static + local) | Blocked earlier by §6's missing-script defect (exit 127 at "Prepare testing environment") | Exit 0 *if reached* (proven locally) — currently masked by the earlier, unrelated failure |
| `performance-heavy` | A load/seed-scale performance tier (never implemented) | `--group performance_heavy` | None anywhere in repo history | **0** (static + local) | Same as `performance-budget` | Same as `performance-budget` |

## 3. Options considered (revised — Option A's flaw corrected, Option D added)

### Option A — Minimal selector fix only (WITHDRAWN as the recommendation; kept for comparison)

- `performance-tests`: add `--group performance` + `--fail-on-empty-test-suite`.
- `performance-budget` / `performance-heavy`: change `--group performance_budget`/`--group performance_heavy` to `--group performance`, **while leaving the jobs' names/descriptions ("Performance Budget Tests", "Performance Heavy Tests") unchanged.**
- **Why this is now rejected, not merely "smallest diff":** as Owner's round-1 decision identifies, this does not establish semantic truthfulness. It changes the failure mode from "zero tests under a claimed tier" to "real generic tests under a tier claim those tests do not actually implement." The job would go green, but its name is still a false claim — the exact category of defect GAP-041 exists to eliminate, just relocated rather than removed. v1 incorrectly described this as the jobs being "relabeled honestly"; no label was actually changed, so that description was wrong and is withdrawn.
- **Correctness**: fixes `performance-tests`; does **not** fix the naming-truthfulness of `performance-budget`/`performance-heavy`.
- **Fail-closed**: yes, via the native flag, for all three — but fail-closed behavior on a mislabeled job does not make the label true.
- Retained in this document only as the comparison baseline that motivated Option D.

### Option B — Backfill real, distinct `performance_budget`/`performance_heavy` tests

- Write new test methods (or split existing `PerformanceMonitoringTest`/`DashboardPerformanceTest` methods) genuinely distinguishing a fast-threshold tier (`performance_budget`) from a load/seed-scale tier using the already-wired `PERF_ITERATIONS`/`PERF_SEED_COUNT` env vars (`performance_heavy`), restoring the original 2026-01-13 design intent. Add `--fail-on-empty-test-suite` to all three jobs.
- **Correctness**: highest fidelity to the *original* author's intent — the only option that makes the job names true by making the underlying tests real, rather than by removing the claim.
- **Fail-closed**: same native flag, same guarantee.
- **Maintainability**: lower — two new/reorganized test surfaces to maintain indefinitely, on top of the existing generic `performance` tests.
- **Regression risk**: medium — new/split tests can be flaky or budget-threshold-brittle; defining "what counts as budget vs. heavy" is itself a judgment call requiring product/ops input on real thresholds, not a mechanical CI-config fix.
- **Scope size**: largest — new/modified test code, not just CI config. This is precisely the "invent budget/heavy tests under GAP-041" outcome the Owner's round-1 decision explicitly excludes from this gap's scope.
- **Evidence that could prove it**: live-dispatch verification (as in Option A/D) plus code review of the new/split test methods' actual threshold/load semantics against a defined SLA — none of which exists yet in this repo.

### Option C — Retire `performance-budget`/`performance-heavy` entirely, no explicit companion fix stated for `performance-tests`

- As originally scoped in v1: delete the two never-realized jobs, implicitly assuming `performance-tests` gets fixed as a side effect of "the fix" without stating the mechanism in the same option.
- Functionally this converges with Option D once `performance-tests`' own fix is made explicit — v1's Option C was already most of the way to what Owner is calling Option D, but left the `performance-tests` selector-fix association implicit rather than a stated, joint part of the same option. Superseded by Option D below, which states both halves explicitly as one coherent, evaluable option.

### Option D — Repair the real performance surface; retire the phantom tiers (Owner-directed; recommended)

**`performance-tests`:**
- Retain the job.
- Restore its real file-matrix population via the `--group performance` CLI override (§1.1's fix).
- Add native `--fail-on-empty-test-suite`.
- Preserve the existing real-MySQL fail-closed preflight (`zena_mysql_ensure_connection`/`zena_mysql_preflight_connection`) unchanged.

**`performance-budget` / `performance-heavy`:**
- Because no distinct budget/heavy test population has ever existed in this repository (§1.2), retire these two job definitions from `a11y-perf-testing.yml` rather than pointing them at the generic `performance` group under their current, now-false names.
- Do **not** create fake `@group performance_budget`/`performance_heavy` annotations on existing tests merely to make the jobs "pass truthfully" — that would be annotation-for-CI's-sake, explicitly forbidden by both the original Gate 1 scope and Owner's round-1 direction.
- Do **not** invent new budget/heavy tests under GAP-041 (that is Option B's territory, and Option B is not recommended here — see §4).
- Do **not** treat the currently-unused `PERF_ITERATIONS`/`PERF_SEED_COUNT` env vars as evidence of a meaningful heavy profile — they are unconsumed scaffolding, not a working mechanism (§1.2).
- If genuine budget/heavy performance tiers are desired later, that is a separately scoped future work item requiring real test semantics, threshold/load definitions, and its own evidence — not an extension of this CI-truthfulness gap.

**Consequential `test-summary` cleanup (added in v3, per §1.3 and Owner round-2 Finding 1) — required as part of the same change, not optional:**
- Remove `performance-budget` and `performance-heavy` from `test-summary.needs` (currently `[accessibility-tests, performance-budget, performance-heavy, lighthouse-ci, e2e-tests]`).
- Remove the two corresponding summary-body sections ("### Performance Budget Tests" / `${{ needs.performance-budget.result }}` and "### Performance Heavy Tests" / `${{ needs.performance-heavy.result }}`) from the "Generate test summary" step.
- This is *retirement housekeeping*, not scope creep: `test-summary` is a reporting consumer of the two retired jobs (§1.3), and a dangling `needs:` reference to a deleted job is a hard GitHub Actions workflow-validation failure, not a soft no-op — leaving it unfixed would not "preserve old behavior," it would break the workflow's ability to run at all.
- Explicitly **not** in scope: any other reorganization of `test-summary`, `accessibility-tests`, `lighthouse-ci`, or `e2e-tests` beyond removing the two now-dangling references. No other job in this workflow is touched.

**Comparison against B and C:**

| Dimension | A (withdrawn) | B (backfill) | D (repair + retire, recommended) |
|---|---|---|---|
| Correctness (job claim matches what's tested) | No — claim unchanged, tests generic | Yes — claim becomes true by building real tests | Yes — false claim removed entirely |
| Fail-closed | Yes (mechanical) | Yes (mechanical) | Yes, for the one surviving job |
| Invents test semantics under GAP-041? | No, but leaves a false claim standing | Yes — exactly what Owner excludes from scope | No |
| Maintainability | Two jobs whose names lie | Two new test surfaces to maintain | One real job, no phantom surfaces |
| Regression risk | Low (mechanical), but leaves the truthfulness defect standing under a new guise | Medium (new test content, threshold judgment calls) | Low — deletion of never-functioning job config, no application/test code touched |
| Scope size | Smallest (3 CLI edits), but does not complete GAP-041's actual problem statement | Largest | Small — CLI fix for one job + workflow-job deletion for two |
| Reversibility | N/A | N/A | Fully reversible — retired job definitions remain in git history; nothing about future backfill (Option B, separately scoped) is foreclosed by deleting a currently-nonfunctional job |

## 4. Recommendation

**Option D is recommended. Option A is withdrawn as the recommendation** — Owner's round-1 finding is correct: repointing `performance-budget`/`performance-heavy` at `--group performance` does not make those jobs truthful, it only relocates the falsehood from "zero tests" to "tests that don't implement the claimed tier." Option D is the smallest change that makes every *surviving* job's name/claim match what it actually verifies: `performance-tests` is repaired to run its real, already-existing population with a fail-closed guard, and the two never-realized tier claims are removed rather than repainted. Option B remains available as a legitimate, separately-scoped future enhancement if Owner wants real budget/heavy tiers — it is not recommended as part of GAP-041 because defining genuine performance thresholds/load profiles is a product/ops decision with its own evidence requirements, not a CI-configuration truthfulness fix.

**Correction (v3):** v2 stated no workflow consumer depends on `performance-budget`/`performance-heavy` — this was false. `test-summary` genuinely references both, in its `needs:` list and its generated summary body (§1.3). This does **not** change the recommendation: `test-summary` is a reporting consumer, not a hard functional dependency (it doesn't gate anything downstream, doesn't itself get consumed by any other job, and its `if: always()` behavior means it never required the two jobs to succeed, only to exist as named `needs:` entries). No repository evidence was found of anything that requires `performance-budget`/`performance-heavy` to keep *executing tests* — the correction is that retiring them is not "delete two job blocks in isolation," it is "delete two job blocks and update their one real reporting consumer in the same change" (§3's consequential cleanup).

## 5. Gate 3 acceptance contract (revised — no LIVE/STATIC escape hatch)

Per Owner's round-1 correction, exactly one of the following two models applies, chosen by which option is ultimately approved — never a hybrid "Gate 3 complete but LIVE pending" state:

**Model A (applies if `performance-budget`/`performance-heavy` remain as executable jobs — i.e., if Option A or B is chosen instead of D):** their required LIVE proof remains mandatory and non-waivable. GAP-041 Gate 3 is **BLOCKED** for those two jobs until the independent `.github/scripts/ci_prepare_testing_env.sh` defect is resolved (by its own, separately authorized work item) and the jobs genuinely reach PHPUnit on a live run.

**Model B (applies under the recommended Option D):** `performance-budget`/`performance-heavy` no longer exist as jobs, so they carry no PHPUnit live-execution requirement. Instead, Gate 3 must prove, on the exact implementation tree:

**(A) Phantom-tier retirement proof (static, from the exact committed workflow file at the Gate-3 SHA):**
1. No `performance-budget` job definition remains.
2. No `performance-heavy` job definition remains.
3. `test-summary.needs` does not reference either (§1.3/§3's consequential cleanup).
4. The generated Test Summary step no longer claims/reports either ("### Performance Budget Tests" / "### Performance Heavy Tests" sections removed).
5. No dangling `${{ needs.performance-budget.* }}` or `${{ needs.performance-heavy.* }}` expression remains anywhere in the workflow file.
6. No stale tier-specific artifact reference remains where its producer job has been retired (e.g., an `actions/download-artifact` step expecting `performance-budget-results`/`performance-heavy-results` with no job left to produce them).
7. The resulting workflow remains syntactically and structurally valid — verified by the repository's available YAML/workflow validation mechanism (as of this design, no dedicated `actionlint`/`yamllint` is wired into this repo; a basic YAML-parse check, e.g. `python3 -c "import yaml, sys; yaml.safe_load(open(sys.argv[1]))"`, is the available baseline) and, as the strongest evidence, a real GitHub Actions dispatch of the modified workflow that reaches and completes `test-summary` without a `needs:`-resolution error.

**(B) Surviving `performance-tests` LIVE proof — the full acceptance contract below, mandatory and non-waivable.**

**Full LIVE acceptance contract for `performance-tests` (mandatory, non-waivable, under either model):**
1. The job's name/description matches what it actually tests — no claim of coverage the job does not provide.
2. The intended test population is real and traceable to specific, existing test files/methods, on each applicable matrix leg (not merely "some group exists somewhere").
3. **≥1 test belonging to that intended population executes in a LIVE GitHub Actions run on the exact implementation tree**, for each matrix leg — a JUnit/log artifact showing a non-zero test count from that actual run, not a local reproduction and not a static read of configuration.
4. The real-MySQL fail-closed preflight (`zena_mysql_ensure_connection`/`zena_mysql_preflight_connection`) succeeds, live, before the PHPUnit step runs.
5. Zero-selection causes a non-zero job failure via `--fail-on-empty-test-suite` (or a technically equivalent native fail-closed mechanism — see the implementation-mechanism note below) — proven **live** using a disposable proof branch/commit (or an equivalent isolated validation method) that deliberately reintroduces a zero-selection condition (e.g., a mismatched group name), is shown via a real GitHub Actions run to fail the job, and is then cleanly removed/reverted before Gate 3 is presented as complete. Reading the flag's own documentation or a local reproduction is supporting evidence, not a substitute for this live proof.
6. Evidence classification (LIVE/STATIC/LOCAL/HISTORICAL) is used honestly throughout the Gate 3 packet — no STATIC or LOCAL result is described as LIVE.

No Gate-3-complete state is permitted while any LIVE-classified criterion above is still pending; if a live run cannot yet be captured (e.g., because of the missing-script dependency under Model A, for a job that survives under Option A/B instead of D), Gate 3 must remain `blocked_technical` or `awaiting_owner`-with-explicit-blocker, not "complete."

**Implementation-mechanism note (deferred to the future implementation plan, not resolved here):** this Gate-2 contract is deliberately behavioral — *zero intended tests selected must cause a non-zero job failure* — not a specific CLI spelling. `--fail-on-empty-test-suite` is the currently-identified native PHPUnit 11.5 mechanism (§7's STATIC/LOCAL evidence), but `performance-tests` invokes tests via `php artisan test`, Laravel's Collision-wrapped test runner, not `vendor/bin/phpunit` directly — whether that wrapper passes an unrecognized-to-Artisan flag through to the underlying PHPUnit process unmodified has **not** been verified in this Gate-2 design. The future implementation plan must verify this first; if `php artisan test` does not cleanly pass the flag through, implementation may substitute the smallest technically equivalent native PHPUnit invocation (e.g., invoking `vendor/bin/phpunit` directly with the same file-matrix target) that preserves the approved file-matrix selection semantics. This is an implementation-detail question, not a Gate-2 decision point.

## 6. The masked-job (`performance-budget`/`performance-heavy`) verification problem — disposition under Option D (corrected in v3, per Owner round-2 Finding 2)

**v2's claim was wrong.** v2 (following Gate 1's §1.4 framing) stated the missing script also blocks `accessibility-tests`, `e2e-tests`' environment setup, and `Lighthouse CI`. An exhaustive exact-baseline re-check does not support this.

**Exhaustive reference inventory (exact canonical baseline `0b777475`, repo-wide, both source-tree grep and `git log --all` history search):**

```
$ grep -rn "ci_prepare_testing_env" --include="*.yml" --include="*.yaml" --include="*.sh" .
.github/workflows/a11y-perf-testing.yml:150:      run: ./.github/scripts/ci_prepare_testing_env.sh
.github/workflows/a11y-perf-testing.yml:236:      run: ./.github/scripts/ci_prepare_testing_env.sh

$ git log --all --oneline -- .github/scripts/ci_prepare_testing_env.sh
(no output — the file has never existed in this repository's history)
```

**Both references, and only these two, resolve to job context:** line 150 is inside the `performance-budget` job's "Prepare testing environment" step; line 236 is inside the `performance-heavy` job's "Prepare testing environment" step (verified by scanning backward from each line to the nearest preceding top-level `  <job-id>:` key). No other job in `a11y-perf-testing.yml`, or anywhere else in the repository, calls this script.

**The other three jobs each have their own, independent, self-contained environment setup — they do not call the missing script at all:**
- `accessibility-tests` (job at `a11y-perf-testing.yml:14`): its own "Setup environment (SQLite...)" step at line 54, its own "Run database migrations" step at line 66 — no reference to `ci_prepare_testing_env.sh`.
- `lighthouse-ci` (job at `a11y-perf-testing.yml:256`): its own "Setup environment" step at line 303, its own "Start Laravel server" step at line 314 — no reference to `ci_prepare_testing_env.sh`.
- `e2e-tests` (job at `a11y-perf-testing.yml:337`): its own "Setup environment" step at line 393, its own "Verify MySQL is genuinely reachable (GAP-039)" step at line 404 — no reference to `ci_prepare_testing_env.sh`.

Gate 1's §1.4 conflated "all four/five jobs failed in the same historical dispatched run" with "all four/five jobs failed *because of* the missing script." They failed for independent, different reasons in that run (differing exit codes: `Accessibility Tests` exit 2, `E2E Tests` exit 1, `Lighthouse CI` exit 1, versus `performance-budget`/`performance-heavy`'s exit 127) — correlation in one CI run is not the same evidence as a shared code-level dependency, and the code-level dependency does not exist for the other three jobs.

**Corrected disposition:** since the *only* two references to `.github/scripts/ci_prepare_testing_env.sh` anywhere in this repository are inside the two jobs Option D retires, retiring `performance-budget`/`performance-heavy` **removes the file's only consumers**. After that:
- The former "missing-script blocks GAP-041's masked jobs" problem disappears **as a direct consequence of retiring its only two consumers** — not because the missing file was fixed, but because nothing left in the repository calls it.
- An unreferenced, nonexistent file with zero remaining callers is not, by itself, an actionable runtime defect. **A separate Operational Gap Register entry for this missing script is NOT warranted based on current evidence** — this reverses v1/v2's recommendation, which was based on the (now-corrected) belief that the script also blocked `accessibility-tests`/`e2e-tests`/`lighthouse-ci`. If a future change reintroduces a real caller of this path, that would be new evidence warranting its own gap at that time — not now.
- `accessibility-tests`'/`e2e-tests`'/`lighthouse-ci`'s prior failures in the Gate-1-era historical dispatch (`32460830217`) were real but are **not attributable to the missing script** — their causes (exit 2, exit 1, exit 1 respectively) are unexplored and out of GAP-041's scope; this document does not investigate them further and does not attribute a cause to them beyond what Gate 1 already observed.

## 7. Evidence classification

- **[STATIC]**: `phpunit.xml` group-exclude config; `@group` annotations (or their absence) in `tests/Performance/*.php` and repo-wide; PHPUnit `Merger.php`/`Help.php`/`Cli/Builder.php` source confirming `--group` cancellation semantics and `--fail-on-empty-test-suite` existence; full commit history of both workflow files via `git log --follow -p` / `git log -S`; `test-summary`'s `needs:` list and summary-body references to `performance-budget`/`performance-heavy` (§1.3, exact line numbers verified against a byte-identical copy of the canonical baseline); the exhaustive repo-wide inventory of `ci_prepare_testing_env.sh` references (§6) confirming exactly two call sites, both inside the two retired jobs, and the independent "Setup environment" steps in `accessibility-tests`/`lighthouse-ci`/`e2e-tests` that do not call it.
- **[LOCAL]**: direct `./vendor/bin/phpunit` invocations in the pinned worktree reproducing (a) `No tests executed!` at exit `0` for both defect shapes, and (b) exit `1` for the identical invocations once `--fail-on-empty-test-suite` is added.
- **[LIVE]**: carried forward, unchanged, from the approved Gate 1 packet (`performance-tests` false-green on exact current main; `performance-budget`/`performance-heavy` blocked earlier by the unrelated missing-script defect). No new live dispatch was performed in this Gate 2 design step, consistent with "no workflow/test code changes at Gate 2."
- No claim in this document describes a STATIC or LOCAL result as a LIVE workflow proof, and none describes a pending LIVE requirement as satisfied.

## 8. Scope boundaries (revised in v3 — `test-summary` cleanup added)

**In scope for the recommended Option D implementation (Gate 3, not authorized yet):** `performance-tests`' CLI invocation (`--group performance` + a fail-closed mechanism, per the implementation-mechanism note in §5); deletion of the `performance-budget`/`performance-heavy` job definitions from `a11y-perf-testing.yml`; the consequential `test-summary` cleanup (removing both job IDs from `needs:` and both summary-body sections, §3); a live-dispatch verification proof per §5's contract. **Out of scope:** performance thresholds/budgets, new/backfilled tests (Option B), any reorganization of `accessibility-tests`/`lighthouse-ci`/`e2e-tests` beyond what's strictly dangling after retirement, application/business/RBAC/tenant/production-schema code, GAP-040, GAP-042, `.github/scripts/ci_prepare_testing_env.sh` (moot after Option D per §6, not separately registered or fixed), and any branch-protection policy change. None of these boundaries require a Design Dependency Preflight — no proposed option touches production/business/tenant/RBAC semantics.

## 9. Gate-1 epistemic correction (narrow — does not reopen the approved Gate-1 decision)

Per Owner's round-2 direction: this is a correction of one side-claim's evidentiary scope, not a reopening of GAP-041's core Gate-1 finding. **The approved Gate-1 core problem statement remains valid and unchanged:** `performance-tests` is a live-proven zero-test false-green; `performance-budget`/`performance-heavy` select nonexistent test populations under phantom tier semantics. Both facts are re-confirmed, unchanged, in this v3 document (§1, §2).

**The correction:** Gate 1's audit §1.4 (carried into this design's v1/v2 as "the missing script blocks `accessibility-tests`, `e2e-tests`' environment setup, and `Lighthouse CI`") was **broader than current exact-baseline code supports**. The exhaustive re-check in §6 establishes that `.github/scripts/ci_prepare_testing_env.sh` is referenced by exactly two call sites, both inside `performance-budget`/`performance-heavy`, and that the other three jobs in the same workflow have their own independent environment setup with no reference to that script. Gate 1's broader claim appears to have inferred a shared code-level cause from the fact that several jobs failed together in one historical dispatched run, without verifying each job's actual step-level dependency.

This does not alter GAP-041's approved scope, its Gate-1 problem statement, or the Owner's Gate-1 approval — it corrects one downstream disposition recommendation (the missing-script blast radius) that depended on the overstated claim. No separate erratum artifact is created for this: `docs/owner-decisions/GAP-041/01-request.md` remains the accurate historical record of what was approved and when (the core finding it approved is unaffected); this Gate-2 v3 document is the correct and sufficient place to carry the correction forward, since Gate 1 itself is not reopened and governance convention does not call for amending an already-approved, historically-accurate decision record to fix an unrelated downstream document's error.
