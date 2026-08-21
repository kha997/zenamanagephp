# GAP-041 CI Test-Selection Truthfulness — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `performance-tests` (automated-testing.yml) truthfully execute and fail-closed-verify its real `@group performance` population on genuine MySQL, and retire the never-implemented `performance-budget`/`performance-heavy` phantom-tier jobs (a11y-perf-testing.yml) including their consequential `test-summary` references.

**Architecture:** Two workflow-file edits only. No application code, no new tests, no new job definitions. `performance-tests` gains `--group performance --fail-on-empty-test-suite` on its existing `php artisan test "${{ matrix.perf_file }}"` invocation. `a11y-perf-testing.yml` loses the `performance-budget` and `performance-heavy` job blocks and their references in `test-summary`.

**Tech Stack:** GitHub Actions YAML, PHPUnit 11.5.56 (`--fail-on-empty-test-suite` confirmed present), Laravel's Collision `php artisan test` wrapper (confirmed to pass unrecognized flags straight through to the phpunit binary — see Task 1 evidence below).

**Authority:** `docs/superpowers/specs/2026-08-21-gap-041-ci-test-selection-truthfulness-design.md` (approved Gate-2 engineering spec, v3) and `docs/owner-decisions/GAP-041/02-design.md` (Owner APPROVE decision, Option D, hard-LIVE Gate-3 acceptance contract, non-waivable).

## Global Constraints

- Only `.github/workflows/automated-testing.yml` and `.github/workflows/a11y-perf-testing.yml` may be modified (approved implementation surface).
- No new `performance_budget`/`performance_heavy` tests, annotations, or jobs may be invented under GAP-041.
- `accessibility-tests`, `lighthouse-ci`, `e2e-tests` semantics must be preserved exactly except for the dangling-reference removal in `test-summary`.
- No merge, release, or deployment — this branch stays a feature branch pending a separate Gate-3 Owner decision.
- Every LIVE-classified Gate-3 criterion (real GitHub Actions run) is mandatory; no LOCAL/STATIC result may be promoted to LIVE.

---

## Task 1: Fail-on-empty mechanism — already verified, record the evidence

**Files:**
- Modify (evidence only, no code in this task): none — this task documents already-completed empirical verification for Task 2 to build on.

**Evidence (LOCAL, already gathered this session):**

`vendor/nunomaduro/collision/src/Adapters/Laravel/Commands/TestCommand.php::phpunitArguments()` filters out only `--env=*`, `-q`, `--quiet`, `--coverage`, `--compact`, `--profile`, `--ansi`, `--no-ansi`, `--min=*` from argv before forwarding the rest verbatim to `vendor/phpunit/phpunit/phpunit`. `--group` and `--fail-on-empty-test-suite` are not filtered — they pass through unmodified.

Empirical run (from repo root, PHPUnit 11.5.56):
```
php artisan test tests/Performance/PerformanceMonitoringTest.php --group=nonexistent-group-gap041-probe --fail-on-empty-test-suite
# => "INFO No tests found." exit code 1

php artisan test tests/Performance/PerformanceMonitoringTest.php --group=nonexistent-group-gap041-probe
# => exit code 0 (no flag, zero selection is silently "success" — this is the GAP-041 defect reproduced)

php artisan test tests/Performance/PerformanceMonitoringTest.php --group=performance --fail-on-empty-test-suite
# => tests actually run (not "No tests found") — confirms --group=performance selects the real population
```

**Conclusion: use the native flags directly.** No wrapper substitution needed.

- [ ] Step 1: No code change in this task — proceed to Task 2 using `--group performance --fail-on-empty-test-suite` as the confirmed mechanism.

---

## Task 2: Repair `performance-tests` in automated-testing.yml

**Files:**
- Modify: `.github/workflows/automated-testing.yml:1200-1206` (the "🧪 Performance tests" step of the `performance-tests` job)

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: `performance-tests` job now fails non-zero on zero-selection; later tasks (LIVE validation) depend on this change being present.

Current step (lines 1200-1206):
```yaml
    - name: 🧪 Performance tests
      env:
        APP_ENV: testing
      run: |
        echo "::group::Performance tests"
        php artisan test "${{ matrix.perf_file }}"
        echo "::endgroup::"
```

