# PR #3: WebSocket Auth Guard

## Summary
Refactored WebSocket authentication and authorization into separate guard classes (`AuthGuard` and `RateLimitGuard`) for better separation of concerns, testability, and maintainability.

## Changes

### New Files
1. **`app/WebSocket/AuthGuard.php`**
   - Centralized authentication and authorization logic
   - Sanctum token verification using `PersonalAccessToken::findToken()`
   - Channel format validation
   - Permission-based subscription checks
   - Tenant isolation enforcement

2. **`app/WebSocket/RateLimitGuard.php`**
   - Per-connection rate limiting (10 messages/second)
   - Per-tenant rate limiting (500 messages/second)
   - Connection limit per tenant (50 connections)
   - Sliding window algorithm for rate limiting

3. **`tests/Unit/WebSocket/AuthGuardTest.php`**
   - Unit tests for AuthGuard
   - Token verification tests
   - Channel format validation tests
   - Subscription permission tests

4. **`tests/Unit/WebSocket/RateLimitGuardTest.php`**
   - Unit tests for RateLimitGuard
   - Rate limiting tests
   - Connection limit tests

### Modified Files
1. **`app/WebSocket/DashboardWebSocketHandler.php`**
   - Refactored to use `AuthGuard` and `RateLimitGuard`
   - Removed inline authentication logic
   - Removed inline rate limiting logic
   - Updated `getStats()` to use `RateLimitGuard::getStats()`

2. **`tests/Feature/WebSocketAuthTest.php`**
   - Updated to use `AuthGuard` directly instead of reflection
   - Removed reflection-based tests

3. **`tests/Feature/WebSocketHardeningTest.php`**
   - Updated to use `AuthGuard` directly instead of reflection
   - Removed reflection helper method

## Features

### Authentication
- Sanctum token verification
- Token expiration check
- User active status check
- Comprehensive error logging

### Authorization
- Tenant isolation enforcement
- Permission-based channel subscription
- Support for legacy channel formats (backward compatibility)
- Resource-specific permission checks (tasks, projects, documents)

### Rate Limiting
- Per-connection: 10 messages/second
- Per-tenant: 500 messages/second
- Connection limit: 50 connections per tenant
- Automatic cleanup on connection close

## Testing

### Unit Tests
```bash
php artisan test --filter=AuthGuardTest
php artisan test --filter=RateLimitGuardTest
```

### Feature Tests
```bash
php artisan test --filter=WebSocketAuthTest
php artisan test --filter=WebSocketHardeningTest
```

## Migration Notes

### Breaking Changes
None - all changes are internal refactoring. The WebSocket API remains the same.

### Backward Compatibility
- Legacy channel formats are still supported
- All existing WebSocket clients continue to work

## Security Improvements

1. **Separation of Concerns**: Auth and rate limiting logic separated from handler
2. **Testability**: Guards can be tested independently
3. **Maintainability**: Easier to update auth/rate limiting logic
4. **Logging**: Comprehensive logging for security events

## Performance

- Rate limiting uses sliding window algorithm (O(1) per check)
- Connection tracking uses in-memory arrays
- No database queries for rate limiting checks

## Future Improvements

1. Redis-based rate limiting for multi-server deployments
2. Configurable rate limits per tenant/user role
3. WebSocket connection pooling
4. Metrics export for monitoring

