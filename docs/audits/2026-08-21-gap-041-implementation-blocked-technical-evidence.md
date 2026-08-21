# GAP-041 — Implementation status: mechanism complete, `blocked_technical` before Gate 3

**Date:** 2026-08-21
**Implementation branch:** `feature/GAP-041-ci-test-selection-truthfulness`
**Implementation SHA at time of this record:** `bde3589c`
**Baseline:** `origin/main` `0b77747551a3e0da08e3e41c73a0a88f529b19f3` (no drift from the Gate-2-reviewed canonical baseline)
**Authority:** `docs/owner-decisions/GAP-041/02-design.md` (Owner APPROVE, Option D, hard-LIVE Gate-3 acceptance contract) and Owner's scope ruling of 2026-08-21 after LIVE execution (Option (a): do not expand GAP-041 to repair newly exposed failures; register separately; hold GAP-041 at `blocked_technical`).

## Status

**`blocked_technical`** — implementation of the approved Option D mechanism is complete and unchanged from Gate-2 approval; Gate 3 cannot be presented while the surviving `performance-tests` lane is red due to separately-scoped defects it exposed.

## What GAP-041 has proven LIVE

- Real-MySQL preflight (`zena_mysql_ensure_connection`/`zena_mysql_preflight_connection`) reached and passed on both `performance-tests` matrix legs — GitHub Actions run [`32471481216`](https://github.com/kha997/zenamanagephp/actions/runs/32471481216).
- Monitoring leg (`PerformanceMonitoringTest.php`) selected and executed 10 tests (job `96739005481`): `Tests: 7 failed, 3 passed (9 assertions)`.
- Dashboard leg (`DashboardPerformanceTest.php`) selected and executed 19 tests (job `96739005491`): `Tests: 2 failed, 17 passed (153 assertions)`.
- The original GAP-041 defect — zero-test silent "success" — is gone on both legs: the jobs now fail (exit code 2) because real tests ran and some failed, not because nothing ran.
- Revised `a11y-perf-testing.yml` has no dangling phantom-job dependency: `performance-budget`/`performance-heavy` job definitions are fully removed, and `test-summary.needs` no longer references either.
- LIVE dispatch run [`32471502944`](https://github.com/kha997/zenamanagephp/actions/runs/32471502944) reached and completed the `test-summary` job successfully (3s, no `needs:`-resolution error), confirming the retirement did not break the workflow's dependency graph.

## What GAP-041 has NOT proven yet

- A positive, all-tests-green `performance-tests` lane — currently blocked by GAP-043/GAP-044/GAP-045 (registered separately, PR #278; not fixed here per Owner scope ruling).
- The mandatory LIVE negative zero-selection fail-closed proof (Plan Task 6) — not yet executed. Deferred until the branch can produce a genuinely green positive run first, since a negative proof on top of an already-red branch would not cleanly isolate the fail-closed mechanism's LIVE behavior.
- Gate-3 readiness — explicitly not claimed. No Gate-3 packet has been or will be prepared in this session.

## Failure classification table (exact LIVE evidence, run `32471481216`)

| Class | Job / leg | Symptom | Registered as | Status |
|---|---|---|---|---|
| SQLite-only schema introspection under real MySQL | Monitoring (`PerformanceMonitoringTest.php`) | `SQLSTATE[42000]: Syntax error ... near 'PRAGMA table_info(projects)'` — `tableInsertDefaults()` uses SQLite-only DDL introspection | GAP-043 | OPEN (LIVE-confirmed) |
| Shared fixture / transaction SAVEPOINT failure | Both legs | `SQLSTATE[42000]: SAVEPOINT trans2 does not exist`, via `FixtureFactory`/`TenantUserFactoryTrait` | GAP-044 | OPEN (LIVE symptom confirmed, root cause unverified) |
| Performance assertion exceeded CI budget | Dashboard (`DashboardPerformanceTest.php`) | "Alerts median load time should be less than 450ms", measured `502.08210945129395ms` | GAP-045 | UNVERIFIED (LIVE assertion observed, reproducibility not yet established) |

## Confirmation of scope discipline

- No file under `tests/`, `app/`, `src/`, or any application/runtime path was modified in this GAP-041 session.
- No performance threshold was changed.
- No failing test was suppressed, excluded, or marked `continue-on-error`.
- The implemented Option D mechanism (`--group=performance --fail-on-empty-test-suite` on `performance-tests`; phantom-tier retirement + `test-summary` cleanup in `a11y-perf-testing.yml`) is unchanged from the commits reviewed above — it was not reverted or weakened to chase a green job.
- GAP-043/GAP-044/GAP-045 were registered only (`OPERATIONAL_GAP_REGISTER.md`, PR #278) — no Gate 1 investigation was started for any of them in this session.
- GAP-042 was not investigated or absorbed.

## Recommended resolution order (for future sessions, per Owner direction)

1. GAP-043 (narrowest, best-understood portability defect)
2. GAP-044 (shared transaction/fixture failure — requires root-cause investigation and reconciliation against GAP-040)
3. GAP-045 (rerun only after GAP-043/GAP-044 land on main, so the latency measurement isn't contaminated by unrelated failures)

After GAP-043/GAP-044 (and GAP-045's disposition) land on canonical `main`: rehydrate GAP-041 from current main, reconcile/rebase this branch, rerun both matrix legs LIVE, require a genuinely green positive run, perform the mandatory LIVE zero-selection negative proof (create disposable mutation → LIVE failing run → revert → verify clean), then prepare GAP-041 Gate 3.