- [ ] **Step 1: Edit the step to add `--group performance --fail-on-empty-test-suite`**

```yaml
    - name: 🧪 Performance tests
      env:
        APP_ENV: testing
      run: |
        echo "::group::Performance tests"
        php artisan test "${{ matrix.perf_file }}" --group=performance --fail-on-empty-test-suite
        echo "::endgroup::"
```

Do not touch the matrix (`perf_file`/`perf_slug`), the MySQL service block, the DB setup step, or the GAP-039 fail-closed MySQL preflight step (`Verify MySQL is genuinely reachable`) — all preserved unchanged.

- [ ] **Step 2: YAML-validate the file**

Run: `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/automated-testing.yml'))" && echo OK`
Expected: `OK`

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/automated-testing.yml
git commit -m "fix(GAP-041): performance-tests — restore @group performance selection, fail-closed on empty"
```

---

## Task 3: Retire `performance-budget` and `performance-heavy` job definitions

**Files:**
- Modify: `.github/workflows/a11y-perf-testing.yml:84-254` (delete the entire `performance-budget:` job block, lines 84-168, and the entire `performance-heavy:` job block, lines 170-254)

**Interfaces:**
- Consumes: nothing.
- Produces: the two job IDs `performance-budget`/`performance-heavy` no longer exist anywhere in the workflow; Task 4 removes the now-dangling references to them.

- [ ] **Step 1: Delete the `performance-budget` job block**

Remove the entire block from `  performance-budget:` (line 84) through the blank line before `  performance-heavy:` (through line 169, inclusive of the trailing blank line).

- [ ] **Step 2: Delete the `performance-heavy` job block**

Remove the entire block from `  performance-heavy:` through the blank line before `  lighthouse-ci:` (originally lines 170-255).

Result: the file goes directly from the `accessibility-tests` job to the `lighthouse-ci` job.

- [ ] **Step 3: YAML-validate the file**

Run: `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/a11y-perf-testing.yml'))" && echo OK`
Expected: `OK`

- [ ] **Step 4: grep-verify no job definition remains**

Run: `grep -n "performance-budget:\|performance-heavy:" .github/workflows/a11y-perf-testing.yml`
Expected: no output (only `needs:`/summary references remain, removed in Task 4).

- [ ] **Step 5: Commit**

```bash
git add .github/workflows/a11y-perf-testing.yml
git commit -m "fix(GAP-041): retire phantom performance-budget/performance-heavy tier jobs"
```

---

## Task 4: Clean `test-summary`'s consequential references

**Files:**
- Modify: `.github/workflows/a11y-perf-testing.yml` (the `test-summary` job — `needs:` list and the generated summary body)

**Interfaces:**
- Consumes: Task 3's removal of the two job definitions.
- Produces: a11y-perf-testing.yml with no dangling `needs.performance-budget.*`/`needs.performance-heavy.*` expressions anywhere.

Current (post-Task-3, original lines 427-455):
```yaml
  test-summary:
    name: Test Summary
    runs-on: ubuntu-latest
    needs: [accessibility-tests, performance-budget, performance-heavy, lighthouse-ci, e2e-tests]
    if: always()

    steps:
    - name: Download all artifacts
      uses: actions/download-artifact@v4

    - name: Generate test summary
      run: |
        echo "## Test Results Summary" >> $GITHUB_STEP_SUMMARY
        echo "" >> $GITHUB_STEP_SUMMARY
        echo "### Accessibility Tests" >> $GITHUB_STEP_SUMMARY
        echo "- Status: ${{ needs.accessibility-tests.result }}" >> $GITHUB_STEP_SUMMARY
        echo "" >> $GITHUB_STEP_SUMMARY
        echo "### Performance Budget Tests" >> $GITHUB_STEP_SUMMARY
        echo "- Status: ${{ needs.performance-budget.result }}" >> $GITHUB_STEP_SUMMARY
        echo "" >> $GITHUB_STEP_SUMMARY
        echo "### Performance Heavy Tests" >> $GITHUB_STEP_SUMMARY
        echo "- Status: ${{ needs.performance-heavy.result }}" >> $GITHUB_STEP_SUMMARY
        echo "" >> $GITHUB_STEP_SUMMARY
        echo "### Lighthouse CI" >> $GITHUB_STEP_SUMMARY
        echo "- Status: ${{ needs.lighthouse-ci.result }}" >> $GITHUB_STEP_SUMMARY
        echo "" >> $GITHUB_STEP_SUMMARY
        echo "### E2E Tests" >> $GITHUB_STEP_SUMMARY
        echo "- Status: ${{ needs.e2e-tests.result }}" >> $GITHUB_STEP_SUMMARY
```

