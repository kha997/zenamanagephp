# Dashboard API 500 Error - Type Hint Fix

## Error
```
App\Http\Controllers\Api\V1\App\DashboardController::getStatsData(): 
Argument #1 ($tenantId) must be of type int, string given
```

## Root Cause
The database uses ULID (Unique Lexicographically Sortable Identifier) for IDs, which are **strings**.

But the helper methods were typed as expecting **int**:
```php
private function getStatsData(int $tenantId): array  // ❌ WRONG
```

## The Fix
Changed type hints from `int` to `string`:

```php
// BEFORE - Wrong type
private function getStatsData(int $tenantId): array

// AFTER - Correct type
private function getStatsData(string $tenantId): array
```

### All Methods Fixed
- ✅ `getStatsData(string $tenantId): array`
- ✅ `getRecentProjectsData(string $tenantId, int $limit): array`
- ✅ `getRecentTasksData(string $tenantId, int $limit): array`
- ✅ `getRecentActivityData(string $tenantId, int $limit, $user = null): array`

## Why ULID?
- ULIDs are **strings** like "01k8fjw1s2c4z4mvptg83h2aa5"
- They provide better database performance and are sortable
- More compact than UUIDs
- Works better with string-based indexes

## Status
✅ Fixed - Dashboard API should now work correctly

