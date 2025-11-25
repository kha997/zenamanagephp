# WebSocket Status - UAT Phase 1

## Overview

WebSocket functionality in ZenaManage is **experimental** for UAT Phase 1. All core features work via HTTP polling, and WebSocket is an optional enhancement for real-time updates.

**⚠️ IMPORTANT FOR UAT & PRODUCTION:**
- **WebSocket is 100% optional** - The system works completely without WebSocket
- **HTTP polling is the default and production-ready** - All dashboard features use React Query hooks with HTTP polling
- **No part of the system requires WebSocket** - All data loading works via HTTP API endpoints
- **Feature flags default to `false`** - WebSocket is disabled by default for UAT safety

## Current Status

### Production-Ready (HTTP-Based)
- ✅ Dashboard KPIs (`useDashboardStats` hook)
- ✅ Dashboard Alerts (`useDashboardAlerts` hook)
- ✅ Recent Projects/Tasks (`useRecentProjects`, `useRecentTasks` hooks)
- ✅ Activity Feed (`useRecentActivity` hook)
- ✅ All data fetching via `/api/v1/app/dashboard/*` endpoints

### Experimental (WebSocket)
- ⚠️ Realtime dashboard KPI updates
- ⚠️ Realtime overdue alerts broadcasting
- ⚠️ Realtime notification delivery
- ⚠️ Live collaboration features

## Architecture

### HTTP Fallback Strategy

All dashboard features use **React Query** hooks that:
1. Fetch data via HTTP API on initial load
2. Automatically refetch based on `staleTime` configuration
3. Provide polling-like behavior without WebSocket dependency

**Example:**
```typescript
// frontend/src/features/dashboard/hooks.ts
export const useDashboardStats = () => {
  return useQuery({
    queryKey: ['dashboard', 'stats'],
    queryFn: () => dashboardApi.getStats(),
    staleTime: 60 * 1000, // Refetch every 60 seconds
  });
};
```

### WebSocket Implementation

WebSocket is implemented in:
- `app/WebSocket/DashboardWebSocketHandler.php` - Main WebSocket handler
- Uses `AuthGuard` for authentication
- Uses `RateLimitGuard` for rate limiting
- Supports per-tenant message routing

## Feature Flags

WebSocket features are controlled via `config/features.php`:

```php
'websocket' => [
    'enable_dashboard_updates' => env('WEBSOCKET_ENABLE_DASHBOARD', false),
    'enable_alerts' => env('WEBSOCKET_ENABLE_ALERTS', false),
],
```

**Default:** `false` (disabled) for UAT safety.

### Enabling WebSocket

To enable WebSocket features (not recommended for UAT):

1. Set environment variables:
   ```bash
   WEBSOCKET_ENABLE_DASHBOARD=true
   WEBSOCKET_ENABLE_ALERTS=true
   ```

2. Ensure WebSocket server is running:
   ```bash
   php artisan websocket:serve
   ```

3. Frontend will automatically use WebSocket if enabled, otherwise falls back to HTTP polling.

## HTTP vs WebSocket Comparison

| Feature | HTTP (Current) | WebSocket (Experimental) |
|---------|---------------|---------------------------|
| Dashboard KPIs | ✅ React Query polling | ⚠️ Real-time push |
| Alerts | ✅ React Query polling | ⚠️ Real-time push |
| Initial Load | ✅ Immediate | ✅ Immediate |
| Updates | ✅ Polling (60s interval) | ⚠️ Push (instant) |
| Reliability | ✅ High (HTTP fallback) | ⚠️ Medium (requires WS server) |
| UAT Ready | ✅ Yes | ❌ No (experimental) |

## Recommendations for UAT

1. **Keep WebSocket disabled** (`WEBSOCKET_ENABLE_DASHBOARD=false`, `WEBSOCKET_ENABLE_ALERTS=false`)
2. **Use HTTP polling** for all dashboard features (already implemented and default)
3. **Monitor performance** - HTTP polling with 60s intervals is sufficient for UAT
4. **Test WebSocket separately** - Don't block UAT on WebSocket functionality
5. **Verify HTTP-only operation** - All features must work 100% with HTTP polling only

## UAT & Production Readiness

✅ **System is 100% functional without WebSocket:**
- Dashboard KPIs load via HTTP API (`/api/v1/app/dashboard/stats`)
- Dashboard alerts load via HTTP API (`/api/v1/app/dashboard/alerts`)
- All data fetching uses React Query hooks with automatic polling
- No WebSocket connection is required for any feature
- Frontend does not initialize WebSocket when feature flags are disabled

✅ **HTTP Fallback is Production-Ready:**
- React Query handles automatic refetching based on `staleTime`
- Polling intervals are optimized (30-60 seconds)
- Error handling and retry logic are built-in
- Performance is acceptable for UAT and production use

## Future Enhancements

After UAT Phase 1, consider:
- Enabling WebSocket for improved real-time experience
- Implementing WebSocket reconnection logic
- Adding WebSocket metrics and monitoring
- Optimizing WebSocket message payloads

## Related Files

- `app/WebSocket/DashboardWebSocketHandler.php` - WebSocket handler
- `frontend/src/features/dashboard/hooks.ts` - HTTP-based hooks
- `frontend/src/features/dashboard/api.ts` - API client
- `config/features.php` - Feature flags
- `routes/api_v1.php` - Dashboard API endpoints

