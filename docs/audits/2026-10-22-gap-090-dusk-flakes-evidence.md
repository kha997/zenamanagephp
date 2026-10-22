# GAP-090 — Evidence: browser-tests (Dusk) fail on the first attempt about half the time

Base: `90542b0d6ee1cc4aa7019746942425109d6e2563` (main after GAP-089).

## How the job retries today

`.github/workflows/button-tests.yml`, job `browser-tests`:
- **Server:** it serves the app with PHP's built-in server (`php -S 127.0.0.1:8000`) inside a supervisor loop that
  respawns it 0.2 s after it exits. The workflow comment records that `php -S` segfaults under sustained Dusk load.
- **Tests:** "Run Browser Tests" runs `php artisan dusk tests/Browser/Projects/ tests/Browser/Crm/ --stop-on-failure`
  up to two times. The job fails only if both attempts fail.

## Measurement: the 25 most recent completed runs (#1241–#1265)

Fourteen job logs were read. The other eleven were classified by duration: a single attempt finishes in 13–20 min,
a retried run takes 29–39 min, and no run fell in between.

| | Runs |
|---|---|
| Passed on attempt 1 | 13 |
| Needed attempt 2 (then passed) | **12 (48%)** |
| Failed both attempts | 0 |

Median job duration: 17.4 min without a retry and **35.5 min** with one.

Attempt 1 runs all 26 tests even with `--stop-on-failure`, so every retry costs about 15–18 extra minutes.

## What fails in attempt 1 (25 failures across the 12 retried runs)

| Test | Runs | Exception |
|---|---|---|
| `ProjectCreateTest::test_project_create_cancel_flow` | 7 | `ElementClickInterceptedException` ×5, `ERR_CONNECTION_REFUSED` ×2 |
| `ProjectCreateTest::test_project_create_submit_flow` | 6 | `TimeoutException` waiting for location `/app/projects` ×5, `ERR_CONNECTION_REFUSED` ×1 |
| `ProjectCreateTest::test_project_create_page_renders` | 5 | `ERR_CONNECTION_REFUSED` ×5 |
| `ProjectCreateCanaryTest` | 3 | `ERR_CONNECTION_REFUSED` ×3 |
| `ProjectCreateTest::test_project_create_validation_surface` | 3 | `TimeoutException` waiting for `@project-submit` ×2, `ERR_CONNECTION_REFUSED` ×1 |
| `WorkTemplateApplyBrowserTest` (loads templates) | 1 | `ERR_CONNECTION_REFUSED` ×1 |

`Crm\PipelineDragDropTest` (19 tests) never failed.

## Three causes

1. **`ERR_CONNECTION_REFUSED`** (13 failures, 8 runs). The browser reaches port 8000 while `php -S` is down,
   between a crash and the respawn.
   - Indirect evidence: in all 12 retried runs, the `php` process killed at job cleanup has a high PID. In clean
     runs it is the original server process.
   - The server log (`/tmp/laravel-serve.log`) is uploaded as an artifact, not printed, so the crashes were not
     read directly.
2. **`ElementClickInterceptedException`** on `@project-cancel` (5 failures). The click lands at (1829, 22) and the
   fixed topbar user block (`.operator-topbar-user`) receives it. Dusk scrolls the button to the top edge of the
   viewport, under the topbar.
3. **`TimeoutException`** (7 failures). The location or element did not appear within 10 s. These are likely the
   same server stalls or restarts (slow first request after a respawn). This is to be confirmed in Gate 2.

## Notes for Gate 2

- **Server:** replace `php -S` with a server that does not crash under this load (for example `php-fpm` behind a
  small web server). Or keep it and make the tests wait for the server before each page visit.
- **Cancel test:** scroll `@project-cancel` into the middle of the viewport before clicking, or click it in a way
  that ignores the overlay.
- **Retry:** decide whether to keep the second attempt as a safety net.
- **Proof:** it must cover several consecutive runs, since a single green run proves little against a 48% failure rate.
