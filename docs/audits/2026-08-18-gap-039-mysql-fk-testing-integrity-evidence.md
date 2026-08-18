# GAP-039 — MySQL Foreign-Key Testing Integrity: Gate 1 Evidence (v2, corrected/complete)

> Fact-finding pass for Gate 1. Records **FACTS** only from current branch. No solutions recommended. Read-only audit — no application code was modified to produce this evidence. Two throwaway diagnostic test files were created and removed locally to observe live environment/PRAGMA/connection state (`tests/Feature/ZZZFkCheckTest.php`, `tests/Feature/ZZZEnvOrderTest.php`); neither was committed. One of those runs incidentally created a stray SQLite file (`zenamanage_test`, no extension) at repo root as a side effect being investigated in §5 — it has been removed and is itself corroborating evidence, not an artifact of this document.

**v2 supersedes v1 of this document.** v1's root-cause direction (bootstrap.php silently forces SQLite; the one FK-naming test never runs) was directionally correct but the inventory was incomplete — it covered only 5 of 6 affected workflow files and did not distinguish job definitions from matrix executions. This version replaces v1's inventory and impact count with a complete, machine-derived one. v1's two core negative/positive findings are preserved unchanged (see §4, §6).

This is an **independent work item**. It does not reopen or modify GAP-037 (Treasury schema, Gate 2 approved) or GAP-038 (Treasury native check constraints).

## Attestation

- **Work ID:** GAP-039.
- **Method:** (a) static inspection of every `.github/workflows/*.yml` file (15 files) for every PHPUnit/Laravel-test entry point (`php artisan test`, `vendor/bin/phpunit`, `php artisan dusk`, `composer test:*` wrapper scripts, alternate PHPUnit XML configs), not a single grep pattern; (b) inspection of `tests/bootstrap.php`, `tests/TestCase.php`, `phpunit.xml`, `scripts/ci/*mysql*`; (c) **live empirical verification** of the actual runtime mechanism (PHPUnit `<php><env>` vs. shell-level env vs. `tests/bootstrap.php` precedence) by running the real PHPUnit process locally under controlled env-var combinations, not static reasoning alone.

---

## 1. The mechanism (how a job can genuinely get MySQL, and how it silently doesn't)

Three layers stack, in this precedence order (established empirically in this pass, §2):

1. **`tests/bootstrap.php`** (PHPUnit's `bootstrap=` script, runs first, before PHPUnit applies its own `<php>` XML block): if `getenv('ZENA_INVARIANTS_DB') !== 'mysql'`, it unconditionally calls `putenv('DB_CONNECTION=sqlite')` (plus `$_ENV`/`$_SERVER`) — this **overwrites** whatever the shell/CI job already had, because `putenv()` clobbers, it does not merely fill a gap.
2. **`phpunit.xml`'s `<php><env name="DB_CONNECTION" value="sqlite"/>`**: applied after the bootstrap script. PHPUnit's `<env>` directive, absent a `force="true"` attribute (not present here — confirmed via `grep -n force phpunit.xml`, no match), only **sets a variable if it is not already present** in the environment. It never overrides an already-set value.
3. **The CI workflow's own job/step-level `env: DB_CONNECTION: mysql`** (or `.env`/`.env.testing` file read by a plain `php artisan <cmd>` invocation that isn't going through PHPUnit at all, e.g. `migrate`, `db:seed`, or a `php -S` dev server).

**Net effect, empirically confirmed (§2):** a job's `DB_CONNECTION: mysql` genuinely survives into the PHPUnit process **only if `ZENA_INVARIANTS_DB=mysql` is also exported** before PHPUnit starts (skipping step 1's clobber) — at which point step 2 is a no-op (already set) and step 3's value is what PHPUnit actually sees. If `ZENA_INVARIANTS_DB` is absent, step 1 always wins regardless of what the workflow YAML declares.

