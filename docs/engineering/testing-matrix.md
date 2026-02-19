# Testing Matrix

This project uses grouped PHPUnit suites to keep local runs fast while enabling deeper nightly coverage.

## Fast (default)
Command:

```bash
composer test:fast
```

Behavior:
- Runs all tests except `slow`, `load`, `stress`, and `redis` groups.
- Intended for local iteration and PR checks.

## Slow
Command:

```bash
RUN_SLOW_TESTS=1 composer test:slow
```

Behavior:
- Executes only `--group slow` tests.
- Tests in this group are expected to call `markTestSkipped()` when `RUN_SLOW_TESTS` is not set to `1`.

## Stress
Command:

```bash
RUN_STRESS_TESTS=1 composer test:stress
```

Behavior:
- Executes only `--group stress` tests.
- Tests in this group are expected to skip unless `RUN_STRESS_TESTS=1`.

## Load
Command:

```bash
RUN_LOAD_TESTS=1 composer test:load
```

Behavior:
- Executes only `--group load` tests.
- Tests in this group are expected to skip unless `RUN_LOAD_TESTS=1`.

## Redis
Command:

```bash
composer test:redis
```

Behavior:
- Executes only `--group redis` tests.
- In `composer test:nightly`, this group always runs and reports explicit PASS/FAIL/SKIP totals.

## Nightly Matrix
Command:

```bash
composer test:nightly
```

Sequence:
1. `php artisan optimize:clear`
2. `composer ssot:lint`
3. `composer test:fast`
4. `phpunit --group slow` with `RUN_SLOW_TESTS=1`
5. `phpunit --group load` with `RUN_LOAD_TESTS=1`
6. `phpunit --group stress` with `RUN_STRESS_TESTS=1`
7. `phpunit --group redis` with `RUN_REDIS_TESTS=1`

Nightly outputs:
- Group-level logs and JUnit files under `storage/app/nightly/`.
- Markdown summary artifact at `storage/app/nightly/nightly-report.md`.
- Deterministic group status:
  - `PASS` when group command succeeds and at least one test executed.
  - `SKIP` when all tests in a group are skipped.
  - `FAIL` when phpunit exits non-zero.

## Suggested CI Usage
PR pipeline:

```bash
php artisan optimize:clear
composer ssot:lint
composer test:fast
```

Nightly pipeline:

```bash
COMPOSER_PROCESS_TIMEOUT=0 composer test:nightly
```

If your environment has long-running jobs, increase timeout with `COMPOSER_PROCESS_TIMEOUT`.

## SSOT Strict Mode
PR lint mode:

```bash
SSOT_STRICT=1 composer ssot:lint
```

Behavior:
- Uses `scripts/ssot/allow_orphan_baseline.txt` as the strict baseline.
- Fails if new allowlist paths are introduced.
- Fails if allowlist path count increases beyond baseline.
- Normal `composer ssot:lint` behavior remains unchanged for local development.

Nightly maintenance:

```bash
SSOT_UPDATE_BASELINES=1 composer ssot:lint
```

Use this only when intentionally accepting baseline changes.

## SSOT Error Code Contracts
- RBAC middleware denial returns `403` with `error.code = RBAC_ACCESS_DENIED`.
- Policy/authorization denial returns `403` with `error.code = E403.AUTHORIZATION`.
- Input sanitization rejection returns `400` with `error.code = SUSPICIOUS_INPUT` (some validation flows may return `422` with `E422.VALIDATION` after sanitization passes).
