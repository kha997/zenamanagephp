---
work_id: GAP-041
gate: 2
gate_status: changes_requested
owner_decision:
  value: changes_requested
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-08-21-gap-041-zero-test-performance-ci-evidence.md
  plan: null
  branch: docs/GAP-041-gate2-design
  pr: 277
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-21T16:10:00+07:00"
  owner_response_reference: "Owner Gate 2 decision — REQUEST CHANGES, recorded against PR #277 head 372a2d97fcb083a46e331be35d2a5430892c0796 (Gate 1 approved record: PR #276 commit 74635722e7465ff043a257c78d6de040f5bf85c5): 'GAP-041 — GATE 2 OWNER DECISION: REQUEST CHANGES ... The investigation/reconstruction is accepted as useful evidence, but the recommended Option A is not yet internally consistent with that evidence. NO implementation is authorized. Keep PR #277 Draft and revise Gate 2 only. OWNER FINDING 1 — SEMANTIC TRUTHFULNESS: changing performance-budget/performance-heavy to --group performance while continuing to present them as Performance Budget Tests/Performance Heavy Tests does NOT establish semantic truthfulness — it changes the failure mode from zero tests under a claimed tier to real generic tests under a tier claim those tests do not actually implement. That is not an acceptable completion of GAP-041. Also correct the statement that the jobs are relabeled honestly unless the design actually changes/removes their semantic labels. OWNER DIRECTION — ADD A HYBRID OPTION: add and seriously evaluate Option D — repair the real performance surface, retire the phantom tiers: performance-tests retains the job, restores its real file-matrix population via --group performance override, adds native --fail-on-empty-test-suite, preserves the existing real-MySQL preflight; performance-budget/performance-heavy retire these two misleading CI job claims rather than pointing them both at the generic performance group, do NOT create fake annotations, do NOT invent budget/heavy tests under GAP-041, do NOT pretend PERF_ITERATIONS/PERF_SEED_COUNT create a meaningful heavy profile. If genuine budget/heavy tiers are desirable later, that should be a separately scoped work item. Compare revised Option D against B and C and state clearly whether A is still recommended after applying the CI-truthfulness invariant. Owner preference is Option D unless repository evidence demonstrates a concrete reason those two phantom jobs must remain. OWNER FINDING 2 — GATE 3 CONTRACT: remove the Gate-3 escape hatch in prior §5.4 (LIVE-required-but-STATIC/LOCAL-acceptable-with-LIVE-pending is self-contradictory and not permitted). Only two valid models: (A) if performance-budget/performance-heavy remain executable jobs, their required LIVE proof remains mandatory and GAP-041 Gate 3 is BLOCKED until the independent missing-script defect is resolved and those jobs genuinely reach PHPUnit; or (B) if the revised design retires those nonexistent tier jobs, they no longer require PHPUnit live-execution proof — instead Gate 3 must prove, on the exact implementation tree, that the misleading CI claims have actually been removed, and that the surviving performance-tests job satisfies its full LIVE acceptance contract. No Gate-3-complete-but-LIVE-pending state is permitted. REVISE THE ACCEPTANCE CONTRACT: for every surviving job require job claim/name matches what is actually tested, intended population is real and traceable, >=1 intended test executes in LIVE GitHub Actions on the exact implementation tree, zero-selection causes non-zero failure via --fail-on-empty-test-suite or technically equivalent native fail-closed behavior, evidence classification remains honest; for any retired phantom job require proof the misleading claim is removed rather than replaced with another unsupported claim; do not weaken LIVE to STATIC/LOCAL. MISSING-SCRIPT DEFECT remains OUT OF SCOPE for GAP-041, do not fix it here, continue recommending a separate Operational Gap work item, do not create/implement it in this correction unless separately authorized; if Option D is selected, explicitly note that retiring the two phantom tiers removes their missing-script dependency from GAP-041's own Gate-3 path but does NOT resolve the independent missing-script defect affecting the rest of a11y-perf-testing.yml. GATE-2 ARTIFACT CONSISTENCY: reconcile the Gate-2 packet so summary/recommended option/acceptance contract/scope/Owner-facing report all say the same thing; inspect frontmatter reference semantics against prior Gate-2 packets such as GAP-040 — if references.spec at Gate 2 is expected to identify the Gate-2 engineering design, do not leave it pointing only to the carried-forward Gate-1 audit merely because that makes the path resolve; follow the canonical repository convention, not just what passes lint; do not create unnecessary artifacts if the governance convention does not require them, document the finding either way. STOP CONDITIONS: no workflow edits, no test edits, no application edits, no implementation plan, no GAP-042 work, no missing-script repair, no merge/release/deploy. Revise the Gate-2 design on PR #277, keep gate_status: awaiting_owner and owner_decision.value: none once revised, run the exact-head governance checks again, then STOP and report new Gate-2 head SHA, files changed, whether Option D was added, final recommendation and reasoning, exact surviving/retired CI semantics, revised Gate-3 acceptance contract, whether every LIVE requirement is now hard/non-waivable, disposition of the missing-script dependency, references.spec reconciliation result, exact-head CI results, and confirmation that no implementation/GAP-042/missing-script fix occurred. Wait for a new Owner Gate-2 decision.'"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-21T15:45:00+07:00"
  updated_at: "2026-08-21T16:10:00+07:00"