- [ ] **Step 1: Edit `needs:` and remove the two summary sections**

```yaml
  test-summary:
    name: Test Summary
    runs-on: ubuntu-latest
    needs: [accessibility-tests, lighthouse-ci, e2e-tests]
    if: always()

    steps:
    - name: Download all artifacts
      uses: actions/download-artifact@v4

    - name: Generate test summary
      run: |
        echo "## Test Results Summary" >> $GITHUB_STEP_SUMMARY
        echo "" >> $GITHUB_STEP_SUMMARY
        echo "### Accessibility Tests" >> $GITHUB_STEP_SUMMARY
        echo "- Status: ${{ needs.accessibility-tests.result }}" >> $GITHUB_STEP_SUMMARY
        echo "" >> $GITHUB_STEP_SUMMARY
        echo "### Lighthouse CI" >> $GITHUB_STEP_SUMMARY
        echo "- Status: ${{ needs.lighthouse-ci.result }}" >> $GITHUB_STEP_SUMMARY
        echo "" >> $GITHUB_STEP_SUMMARY
        echo "### E2E Tests" >> $GITHUB_STEP_SUMMARY
        echo "- Status: ${{ needs.e2e-tests.result }}" >> $GITHUB_STEP_SUMMARY
```

- [ ] **Step 2: Exhaustive stale-reference grep across the whole repo**

Run each of these from repo root and classify every hit (executable workflow reference = must be zero; historical/design-doc reference = acceptable):
```bash
grep -rn "performance-budget\|performance-heavy\|performance_budget\|performance_heavy" --include="*.yml" --include="*.yaml" .github/
grep -rn "needs\.performance-budget\|needs\.performance-heavy" .
grep -rn "ci_prepare_testing_env" .
grep -rn "PERF_ITERATIONS\|PERF_SEED_COUNT" .
```
Expected: zero hits under `.github/workflows/` for all of the above (the docs/audits and docs/superpowers/specs/docs/owner-decisions historical references are expected and acceptable).

- [ ] **Step 3: YAML-validate**

