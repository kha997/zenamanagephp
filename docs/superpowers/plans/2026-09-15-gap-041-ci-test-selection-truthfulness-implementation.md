---
work_id: GAP-041
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-041/02-design.md
---

# GAP-041 CI Test-Selection Truthfulness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement approved Option D so the surviving real-MySQL performance matrix executes its intended non-empty population and fails closed on empty selection, while the two nonexistent performance tiers and only their consequential references are retired.

**Architecture:** Keep the existing `performance-tests` file matrix and MySQL setup intact, changing only its Artisan/PHPUnit arguments to select `@group performance` and fail on an empty suite. Remove the two phantom jobs as complete YAML units, then remove their two `test-summary` dependencies and generated sections. Carry the approved governance artifacts forward verbatim, reconcile only GAP-041's register row, and bind Gate-3 evidence to a frozen implementation subject and canonical digest.

**Tech Stack:** GitHub Actions YAML, Laravel 12.63.0 Artisan test command, Collision 8.9.4, PHPUnit 11.5.56, MySQL 8.0, repository Owner Governance tooling.

**Spec:** `docs/superpowers/specs/2026-08-21-gap-041-ci-test-selection-truthfulness-design.md` (approved Gate-2 v3 at PR #277 commit `23f841b0266e68f095113ab92e79a142e8232f42`)

## Global Constraints

- Implement Option D only; no new `performance_budget` or `performance_heavy` tests or annotations.
- Preserve `performance-tests`' genuine MySQL fail-closed preflight and explicit two-file matrix.
- Do not change performance tests, application code, database/schema/migrations, thresholds, GAP-045, A11y/Lighthouse/E2E behavior, merge/release/deployment state, or branch protection.
- Classify newly exposed assertions separately; do not repair them under GAP-041.
- Gate 3 requires LIVE exact-tree evidence for both non-empty matrix legs and a disposable LIVE empty-selection failure proof; no local/static result substitutes for LIVE.

---

### Task 1: Preserve approved authority and baseline evidence

**Files:**
- Create verbatim from PR #276 commit `74635722e7465ff043a257c78d6de040f5bf85c5`: `docs/audits/2026-08-21-gap-041-zero-test-performance-ci-evidence.md`
- Create verbatim from PR #276 commit `74635722e7465ff043a257c78d6de040f5bf85c5`: `docs/owner-decisions/GAP-041/01-request.md`
- Create from PR #277 commit `23f841b0266e68f095113ab92e79a142e8232f42`: `docs/owner-decisions/GAP-041/02-design.md`; preserve substantive content and provenance while applying only current-main packet-schema normalization (`approve` → `approved`; approved packets use `decision_requested: null`). PR #277 remains unchanged.
- Create verbatim from PR #277 commit `23f841b0266e68f095113ab92e79a142e8232f42`: `docs/superpowers/specs/2026-08-21-gap-041-ci-test-selection-truthfulness-design.md`

**Interfaces:**
- Consumes: approved Owner decisions recorded in PRs #276/#277.
- Produces: local immutable governance references required by this plan and Gate 3; no old branch history is merged.

- [ ] Verify Gate-1 evidence/request and Gate-2 spec byte-for-byte against their approved commits; verify the Gate-2 packet differs only by the two documented current-schema normalization lines.
- [ ] Run `php scripts/ssot/owner_governance_lint.php --enforce-gate-ordering`; expect PASS after all four approved artifacts exist.
- [ ] Commit only the approved authority artifacts plus this implementation plan.

### Task 2: Capture RED and implement the surviving selector contract

**Files:**
- Modify: `.github/workflows/automated-testing.yml` (`performance-tests` test command only)

**Interfaces:**
- Consumes: matrix variable `matrix.perf_file`, existing class-level `@group performance`, and unchanged GAP-039 MySQL preflight.
- Produces: `php artisan test "${{ matrix.perf_file }}" --group=performance --fail-on-empty-test-suite`.

- [ ] Record RED on unmodified main: `php artisan test tests/Performance/DashboardPerformanceTest.php` must report no tests and exit 0.
- [ ] Record fail-closed characterization: `php artisan test tests/Performance/DashboardPerformanceTest.php --group=gap041-no-such-group --fail-on-empty-test-suite` must report no tests and exit non-zero, proving Collision forwards the PHPUnit 11 option.
- [ ] Apply the one-line workflow command change without altering setup, preflight, matrix, test files, or reports.
- [ ] Verify both matrix file populations are discoverable with the exact selected group and each reports at least one test via PHPUnit list mode.
- [ ] Commit the selector/fail-closed workflow change.

### Task 3: Retire phantom tiers and consequential reporting references

**Files:**
- Modify: `.github/workflows/a11y-perf-testing.yml`

**Interfaces:**
- Consumes: the approved exhaustive inventory of the two job blocks, two `test-summary.needs` IDs, two summary sections, tier artifacts, and the missing script's only callers.
- Produces: a structurally valid workflow containing only `accessibility-tests`, `lighthouse-ci`, `e2e-tests`, and their three-entry `test-summary.needs` relationship.

- [ ] Delete complete `performance-budget` and `performance-heavy` job blocks.
- [ ] Change `test-summary.needs` to `[accessibility-tests, lighthouse-ci, e2e-tests]`.
- [ ] Delete only the Budget/Heavy summary sections.
- [ ] Exhaustively search tracked source for retired job IDs, group IDs, artifact IDs, env scaffolding, and `ci_prepare_testing_env.sh`; expected live producer/consumer references: zero.
- [ ] Parse both modified workflows with Symfony YAML and verify referenced `needs` job IDs resolve to defined jobs.
- [ ] Commit the phantom-tier retirement.

### Task 4: Reconcile GAP-041 register truthfully

**Files:**
- Modify: `OPERATIONAL_GAP_REGISTER.md` (GAP-041 row only)

**Interfaces:**
- Consumes: Gate-1 historical classifications, Gate-2 Option D, implementation state, and current-main GAP-043/044/045 status.
- Produces: one row distinguishing historical LIVE false-green `performance-tests`, statically/locally proven phantom tiers masked by their former setup defect, and Gate-3-awaiting implementation status.

- [ ] Replace only the GAP-041 row; retain GAP-045 and every unrelated row byte-identically.
- [ ] Verify `git diff -- OPERATIONAL_GAP_REGISTER.md` contains one-row-only changes and explicitly says merge/release remain pending Owner Gate 3.
- [ ] Commit the register reconciliation.

### Task 5: Verify locally and freeze the implementation subject

**Files:**
- No new implementation files.

**Interfaces:**
- Consumes: Tasks 1-4.
- Produces: an exact implementation subject SHA and clean validation record before Gate-3 packet creation.

- [ ] Run workflow YAML parse/needs validation, exhaustive phantom-tier search, `scripts/ci/lib/mysql-fail-closed.test.sh`, Owner Governance Lint, gate-ordering lint, docs lint, MySQL claim-truthfulness lint/tests, Routes Guardrails' relevant static checks, `git diff --check`, and scope diff checks.
- [ ] Confirm no test/application/schema/migration/GAP-045 file differs from canonical main.
- [ ] Commit any evidence-only corrections, then record the clean exact implementation subject SHA.

### Task 6: Obtain mandatory LIVE positive and negative evidence

**Files:**
- Temporarily modify only `.github/workflows/automated-testing.yml` on a disposable proof branch for the negative proof; remove that branch afterward.

**Interfaces:**
- Consumes: frozen implementation subject.
- Produces: final exact-subject workflow run plus a child disposable commit proving zero-selection exits non-zero.

- [ ] Push the implementation branch and dispatch `automated-testing.yml` at its exact subject; capture run/job IDs, head SHA, preflight-before-PHPUnit ordering, and non-zero test counts for both matrix legs. Classify a genuine unrelated assertion failure (including GAP-045) without modifying behavior.
- [ ] Dispatch `a11y-perf-testing.yml` at the same subject and prove GitHub accepts the YAML and `test-summary` resolves/completes without retired jobs.
- [ ] Create a disposable branch whose sole change replaces `--group=performance` with an unmatched group while retaining `--fail-on-empty-test-suite`; dispatch and capture the exact failing job/run with zero tests and non-zero conclusion.
- [ ] Delete the disposable remote branch and verify the implementation worktree/branch remains exactly at the frozen subject with no disposable diff or commit.

### Task 7: Create and verify the Gate-3 packet and Draft PR

**Files:**
- Create: `docs/owner-decisions/GAP-041/03-release.md`

**Interfaces:**
- Consumes: frozen implementation subject and all LOCAL/STATIC/LIVE evidence from Tasks 2-6.
- Produces: `gate_status: awaiting_owner`, `technical_readiness.value: ready` only if every mandatory criterion passed, `owner_decision.value: none`, exact evidence binding, and a Draft PR.

- [ ] Compute `implementation_tree_digest` with `owner_governance_compute_implementation_tree_digest()` against the frozen subject, excluding only the active GAP-041 Gate-3 packet per repository schema.
- [ ] Write the packet with `subject_sha`, digest, exact verified PR head/run IDs/timestamps, evidence classifications, unrelated failures, residual risk, and null owner binding.
- [ ] Commit the packet, push, and open a Draft PR whose first non-empty line is `Work ID: GAP-041`; do not mark ready.
- [ ] Run full local verification again, wait for exact-head PR checks, run evidence-freshness verification, and confirm PR head equals the packet's verified head.
- [ ] Stop for Owner Gate-3 review without merge, release, deployment, self-approval, or Ready-for-review transition.
