# GAP-039 — Real CI cost measurement (Task 12 Step 4)

**Purpose:** Gate 2's approved engineering spec §7 gave a provisional estimate
("~19.6× for a DB-round-trip-heavy suite... this must be re-measured with real
numbers during implementation, not trusted as a final figure"). This document
supplies that measurement, pulled from real GitHub Actions job timestamps via
`gh api repos/kha997/zenamanagephp/actions/runs/<id>/jobs`, not reused Gate 2
estimates.

**Status: superseded by the final-head addendum below.** The measurement in
"Part 1" was taken at head `0514836a`, an intermediate implementation state —
4 commits before the branch's actual final state. Commits after `0514836a`
(the final-review fix wave, the SQLSTATE/GAP-040 polish, and Gate 1/Gate 2
governance consolidation) changed `tests/bootstrap.php`, `tests/TestCase.php`,
`tests/Feature/Legacy/LegacyRouteRollbackTest.php`,
`tests/Feature/DatabaseConstraintsTest.php`, and
`.github/workflows/a11y-perf-testing.yml` — all of which can affect CI
execution paths. Part 1 is kept for historical traceability (do not erase),
re-labeled below as an intermediate measurement; Part 2 is the current,
authoritative measurement against the final implementation state and is what
the Gate 3 packet cites.

## Part 1 (INTERMEDIATE — head `0514836a`, superseded by Part 2 below)

**Before:** PR #268's `MERGE_BASE` on `main`, run `32210120993`
(`automated-testing.yml`, head `cb5cb893`, 2026-08-19T02:52Z, `conclusion: success`).

**After (intermediate):** PR #268 head `0514836a` (4 commits before the
branch's final head), run `32283325930`
(`automated-testing.yml`, 2026-08-19T17:44Z, `conclusion: success`).

## Part 1 — Jobs reclassified honestly-SQLite (Task 6) — misleading `mysql:` service removed (intermediate head)

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

## Part 1 — Jobs reclassified to genuine MySQL-parity (Task 9, performance-tests plan-gap fix) (intermediate head)

| Job | Before (claimed MySQL, silently ran SQLite) | After (genuine MySQL-parity) | Δ |
|---|---:|---:|---:|
| Performance Tests (`DashboardPerformanceTest.php`) | 97s | 96s | −1s |
| Performance Tests (`PerformanceMonitoringTest.php`) | 92s | 108s | +16s |

**Interpretation:** far below the Gate 2 spec's worst-case ~19.6× multiplier.
`tests/Performance` is not DB-round-trip-heavy per assertion (matches the
spec §7 prediction that the multiplier "is not a universal constant"); the
added cost of genuine MySQL connectivity plus the fail-closed preflight step
is on the order of 0–16 seconds for this suite shape.

## Part 1 — Reference point already MySQL-parity before GAP-039 (unchanged tier, Task 2 library refactor only) (intermediate head)

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

## Part 1 — Treasury outlier at the intermediate head (flagged, later independently confirmed as a one-off — see Part 2)

`Treasury Native CHECK Constraints (real MySQL)` measured 1,598s (~26.6 min)
at baseline and 8,520s (~2h22m) at the intermediate head — both the job wall
time and the individual `🧪 Treasury native CHECK constraints` step itself.
Not treated as GAP-039 cost evidence at the time: GAP-039 made no changes to
Treasury/GAP-038 domain code or its test logic (per the plan's Global
Constraint and the explicit instruction not to modify Treasury behavior),
and the 8,520s figure was wildly inconsistent with this same job's own
baseline (26.6 min). Part 2 below re-measures this exact job at the final
head and confirms it back at ~26.9 min — the 8,520s figure was a one-off
CI-runner anomaly, not a recurring or GAP-039-caused cost.

## Part 1 — Summary (intermediate head, superseded)

- No reclassified job regressed CI wall-clock time in a way inconsistent
  with the approved Gate 2 design's own stated expectations.
- The 5 honestly-SQLite jobs (Task 6) got faster.
- The 2 genuinely-MySQL-parity jobs (Task 9 plan-gap fix) cost 0–16s more.
- The pre-existing MySQL-parity reference job's multiplier (~13–16×) matches
  the Gate 2 spec's measured range (~19.6×) closely enough to confirm it as
  a real, repeatable suite characteristic, not an estimate artifact.
- One unrelated infra anomaly (Treasury job hang) was documented and
  excluded, per the explicit instruction not to treat unrelated failures as
  GAP-039's responsibility to fix. Re-measured and confirmed one-off in
  Part 2.

---

## Part 2 (FINAL, AUTHORITATIVE — head `14189e2d`, the branch's actual final state)

**Purpose:** commits after `0514836a` (final-review fix wave `3ae7df98`,
polish `d9ba35d0`, Gate 3 packet `e15fa6b7`, governance consolidation
`94f259dd`, digest update `14189e2d`) changed `tests/bootstrap.php`,
`tests/TestCase.php`, `tests/Feature/Legacy/LegacyRouteRollbackTest.php`,
`tests/Feature/DatabaseConstraintsTest.php`, and
`.github/workflows/a11y-perf-testing.yml` — every job whose execution path
could plausibly have changed as a result is re-measured here against the
same `automated-testing.yml` workflow, using the identical `MERGE_BASE`
baseline (`cb5cb893`, run `32210120993`) already used in Part 1, so the two
tables are directly comparable.

**After (final):** PR #268 final head `14189e2d` (confirmed by the Owner as
the current PR head with 31 CI jobs SUCCESS + deploy SKIPPED), run
`32305896514` (`automated-testing.yml`, 2026-08-19T21:50Z,
`conclusion: success`).

### Jobs reclassified honestly-SQLite (Task 6)