Run: `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/a11y-perf-testing.yml'))" && echo OK`

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/a11y-perf-testing.yml
git commit -m "fix(GAP-041): test-summary — remove dangling performance-budget/performance-heavy references"
```

---

## Task 5: LIVE validation (positive proof, both matrix legs + full workflow dispatch)

**Files:** none (CI execution + evidence capture only).

- [ ] **Step 1: Push the branch and open/refresh a draft PR or dispatch directly**

```bash
git push -u origin feature/GAP-041-ci-test-selection-truthfulness
```

- [ ] **Step 2: Trigger `automated-testing.yml` on the exact implementation SHA**

Push triggers it automatically for `feature/*` branches (see `on.push.branches` in the workflow). Capture the run ID:
```bash
gh run list --workflow=automated-testing.yml --branch=feature/GAP-041-ci-test-selection-truthfulness --limit=1
```

- [ ] **Step 3: Wait for `performance-tests` (both matrix legs) and capture evidence**

```bash
gh run watch <run-id> --exit-status
gh run view <run-id> --log | grep -A5 "Performance tests"
```
Required evidence per leg (monitoring, dashboard): MySQL preflight step succeeds; the "🧪 Performance tests" step shows >=1 test executed (not "No tests found"); job concludes success.

- [ ] **Step 4: Trigger `a11y-perf-testing.yml` via workflow_dispatch and capture evidence**

```bash
gh workflow run a11y-perf-testing.yml --ref feature/GAP-041-ci-test-selection-truthfulness
gh run list --workflow=a11y-perf-testing.yml --branch=feature/GAP-041-ci-test-selection-truthfulness --limit=1
gh run watch <run-id> --exit-status
```
Required evidence: `test-summary` job runs and completes (no `needs:`-resolution error), `performance-budget`/`performance-heavy` do not appear in the job list at all.

- [ ] **Step 5: Record run IDs, job IDs, and log excerpts** in a scratch evidence file for the Gate-3 packet (Task 7).

---

## Task 6: LIVE negative fail-closed proof (disposable, then reverted)

**Files:**
- Temporary modify (on a disposable commit only, never merged): `.github/workflows/automated-testing.yml` performance-tests step — change `--group=performance` to a nonexistent group to reintroduce zero-selection.

- [ ] **Step 1: Create a disposable commit on top of the implementation branch**

```bash
git checkout -b gap041-negative-proof-disposable
```
Edit the step from Task 2 to: `php artisan test "${{ matrix.perf_file }}" --group=nonexistent-gap041-negative-proof --fail-on-empty-test-suite`

```bash
git add .github/workflows/automated-testing.yml
git commit -m "TEMP(GAP-041): disposable negative fail-closed proof — DO NOT MERGE"
git push -u origin gap041-negative-proof-disposable
```

- [ ] **Step 2: Capture the LIVE failing run**

```bash
gh run list --workflow=automated-testing.yml --branch=gap041-negative-proof-disposable --limit=1
gh run watch <run-id>   # expect non-zero / failure
gh run view <run-id> --log | grep -A5 "Performance tests"
```
Required evidence: `performance-tests` job fails (non-zero) on both matrix legs because zero tests were selected, confirming the fail-closed mechanism is live-proven, not just locally proven.

- [ ] **Step 3: Delete the disposable branch and confirm it never merges**

```bash
git push origin --delete gap041-negative-proof-disposable
git branch -D gap041-negative-proof-disposable
git checkout feature/GAP-041-ci-test-selection-truthfulness
git branch --list gap041-negative-proof-disposable   # expect empty
git log --oneline feature/GAP-041-ci-test-selection-truthfulness | grep -i "negative-proof"   # expect empty
```

- [ ] **Step 4: Record the negative-proof run ID and log excerpt** for the Gate-3 packet.

---

## Task 7: Whole-branch review, exhaustive final search, and Gate-3 packet

**Files:**
- Create: `docs/owner-decisions/GAP-041/03-release.md` (Gate 3 packet, `gate_status: awaiting_owner`)

- [ ] **Step 1: Run `superpowers:code-review` (or the `code-review` skill) against the full diff** vs the approved Gate-2 spec (not just the latest commit).

- [ ] **Step 2: Exhaustive final grep** (repeat Task 4 Step 2's greps) on the final implementation-tree SHA; classify every match as historical/design-doc (acceptable) or executable workflow (must be zero).

- [ ] **Step 3: Run `superpowers:verification-before-completion`** before writing any completion claim.

- [ ] **Step 4: Run governance lint + `--enforce-gate-ordering` + Routes Guardrails** on the exact implementation-tree SHA.

```bash
php scripts/ssot/owner_governance_lint.php --enforce-gate-ordering
```

- [ ] **Step 5: Write `docs/owner-decisions/GAP-041/03-release.md`** with: exact implementation-tree SHA; this plan's path; complete changed-file inventory; the confirmed fail-closed mechanism + Task 1 empirical evidence; Task 5's positive LIVE run IDs (both matrix legs) + real-MySQL preflight evidence + selected-test counts; Task 6's negative-proof commit SHA (on the now-deleted branch, referenced by SHA only) + failing run/job IDs + proof of removal from final tree; Task 5 Step 4's `a11y-perf-testing.yml` dispatch/run evidence + `test-summary` completion proof; Task 7 Step 2's exhaustive search results; governance lint + gate-ordering + Routes Guardrails results; whole-branch review result; explicit confirmation no GAP-042/application/business/schema/RBAC work was absorbed. Set:
```yaml
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
```

- [ ] **Step 6: Commit and STOP**

```bash
git add docs/owner-decisions/GAP-041/03-release.md
git commit -m "docs(GAP-041): Gate 3 packet — awaiting Owner decision"
```

Do not mark ready for merge. Do not merge. Do not release. Do not deploy. Wait for Owner Gate-3 decision.
