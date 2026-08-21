---
work_id: GAP-041
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-041/02-design.md
---

# GAP-041 — CI Test-Selection Truthfulness: Gate 2 Engineering Design (v2)

**Status:** Gate 1 approved (`docs/owner-decisions/GAP-041/01-request.md`, PR #276, Owner APPROVE 2026-08-21). Gate 2 design v2 — Owner requested changes on v1 (see `docs/owner-decisions/GAP-041/02-design.md` decision_provenance for the verbatim round-1 decision); this version corrects the recommendation, the Gate-3 contract, and the acceptance contract. Gate 3 not started; implementation, merge, and release are not authorized. No workflow, test, application, or migration code has been changed to produce this document — it is design only.

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

No repository evidence was found that would require `performance-budget`/`performance-heavy` to remain as executable jobs — no test, workflow consumer, dashboard, or downstream process was found (via repo-wide search) to depend on either job's existence, only on `performance-tests`' artifacts (`performance-report-*`) and the general `a11y-perf-testing.yml` schedule.

## 5. Gate 3 acceptance contract (revised — no LIVE/STATIC escape hatch)

Per Owner's round-1 correction, exactly one of the following two models applies, chosen by which option is ultimately approved — never a hybrid "Gate 3 complete but LIVE pending" state:

**Model A (applies if `performance-budget`/`performance-heavy` remain as executable jobs — i.e., if Option A or B is chosen instead of D):** their required LIVE proof remains mandatory and non-waivable. GAP-041 Gate 3 is **BLOCKED** for those two jobs until the independent `.github/scripts/ci_prepare_testing_env.sh` defect is resolved (by its own, separately authorized work item) and the jobs genuinely reach PHPUnit on a live run.

**Model B (applies under the recommended Option D):** `performance-budget`/`performance-heavy` no longer exist as jobs, so they carry no PHPUnit live-execution requirement. Instead, Gate 3 must prove, on the exact implementation tree:
1. The two misleading job definitions (`performance-budget`, `performance-heavy`) have actually been removed from `a11y-perf-testing.yml` — not merely renamed or repointed — verified by reading the exact committed workflow file at the Gate-3 SHA.
2. `performance-tests` satisfies its **full LIVE acceptance contract** (below) with no exception.

**Full LIVE acceptance contract for every surviving job (`performance-tests` under either model):**
1. The job's name/description matches what it actually tests — no claim of coverage the job does not provide.
2. The intended test population is real and traceable to specific, existing test files/methods (not merely "some group exists somewhere").
3. **≥1 test belonging to that intended population executes in a LIVE GitHub Actions run on the exact implementation tree** — a JUnit/log artifact showing a non-zero test count from that actual run, not a local reproduction and not a static read of configuration.
4. Zero-selection causes a non-zero job failure via `--fail-on-empty-test-suite` (or a technically equivalent native fail-closed mechanism) — proven **live**: a throwaway, subsequently-reverted commit that reintroduces a zero-selection condition (e.g., a mismatched group name) must be shown, via a real GitHub Actions run, to fail the job. Reading the flag's own documentation or a local reproduction is supporting evidence, not a substitute for this live proof.
5. Evidence classification (LIVE/STATIC/LOCAL/HISTORICAL) is used honestly throughout the Gate 3 packet — no STATIC or LOCAL result is described as LIVE.

No Gate-3-complete state is permitted while any LIVE-classified criterion above is still pending; if a live run cannot yet be captured (e.g., because of the missing-script dependency under Model A), Gate 3 must remain `blocked_technical` or `awaiting_owner`-with-explicit-blocker, not "complete."

## 6. The masked-job (`performance-budget`/`performance-heavy`) verification problem — disposition under Option D

`.github/scripts/ci_prepare_testing_env.sh` does not exist anywhere in the repository's git history, which is why `performance-budget`/`performance-heavy` currently fail at "Prepare testing environment" (exit 127) before ever reaching PHPUnit. Under the recommended Option D:

- Retiring the two phantom tier jobs **removes their dependency on the missing-script defect from GAP-041's own Gate-3 path** — there is no longer a `performance-budget`/`performance-heavy` PHPUnit step whose live reachability GAP-041 needs to prove, so Model B (§5) applies and GAP-041 is not blocked by that defect.
- This **does not resolve** the independent missing-script defect itself. `.github/scripts/ci_prepare_testing_env.sh` is still referenced (and still missing) for the *other* jobs in `a11y-perf-testing.yml` — `accessibility-tests`, `e2e-tests`' environment setup, and (per Gate 1's evidence) it also blocks that workflow's `Lighthouse CI` job. Those jobs remain broken, independently of GAP-041, and independently of whether Option D is approved.
- Recommend (informational only, not authorized for action here): register the missing-script defect as its own Operational Gap Register entry once Owner separately authorizes it. Not created, investigated further, or fixed under this directive.

## 7. Evidence classification

- **[STATIC]**: `phpunit.xml` group-exclude config; `@group` annotations (or their absence) in `tests/Performance/*.php` and repo-wide; PHPUnit `Merger.php`/`Help.php`/`Cli/Builder.php` source confirming `--group` cancellation semantics and `--fail-on-empty-test-suite` existence; full commit history of both workflow files via `git log --follow -p` / `git log -S`; repo-wide search confirming no consumer of `performance-budget`/`performance-heavy` job artifacts exists outside `a11y-perf-testing.yml` itself.
- **[LOCAL]**: direct `./vendor/bin/phpunit` invocations in the pinned worktree reproducing (a) `No tests executed!` at exit `0` for both defect shapes, and (b) exit `1` for the identical invocations once `--fail-on-empty-test-suite` is added.
- **[LIVE]**: carried forward, unchanged, from the approved Gate 1 packet (`performance-tests` false-green on exact current main; `performance-budget`/`performance-heavy` blocked earlier by the unrelated missing-script defect). No new live dispatch was performed in this Gate 2 design step, consistent with "no workflow/test code changes at Gate 2."
- No claim in this document describes a STATIC or LOCAL result as a LIVE workflow proof, and none describes a pending LIVE requirement as satisfied.

## 8. Scope boundaries (restated, unchanged)

**In scope for the recommended Option D implementation (Gate 3, not authorized yet):** `performance-tests`' CLI invocation (`--group performance` + `--fail-on-empty-test-suite`); deletion of the `performance-budget`/`performance-heavy` job definitions from `a11y-perf-testing.yml`; a live-dispatch verification proof per §5's contract. **Out of scope:** performance thresholds/budgets, new/backfilled tests (Option B), application/business/RBAC/tenant/production-schema code, GAP-040, GAP-042, `.github/scripts/ci_prepare_testing_env.sh`, and any branch-protection policy change. None of these boundaries require a Design Dependency Preflight — no proposed option touches production/business/tenant/RBAC semantics.
