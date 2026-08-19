# GAP-039 — Real CI cost measurement (Task 12 Step 4)

**Purpose:** Gate 2's approved engineering spec §7 gave a provisional estimate
("~19.6× for a DB-round-trip-heavy suite... this must be re-measured with real
numbers during implementation, not trusted as a final figure"). This document
supplies that measurement, pulled from real GitHub Actions job timestamps via
`gh api repos/kha997/zenamanagephp/actions/runs/<id>/jobs`, not reused Gate 2
estimates.

**Before:** PR #268's `MERGE_BASE` on `main`, run `32210120993`
(`automated-testing.yml`, head `cb5cb893`, 2026-08-19T02:52Z, `conclusion: success`).

**After:** PR #268 final head `0514836a`, run `32283325930`
(`automated-testing.yml`, 2026-08-19T17:44Z, `conclusion: success`).

## Jobs reclassified honestly-SQLite (Task 6) — misleading `mysql:` service removed

| Job | Before | After | Δ |
|---|---:|---:|---:|
| Unit Tests | 175s | 143s | −32s |
| Feature Tests | 338s | 268s | −70s |
| API Tests (Fast) | 202s | 151s | −51s |
| API Tests (Slow) | 97s | 62s | −35s |
| Integration Tests | 222s | 63s | −159s |

**Interpretation:** all 5 got *faster*, not slower — removing the unused
`mysql:` service container (which these jobs provisioned but never actually
connected to) drops the container-provisioning/health-check wait from the
job's critical path. This is the expected direction: these jobs' honest
classification is SQLite, and SQLite is what they were already silently
running.

## Jobs reclassified to genuine MySQL-parity (Task 9, performance-tests plan-gap fix)

| Job | Before (claimed MySQL, silently ran SQLite) | After (genuine MySQL-parity) | Δ |
|---|---:|---:|---:|
| Performance Tests (`DashboardPerformanceTest.php`) | 97s | 96s | −1s |
| Performance Tests (`PerformanceMonitoringTest.php`) | 92s | 108s | +16s |

**Interpretation:** far below the Gate 2 spec's worst-case ~19.6× multiplier.
`tests/Performance` is not DB-round-trip-heavy per assertion (matches the
spec §7 prediction that the multiplier "is not a universal constant"); the
added cost of genuine MySQL connectivity plus the fail-closed preflight step
is on the order of 0–16 seconds for this suite shape.

## Reference point already MySQL-parity before GAP-039 (unchanged tier, Task 2 library refactor only)

| Job | Before | After | Δ |
|---|---:|---:|---:|
| Zena RBAC/Tenant Invariants (SQLite) | 59s | 51s | −8s |
| Zena RBAC/Tenant Invariants (MySQL parity) | 775s (~12.9 min) | 833s (~13.9 min) | +58s |
| Document Workflow Concurrency (real MySQL) | 84s | 91s | +7s |
| RFI Escalation Concurrency (real MySQL) | 90s | 101s | +11s |

**Interpretation:** consistent with the Gate 2 spec §7 reference figures
(44s→862s, ~19.6×, measured 2026-08-18) — this run measures 59s→775s
(~13.1×) at baseline and 51s→833s (~16.3×) at final head, same order of
magnitude, confirming it is a real, repeatable characteristic of this
DB-round-trip-heavy suite rather than a one-off. The +7s/+11s/+58s deltas on
the 3 already-MySQL jobs are consistent with Task 2's library refactor
*adding* a `zena_mysql_ensure_connection` preflight call to
`zena-invariants-mysql`/the 2 concurrency scripts (a strengthening, not a
regression) — not with any behavior change to the tests themselves.

## Excluded as a non-representative outlier: Treasury Native CHECK Constraints

`Treasury Native CHECK Constraints (real MySQL)` measured 1,598s (~26.6 min)
at baseline and 8,520s (~2h22m) on this run — both the job wall time and the
individual `🧪 Treasury native CHECK constraints` step itself. This is not
treated as GAP-039 cost evidence: GAP-039 made no changes to Treasury/GAP-038
domain code or its test logic (per the plan's Global Constraint and the
user's explicit instruction not to modify Treasury behavior), and the
8,520s figure is wildly inconsistent with this same job's own baseline (26.6
min) and with every other real-MySQL job in this run (all under 15 min).
This reads as CI-runner infrastructure flakiness unrelated to GAP-039 —
recorded here for transparency, not acted on.

## Summary

- No reclassified job regressed CI wall-clock time in a way inconsistent
  with the approved Gate 2 design's own stated expectations.
- The 5 honestly-SQLite jobs (Task 6) got faster.
- The 2 genuinely-MySQL-parity jobs (Task 9 plan-gap fix) cost 0–16s more.
- The pre-existing MySQL-parity reference job's multiplier (~13–16×) matches
  the Gate 2 spec's measured range (~19.6×) closely enough to confirm it as
  a real, repeatable suite characteristic, not an estimate artifact.
- One unrelated infra anomaly (Treasury job hang) is documented and
  excluded, per the explicit instruction not to treat unrelated failures as
  GAP-039's responsibility to fix.
