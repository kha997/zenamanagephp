# Debug Login 404 Error

## Current Status

### ✅ Working:
- Backend API: http://localhost:8000 (200 OK)
- Backend Login API: http://localhost:8000/api/v1/auth/login (returns success)
- Frontend Dev Server: http://localhost:5173 (200 OK)
- Proxy test: http://localhost:5173/api/v1/auth/login (works!)

### ❌ Issue:
Frontend login still returns 404. This suggests the **browser** might be caching old code.

## Solution: Hard Refresh Browser

### Step 1: Clear Browser Cache
1. Open: http://localhost:5173/login
2. Press **F12** to open DevTools
3. Right-click the **refresh button** (next to address bar)
4. Select **"Empty Cache and Hard Reload"**

OR use keyboard shortcut:
- **Windows/Linux**: Ctrl + Shift + R
- **Mac**: Cmd + Shift + R

### Step 2: Check Network Tab
After hard refresh:
1. Keep DevTools open → **Network** tab
2. Try to login
3. Look for the request to `/auth/login`
4. Click on it to see:
   - **Request URL**: Should be `http://localhost:8000/api/v1/auth/login`
   - **Status**: Should be 200 (not 404)
   - **Response**: Should show the JSON with token

### Step 3: If Still 404
Check the actual request in browser console:
1. F12 → Console tab
2. Look for any error messages
3. F12 → Network tab → Click on the failed request
4. Look at the **Request URL** column

## Manual Test

Test the proxy directly:
```bash
curl http://localhost:5173/api/v1/auth/login -X POST \
  -H 'Content-Type: application/json' \
  -d '{"email":"test@example.com","password":"password"}'
```

Should return: `{"status":"success",...}`

## Files to Check

1. **frontend/src/shared/auth/store.ts** (line 62):
   - Has: `baseURL: 'http://localhost:8000/api/v1'`
   - This should make the call go to full URL

2. **frontend/vite.config.ts** (line 82-88):
   - Has proxy config for `/api`

## Quick Fix if Cache is Issue

In browser console (F12):
```javascript
// Clear localStorage
localStorage.clear();

// Clear auth_token
localStorage.removeItem('auth_token');

// Refresh
location.reload(true);
```

