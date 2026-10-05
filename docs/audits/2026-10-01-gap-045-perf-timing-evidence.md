# GAP-045 — Dashboard performance timing assertions: Gate-1 evidence

**Date:** 2026-10-01 (+07:00)

**Canonical base:** `2ca3def397b93a8aa4d632e5d477025b3f62f0bd`

**Branch:** `docs/GAP-045-perf-timing-gate1`

**Scope:** Read-only investigation and Gate-1 documentation, as the register
row requires: repeated, controlled LIVE measurements before any conclusion. No
threshold, test, application or workflow change on any mergeable branch.

## Preconditions from the register row (met)

- GAP-043 and GAP-044 (fixture/portability noise in the same suite) were
  released before this investigation (squashes `c345df2d`, `c3a12260`).
- The performance job only started executing these tests at all with GAP-041
  (PR #316, not yet merged); on `main` it still runs zero tests
  (scheduled run `36834855284`: `INFO  No tests found.`).

## Method

A disposable branch `proof/GAP-045-timing-distribution` (commit `3ecfd1d2`,
built on PR #316 head `33d6f76f`, never part of any PR, deleted after capture)
added one push-triggered workflow running **10 parallel jobs**, each a copy of
the real `performance-tests` job (MySQL 8.0 service, GAP-039 preflight), with
`PERF_DEBUG=1` and:

```text
php artisan test tests/Performance/DashboardPerformanceTest.php --group=performance \
  --fail-on-empty-test-suite \
  --filter="it_can_load_alerts_with_large_dataset_quickly|it_can_mark_alerts_as_read_quickly|it_can_load_dashboard_with_large_dataset_quickly"
```

Each job also printed `nproc` and the CPU model. Run `36877402846`
(2026-10-01), jobs `110420244228` … `110420245013`.

## Results

| Job | Runner CPU (4 vCPU each) | Alerts samples (ms) | Alerts median | ≤ 450? | Mark-100-read (ms) | ≤ 1000? |
|---|---|---|---|---|---|---|
| 244228 | AMD EPYC 9V45 | 262.49, 265.01, 297.63 | 265.01 | yes | 616.38 | yes |
| 244645 | AMD EPYC 9V45 | 273.63, 278.77, 301.26 | 278.77 | yes | 576.49 | yes |
| 244890 | Intel Xeon 6973P-C | 295.53, 308.30, 379.85 | 308.30 | yes | 694.58 | yes |
| 244960 | AMD EPYC 9V74 | 327.73, 329.01, 385.80 | 329.01 | yes | 878.38 | yes |
| 244493 | AMD EPYC 9V74 | 421.06, 422.24, 443.35 | 422.24 | yes | 964.77 | yes |
| 244662 | Intel Xeon Platinum 8370C | 458.34, 459.07, 463.93 | 459.07 | **no** | 986.04 | yes |
| 244834 | AMD EPYC 7763 | 510.09, 512.50, 517.46 | 512.50 | **no** | 995.85 | yes |
| 244841 | AMD EPYC 7763 | 512.33, 514.78, 518.36 | 514.78 | **no** | 1028.52 | **no** |
| 244783 | AMD EPYC 7763 | 503.84, 520.43, 520.51 | 520.43 | **no** | 996.67 | yes |
| 245013 | AMD EPYC 7763 | 515.05, 518.82, 520.00 | 518.82 | **no** | 996.05 | yes |

Query counts per alerts request: 4–5 in every sample (budget 20).

Earlier recorded failures fit the same band: 502.08ms (GAP-045 discovery,
run `32471481216`), 514.10/520.78ms, 529.21ms (PR #316 run `36869260580`);
mark-read 1023.63/1027.92/1174.17ms.

## Findings

1. **The result is decided by which runner hardware the job lands on, not by
   the code.** Within one job the three samples agree within a few percent;
   across jobs the median ranges **265–521ms (×2.0)**. Every AMD EPYC 7763
   runner (4/10) failed the 450ms alerts assertion and its mark-read time sits
   at ~996–1029ms against 1000ms; the faster CPU classes pass with wide margin.
   5/10 jobs failed at least one assertion on identical code.
2. **No regression signal.** Query counts are flat (4–5) and far under the
   budget; there is no evidence of an N+1 or of setup contamination (GAP-043/044
   fixed). The runtime is CPU-bound work.
3. **Where the time goes (static reading).** `GET /api/v1/dashboard/alerts`
   → `DashboardService::getUserAlerts()` (`app/Services/DashboardService.php:184-208`)
   returns **every** alert of the user, unpaginated and unbounded
   (`->latest()->get()->toArray()`); the test seeds 1000 alerts, so each call
   hydrates and serialises ~1000 rows. The mark-read assertion times **100
   sequential full HTTP requests** (`DashboardPerformanceTest.php:393-410`), a
   ~10ms-per-request budget for the whole middleware stack.
4. **Consumers.** No web view or JS in `resources`/`public/js` calls
   `/api/v1/dashboard/alerts`; the server-side callers are
   `Api\DashboardController::getUserAlerts()` and
   `Api\DashboardSSEController` (`:182`). Any limit/pagination would change an
   API contract for non-UI clients.

## Conclusion

The two assertions are **wall-clock thresholds on heterogeneous shared
runners** and are inherently non-deterministic: roughly half of runs fail on
unchanged code. This is not, on current evidence, an application performance
regression. The one genuine design smell is the unbounded alerts list.

## Options for Gate 2 (decision needed)

- **A. Deterministic gate, timings reported only (technical):** keep
  query-count / payload-bound assertions as the CI gate; record wall-clock
  timings in the job summary instead of failing on them.
- **B. Hardware-normalised timing:** measure a fixed calibration workload in the
  same job and assert a ratio, keeping the spirit of 450ms/1000ms.
- **C. Bound the alerts endpoint (product/API):** return the newest N alerts
  with pagination — faster on every runner, but changes the API contract;
  needs the Owner to choose N.
- **D. Change the thresholds:** the register row records an Owner direction not
  to change the 450ms threshold before Gate-1 evidence — so any threshold change
  is an Owner decision.

## Out of scope for this Gate 1

Any change; GAP-041 release (blocked on this gap).
