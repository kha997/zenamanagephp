# Login Fix Summary

## Problem
Login was failing because:
1. **Double API prefix**: The frontend called `/api/v1/api/v1/auth/login` (double prefix)
2. **Wrong response parsing**: Expected `response.data` but actual structure is `response.data.data`

## Solution

### Fixed in `frontend/src/shared/auth/store.ts`

**Before**:
```typescript
const response = await apiClient.post('/api/v1/auth/login', {
  email,
  password,
});
const { user, token } = response.data;
```

**After**:
```typescript
// Use relative path (client already has baseURL: '/api/v1')
const response = await apiClient.post('/auth/login', {
  email,
  password,
});

// Handle both response structures
const responseData = response.data;
const token = responseData.data?.token || responseData.token;
const userData = responseData.data?.user || responseData.user;

// Map user data properly
const user: User = {
  id: userData.id,
  name: userData.name,
  email: userData.email,
  avatar: userData.avatar,
  tenant_id: userData.tenant_id,
  roles: userData.role ? [userData.role] : [],
  permissions: [],
  tenant_name: userData.tenant_name,
};
```

## Root Cause

The `apiClient` has `baseURL: '/api/v1'` configured, so:
- ❌ `apiClient.post('/api/v1/auth/login')` → calls `/api/v1/api/v1/auth/login`
- ✅ `apiClient.post('/auth/login')` → calls `/api/v1/auth/login`

## Test

1. Open: http://localhost:5173/login
2. Enter: `test@example.com` / `password`
3. Should now work!