generated_by: agent
---

## Gate 1 provenance

Gate 1 approved by Owner on `docs/owner-decisions/GAP-041/01-request.md` at commit `74635722e7465ff043a257c78d6de040f5bf85c5` on PR #276 (reviewed head `98a45eac9d2819caa0ea40c572f2a0ac8d675aa3`, canonical baseline `0b77747551a3e0da08e3e41c73a0a88f529b19f3`). PR #276's CI is green at the Gate-1-approval commit (`Owner Governance Lint` pass, `test-routes-guardrails` pass). This Gate 2 packet is submitted as a separate Draft PR cut from clean `origin/main` at the same baseline `0b77747551a3e0da08e3e41c73a0a88f529b19f3`, per the GAP-039/GAP-040 gate-ordering precedent — Gate 1 history is not mixed into this branch.

## Owner Summary

GAP-041 is a CI truthfulness/test-selection integrity defect, not a performance regression or a security issue. Three jobs across two workflows claim to run performance tests against real MySQL and each currently selects **zero** PHPUnit tests. `performance-tests` is live-proven to do this while still reporting `success` (false-green). `performance-budget`/`performance-heavy` are proven by exhaustive static search + local reproduction to have the identical zero-selection defect, but their live workflow currently fails *earlier*, loudly, for an unrelated missing-script reason (§6) — so they must not be described as currently live-green. This design proposes the smallest complete mechanism that makes all three surfaces truthful, and separates "select the right tests" from "never go silently green on zero tests" as two independent, permanent concerns.

## 1. Reconstructed semantics of each job (not inferred from job names)

### 1.1 `automated-testing.yml` → `performance-tests`

- **Origin**: this job has always been matrix-driven over explicit file paths (`matrix.perf_file: tests/Performance/PerformanceMonitoringTest.php` / `DashboardPerformanceTest.php`), confirmed across its entire commit history (`git log --follow -p` on `automated-testing.yml`) — the matrix has never selected tests by group name.
- **Intended population**: "every test defined in the matrix-selected file." The job asks for a *file*, not a *group*; group filtering was never part of its design.
- **What breaks it**: `phpunit.xml`'s global `<groups><exclude><group>performance</group>...</exclude></groups>` default-excludes tests tagged `@group performance` unless the CLI passes `--group performance` to cancel that specific exclusion (PHPUnit 11.5 `Merger.php:712-725`: `--group` only cancels the exclude for the group(s) actually passed, never wildcard). Both target classes carry only `@group performance`, and the CLI invocation (`php artisan test "${perf_file}"`) never passes `--group`. The file-level intent and the group-exclusion default are in direct, silent conflict — nobody chose this outcome; it is an interaction nobody exercised in CI until now.
- **Conclusion**: the workflow's selector is wrong relative to its own intent. Fix belongs in the CLI invocation (add `--group performance`), not in the test annotations (which are correct and consistent with every other real `@group performance` consumer in the repo).

### 1.2 `a11y-perf-testing.yml` → `performance-budget` / `performance-heavy`

