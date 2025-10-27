# Dashboard API 403 Forbidden - Investigation Report

## Issue Status
Attempting to diagnose why authenticated users are getting 403 Forbidden on `/api/v1/app/dashboard`

## Authentication Flow Analysis

### 1. Frontend Token Storage
- ✅ Tokens stored in `localStorage` as `auth_token`
- ✅ Token attached to requests in `Authorization: Bearer {token}` header
- ✅ Multiple API clients exist, all using same pattern

### 2. Backend Route Configuration  
```php
// routes/api_v1.php
Route::prefix('app')->middleware(['auth:sanctum'])->group(function () {
    // ...
    Route::middleware(['ability:tenant'])->prefix('dashboard')->group(function () {
        Route::get('/', [...]);
    });
});
```

- ✅ Route registered
- ✅ Uses `auth:sanctum` middleware
- ✅ Also uses `ability:tenant` middleware

### 3. Middleware Chain
Request flow:
1. `auth:sanctum` - Should authenticate the user
2. `ability:tenant` - Should authorize tenant access

### 4. AbilityMiddleware (Already Fixed)
- ✅ Now returns `null` on success instead of blocking response
- ✅ Expanded allowed roles
- ✅ Properly calls `$next($request)`

### 5. Potential Issues

#### Issue #1: Sanctum Token Authentication Not Working
**Possibility**: Sanctum might not be finding/validating the token properly

**Symptoms**:
- Token exists in localStorage
- Token is sent in Authorization header
- But Sanctum can't find it

**Diagnostic Steps**:
1. Check if token is being received by backend
2. Check if token exists in `personal_access_tokens` table
3. Check Sanctum configuration

#### Issue #2: Token Format Mismatch
**Possibility**: Token might be in wrong format or missing hash

**Check**:
```bash
# In database
SELECT * FROM personal_access_tokens WHERE tokenable_type = 'App\\Models\\User';
```

#### Issue #3: User Missing Tenant ID
**Possibility**: User might not have tenant_id set

**Check**:
```bash
SELECT id, email, tenant_id, role FROM users WHERE email = 'test@example.com';
```

## Diagnostic Steps

### Step 1: Test Authentication Endpoint
```bash
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'
```

### Step 2: Test Dashboard Endpoint with Token
```bash
curl http://localhost/api/v1/app/dashboard \
  -H "Authorization: Bearer {TOKEN_FROM_STEP_1}"
```

### Step 3: Check Laravel Logs
```bash
tail -f storage/logs/laravel.log
```

Look for:
- "Unauthenticated request" entries
- "User without tenant" entries  
- "User with invalid role" entries

### Step 4: Check Database
```bash
php artisan tinker
```

```php
// Check if token exists
$user = User::where('email', 'test@example.com')->first();
$user->tokens;

// Check token in database
PersonalAccessToken::where('tokenable_id', $user->id)->get();

// Test if middleware would pass
Auth::guard('sanctum')->setUser($user);
Auth::guard('sanctum')->check(); // Should return true
```

## Next Actions

Based on the investigation, I should:

1. **Create a test endpoint** without `ability:tenant` middleware to isolate the issue
2. **Add logging** to AbilityMiddleware to see exactly why it's failing
3. **Check the actual user data** in the database
4. **Verify token is being sent** by adding request logging

## Recommended Fix

Add comprehensive logging to diagnose the exact issue:

```php
// In AbilityMiddleware
Log::debug('Ability check started', [
    'ability' => $ability,
    'user_id' => $user->id,
    'user_role' => $user->role,
    'user_tenant_id' => $user->tenant_id,
    'url' => $request->fullUrl()
]);
```

Then check logs after making a request to see exactly what's happening.

