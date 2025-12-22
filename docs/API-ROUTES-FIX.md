# API Routes Fix - Auth Endpoints

## Problem
Frontend was calling `/api/v1/auth/me` but route is actually `/api/auth/me` (no v1 prefix).

## Solution
Updated `frontend/src/features/auth/api.ts` to call correct endpoints:

### Fixed Endpoints

1. **GET /api/auth/me** (was calling `/api/v1/auth/me`)
   - Route location: `routes/api.php` line 199
   - Prefix: `Route::prefix('auth')` (not `v1/auth`)
   - Middleware: `auth:sanctum`, `ability:tenant`
   - Uses session auth via `withCredentials: true`

2. **GET /api/auth/permissions** (was calling `/api/v1/auth/permissions`)
   - Route location: `routes/api.php` line 201
   - Prefix: `Route::prefix('auth')` (not `v1/auth`)
   - Middleware: `auth:sanctum`, `ability:tenant`
   - Uses session auth via `withCredentials: true`

### Working Endpoints (No Change Needed)

- **POST /api/v1/auth/login** ✅ (route is in `v1/auth` prefix)
- **POST /api/v1/auth/logout** ✅ (route is in `v1/auth` prefix)

## Implementation

Changed `getMe()` and `getPermissions()` to use direct axios calls with:
- Base URL: `/api/auth/me` (not `/api/v1/auth/me`)
- `withCredentials: true` for session auth
- CSRF token header
- Bearer token header (if available, fallback to session)

## Test

After fix, `/api/auth/me` should return 200 instead of 404.

