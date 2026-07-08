# Production Runtime Surface Hardening Lock

Date: 2026-04-14

## Scope

One bounded hardening round to remove or explicitly gate non-business runtime surfaces from the default app runtime without changing canonical business ownership.

## Root Cause

Several test/debug/probe surfaces were still mounted on the normal route table:

- app-owned auth probe routes were mounted directly inside `routes/api_zena.php` on the canonical `/api/zena` prefix
- `_debug/*` routes were still registered from `routes/web.php` and only blocked at request time by middleware
- package tooling routes for Ignition and Dusk were still auto-discovered into the route table

This left the runtime surface broader than intended even when those endpoints were not part of the canonical business API.

## Locked Decisions

- Canonical `/api/zena/*` business surfaces remain mounted and unchanged.
- Test probe routes under `/api/zena` are no longer mounted by default; they are now explicit opt-in through `config('runtime_surface.enable_test_probe_routes')`.
- `_debug/*` routes and legacy debug redirects are no longer mounted by default; they are now explicit opt-in through `config('runtime_surface.enable_debug_routes')`.
- `spatie/laravel-ignition` and `laravel/dusk` are removed from package auto-discovery and may only be manually registered in local/testing when their explicit runtime-surface flags are enabled:
  - `runtime_surface.enable_ignition_routes`
  - `runtime_surface.enable_dusk_routes`

## Surfaces Hardened

- `/api/zena/simple-test`
- `/api/zena/minimal-auth-test`
- `/api/zena/sanctum-auth-test`
- `/api/zena/me-test`
- `/api/zena/auth-test`
- `/_debug/*`
- `/_dusk/*`
- `/_ignition/*`

## Explicit Non-Claims

- No canonical owner-family change.
- No business-logic change.
- No business-payload change.
- No Material Request / Receipt / Contract / Payment / Dashboard semantic change.

## Verify

- `php artisan route:list | grep -E 'simple-test|minimal-auth-test|sanctum-auth-test|me-test|auth-test|_debug|_ignition|_dusk' || true`
- `php artisan route:list --path=api/zena`
- `php ./vendor/bin/phpunit tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`

## Result

Default runtime route registration is now hardened so debug, test, and tooling surfaces stay absent unless explicitly re-enabled for local/dev workflows.