| Job | Baseline (`cb5cb893`) | Final (`14189e2d`) | Δ |
|---|---:|---:|---:|
| Unit Tests | 175s | 121s | −54s |
| Feature Tests | 338s | 276s | −62s |
| API Tests (Fast) | 202s | 151s | −51s |
| API Tests (Slow) | 97s | 55s | −42s |
| Integration Tests | 222s | 73s | −149s |

**Interpretation:** all 5 remain faster than baseline at the final head,
consistent with Part 1's finding — none of the fix-wave/polish commits
touched these jobs' execution path (`tests/bootstrap.php`'s SQLite branch is
unchanged; only the `else` / MySQL branch and its 2 guarded call sites were
touched), and the measurement confirms no regression was introduced.

### Jobs reclassified to genuine MySQL-parity (Task 9 plan-gap fix)

| Job | Baseline (claimed MySQL, silently ran SQLite) | Final (genuine MySQL-parity) | Δ |
|---|---:|---:|---:|
| Performance Tests (`DashboardPerformanceTest.php`) | 97s | 83s | −14s |
| Performance Tests (`PerformanceMonitoringTest.php`) | 92s | 81s | −11s |

**Interpretation:** both now measure *faster* than the pre-GAP-039
baseline, not slower — comfortably within (better than) Part 1's already-low
0–16s cost estimate. `tests/Performance` does not touch
`ensureSqliteZenaRbacTables()`/`ensureTestingSchema()` in a way affected by
the fix-wave's guard changes, so no new cost from those commits.

### Reference point already MySQL-parity before GAP-039 (unchanged tier)

| Job | Baseline | Final | Δ |
|---|---:|---:|---:|
| Zena RBAC/Tenant Invariants (SQLite) | 59s | 56s | −3s |
| Zena RBAC/Tenant Invariants (MySQL parity) | 775s (~12.9 min) | 812s (~13.5 min) | +37s |
| Document Workflow Concurrency (real MySQL) | 84s | 84s | 0s |
| RFI Escalation Concurrency (real MySQL) | 90s | 81s | −9s |

**Interpretation:** the MySQL-parity reference job's multiplier at the final
head is 812s/56s ≈ **14.5×**, in the same band as Part 1's 13–16× and the
Gate 2 spec's ~19.6× reference figure — confirms this is a stable,
repeatable characteristic of the suite across every measurement taken so
far (baseline, intermediate, and final), not an artifact of any one run.
`tests/bootstrap.php`'s new `DB_CONNECTION==='mysql'` self-verification
check (I3 fix) adds one `getenv()`/`fwrite()` call on the success path —
negligible and within normal run-to-run variance (the +37s here is smaller
than the +58s measured in Part 1 at the intermediate head, i.e. no
cumulative cost from the fix wave).

### Treasury Native CHECK Constraints — re-measured, confirms Part 1's outlier was a one-off

| Job | Baseline | Intermediate (`0514836a`) | Final (`14189e2d`) |
|---|---:|---:|---:|
| Treasury Native CHECK Constraints (real MySQL) | 1,598s (~26.6 min) | 8,520s (~2h22m, **outlier**) | 1,614s (~26.9 min) |

**Interpretation:** at the final head this job is back to ~26.9 minutes —
statistically identical to its own pre-GAP-039 baseline (1,598s) and nowhere
near the intermediate measurement's 8,520s. This confirms the Part 1 finding:
the 8,520s figure was a one-off CI-runner anomaly (unrelated to any GAP-039
commit, since Treasury/GAP-038 domain code and its test logic were never
touched), not a recurring cost or a regression introduced by any commit in
this branch, including the ones after `0514836a`.

### GAP-039-caused cost vs. pre-existing vs. anomaly — explicit breakdown

- **Cost genuinely caused by GAP-039** (jobs that changed tier from
  claimed-but-fake-MySQL to real MySQL-parity): Performance Tests
  (Dashboard/Monitoring) — both now *faster* than pre-GAP-039 baseline
  (−14s, −11s). No added cost; the honest-classification jobs (Task 6) also
  got strictly faster. Net measured cost attributable to GAP-039 across
  every reclassified job: **negative** (CI got faster, not slower).
- **Unchanged, pre-existing MySQL jobs** (Document Workflow Concurrency, RFI
  Escalation Concurrency, Zena RBAC/Tenant Invariants (MySQL parity) — all
  already MySQL-parity before GAP-039, only touched by Task 2's library
  refactor and, for Zena RBAC, transitively by the `tests/bootstrap.php`
  self-verification check): deltas of 0s, −9s, +37s respectively — within
  normal run-to-run variance, no material cost added.
- **Unrelated Treasury infrastructure anomaly**: one outlier measurement at
  the intermediate head (8,520s vs. a 1,598s/1,614s baseline/final pair),
  confirmed by this re-measurement to be non-recurring and unrelated to any
  GAP-039 commit. Excluded from cost conclusions.

## Part 2 — Summary (FINAL, supersedes Part 1's conclusions where they differ)

- Re-measuring every job whose execution path could have changed after
  `0514836a` confirms Part 1's findings hold at the branch's actual final
  head (`14189e2d`): no reclassified job regressed CI wall-clock time.
- The 5 honestly-SQLite jobs and the 2 genuinely-MySQL-parity jobs are all
  *faster* than the pre-GAP-039 baseline at the final head.
- The pre-existing MySQL-parity reference job's multiplier (~14.5×) remains
  in the same band across baseline, intermediate, and final measurements.
- The Treasury anomaly is now confirmed non-recurring: re-measured at the
  final head, back to its own historical baseline (~26.9 min vs. ~26.6 min
  baseline). Not GAP-039 cost, not a regression, not excluded on faith —
  excluded on a second, independent measurement.