- **Origin**: `--group performance_budget` / `--group performance_heavy` have been present, unchanged, since the job was created by commit `cd60e4f8` ("ci: split perf workflow into budget/heavy and run nightly only", 2026-01-13) — this was a deliberate design split, not an accident.
- **Intended population**: the split implies two distinct performance-test tiers — a fast "budget" check (thresholds) and a "heavy" load/seed-scale check. This is reinforced by `performance-heavy`'s invocation setting `PERF_ITERATIONS=1 PERF_SEED_COUNT=10` env vars specifically for that job.
- **What actually exists**: a repo-wide, exhaustive search (`grep -rn` across all of `tests/`) finds **zero** tests, ever, in the entire git history, carrying `@group performance_budget` or `@group performance_heavy`. Additionally, `PERF_ITERATIONS`/`PERF_SEED_COUNT` are **never read** by any application or test code (`grep -rn` across `tests/`, `app/`, `src/` finds no reference) — the env-var wiring is itself unconsumed scaffolding.
- **Conclusion**: this is not a naming typo or an annotation drift on existing tests — it is a **job split that was designed but never followed through with actual tests**. `performance_budget`/`performance_heavy` are stale/aspirational group names with no current referent in the test suite. Simply adding `--group performance` to these two jobs would make them pass by re-running the *same* generic `performance`-tagged tests under two different job names/env vars that those tests don't even read — technically "green," but semantically meaningless (exactly the kind of fix the Owner's directive warns against: "do not create meaningless annotations merely to satisfy CI," which applies symmetrically to selector changes that erase a real intended distinction without saying so).

## 2. Current-state matrix

| Surface | Intended selector (reconstructed) | Actual selector | Existing group annotations found | Selected test count today | Live reachability today | Current exit behavior on 0 selected |
|---|---|---|---|---|---|---|
| `performance-tests` (both matrix legs) | Run every test in the matrix-selected file | `php artisan test "${perf_file}"` (no `--group`) | Both classes: `@group performance` only | **0** (live-confirmed) | Reached every PR/push to `main` (not a required check) | Exit 0, job **succeeds** — false-green, live-proven |
| `performance-budget` | A fast/threshold-focused performance tier (never implemented) | `--group performance_budget` | None anywhere in repo history | **0** (static + local) | Blocked earlier by §6's missing-script defect (job fails at "Prepare testing environment", exit 127, before MySQL/PHPUnit) | Exit 0 *if reached* (proven locally) — currently masked by the earlier, unrelated failure |
| `performance-heavy` | A load/seed-scale performance tier (never implemented) | `--group performance_heavy` | None anywhere in repo history | **0** (static + local) | Same as `performance-budget` | Same as `performance-budget` |

## 3. Options considered

### Option A — Minimal selector fix + native fail-closed flag, no new tests (recommended)

- **performance-tests**: add `--group performance` to the CLI invocation, so the matrix-file intent is restored (existing `@group performance` tests run as originally designed).
- **performance-budget / performance-heavy**: change `--group performance_budget` / `--group performance_heavy` to `--group performance` (run the existing, real performance tests under both jobs — differentiated only by their already-distinct env vars and nightly-only schedule, honestly relabeled rather than left pointing at group names that were never real). This is a scope-honest correction: it converts an aspirational-but-abandoned split into "the same coverage, run under two operational profiles," and stops pretending a distinction exists in the test suite that was never built.
- **All three jobs**: add `--fail-on-empty-test-suite` (native PHPUnit 11.5 CLI flag, confirmed present at `vendor/phpunit/phpunit/src/TextUI/Help.php:215` and `Cli/Builder.php:116,724`; **[LOCAL]**-verified in the pinned worktree: without it, `No tests executed!` exits `0`; with it, the identical invocation exits `1` for both the no-`--group` and `--group performance_budget` reproductions). This is the permanent fail-closed guard: any future drift that again resolves an intended selection to zero tests will hard-fail the job instead of silently succeeding.
- **Correctness**: full — restores each job's traceable intent using only the selector fix each surface actually needs.
- **Fail-closed**: yes, native mechanism, no custom code.
- **Maintainability**: highest — one CLI flag change per job invocation line, zero new files, zero new scripts.
- **Regression risk**: low. `--group performance` is already the working pattern for `performance-tests`' own historical intent; reusing it for budget/heavy only changes *which* existing tests run, not application/production code.
- **Scope size**: smallest of the three options — 3 workflow-line edits.
- **Evidence that could prove it**: re-dispatch both workflows live at the fix commit; confirm non-zero `tests` count in job output/JUnit for all three jobs, and confirm a deliberately-reintroduced zero-selection (e.g., a throwaway commit renaming the group in a test file) makes the job fail, not succeed — proving the fail-closed guard actually functions before removing the throwaway commit.

### Option B — Backfill real, distinct `performance_budget`/`performance_heavy` tests

