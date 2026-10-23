# GAP-093 — Evidence: the Nightly Test Matrix has never passed

Base: `b62d5f5beb3d889978614a8419682dcfdcb414cc` (main after GAP-092).

## What the workflow does

`.github/workflows/nightly-matrix.yml`, job "Nightly Matrix (slow/load/stress/redis)", runs on a daily schedule and on
`workflow_dispatch`:
1. installs Composer dependencies;
2. "Prepare application env": `cp .env.testing .env`, `key:generate`, then `migrate:fresh` on SQLite;
3. starts the app with `php artisan serve` on `127.0.0.1:8000`, and starts Redis as a service;
4. waits for `/api/csrf-token`, then starts a websocket server if a `websocket:serve` command exists;
5. runs `composer test:nightly` (`scripts/test/nightly_matrix.sh`). That script runs `optimize:clear`, `ssot:lint`, the
   fast PHPUnit suite, then the `slow`, `load`, `stress` and `redis` groups, and writes a report.

## Measurement: all 91 runs (#1, 2026-02-20 to #91, 2026-10-22)

- **91 of 91 failed.** None has ever passed.
- Each run fails within about a minute. The latest run (#91, run 39747300232) fails in "Prepare application env" with
  `cp: cannot stat '.env.testing': No such file or directory`. The repository has `.env.example` but no `.env.testing`
  (`.gitignore` excludes `.env.*`).
- Because of this, no run has ever reached `artisan serve`, the readiness wait or any test. There is no evidence about
  the server or the nightly test groups either way.

## Consequences

- The slow, load, stress and redis test groups have never run in CI.
- The red runs carry no signal, so a real regression in those groups would go unnoticed.

## Notes for Gate 2

- Build the env in the workflow, as other jobs do (for example from `.env.example` with the values the job already sets),
  instead of copying a file that does not exist.
- Serve the app with php-fpm behind nginx instead of `artisan serve` (GAP-090, GAP-092).
- `websocket:serve` cannot start (Ratchet is not installed, GAP-089), so that step only logs "not found" or fails. Decide
  whether to keep it.
- Run the workflow by hand (`workflow_dispatch` on the PR branch, or a temporary `pull_request` trigger) to find out
  which later steps also fail. Those may be test failures that need their own decision.
