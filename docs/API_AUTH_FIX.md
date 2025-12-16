# API Authentication Fix for Admin Dashboard

## Problem

401 Unauthorized error when calling `/api/admin/dashboard/summary` from React frontend (port 5173).

## Root Cause

1. **Request not using apiClient**: The `getAdminDashboard()` function was using `axios` directly instead of `apiClient`, which meant authentication headers (Bearer token) were not automatically included.

2. **Sanctum stateful domains**: Sanctum needs to recognize `localhost:5173` as a stateful domain to accept session-based authentication from the React dev server.

## Solution

### 1. Updated Frontend API Call

Changed `frontend/src/features/dashboard/api.ts` to use `apiClient` instead of `axios`:

```typescript
// Before (wrong)
const response = await axios.get('/api/admin/dashboard/summary', { ... });

// After (correct)
const response = await apiClient.get('/admin/dashboard/summary');
```

**Benefits:**
- `apiClient` automatically includes `Authorization: Bearer {token}` header
- `apiClient` includes CSRF token if available
- `apiClient` includes other required headers (`X-Requested-With`, etc.)
- `apiClient` has retry logic for network errors

### 2. Updated Sanctum Configuration

Updated `config/sanctum.php` to include `localhost:5173` in stateful domains:

```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:5173,dev.zena.local',
    ...
))),
```

**Note:** This is a temporary solution for development. In production with single-origin routing, only the domain (without port) should be included.

## Testing

1. **Verify token is stored:**
   ```javascript
   // In browser console
   localStorage.getItem('auth_token')
   ```

2. **Check request headers:**
   - Open browser DevTools → Network tab
   - Check request to `/api/admin/dashboard/summary`
   - Verify `Authorization: Bearer {token}` header is present

3. **Test API call:**
   ```bash
   curl -H "Authorization: Bearer {token}" \
        -H "Accept: application/json" \
        http://localhost:8000/api/admin/dashboard/summary
   ```

## Long-term Solution

For production, use **single-origin routing** (as implemented in `docs/SINGLE_ORIGIN_ROUTING.md`):

- All requests go through one domain (e.g., `dev.zena.local` or `manager.zena.com.vn`)
- No need for port-specific Sanctum config
- Session/cookies work consistently
- No CORS issues

## Related Files

- `frontend/src/features/dashboard/api.ts` - Fixed API call
- `config/sanctum.php` - Updated stateful domains
- `frontend/src/shared/api/client.ts` - API client with auth interceptors

