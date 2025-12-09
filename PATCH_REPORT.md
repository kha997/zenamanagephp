# Patch Report: Remove In-Memory Cache from NotificationPreferenceService

## Summary
Removed in-memory caching from `NotificationPreferenceService` to prevent stale preference decisions in long-lived processes (queue workers, scheduler). All preference checks now read directly from the database.

## Files Changed

### 1. `app/Services/NotificationPreferenceService.php`

#### Removed Code:
- **Line 20-28**: Removed `private array $cache = [];` property and its documentation comment
- **Line 40-45**: Removed cache key generation and cache lookup logic from `isTypeEnabledForUser()`
- **Line 57-58**: Removed cache write logic from `isTypeEnabledForUser()`
- **Line 149-151**: Removed cache invalidation logic from `updatePreferencesForUser()`
- **Line 155-165**: Removed entire `clearCache()` method

#### Modified Code:
- **Line 18-19**: Added note in class docblock about direct database reads for immediate effect
- **Line 25-27**: Updated `isTypeEnabledForUser()` docblock to mention direct database reads
- **Line 34-45**: Simplified `isTypeEnabledForUser()` to query database directly without cache:
  - Removed cache key generation
  - Removed cache lookup (`if (isset($this->cache[$cacheKey]))`)
  - Removed cache write (`$this->cache[$cacheKey] = $isEnabled`)
  - Now directly queries and returns result in one step

#### Unchanged Code:
- `getPreferencesForUser()` - Already used direct DB reads, no changes needed
- `updatePreferencesForUser()` - Only removed cache invalidation, upsert logic unchanged

### 2. `tests/Feature/Api/V1/App/NotificationPreferencesApiTest.php`

#### Added Code:
- **New test method** `test_reenabled_preference_allows_notifications_again()` (lines 355-410):
  - Tests that disabling a preference prevents notifications
  - Tests that re-enabling a preference via API immediately allows notifications again
  - Uses the same `NotificationService` instance throughout to prove no stale cache
  - Verifies notification is created after re-enabling within the same process

## Implementation Details

### How `isTypeEnabledForUser()` Now Works:

**Before (with cache):**
```php
public function isTypeEnabledForUser(string $tenantId, string $userId, string $type): bool
{
    $cacheKey = "{$tenantId}:{$userId}:{$type}";
    
    // Check cache first
    if (isset($this->cache[$cacheKey])) {
        return $this->cache[$cacheKey];
    }

    // Query preference
    $preference = UserNotificationPreference::withoutGlobalScope('tenant')
        ->where('tenant_id', $tenantId)
        ->where('user_id', $userId)
        ->where('type', $type)
        ->first();

    // If no preference row exists → default to enabled
    $isEnabled = $preference ? $preference->is_enabled : true;

    // Cache the result
    $this->cache[$cacheKey] = $isEnabled;

    return $isEnabled;
}
```

**After (no cache):**
```php
public function isTypeEnabledForUser(string $tenantId, string $userId, string $type): bool
{
    // Query preference directly from database (no cache)
    $preference = UserNotificationPreference::withoutGlobalScope('tenant')
        ->where('tenant_id', $tenantId)
        ->where('user_id', $userId)
        ->where('type', $type)
        ->first();

    // If no preference row exists → default to enabled
    return $preference ? $preference->is_enabled : true;
}
```

### Tenant Isolation:
- Uses `withoutGlobalScope('tenant')` with explicit `where('tenant_id', $tenantId)` filter
- Ensures tenant isolation is maintained without relying on global scope

## Test Results

### NotificationPreferencesApiTest
```
✓ can get default notification preferences for current user
✓ can update notification preferences for current user
✓ cannot set preferences for unknown type
✓ tenant isolation for preferences
✓ disabled type prevents notifications from being created
✓ enabled type allows notifications by default
✓ preferences affect direct notification service calls
✓ reenabled preference allows notifications again (NEW TEST)
✓ requires authentication
✓ validation errors for invalid request

Tests: 10 passed
```

### TaskDueRemindersTest
```
Tests: 10 passed
```

All existing tests continue to pass, confirming backward compatibility.

## Impact

### Benefits:
- ✅ Preference changes take effect immediately in long-lived processes
- ✅ No stale cache issues in queue workers or scheduler
- ✅ Simpler code without cache management logic
- ✅ All tests pass, including new test proving immediate effect

### Performance:
- Each preference check now performs one database query
- For our scale, this is acceptable (preferences are checked per notification, not per request)
- Database queries are indexed on `(tenant_id, user_id, type)` for fast lookups

### Backward Compatibility:
- ✅ No changes to public API signatures
- ✅ No changes to controllers or routes
- ✅ All existing tests pass
- ✅ Behavior is identical except for immediate effect of changes
