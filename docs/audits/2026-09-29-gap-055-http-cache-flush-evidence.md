# GAP-055 — HTTP-triggered whole-cache flush: Gate-1 evidence

**Date:** 2026-09-29 (+07:00)

**Canonical base:** `30f7104588bfc69373a1a21f4d8bc078d50543b7`

**Branch:** `docs/GAP-055-admin-clear-cache-flush`

**Scope:** Read-only investigation and Gate-1 documentation. No application,
route, cache, config, test, CI, deployment, or environment change is included.
Runtime probes used a disposable, never-committed PHPUnit file (deleted before
any commit) against the canonical base in the main checkout.

## Summary

The register row (OWN-2026-013) named one live site. A repository-wide scan
finds **three HTTP surfaces** that flush the whole default cache store, one of
them reachable by a GET, plus one latent surface reachable by every role once
an unrelated import bug is fixed. The flush resets rate-limit counters of
**every tenant**. It does not reach sessions or scheduler locks under the
default Redis layout (this narrows a claim made in the GAP-054 evidence).

## Method

1. `git grep` for `Cache::flush(`, `->flush()`, `'cache:clear'`,
   `Redis::flushdb`, `Cache::store(...)->flush` over `app routes src config
   bootstrap` → 17 sites.
2. Runtime route table: `php artisan route:list --json` on the canonical base
   (1171 routes) to decide which sites are HTTP-reachable and under which
   middleware.
3. Caller tracing (`git grep '<method>('`) for every flushing service method.
4. Disposable runtime probes (below).

## Live surfaces

| # | Route | Handler → flush | Access |
|---|---|---|---|
| A | `POST admin/maintenance/clear-cache` (`routes/web.php:194`) | `Admin\MaintenanceController::clearCache()` → `cache:clear`, `config:clear`, `route:clear`, `view:clear`, `Cache::flush()` (`app/Http/Controllers/Admin/MaintenanceController.php:283-292`); UI button `resources/views/admin/maintenance-content.blade.php:162` | `rbac:admin` |
| B1 | `POST api/v1/final-integration/pre-launch-actions` (`routes/web.php:173`) | `FinalIntegrationController::executePreLaunchActions()` → `LaunchChecklistService::executePreLaunchActions()` → `cache:clear` (`app/Services/LaunchChecklistService.php:225`) | `rbac:admin` |
| B2 | `GET api/v1/final-integration/launch-report` | `FinalIntegrationController::generateLaunchReport()` (`app/Http/Controllers/FinalIntegrationController.php:193`) calls the same `executePreLaunchActions()` — **a GET flushes the cache** | `rbac:admin` |

`rbac:admin` resolves to super-admin only: `'admin'` is neither in
`RoleBasedAccessControlMiddleware::isRole()`'s list nor a permission code, so
`checkAccess()` falls through to deny for everyone except `isSuperAdmin()`
(`app/Http/Middleware/RoleBasedAccessControlMiddleware.php:109-127,142-154`).
Probe: a tenant user with role `admin` receives **403** on surface A; a
`super_admin` receives 200.

### Also discovered on B1/B2 (outside GAP-055's cache scope)

`executePreLaunchActions()` additionally runs `optimize`, `config:cache`,
`route:cache` and **`migrate --force`** (`LaunchChecklistService.php:233-249`)
— so a super-admin GET of `launch-report` runs production migrations from a web
request. No UI calls `final-integration/*` (`git grep` over `resources`,
`public/js`: no match). Recommended as a **separate candidate gap**; GAP-055
does not propose to fix it.

## Latent surface (reachable by every role once one import is fixed)

| # | Route | Handler → flush | Access |
|---|---|---|---|
| C | `DELETE api/badges/cache` (`routes/api.php:984`) | `Api\BadgeController::clearUserBadgeCache()` → `BadgeService::clearUserBadgeCache()` → `Cache::flush()` (`app/Services/BadgeService.php:132-141`); `clearItemBadgeCache()` (`:146-151`) also flushes but has no caller | `auth:sanctum` + `RoleBasedAccessControlMiddleware` **with no argument** → `handleGeneralAccess()` admits any of 12 roles including `client` and `viewer` (`RoleBasedAccessControlMiddleware.php:255-294`) |

