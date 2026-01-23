# Testing Guide

## Running PHPUnit

The suite runs with PCOV enabled by default; no special bootstrap logic tries to opt out of it anymore.
When you need to exclude `mysql`, `external`, `a11y`, or `performance` groups, the repository provides a small wrapper:

```bash
./bin/phpunit-api [args]
```

This script mirrors the CLI-friendly flags we normally pass through `php -d opcache.enable_cli=0 -d memory_limit=-1 -d max_execution_time=0 ./vendor/bin/phpunit`, but with the standard group exclusions already wired in. It still sets `XDEBUG_MODE=off` so Xdebug does not interfere, but it no longer clears `PHP_INI_SCAN_DIR`, so PCOV stays loaded.

For more targeted runs, call `./vendor/bin/phpunit` directly with the flags you need; the wrapper is just a convenience.

### Feature/Api + PCOV segfault

- `bin/phpunit-api` keeps PCOV enabled and always passes `--debug` so the raw PHPUnit output ends up in `storage/logs/phpunit-feature-api.debug.log`. When the runner crashes, grab the last started test from the log:

```bash
rg "Test '.*' started" storage/logs/phpunit-feature-api.debug.log | tail -n 5
```

- If Feature/Api hits a `Signal(11)` (or another fatal error) when PCOV is loaded, rerun that suite without PCOV to unblock the rest of CI/dev work:

```bash
ZENA_TRACE_DOC_VERSION=0 ZENA_TRACE_FAIL_500=0 ./bin/phpunit-api-nocov \
  -c phpunit.xml --exclude-group mysql,external,a11y,performance \
  -v --stop-on-failure tests/Feature/Api
```

  PCOV stays enabled for every other suite; the `-nocov` helper is only for the heavy Feature/Api run or a confirmed PCOV segfault.

## Confirming PCOV

If you want to see whether PCOV is available in your CLI environment, run:

```bash
php -m | rg -i '^pcov$'
php --ini
```

If the first command prints `pcov`, the module is loaded and the test suite should pick it up automatically. When it does not show up, double-check which `php.ini` and `additional .ini` files are being loaded (`php --ini`) and adjust your PHP configuration so that PCOV is enabled when the tests run.

## RBAC bypass flag

The RBAC middleware now uses `RBAC_BYPASS_TESTING` in addition to `APP_ENV=testing`. When the flag is set to `1` (the default), tests shortcut RBAC checks so authentication gates do not block suite setup. Setting `RBAC_BYPASS_TESTING=0` forces the guard to validate roles/permissions during the run, which is useful for regression testing or smoke tests that must exercise the real access rules.

```
RBAC_BYPASS_TESTING=1 ./vendor/bin/phpunit --filter=YourFeatureTest
RBAC_BYPASS_TESTING=0 ./vendor/bin/phpunit --filter=YourFeatureTest
```

You can combine these with your normal `./bin/phpunit-api` wrapper or any custom command; just ensure `APP_ENV=testing` stays set so the middleware reads the flag consistently.

## API migration expectations

- Legacy `/api/*` and `/api/zena/*` endpoints should emit the Deprecation, Sunset, Link, and `X-API-Legacy` headers described in `docs/API_MIGRATION.md`; tests that exercise those routes can assert their presence when the migration flags are enabled.
- `API_CANONICAL_PROJECTS` controls whether `/api/projects` and `/api/zena/projects` bind to the legacy controllers or the new handlers in `src`. When the flag is `1`, tests will hit the canonical controllers, so flip it deliberately when you are validating the new surface.
- `API_CANONICAL_DOCUMENTS` mirrors the projects flag for document-related routes; keep it at `0` when you are exercising the legacy handlers so the depraction telemetry remains stable.
- `API_CANONICAL_INSPECTIONS` behaves the same way for `/api/zena/inspections` (and the new `/api/v1/inspections` routes), so toggle it explicitly whenever you are validating the canonical inspection surface.