- Write new test methods (or split existing `PerformanceMonitoringTest`/`DashboardPerformanceTest` methods) that are genuinely fast-threshold-only (tag `performance_budget`) versus genuinely load/seed-scale using the already-wired `PERF_ITERATIONS`/`PERF_SEED_COUNT` env vars (tag `performance_heavy`), restoring the *original* 2026-01-13 design intent rather than abandoning it. Add `--fail-on-empty-test-suite` to all three jobs as in Option A.
- **Correctness**: highest fidelity to the *original* author's intent (the env-var wiring strongly suggests this was the plan), but requires new test-writing judgment calls (what counts as "budget" vs "heavy") that risk becoming a bug-fix-shaped feature addition rather than a truthfulness fix.
- **Fail-closed**: same native flag, same guarantee.
- **Maintainability**: lower — two new or reorganized test surfaces to maintain going forward, on top of the existing generic `performance` tests.
- **Regression risk**: medium — new/split tests can themselves be flaky or budget-threshold-brittle; this is exactly the kind of "add features/tests beyond what a truthfulness fix requires" scope creep the directive's Design Dependency Preflight framing is meant to catch if it started touching production behavior (it doesn't here, but it is a larger, judgment-heavy diff for a CI-integrity gap).
- **Scope size**: largest — new/modified test code, not just CI config.
- **Evidence that could prove it**: same live-dispatch method as Option A, plus code review of the new/split test methods' actual threshold/load semantics.

### Option C — Retire `performance-budget`/`performance-heavy` as jobs entirely

- Delete the two never-realized jobs from `a11y-perf-testing.yml`, keeping only `performance-tests` (fixed per Option A) as the single real MySQL-performance CI surface, since no distinct budget/heavy tests were ever built in over 7 months of history.
- **Correctness**: honest about what currently exists, but destructive to whatever the original 2026-01-13 split was trying to accomplish, with no owner sign-off that the split is genuinely unwanted rather than merely unfinished.
- **Fail-closed**: not applicable to the removed jobs; `performance-tests` still gets the guard.
- **Maintainability**: simplest steady state, but forecloses the option to backfill later without re-deciding workflow structure.
- **Regression risk**: low technically, but a governance risk — removing a CI surface is a bigger, less reversible decision than fixing its selector, and this Gate 2 packet does not have evidence that Owner intends to abandon the budget/heavy tiering concept rather than complete it.
- **Scope size**: smallest in one sense (deletion) but carries the highest "did we throw away real intent" risk.
- **Evidence that could prove it**: absence of the two jobs from subsequent workflow runs; not falsifiable by test evidence the way A/B are, since there is nothing left to select.

## 4. Recommended option

**Option A.** It is the minimum *complete* solution, not merely the minimum diff: every job's selector is corrected to match its own reconstructed intent (§1), the two masked jobs are relabeled honestly rather than left pointing at group names with zero real referents or silently deleted, and the native `--fail-on-empty-test-suite` flag closes the truthfulness gap permanently and structurally for all three jobs with no new custom shell/CI machinery — satisfying "prefer the smallest reusable mechanism consistent with existing repository patterns" directly. Option B is a legitimate future enhancement (restoring the original budget/heavy distinction with real tests) but is a test-content decision that deserves its own scoped work item if Owner wants it, not bundled into a CI-truthfulness fix. Option C is not recommended without explicit Owner sign-off that the budget/heavy split itself (not just its unfinished state) should be abandoned — that is a bigger decision than this gap's problem statement authorizes.

## 5. Acceptance contract (Gate 3 completion criteria)

GAP-041 is complete only when, for each of `performance-tests`, `performance-budget`, `performance-heavy`:

1. The job's PHPUnit invocation selects **at least one real test** belonging to its now-honestly-reconstructed intended population, proven by a **live** GitHub Actions run at the fix commit showing a non-zero test count in the job's own output/JUnit artifact (not a local reproduction only).
2. `--fail-on-empty-test-suite` (or an equivalently strict native mechanism) is present on the job's PHPUnit CLI invocation, and its fail-closed behavior is **live-proven**: a throwaway, reverted commit that reintroduces a zero-selection condition (e.g., mismatched group name) must make the job **fail**, observed on a real GitHub Actions run — not just asserted by reading the flag's documentation.
3. No production, business, RBAC, tenant, or schema code is touched; no performance threshold/budget value is changed; GAP-040 and GAP-042 remain untouched.
4. `performance-budget`/`performance-heavy` specifically: the live-green proof required by (1)/(2) can only be captured once §6's missing-script defect is independently resolved (out of GAP-041's implementation scope, see §6) — Gate 3 for these two jobs must either (a) wait on that independent fix landing first, or (b) accept and explicitly document a Gate-3 proof that is STATIC+LOCAL-complete but LIVE-pending, with a named follow-up condition, not a silent gap in the evidence. Gate 3 must not claim LIVE proof for these two jobs unless a real GitHub Actions run actually reached and passed the PHPUnit step.

