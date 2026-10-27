# GAP-092 — Evidence: staging-smoke's `artisan serve` dies mid-run in about a quarter of runs

Base: `84abd9a52cf7a22941a2435fcaf5dc5f856118c7` (main after GAP-091).

## How the job runs today

`.github/workflows/staging-smoke.yml`, job `staging-smoke`, step "Run staging smoke":
- runs `bash scripts/smoke_staging.sh` up to 3 times and fails only if all 3 attempts fail;
- the workflow comment says the retry guards against a GitHub runner-image regression that made `php artisan serve` segfault (PR #152, 2026-07-16), and should be removed "once GitHub's runner pool is confirmed stable again".

`scripts/smoke_staging.sh`:
- prepares a SQLite smoke database;
- starts `php artisan serve` on `127.0.0.1:18080`, which is PHP's built-in single-process server (`php -S`);
- checks the health, login, `/me` and projects endpoints with `curl`.

## Measurement: the 30 most recent completed runs (#950–#979, 2026-10-21 00:59 to 2026-10-22 16:09 UTC)

All 31 job logs were read: the 30 runs, plus the first attempt of run #978.

| | Runs |
|---|---|
| Passed on script attempt 1 | 22 |
| Needed script attempt 2 (then passed) | **8 (27%)** |
| Failed all 3 attempts (job red) | 1 (run #978 attempt 1, on PR #364; green on re-run) |

Every failed attempt has the same signature:
- one request gets `curl: (52) Empty reply from server`. That request was `/auth/me` 8 times, `/auth/login` 2 times and `/projects` 2 times;
- the next request gets `curl: (7) Failed to connect to 127.0.0.1 port 18080`;
- the server log ends at the request before the crash;
- health and login always passed before a crash, so start-up never failed.

There were no HTTP 4xx or 5xx responses and no assertion failures. This is the server process dying mid-request, the same failure mode as `php -S` in the Dusk job, which GAP-090 removed by switching to nginx + php-fpm.

## Consequences

- About 1 run in 4 needs the retry, and occasionally all 3 attempts fail and the job goes red.
- The 3-attempt retry would also hide a real crash bug in the app if one appeared.

## Notes for Gate 2

- Serve the smoke app with php-fpm behind nginx, as GAP-090 did for Dusk, instead of `artisan serve`. Keep `artisan serve`
  as the local default if the script is also run by developers.
- Decide whether to keep the 3-attempt retry.
- Proof: several consecutive runs pass on the first attempt (for example an 8-copy matrix), plus a check that killing
  php-fpm workers does not make the port refuse connections.
