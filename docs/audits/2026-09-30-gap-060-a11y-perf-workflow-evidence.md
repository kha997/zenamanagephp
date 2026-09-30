# GAP-060 — Nightly "Accessibility & Performance Testing" workflow has never passed: Gate-1 evidence

**Date:** 2026-09-30 (+07:00)

**Canonical base:** `8233b3ef7b56857e46c62185c8e005e377cd8372`

**Branch:** `docs/GAP-060-a11y-perf-ci-red`

**Scope:** Read-only investigation and Gate-1 documentation. No workflow, test,
code or deployment change.

## Summary

`.github/workflows/a11y-perf-testing.yml` (`schedule: 0 3 * * *` +
`workflow_dispatch`) failed on **all 200 of its most recent runs** (oldest in
that window 2026-01-01; `gh run list --limit 200`: `failure=200`, no success).
A permanently red nightly job trains everyone to ignore it, and it hides that
several of its jobs would test nothing even if they ran.

Latest run analysed: `36693528967` (2026-09-30, head `3e6d55f0`). Five jobs
red, `Test Summary` green (it only reports).

## Job-by-job root causes

| Job | Failure | Root cause |
|---|---|---|
| Performance Budget Tests (`:84-169`) | exit 127 | Step `./.github/scripts/ci_prepare_testing_env.sh` (`:150`): the file **does not exist and never existed** in git history (`git log --all -- <path>`: empty). |
| Performance Heavy Tests (`:170-255`) | exit 127 | Same missing script (`:236`). |
| Lighthouse CI (`:256-335`) | `SQLSTATE[HY000] [1045] Access denied for user 'root' … Database: laravel` during `php artisan migrate --force` (`:312`) | Job copies `.env.example` and never sets `DB_*` for its MySQL service, so migrations use the defaults. |
| E2E Tests (`:337-428`) | `CriticalUserFlowsE2ETest` expected redirect `http://localhost/app/dashboard`, got `http://localhost/app/today` (`tests/E2E/CriticalUserFlowsE2ETest.php:58`) | Stale test: login now redirects to `/app/today` (`app/Http/Controllers/AuthController.php:46`, Today workspace). |
| Accessibility Tests (`:14-83`) | `Unknown option "--junit"` (exit 2) after the tests themselves passed (10 tests / 34 assertions) | Step `php artisan test … --junit --log-junit=…` (`:75`, same at `:419`): `--junit` is not a PHPUnit 11 option. |

## Deeper problems behind the red

1. **The two performance jobs would select zero tests.** They run
   `phpunit --group performance_budget` / `--group performance_heavy`
   (`:162`, `:248`), but no test in the repository carries either group
   (`git grep` for both names under `tests/`: no match). Fixing the missing
   script alone would turn them into false-green jobs — the exact pattern GAP-041
   tracks. The real performance tests (`tests/Performance/*`, group
   `performance`) already run in `automated-testing.yml:1193-1195`.
2. **The accessibility job duplicates main CI.** `tests/Feature/Accessibility`
   is inside the `Feature` testsuite (`phpunit.xml:7-10`) that the per-PR CI
   already runs; only this workflow's broken flag fails.
3. **Lighthouse would audit the login page.** It targets
   `http://localhost:8000/app/dashboard` (`:329`) without a session, and there is
   no Lighthouse config/budget file in the repository (`lighthouserc*`: none),
   so even after the DB fix it would measure the redirect target with default
   settings and no assertions.
4. **E2E only runs here.** `tests/E2E` is in no `phpunit.xml` testsuite, so this
   nightly workflow is the only place `CriticalUserFlowsE2ETest`,
   `DashboardE2ETest` and `TransactionIsolationColdStartTest` run.

## Out of scope for this Gate 1

Any workflow or test change; deciding which checks to keep (Gate 2); the
product meaning of performance budgets.