## 6. The masked-job (`performance-budget`/`performance-heavy`) verification problem — explicit disposition

These two jobs cannot reach PHPUnit on a live run today because `.github/scripts/ci_prepare_testing_env.sh` does not exist anywhere in the repository's git history (confirmed: `git log --all -- .github/scripts/ci_prepare_testing_env.sh` returns nothing), so the job fails at "Prepare testing environment" (exit 127) before MySQL setup or PHPUnit ever run. Three options were available for GAP-041's own completion path; recommending the following:

- **Recommended**: fix GAP-041's selector + fail-closed-flag configuration now, on schedule with `performance-tests`, and treat the missing-script defect as an **independent blocking dependency for LIVE Gate-3 proof of `performance-budget`/`performance-heavy` specifically** (not for `performance-tests`, which is unaffected and already live-provable). This keeps GAP-041's fix atomic and correctly scoped, while being explicit that full end-to-end confidence for two of the three jobs is contingent on a separate, already-flagged defect being resolved by its own work item. This is preferable to silently declaring Gate 3 complete on STATIC/LOCAL evidence alone for those two jobs, and preferable to scope-creeping the missing-script fix into GAP-041 (which the Owner's Gate 1 decision explicitly forbids).
- Not recommended: bundling the missing-script fix into GAP-041 (forbidden by Owner direction) or declaring Gate 3 done for all three jobs using only STATIC/LOCAL evidence for two of them (would misrepresent the evidence classification this whole gap exists to enforce).

## 7. Missing-script defect — disposition recommendation (informational only, not authorized for action here)

Recommend registering `.github/scripts/ci_prepare_testing_env.sh`'s absence as its own Operational Gap Register entry once Owner authorizes it — it independently blocks four jobs in `a11y-perf-testing.yml` (`accessibility-tests`, `performance-budget`, `performance-heavy`, and indirectly `e2e-tests`' environment setup), all currently failing loudly (exit 127/other), which is a real operational gap in its own right (a whole nightly workflow silently non-functional since at least May 2026, per the register's historical note) independent of GAP-041's truthfulness concern. Not created, investigated further, or fixed under this directive.

## 8. Evidence classification used in this design

- **[STATIC]**: `phpunit.xml` group-exclude config; `@group` annotations (or their absence) in `tests/Performance/*.php` and repo-wide; PHPUnit `Merger.php`/`Help.php`/`Cli/Builder.php` source confirming `--group` cancellation semantics and `--fail-on-empty-test-suite` existence; full commit history of both workflow files via `git log --follow -p` / `git log -S`.
- **[LOCAL]**: direct `./vendor/bin/phpunit` invocations in the pinned worktree reproducing (a) `No tests executed!` at exit `0` for both defect shapes, and (b) exit `1` for the identical invocations once `--fail-on-empty-test-suite` is added — proving the recommended mechanism actually flips the outcome, not just that the flag exists.
- **[LIVE]**: carried forward, unchanged, from the approved Gate 1 packet (`performance-tests` false-green on exact current main; `performance-budget`/`performance-heavy` blocked earlier by the unrelated missing-script defect) — no new live dispatch was performed in this Gate 2 design step, consistent with "no workflow/test code changes at Gate 2."
- No claim in this document describes a STATIC or LOCAL result as a LIVE workflow proof.

## 9. Scope boundaries (restated)

**In scope for the recommended Option A implementation (Gate 3, not authorized yet):** the three workflow CLI invocation lines (`--group` values), the three matching `--fail-on-empty-test-suite` additions, and a live-dispatch verification proof per the acceptance contract. **Out of scope:** performance thresholds/budgets, application/business/RBAC/tenant/production-schema code, GAP-040, GAP-042, `.github/scripts/ci_prepare_testing_env.sh`, and any branch-protection policy change. None of these boundaries require a Design Dependency Preflight — no proposed option touches production/business/tenant/RBAC semantics.

## What the owner is NOT being asked to decide

Owner is not being asked to approve any code/workflow change at this step — only whether Option A's mechanism (selector correction reconstructed per-surface + native `--fail-on-empty-test-suite`) is the right target, whether the `performance-budget`/`performance-heavy` relabeling-to-`performance` (rather than backfilling new tests, Option B, or deleting the jobs, Option C) is acceptable, whether the acceptance contract in §5 is correct, and whether the masked-job disposition in §6 and the missing-script disposition recommendation in §7 are acceptable. No implementation plan, no code, and no Gate 3 are authorized by this document.
