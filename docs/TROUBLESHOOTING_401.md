# Troubleshooting 401 Unauthorized Errors

## Problem

Getting `401 Unauthorized` when accessing `/api/admin/dashboard/summary` from React frontend.

## Common Causes

### 1. No Authentication Token

**Symptom:** Request fails with 401, no `Authorization` header in request.

**Solution:**
1. Check browser console for token status:
   ```javascript
   localStorage.getItem('auth_token')
   ```
2. If token is missing, log in again through React login page
3. Verify token is saved after login

### 2. Token Expired or Invalid

**Symptom:** Token exists but request still returns 401.

**Solution:**
1. Clear localStorage and log in again:
   ```javascript
   localStorage.clear();
   // Then log in again
   ```
2. Check token format in Network tab → Request Headers → Authorization
3. Verify token hasn't been revoked on server

### 3. Sanctum Configuration

**Symptom:** Token is sent but Sanctum rejects it.

**Check:**
1. Verify `config/sanctum.php` includes your domain:
   ```php
   'stateful' => ['localhost', 'localhost:5173', 'dev.zena.local']
   ```
2. Check `.env` for `SANCTUM_STATEFUL_DOMAINS`
3. Ensure `SESSION_DOMAIN` is correct (empty for same-origin)

### 4. User Not Logged In

**Symptom:** User can access page but API calls fail.

**Solution:**
1. Check if user is authenticated:
   ```javascript
   // In browser console
   const { user, isAuthenticated } = useAuthStore.getState();
   console.log({ user, isAuthenticated });
   ```
2. If not authenticated, redirect to login
3. Ensure login flow saves token to localStorage

### 5. Wrong API Endpoint

**Symptom:** Request goes to wrong URL.

**Check:**
- Admin routes: `/api/admin/dashboard/summary` (not `/api/v1/admin/...`)
- Verify `apiClient` baseURL is `/api`
- Check Vite proxy config forwards `/api/*` correctly

## Debugging Steps

### Step 1: Check Token

Open browser console and run:
```javascript
const token = localStorage.getItem('auth_token');
console.log('Token exists:', !!token);
console.log('Token length:', token?.length);
console.log('Token preview:', token?.substring(0, 20) + '...');
```

### Step 2: Check Request Headers

1. Open DevTools → Network tab
2. Find request to `/api/admin/dashboard/summary`
3. Click on request → Headers tab
4. Check Request Headers for:
   - `Authorization: Bearer {token}` (should be present)
   - `Accept: application/json`
   - `X-Requested-With: XMLHttpRequest`

### Step 3: Check Response

1. In Network tab, click on failed request
2. Check Response tab for error message
3. Common responses:
   - `{"message": "Unauthenticated"}` - No token or invalid token
   - `{"message": "Admin access required"}` - User doesn't have admin role
   - `{"message": "CSRF token mismatch"}` - CSRF issue

### Step 4: Verify User Role

```javascript
// In browser console
const { user } = useAuthStore.getState();
console.log('User role:', user?.role);
console.log('Is admin:', ['admin', 'super_admin'].includes(user?.role?.toLowerCase()));
```

### Step 5: Test API Directly

```bash
# Get token from localStorage first
TOKEN="your-token-here"

# Test API call
curl -H "Authorization: Bearer $TOKEN" \
     -H "Accept: application/json" \
     http://localhost:8000/api/admin/dashboard/summary
```

## Solutions

### Solution 1: Re-login

1. Clear browser storage:
   ```javascript
   localStorage.clear();
   sessionStorage.clear();
   ```
2. Navigate to `/login`
3. Log in with admin credentials
4. Verify token is saved: `localStorage.getItem('auth_token')`

### Solution 2: Check Sanctum Config

Verify `config/sanctum.php`:
```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 
    'localhost,localhost:5173,dev.zena.local'
)),
```

### Solution 3: Use Single-Origin Routing

For production, use single-origin routing (see `docs/SINGLE_ORIGIN_ROUTING.md`):
- All requests go through one domain
- No port-specific config needed
- Session/cookies work consistently

### Solution 4: Verify Route Middleware

Check `routes/api.php`:
```php
Route::prefix('admin/dashboard')
    ->middleware(['auth:sanctum', 'ability:admin'])
    ->group(function () {
        // Routes here
    });
```

Ensure:
- `auth:sanctum` is present
- `ability:admin` is present
- User has `admin` or `super_admin` role

## Prevention

1. **Always use `apiClient`** instead of `axios` directly
2. **Check auth before API calls** in components
3. **Handle 401 errors gracefully** with redirect to login
4. **Use single-origin routing** in production
5. **Test authentication flow** after changes

## Related Files

- `frontend/src/shared/api/client.ts` - API client with auth
- `frontend/src/features/auth/store.ts` - Auth state management
- `config/sanctum.php` - Sanctum configuration
- `routes/api.php` - API routes with middleware