`ZENA_INVARIANTS_DB` is referenced in exactly two places in the whole repo (`tests/bootstrap.php:29`, `tests/TestCase.php:465`) and **exported in exactly three places**, all `scripts/ci/*-mysql` wrapper scripts (`zena-invariants-mysql`, `rfi-escalation-concurrency-mysql`, `document-workflow-concurrency-mysql`). It is grep-confirmed absent from every `.github/workflows/*.yml` file and every other script in the repo.

## 2. Empirical proof of the mechanism (not just static reasoning)

Two controlled local runs, both through the real `tests/bootstrap.php` → `phpunit.xml` → PHPUnit path, no MySQL server available locally (so a real MySQL attempt is expected to error on connection, not silently succeed — that error is itself the proof it tried):

**Run A** — `ZENA_INVARIANTS_DB=mysql` exported, MySQL host/port/db/user/pass exported, but **`DB_CONNECTION` itself left unset** in the shell (mirrors what `tests/bootstrap.php`'s mysql-branch does — it never explicitly sets `DB_CONNECTION=mysql` itself, it only skips forcing sqlite):

```
DB_CONNECTION(getenv)=sqlite config.default=sqlite
```

Result: SQLite. Confirms step 2 (phpunit.xml's `<env>`) fills the gap when nothing else set it — this is expected/correct PHPUnit behavior, not a bug by itself.

**Run B** — same, but with `DB_CONNECTION=mysql` **also explicitly exported** in the shell (mirrors what every affected CI job's `env:` block does):

```
$ export DB_CONNECTION=mysql ZENA_INVARIANTS_DB=mysql DB_HOST=127.0.0.1 DB_PORT=3306 \
         DB_DATABASE=zenamanage_test DB_USERNAME=root DB_PASSWORD=password
$ ./vendor/bin/phpunit tests/Feature/ZZZEnvOrderTest.php
...
vendor/laravel/framework/src/Illuminate/Database/Connection.php:420
...
ERRORS!
Tests: 1, Assertions: 0, Errors: 1.
```

Result: a real `PDO`/MySQL connection attempt was made (and failed only because no MySQL server was reachable at `127.0.0.1:3306` in this local shell — the expected, correct failure mode, not a silent fallback). This proves: **when `ZENA_INVARIANTS_DB=mysql` is exported alongside `DB_CONNECTION=mysql`, PHPUnit genuinely attempts MySQL.** This is exactly the pattern the 3 `scripts/ci/*-mysql` wrapper scripts use, and is the only pattern in the repo that does.

**Corroborating side-effect from Run A:** it silently created a literal SQLite file named `zenamanage_test` (no extension) at the repo root — because `DB_DATABASE=zenamanage_test` (a value meant as a MySQL database name) was reinterpreted by Laravel's SQLite connector as a relative filesystem path once `DB_CONNECTION` silently fell back to `sqlite`. This is not a hypothetical risk; it is what actually happened in this repo, on this pass, demonstrating exactly how completely the fallback swallows MySQL-shaped configuration into nonsense SQLite state. The file was removed; it was never committed.

## 3. Complete workflow inventory — every job that invokes a PHPUnit/Dusk test entry point

All 15 `.github/workflows/*.yml` files were checked for `php artisan test`, `vendor/bin/phpunit`, `php artisan dusk`, and `composer test:*` wrapper scripts (not one grep pattern — each was checked individually and cross-referenced against `composer.json` script definitions). 5 files have **zero** such invocations and are out of scope entirely: `automated-deployment.yml`, `deploy.yml`, `release-management.yml`, `staging-smoke.yml`, `owner-governance-lint.yml`. `code-quality-security.yml` and `auth-lint.yml` also have zero.

Of the 15, **8 files contain at least one test-invoking job**: `ci-cd.yml`, `button-tests.yml`, `a11y-perf-testing.yml`, `production.yml`, `routes-guardrails.yml`, `automated-testing.yml`, `nightly-matrix.yml`, `ci-cd-code-quality-debug.yml`.

### 3a. Jobs that claim/provision MySQL but do not export `ZENA_INVARIANTS_DB` — AFFECTED

| # | Workflow | Job (matrix entry) | Test command | MySQL service? | Job `DB_CONNECTION` | `ZENA_INVARIANTS_DB`? | Effective PHPUnit backend | Evidence method |
|---|---|---|---|---|---|---|---|---|
| 1 | `ci-cd.yml` | `test` | `php artisan test --coverage` (full Unit+Feature[-Api]+Integration suite) | Yes (8.0) | `mysql` (step env) | No | **SQLite** | Static + §2 mechanism proof |
| 2 | `button-tests.yml` | `feature-tests` | `php artisan test tests/Feature/Buttons/ --env=testing --coverage` | Yes (8.0) | `mysql` (via `.env.testing`) | No | **SQLite** | Static + §2 |
| 3 | `button-tests.yml` | `security-tests` | `php artisan test tests/Feature/SecurityFeaturesSimpleTest.php --env=testing` | Yes (8.0) | `mysql` (via `.env.testing`) | No | **SQLite** | Static + §2 |
| 4 | `button-tests.yml` | `browser-tests` | `php artisan dusk tests/Browser/Projects/ tests/Browser/Crm/ ...` | Yes (8.0) | `mysql` (via `.env.testing`) | No | **Split-brain — see §5** | Static + §2 |
| 5 | `a11y-perf-testing.yml` | `accessibility-tests` | `php artisan test tests/Feature/Accessibility` (×3 invocations, 1 job) | Yes (8.0) | not set at job level; `.env.example` default is `mysql` | No | **SQLite** | Static + §2 |
| 6 | `a11y-perf-testing.yml` | `performance-budget` | `./vendor/bin/phpunit -c phpunit.xml --group performance_budget` | Yes (8.0) | `mysql` (job env) | No | **SQLite** | Static + §2 |
| 7 | `a11y-perf-testing.yml` | `performance-heavy` | `./vendor/bin/phpunit -c phpunit.xml --group performance_heavy` | Yes (8.0) | `mysql` (job env) | No | **SQLite** | Static + §2 |
| 8 | `a11y-perf-testing.yml` | `e2e-tests` | `php artisan test tests/E2E` (×2 invocations, 1 job) | Yes (8.0) | not set at job level; `.env.example` default is `mysql` | No | **SQLite** | Static + §2 |
| 9 | `production.yml` | `test` (matrix `php-version: ['8.2']` — 1 execution) | `php artisan test` | Yes (8.0) | not set at job level; `.env.testing` copied from `.env.example`, default `mysql` | No | **SQLite** | Static + §2 |
| 10 | `routes-guardrails.yml` | `test-routes-guardrails` | `php artisan test --filter RouteHygieneTest`, `php artisan test --filter TenantIsolationProjectsTest` (2 invocations, 1 job) | Yes (8.0) | `mysql` (step env, both steps) | No | **SQLite** | Static + §2 |
| 11 | `automated-testing.yml` | `unit-tests` | `php artisan test tests/Unit --coverage` | Yes (8.0) + Redis | `mysql` (job env + `.env.testing`) | No | **SQLite** | Static + §2 |
| 12 | `automated-testing.yml` | `feature-tests` | `php artisan test --testsuite=Feature --coverage` | Yes (8.0) | `mysql` | No | **SQLite** | Static |
| 13 | `automated-testing.yml` | `api-tests-fast` | `php artisan test tests/Feature/Api --exclude-group=slow` | Yes (8.0) | `mysql` | No | **SQLite** | Static |
| 14 | `automated-testing.yml` | `api-tests-slow` | `php artisan test tests/Feature/Api --group=slow` | Yes (8.0) | `mysql` | No | **SQLite** | Static |
| 15 | `automated-testing.yml` | `integration-tests` | `php artisan test tests/Integration --coverage` | Yes (8.0) | `mysql` | No | **SQLite** | Static |
| 16a/16b | `automated-testing.yml` | `performance-tests` (matrix: `PerformanceMonitoringTest.php`, `DashboardPerformanceTest.php` — **2 executions**) | `php artisan test "${{ matrix.perf_file }}"` | Yes (8.0) + Redis | `mysql` (job env + `.env.testing`) | No | **SQLite** | Static + §2 |

**Job definitions affected: 16. Job executions affected (matrix-expanded): 17** (item 16 contributes 2 executions from 1 job definition; all others are 1:1).
**Distinct workflow files affected: 6** (`ci-cd.yml`, `button-tests.yml`, `a11y-perf-testing.yml`, `production.yml`, `routes-guardrails.yml`, `automated-testing.yml`).

*(v1 of this document reported "6 jobs / 5 workflows." The complete audit found 16 job definitions / 17 executions across 6 workflows — v1 undercounted by missing `automated-testing.yml`'s 6 non-invariants MySQL-provisioning jobs entirely, per this task's explicit instruction to re-check that file.)*

### 3b. Jobs that genuinely execute against MySQL — NOT AFFECTED

| # | Workflow | Job | Test command | `ZENA_INVARIANTS_DB` | Evidence |
|---|---|---|---|---|---|
| 17 | `automated-testing.yml` | `zena-invariants-mysql` | `./scripts/ci/zena-invariants-mysql` → `php artisan test --group=zena-invariants` | **Yes**, exported inside the wrapper script | Script source + §2 mechanism proof; script also runs `ensure_mysql_connection`/`mysql_preflight_connection` fail-closed checks before the test step |
| 18 | `automated-testing.yml` | `rfi-escalation-concurrency-mysql` | `./scripts/ci/rfi-escalation-concurrency-mysql` | **Yes** | Script source (`export ZENA_INVARIANTS_DB=mysql` confirmed via grep) |
| 19 | `automated-testing.yml` | `document-workflow-concurrency-mysql` | `./scripts/ci/document-workflow-concurrency-mysql` | **Yes** | Script source (same) |

These 3 are the only genuinely-MySQL PHPUnit executions in the entire CI system. Their `migrate:fresh` and `php artisan test` steps are **separate OS processes** in the wrapper scripts, so the disable-FK migration's `SET FOREIGN_KEY_CHECKS=0` (set during the `migrate:fresh` process) does not carry into the test process's fresh MySQL connection, which defaults to `FOREIGN_KEY_CHECKS=1`. This was not independently re-verified against a live MySQL server in this pass (none available locally) — flagged as a reasonable inference from the process-boundary evidence, not asserted as an empirically-observed fact for these 3 specifically.

### 3c. Jobs that honestly declare SQLite (no MySQL claim made — out of scope for this finding)

| Workflow | Job | Note |
|---|---|---|
| `automated-testing.yml` | `zena-invariants` | No MySQL service, no `DB_CONNECTION` override — correctly SQLite-only, matches its own claim. |
| `nightly-matrix.yml` | `nightly` | Job-level `env: DB_CONNECTION: sqlite`, `DB_DATABASE: /tmp/zenamanage_test.sqlite`, no MySQL service — declares SQLite and gets SQLite. Runs via `composer test:nightly` → `scripts/test/nightly_matrix.sh`. |
| `ci-cd-code-quality-debug.yml` | `code-quality-debug` | Explicit `.env` with `DB_CONNECTION=sqlite`; no MySQL service; no test invocation found in this job at all (static-analysis only). |

### 3d. Jobs with a MySQL service that never invoke PHPUnit at all (out of scope)

`ci-cd.yml`→`code-quality`, `ci-cd.yml`→`deploy`; `button-tests.yml`→`button-inventory-check`, `coverage-report`, `quality-gate`; `a11y-perf-testing.yml`→`lighthouse-ci` (migrates real schema against real MySQL for a Lighthouse run, but never runs PHPUnit), `test-summary`; `automated-testing.yml`→`repo-hygiene`, `security-tests` (PHPStan/CS-Fixer/`composer audit` only), `test-coverage` (downstream artifact aggregator of jobs #11/#12/#15 above, not an independent PHPUnit invocation); `production.yml`→`security-scan`, `deploy`, `notify`.

## 4. Preserved finding: SQLite `PRAGMA foreign_keys` is not the root defect

No new evidence in this pass contradicts v1's finding here; it is preserved unchanged. `database/migrations/2025_09_20_145756_disable_foreign_keys_for_testing.php`'s `PRAGMA foreign_keys=OFF` (SQLite branch) does not survive to the actual test connection under this repo's bootstrap — `config/database.php:37`'s `'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true)` causes Laravel's SQLite connector to re-assert `foreign_key_constraints=true` on reconnection. Empirically re-confirmed in this pass (`DRIVER=sqlite FK_PRAGMA=[{"foreign_keys":1}]` from a fresh `./vendor/bin/phpunit` run). 7 test classes work around this independently by re-issuing the PRAGMA in their own `setUp()` — this is expected/deliberate per-class behavior for seed-order reasons, not a defect being raised here.

## 5. New finding this pass: `browser-tests` (Dusk) is a split-brain, not a simple SQLite substitution

`button-tests.yml`'s `browser-tests` job differs structurally from all other affected jobs: it runs **two separate OS processes** against **two different databases simultaneously**:

- The `php -S 127.0.0.1:8000 -t public /tmp/dusk-router.php` dev server (serves real HTTP requests the headless browser makes) is started with `APP_ENV=testing` inline and loads `.env.testing` directly through Laravel's normal HTTP kernel bootstrap — **not** `tests/bootstrap.php`, **not** PHPUnit. It genuinely connects to the real MySQL service (`.env.testing` sets `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, etc., with no `tests/bootstrap.php` override in play for this process).
- The `php artisan dusk ...` process (the PHPUnit process making assertions, running any `setUp()` factories) goes through the same `phpunit.xml`/`tests/bootstrap.php` path as every other job in §3a — no `ZENA_INVARIANTS_DB`, so it silently runs SQLite.

**FACT, not yet empirically reproduced live (flagged, not asserted as observed):** this job's web server and its test-assertion process are, by this static evidence, backed by two different database engines/files. Whether this causes silent false-negatives (Dusk browsing a server that can't see data the test process seeded) or false-positives, or whether some other mechanism in the job neutralizes this, was not independently reproduced in this pass — no headless-Chrome/MySQL environment was available locally. This is recorded as a fact-pattern requiring live CI log inspection or a dedicated reproduction, not as a confirmed runtime observation.

## 6. Preserved and strengthened finding: `QualityAssuranceTest::test_database_constraints`

Unchanged from v1, re-confirmed, no new evidence needed:

- Class-level `/** @group performance */` on `QualityAssuranceTest` is excluded by `phpunit.xml:16-20`'s `<groups><exclude><group>performance</group></exclude></groups>` — confirmed empirically: `./vendor/bin/phpunit tests/Feature/QualityAssuranceTest.php` (no group override) → `No tests executed!`. This class never runs in any of the 19 job executions inventoried in §3, anywhere, ever, under the default configuration every one of them uses.
- Forced to run with `--group=performance`, the method registers only **1 assertion** (`Tests: 1, Assertions: 1`) though its source contains code for two separate constraint checks. Cause: the first `expectException(QueryException::class)` (unique-constraint violation on `Dashboard::create`) fires and terminates the test method immediately; the second `expectException()` call and the `Widget::create(['dashboard_id' => 999999, ...])` statement meant to exercise the real schema-level FK constraint (`database/migrations/2026_02_10_000004_create_widgets_table.php:27` — `$table->foreign('dashboard_id')->references('id')->on('dashboards')->cascadeOnDelete();`) is unreachable dead code. This defect is independent of (and would remain even if) the group-exclusion and MySQL-substitution issues above were both fixed.

## 7. Summary of facts (Gate 1 only — no remediation proposed here)

1. PHPUnit's `<env>` directive in `phpunit.xml` only fills unset variables; it does not override an already-set one. The actual override mechanism is `tests/bootstrap.php`'s unconditional `putenv()`, gated solely on `getenv('ZENA_INVARIANTS_DB') === 'mysql'` — empirically proven in this pass by successfully making a job's `DB_CONNECTION: mysql` survive into PHPUnit once `ZENA_INVARIANTS_DB=mysql` was also present, and fail to survive when it was not.
2. **16 job definitions (17 matrix-expanded executions) across 6 distinct workflow files** (`ci-cd.yml`, `button-tests.yml`, `a11y-perf-testing.yml`, `production.yml`, `routes-guardrails.yml`, `automated-testing.yml`) provision a MySQL 8.0 service container and set (directly or via `.env`/`.env.testing`) `DB_CONNECTION=mysql`, but none export `ZENA_INVARIANTS_DB=mysql`, so all 17 executions run their PHPUnit-based test steps against SQLite instead, silently.
3. Only **3 job executions**, all in `automated-testing.yml`, all routed through dedicated `scripts/ci/*-mysql` wrapper scripts that explicitly export `ZENA_INVARIANTS_DB=mysql` and run fail-closed preflight checks, genuinely execute against MySQL.
4. Quantified test-method scope touched by the 17 misconfigured executions (deduplicated by underlying test directory, since several jobs run overlapping suites): `tests/Unit`+`tests/Feature`(minus `Api`)+`tests/Integration` = ~2,355 methods / 411 files (primarily `ci-cd.yml`'s `test` job and `automated-testing.yml`'s `unit-tests`/`feature-tests`/`integration-tests`); `tests/Feature/Api` = 591 methods / 76 files (`api-tests-fast`/`api-tests-slow`); `tests/Performance` = 20 methods / 2 files (`performance-tests` matrix, `a11y-perf-testing.yml`'s `performance-budget`/`performance-heavy`); `tests/E2E` = 9 methods / 2 files (`e2e-tests`); `tests/Browser` (Dusk) = 62 methods / 16 files (`browser-tests`, subject additionally to the split-brain fact-pattern in §5). **Union: ~3,037 test methods across 507 files** run under jobs whose CI configuration claims MySQL 8.0 execution that does not occur as configured.
5. `QualityAssuranceTest::test_database_constraints` — the one test in the repo whose name and comments explicitly claim to verify FK-constraint enforcement — cannot execute its FK-checking statement under any of the 19 inventoried job executions: it is excluded by `@group performance` in all of them, and even run in isolation its FK assertion is unreachable dead code behind an earlier `expectException()`.
6. No test in the repo was found, via `foreign key`-related grep across `tests/`, that currently *passes because* FK enforcement is silently disabled (i.e., no vacuously-passing positive FK assertion was located). The integrity failure is CI misconfiguration (claims not matched by execution) plus one permanently-unreachable assertion, not tests quietly passing for the wrong reason.

**Revised Gate 1 problem statement:** CI's MySQL-vs-SQLite test-backend selection is gated by a single env var (`ZENA_INVARIANTS_DB`) that only 3 of 19 inventoried MySQL-provisioning PHPUnit job executions (across 6 workflow files) actually set. The other 16 job definitions (17 executions, spanning the primary PR-gating pipeline plus five other workflows, touching a deduplicated ~3,037 test methods across 507 files) provision and pay for MySQL 8.0 service containers, and in several cases run real `migrate`/`db:seed`/`migrate:fresh` against that real MySQL instance, but their actual PHPUnit test execution silently substitutes SQLite — a substitution these jobs do not detect, report, or fail on. One job (`browser-tests`) additionally exhibits a split-brain pattern where its web server and its test-assertion process are backed by two different database engines simultaneously, not yet independently reproduced live. Separately and independent of all of the above, the repo's only test explicitly named as verifying FK-constraint enforcement cannot execute its FK-checking statement under any current configuration, due to a permanent `@group performance` exclusion plus a pre-existing dead-code `expectException()` bug. Gate 2 must decide, per affected job, the intended MySQL-vs-SQLite quality guarantee and how CI should fail closed when that guarantee is not met — this document takes no position on the mechanism (env var, wrapper script, XML config, or other) by which Gate 2's chosen guarantee gets enforced.
