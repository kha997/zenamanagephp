# GAP-041 — Zero-Test Performance CI Evidence (Gate 1)

Baseline: exact canonical `origin/main` at `0b77747551a3e0da08e3e41c73a0a88f529b19f3` (post register-reconciliation merge of PR #270, itself on top of GAP-040's release commit `aab48a23`). All evidence below is dated against this SHA unless explicitly marked otherwise. Investigation performed from a worktree (`.worktrees/GAP-041-gate1-investigation`) freshly created and pinned to this exact SHA — `git log -1` in that worktree reads `0b777475 docs(register): register GAP-041, GAP-042 (#270)`.

## 0. Evidence classification key

- **[LIVE]** — reproduced by triggering the actual GitHub Actions workflow at the exact SHA above (`gh workflow run ... --ref main`) and reading its logs via the GitHub API.
- **[STATIC]** — confirmed by reading repository source (workflow YAML, `phpunit.xml`, test file annotations, PHPUnit vendor source) at the exact SHA above.
- **[LOCAL]** — reproduced by running PHPUnit directly in the pinned worktree (not GitHub Actions; local PHP environment has known unrelated extension-loading noise — used only to double-check exit-code mechanics, not as CI-equivalent proof).
- **[HISTORICAL]** — evidence from the earlier SHA `87a4307f`, retained as background context, reconfirmed by [LIVE] evidence in this document where applicable.

## 1. Reconstructed CI surfaces

### 1.1 `automated-testing.yml` → job `performance-tests`

- **Trigger** [STATIC]: `pull_request` (branches `[main]`), `push` (branches `[main, develop, feature/*]`), `schedule` (daily 02:00), `workflow_dispatch`. `pull_request`/`push` paths exclude `docs/**`/`**/*.md`, but the job runs on ordinary code PRs/pushes to `main`. **Not** a branch-protection required check — `gh api repos/.../branches/main/protection --jq .required_status_checks.contexts` returns `["test-routes-guardrails"]` only.
- **MySQL setup** [STATIC]: real `mysql:8.0` service container, `ZENA_INVARIANTS_DB=mysql`, `DB_CONNECTION=mysql`; job runs `php artisan migrate --env=testing --force` + `php artisan db:seed --env=testing --force` against it, then an explicit step "Verify MySQL is genuinely reachable (GAP-039)" sourcing `scripts/ci/lib/mysql-fail-closed.sh` and calling `zena_mysql_ensure_connection` + `zena_mysql_preflight_connection` (`automated-testing.yml:1192-1196`).
- **Test command** [STATIC]: `php artisan test "${{ matrix.perf_file }}"` (`automated-testing.yml:1205`), matrix over `tests/Performance/PerformanceMonitoringTest.php` / `tests/Performance/DashboardPerformanceTest.php`. **No `--group` flag.**
- **Group configuration** [STATIC]: `phpunit.xml:19-24` — `<groups><exclude><group>performance</group><group>mysql-parity</group></exclude></groups>`. Both target classes carry only `@group performance` (`tests/Performance/PerformanceMonitoringTest.php:19`, `tests/Performance/DashboardPerformanceTest.php:24`).
- **Selection mechanics** [STATIC]: PHPUnit 11.5.56, `vendor/phpunit/phpunit/src/TextUI/Configuration/Merger.php:712-725`. When the CLI omits `--group`, `hasGroups()` is false, so `$groups` falls back to the XML `<groups><include>` list (empty). `$excludeGroups = array_diff($excludeGroups, $groups)` (line 725) is then a no-op against the full XML exclude list. Both classes are excluded outright by their `@group performance` tag.
- **[LOCAL]**: `./vendor/bin/phpunit -c phpunit.xml tests/Performance/PerformanceMonitoringTest.php` (no `--group`) in the pinned worktree → `No tests executed!`, exit `0`.
- **[LIVE] on exact current main**: workflow manually dispatched (`gh workflow run automated-testing.yml --ref main`), run `32460823422`, head SHA confirmed `0b77747551a3e0da08e3e41c73a0a88f529b19f3` via `gh run list --json headSha`.
  - Job "Performance Tests (tests/Performance/PerformanceMonitoringTest.php)" (job id `96707244065`): preflight step logs `Preflight MySQL connection succeeded (127.0.0.1:3306/zenamanage_test)` at `07:57:25.7358225Z`, immediately followed by the "Performance tests" step logging `INFO  No tests found.` at `07:57:26.2512563Z`, then the job proceeds normally to "Generate Performance Report" and completes with **conclusion: success**.
  - Job "Performance Tests (tests/Performance/DashboardPerformanceTest.php)" (job id `96707244153`): same pattern — `Preflight MySQL connection succeeded` at `07:57:17.2416685Z`, `INFO  No tests found.` at `07:57:17.7363345Z`, **conclusion: success**.
- **[HISTORICAL]** (superseded above): original discovery cited run `32328813467` at SHA `87a4307f` with an identical "No tests found." + job-success pattern.

### 1.2 `a11y-perf-testing.yml` → job `performance-budget`

- **Trigger** [STATIC]: `schedule` (nightly 03:00) + `workflow_dispatch` only — never runs on `pull_request` or `push`. Not a branch-protection required check.
- **MySQL setup** [STATIC]: real `mysql:8.0` service container, `ZENA_INVARIANTS_DB=mysql`, `DB_CONNECTION=mysql`; runs `php artisan migrate --env=testing --force`, then the same GAP-039 fail-closed preflight (`a11y-perf-testing.yml:150-155`) — **but only after** an earlier "Prepare testing environment" step (`./.github/scripts/ci_prepare_testing_env.sh`).
- **Test command** [STATIC]: `./vendor/bin/phpunit -c phpunit.xml --group performance_budget --stop-on-failure --log-junit=performance-budget-results.xml` (`a11y-perf-testing.yml:162`).
- **Group configuration** [STATIC]: repo-wide search for `@group performance_budget` / `#[Group('performance_budget')]` across `tests/` returns **zero matches**.
- **[LOCAL]**: `./vendor/bin/phpunit -c phpunit.xml --group performance_budget tests/Performance` in the pinned worktree → `No tests executed!`, exit `0`.
- **[LIVE] on exact current main — BLOCKED by an unrelated, separate defect, not GAP-041**: dispatched `gh workflow run a11y-perf-testing.yml --ref main`, run `32460830217`, head SHA confirmed `0b777475`. Job "Performance Budget Tests" (job id `96707261075`) **fails**, not succeeds — but at the "Prepare testing environment" step, *before* MySQL preflight or the phpunit `--group` step are ever reached:
  ```
  Prepare testing environment
  /home/runner/work/_temp/....sh: line 1: ./.github/scripts/ci_prepare_testing_env.sh: No such file or directory
  ##[error]Process completed with exit code 127.
  ```
  `git log --all --oneline -- .github/scripts/ci_prepare_testing_env.sh` in the pinned worktree returns **no commits at all** — this script has never existed in the repository's committed history. This means the job never reaches the phpunit `--group performance_budget` line on a live run today; it fails loudly and honestly (exit 127) for a completely unrelated missing-file defect. **This is evidence AGAINST a live "green despite 0 tests" reproduction for this job right now** — the job is currently red, for a different reason. The zero-test-selection defect (§ static evidence above) is real and would manifest the moment the missing-script defect is fixed, but cannot be live-demonstrated as "silently green" today because a louder, unrelated failure masks it first.
- **[HISTORICAL]**: prior to this Gate 1, the register's PR #270 discovery text described this job as "only statically confirmed... last real run failed in May 2026 at an old, unrelated SHA." That description is consistent with what was just reproduced live: the job does not currently complete successfully at all.

### 1.3 `a11y-perf-testing.yml` → job `performance-heavy`

- Same trigger/branch-protection profile as §1.2.
- **Test command** [STATIC]: `PERF_ITERATIONS=1 PERF_SEED_COUNT=10 ./vendor/bin/phpunit -c phpunit.xml --group performance_heavy --stop-on-failure --log-junit=performance-heavy-results.xml` (`a11y-perf-testing.yml:248`).
- **Group configuration** [STATIC]: repo-wide search for `@group performance_heavy` / `#[Group('performance_heavy')]` returns **zero matches**.
- **[LIVE] on exact current main — same blocker as §1.2**: job "Performance Heavy Tests" (job id `96707261401`) in the same dispatched run (`32460830217`) fails identically at "Prepare testing environment": `./.github/scripts/ci_prepare_testing_env.sh: No such file or directory`, exit 127 — never reaches the phpunit `--group performance_heavy` step.

### 1.4 A newly discovered, genuinely separate defect (NOT part of GAP-041, NOT fixed here)

`.github/scripts/ci_prepare_testing_env.sh` is referenced by four jobs in `a11y-perf-testing.yml` (`accessibility-tests`, `performance-budget`, `performance-heavy`, and indirectly gating `e2e-tests`'s environment setup) but the file — and the entire `.github/scripts/` directory — does not exist anywhere in the repository's git history (`git log --all -- .github/scripts/ci_prepare_testing_env.sh` returns nothing). All four jobs in this workflow's live dispatch (`32460830217`) failed: `Performance Budget Tests`, `Performance Heavy Tests` (exit 127, missing script), `E2E Tests` (exit 1), `Accessibility Tests (WCAG 2.1 AA)` (exit 2), `Lighthouse CI` (exit 1). Per the Owner's standing direction for GAP-041 (register discovery note) and this Gate 1's own scope rule, this is **not folded into GAP-041** — it is a different failure mode (loud, honest failure vs. GAP-041's silent false-success) and should be registered as its own work item if the Owner wants it tracked, not patched under this gap.

## 2. The three-fact distinction (per surface)

| Surface | 1. Infrastructure fact (MySQL genuinely reached) | 2. Selection fact (tests selected) | 3. Truthfulness defect (green despite 0 tests) |
|---|---|---|---|
| `performance-tests` (both matrix legs) | **Yes** — live-confirmed `Preflight MySQL connection succeeded` immediately precedes the test step, both legs, current main | **0** — live-confirmed `INFO No tests found.`, both legs, current main | **Yes** — both jobs live-confirmed `conclusion: success` on current main |
| `performance-budget` | Not reached on a live run today (blocked earlier by §1.4's missing-script defect); statically the fail-closed preflight code is present and would run if reached | **0** by exhaustive static search (no test anywhere carries `@group performance_budget`); confirmed locally via direct phpunit invocation | **Cannot currently be live-demonstrated** — the job is live-red today, for an unrelated reason, before it would reach the truthfulness-defect condition |
| `performance-heavy` | Same as `performance-budget` | **0** by exhaustive static search (no test anywhere carries `@group performance_heavy`); confirmed locally | Same as `performance-budget` — currently masked by §1.4 |

"Real MySQL preflight passed" is not evidence that MySQL performance tests actually ran — for `performance-tests` these are two independently-verified facts that both currently hold, producing a false-green. For `performance-budget`/`performance-heavy`, the preflight step is not even reached on a live run today, so the false-green cannot be live-demonstrated right now — but the selection-mechanics defect underneath it is proven with the same certainty (an exhaustive, deterministic repo-wide search for zero matching `@group` tags, not a probabilistic inference) and would resurface the moment §1.4 is independently fixed.

## 3. Blast-radius matrix (repo-wide)

Searched: all `.github/workflows/*.yml`, `scripts/**` for `--group`, `php artisan test`, `vendor/bin/phpunit` invocations; all `tests/**` for `@group`/`#[Group(...)]` annotations.

| Surface | Claimed purpose | Actual selection | Tests executed | Exit status | GAP-041 affected? |
|---|---|---|---|---|---|
| `automated-testing.yml` / `performance-tests` (both matrix legs) | Real-MySQL performance regression coverage | 0 (no `--group`, default-excluded `@group performance`) | No | **Success** (live-confirmed) | **Yes** |
| `a11y-perf-testing.yml` / `performance-budget` | Real-MySQL performance-budget coverage | 0 (`--group performance_budget`, no matching tests anywhere) | No (job fails earlier, see §1.4) | Failure today (unrelated defect); would be success once §1.4 is fixed, per static mechanics | **Yes** (mechanism proven; live green currently masked) |
| `a11y-perf-testing.yml` / `performance-heavy` | Real-MySQL heavy-load performance coverage | 0 (`--group performance_heavy`, no matching tests anywhere) | No (job fails earlier, see §1.4) | Same as above | **Yes** (mechanism proven; live green currently masked) |
| `routes-guardrails.yml` MySQL-parity step (`php artisan test --group=mysql-parity`) | MySQL-parity regression coverage | Non-zero — tests carrying `@group mysql-parity` exist | Yes | N/A | No |
| `scripts/ci/zena-invariants-mysql` / `zena-invariants` (`php artisan test --group=zena-invariants`) | Zena invariants on real MySQL | Non-zero — tests carrying `@group zena-invariants` exist | Yes | N/A | No |
| `automated-testing.yml` slow-tests step (`--stop-on-failure --group=slow`) | Slow API test coverage | Non-zero — tests carrying `@group slow` exist | Yes | N/A | No |
| `scripts/test/nightly_matrix.sh` (`slow`/`load`/`stress`/`redis` groups) | Nightly extended-coverage matrix | Non-zero for each — matching tests exist for all four groups; script additionally counts `tests=`/reports `SKIP` per group rather than trusting bare exit code | Yes | N/A (self-reporting) | No — different mechanism, out of scope |
| `scripts/ci/treasury-check-constraints-mysql` (targets specific Treasury files by path) | Treasury CHECK-constraint coverage on real MySQL | Non-zero — target files carry `@group stress` (not excluded by default) or no group | Yes | N/A | No |
| `ci-cd.yml` "Prove GAP-032 migrations on MySQL 8.0" (`phpunit.mysql.xml`, specific files) | GAP-032 migration proof, main gating pipeline | Non-zero — `phpunit.mysql.xml` only excludes `performance`; target files carry method-level `@group slow` (not excluded) or no group | Yes | N/A | No |

No other genuinely separate zero-test-selection defect was found. The missing-script defect (§1.4) is a different failure mode and is called out but not folded into GAP-041. GAP-040 (released) and GAP-042 (unverified, off-limits) are untouched by this investigation.

## 4. Answers to the required Gate 1 questions

1. **How many jobs are actually affected on current main?** Three: `performance-tests` (both matrix legs), `performance-budget`, `performance-heavy`.
2. **Which have live zero-test evidence?** `performance-tests`: yes, both legs, live-confirmed on exact current main (§1.1). `performance-budget`/`performance-heavy`: the zero-test-selection mechanism is proven with certainty by exhaustive static search and local reproduction, but a live "green despite 0 tests" run could not be captured today because an unrelated, separate defect (§1.4, missing `ci_prepare_testing_env.sh`) makes both jobs fail earlier and loudly instead.
3. **Which are only statically suspected?** None are merely "suspected" — `performance-budget`/`performance-heavy`'s zero-selection is deterministically proven (an exhaustive grep finding zero matches is not probabilistic), just not currently observable as a live green run due to §1.4.
4. **Does PHPUnit return exit 0 for the zero-test command in these paths?** Yes, confirmed locally for both command shapes (no `--group`, and `--group performance_budget`) — PHPUnit's "No tests executed!"/"No tests found." message is informational, exit code 0.
5. **Is MySQL genuinely reached before the zero-test result?** For `performance-tests`: yes, live-confirmed both legs. For `performance-budget`/`performance-heavy`: not on a live run today — the job fails at an earlier step, before the MySQL service is even used by PHPUnit; statically the fail-closed preflight code is present and would execute if the job got that far.
6. **Are any release-required workflows relying on these jobs?** No — branch protection's `required_status_checks.contexts` is `["test-routes-guardrails"]` only. None of the three gate PR merges. `performance-tests` does run automatically on every PR/push to `main`, so its false-green is visible in ordinary PR check lists even though it isn't a hard merge gate.
7. **Is the defect merely misleading naming, or does it remove intended mandatory coverage?** It removes intended coverage for `performance-tests` (proven live). For `performance-budget`/`performance-heavy`, the same removal is proven by mechanism, though currently overshadowed by a louder, unrelated, already-failing defect (§1.4) that would need independent resolution before this specific truthfulness defect could resurface in visible form.

## 5. Non-goals / explicit scope boundary

This document does not: propose a fix mechanism, change any workflow/test/script file, evaluate performance budgets or thresholds, touch GAP-040 (already released) or GAP-042 (separate, unverified, off-limits per Owner direction), or fix/register the missing-`ci_prepare_testing_env.sh` defect found in §1.4 — that is called out for the Owner's awareness only, as a candidate for its own separate work item.