Today every `BadgeController` action returns **500**:
`Class "App\Http\Controllers\Api\Request" does not exist` — the controller
type-hints `Request` without `use Illuminate\Http\Request`
(`app/Http/Controllers/Api/BadgeController.php:5-6,13`). So the flush never
executes. This also means the sidebar badge fetch
(`resources/views/components/sidebar.blade.php:209`, `GET /api/badges/{id}`)
currently fails. Anyone who fixes that import without fixing the flush would
hand a global cache flush to `client`/`viewer` users.

## Not HTTP-reachable (checked)

| Site | Why not reachable |
|---|---|
| `Api\CacheController::clearAll()` (`POST api/cache/clear`) | Live, but uses `AdvancedCacheService::invalidateByPattern()` with `tenant:{id}:*` key prefix — tenant-scoped, not a global flush |
| `Api\Admin\PerformanceController::clearCaches()` | Route commented out (`routes/web.php:347`) |
| `App\Http\Controllers\PerformanceController::clearCaches()` | `routes/legacy/api_v1.php:38` not in runtime route table |
| `api/performance/cache/flush*`, `rate-limit/reset` | Routed to `Api\PerformanceController`, which is an empty 9-line class — 500, no flush |
| `OpenApiController::clearCaches()` | No route |
| `EmailConfigController::clearTemplateCache()` | No `email-config` route in runtime table |
| `PermissionService::clearAllCaches()`, `RBACManager::clearAllPermissionsCache()`, `SidebarService::clearAllCaches()`, `QueueManagementService::restartWorkers()`, `RedisCachingService::clearAllCache()`, `AdvancedCachingService::flush()`, `src/Common/Services/CacheService::flush()` | No caller in `app`, `src`, `routes` |
| `CleanupLegacyRoutes` command | Manual artisan only |

These are dead or tenant-scoped today; they are listed so a future change that
wires one of them up is recognisable as re-introducing this defect.

## Runtime proof (disposable probe, canonical base, array cache store)

Setup per case: 5 `RateLimiter::hit('zena-login|victim@other.example.com')`
and `Cache::put("tenant:{victimTenant}:probe")` for a **different** tenant.

| Case | Request | Result |
|---|---|---|
| A, `super_admin` of tenant X | `POST /admin/maintenance/clear-cache` | **200**; victim login attempts 5 → **0**; other tenant's key → **null** |
| A, tenant `admin` role | same | 403 (no flush) |
| C, `client` role | `DELETE /api/badges/cache` | 500 (`Request` import); attempts stay 5 |

B1/B2 were not executed because they also run `migrate --force`; their flush is
established statically (`cache:clear` on the default store, same as A).

## Production blast radius (Redis layout)

`CACHE_DRIVER=redis` (`docker-compose.prod.yml:15`); store `redis` uses
connection `cache` → DB `REDIS_CACHE_DB` (default 1) (`config/cache.php:16-20`,
`config/database.php` redis `cache`). `RedisStore::flush()` is `flushdb()` on
that DB. Rate limiters have no dedicated store (`config/cache.php` has no
`limiter` key) → they live in DB 1 and are wiped. Not affected under defaults:
sessions (`SessionManager::createRedisDriver()` sets the store connection to
`session.connection` = null → `default`, DB 0) and cache locks
(`lock_connection => 'default'`, DB 0) — so, contrary to the GAP-054 evidence
wording, scheduler overlap locks are **not** reset by a Redis cache flush
unless `REDIS_CACHE_DB` is configured equal to `REDIS_DB`.

Effect of one flush: login (`zena-login`), invitation, portal and AI
`throttle:` counters of all tenants reset; OIDC login state and all tenant
application caches dropped (cold-cache load spike).

## Out of scope for this Gate 1

- Any fix (Gate 2 design).
- `migrate --force` / `optimize` from HTTP (B1/B2) — recommended separate gap.
- Restoring badge functionality (`BadgeController` import) other than as
  required to keep surface C from becoming live.
- Dead controllers/services listed above.
