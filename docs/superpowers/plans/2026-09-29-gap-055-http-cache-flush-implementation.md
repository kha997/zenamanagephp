# GAP-055 — HTTP cache-flush fix: implementation plan

Design: `docs/owner-decisions/GAP-055/02-design.md` (Option 1, approved 2026-09-29).

## Task 1 — RED tests
1. `tests/Feature/GAP055/AdminClearCacheFailsClosedTest.php`: super-admin POST
   `/admin/maintenance/clear-cache` → 409 `success=false`; other tenant's
   `RateLimiter` attempts and tenant cache key unchanged.
2. `tests/Unit/GAP055/PreLaunchActionsNoCacheFlushTest.php`: with `Artisan`
   mocked, `executePreLaunchActions()` never calls `cache:clear` and still calls
   `config:clear`, `route:clear`, `optimize`, `config:cache`, `route:cache`,
   `migrate` in order.
3. `tests/Unit/GAP055/BadgeUserCacheClearIsTargetedTest.php`:
   `clearUserBadgeCache($u1)` removes u1's badge keys only; u2 badge keys,
   limiter counters, other keys kept; `clearItemBadgeCache` no longer exists.
Run each → confirm it fails for the expected reason.

## Task 2 — GREEN
- `MaintenanceController::clearCache()` → 409, no mutation, warning log.
- `LaunchChecklistService::executePreLaunchActions()` → drop `cache:clear`.
- `BadgeService`: `BADGE_ENDPOINTS` constant shared by `getBadgeEndpoint()`;
  `clearUserBadgeCache()` forgets `badge_{id}_user_{uid}` per id; delete
  `clearItemBadgeCache()`.

## Task 3 — adjust legacy expectations
`tests/Feature/FinalSystemTest.php` (clear-cache row) and
`tests/Feature/PerformanceTest.php::test_maintenance_task_performance` → 409.

## Task 4 — verify
New tests green; FinalSystem/Performance maintenance tests (performance
group); GAP052 contract test; `git grep` for flush on the three surfaces;
governance lint; push; full CI green; Gate 3 packet with canonical digest.
