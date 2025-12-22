# E2E Test Session Authentication Issue

## 📋 Issue Summary

**Test File:** `tests/e2e/template-apply.spec.ts`  
**Test Case:** `complete flow: admin import → user apply → verify tasks`  
**Status:** ✅ **RESOLVED** (2025-11-15)  
**Root Cause:** ~~Laravel session authentication not recognized after API login~~ **FIXED**

## 🔍 Problem Description

The E2E test attempts to:
1. Login admin user via API (`/api/auth/login`) with `X-Web-Login: true` header
2. Extract session cookies from login response
3. Set cookies in Playwright browser context
4. Navigate to `/admin/templates` (Blade view)
5. Import template from JSON file

**Expected:** Admin should be authenticated and see the templates Blade view  
**Actual:** Page redirects to React frontend login (`http://localhost:5173/login`)

## 🔬 Debug Findings

### ✅ What Works
1. **Login API call succeeds** (status 200)
2. **Session cookies are set** in login response:
   - `XSRF-TOKEN` (domain=localhost, path=/, httpOnly=false)
   - `zenamanage_dashboard_session` (domain=localhost, path=/, httpOnly=true)
3. **Cookies are parsed and added** to Playwright browser context (2 cookies)
4. **Cookies are available** before navigation (verified via `page.context().cookies()`)

### ❌ What Doesn't Work
1. **Session not recognized** when navigating to `/admin/templates`
   - Direct API call with cookies: `GET /api/v1/auth/session-token` returns 401
   - Response: `{"success":false,"error":"Not authenticated","code":"UNAUTHENTICATED"}`
2. **Page redirects to React frontend** instead of rendering Blade view
   - Navigation to `http://localhost:8000/admin/templates` redirects to `http://localhost:5173/login`
   - Response is React HTML (Vite dev server) instead of Blade view
3. **AdminOnlyMiddleware redirects** to login route (which doesn't exist, so falls back to React frontend)

## 🛠️ Attempted Solutions

### 1. Cookie Parsing & Setting
- ✅ Fixed cookie parsing logic to handle multiple cookies in single `Set-Cookie` header string
- ✅ Correctly extracts cookie name, value, domain, path, httpOnly, secure attributes
- ✅ Normalizes domain to `localhost` for Playwright compatibility
- ✅ Sets cookies in browser context using `page.context().addCookies()`

### 2. Navigation Strategy
- ✅ Changed navigation URL from `127.0.0.1:8000` to `localhost:8000` to match cookie domain
- ✅ Added `waitUntil: 'networkidle'` and increased timeout
- ✅ Verified cookies are available before navigation

### 3. Session Verification
- ✅ Added debug logging to check cookies before/after navigation
- ✅ Added direct API call with cookies to verify session recognition
- ✅ Added admin dashboard check to verify authentication

### 4. API Token Extraction
- ✅ Added code to extract API token from login response
- ⚠️ Not used yet (Blade views require web session, not API token)

## 🔍 Root Cause Analysis

### Possible Causes

1. **Session file not saved after API login**
   - API route `/api/auth/login` has `web` middleware
   - `AuthenticationController` calls `Auth::guard('web')->login()` and `session()->save()`
   - But session may not be persisted to file system for API requests

2. **Session cookie domain/path mismatch**
   - Cookie domain: `localhost`
   - Cookie path: `/`
   - Laravel config: `SESSION_DOMAIN` may be `null` or different
   - Laravel config: `SESSION_PATH` is `/`

3. **Session encryption/decryption issue**
   - Session is encrypted (`encrypt: true` in `config/session.php`)
   - Cookie value may not decrypt correctly when sent back

4. **Session driver configuration**
   - Default driver: `file` (from `config/session.php`)
   - Session files stored in `storage/framework/sessions`
   - May not be writable or accessible

5. **Middleware order/execution**
   - Route has `['web', 'auth:web', AdminOnlyMiddleware]`
   - `web` middleware should start session
   - But session may not be started for API requests

## 📝 Code References

### Login Flow
- **Route:** `routes/web.php:135` - `POST /api/auth/login` with `['web', 'throttle:login']` middleware
- **Controller:** `app/Http/Controllers/Api/Auth/AuthenticationController.php:31`
  - Line 55-80: Web session login logic with `X-Web-Login` header check
  - Line 57: `Auth::guard('web')->login($user, $remember)`
  - Line 67: `$request->session()->regenerate()`
  - Line 80: `$request->session()->save()`

### Admin Templates Route
- **Route:** `routes/web.php:287-294` - `GET /admin/templates` with `['web', 'auth:web', AdminOnlyMiddleware]`
- **Controller:** `app/Http/Controllers/Admin/TemplateSetController.php:index`
- **Middleware:** `app/Http/Middleware/AdminOnlyMiddleware.php:24`
  - Line 28-44: Redirects to `route('login')` if not authenticated
  - Line 44: `return redirect()->route('login')` (route doesn't exist, so falls back to root `/`)

### Session Configuration
- **Config:** `config/session.php`
  - Line 21: `'driver' => env('SESSION_DRIVER', 'file')`
  - Line 49: `'encrypt' => true`
  - Line 62: `'files' => storage_path('framework/sessions')`
  - Line 129-132: `'cookie' => env('SESSION_COOKIE', Str::slug(env('APP_NAME', 'laravel'), '_').'_session')`
  - Line 158: `'domain' => env('SESSION_DOMAIN')`
  - Line 184: `'http_only' => true`
  - Line 199: `'same_site' => 'lax'`

## 🎯 Next Steps for Codex

### Option 1: Fix Session Persistence (Recommended)
1. **Verify session file is created** after API login
   - Check `storage/framework/sessions/` directory
   - Verify file permissions (should be writable)
   - Check if session ID in cookie matches file name

2. **Debug session middleware execution**
   - Add logging in `StartSession` middleware
   - Verify session is started for API requests with `web` middleware
   - Check if session data is persisted

3. **Test session cookie manually**
   - Use `curl` or Postman to login and get cookies
   - Manually send cookies in next request
   - Verify session is recognized

### Option 2: Use API Token for Admin Operations
1. **Create API endpoint for template import**
   - Add `POST /api/v1/admin/template-sets/import` endpoint
   - Use `auth:sanctum` + `ability:admin` middleware
   - Accept file upload via multipart/form-data

2. **Update E2E test to use API endpoint**
   - Use API token from login response
   - Call API endpoint directly instead of navigating to Blade view
   - Verify import success via API response

### Option 3: Use Browser-based Login
1. **Navigate to login page first**
   - Use `page.goto('/login')` (if route exists)
   - Fill login form and submit
   - Let browser handle session cookies naturally

2. **Or create test login route**
   - Add `POST /test/login` route for E2E tests only
   - Use `web` middleware and return redirect with session

## 📊 Test Output Logs

```
✅ Login successful!
✅ Set 2 cookie(s) in browser context
📋 Cookies available for localhost:8000: 2
  - XSRF-TOKEN (domain=localhost, path=/, httpOnly=false)
  - zenamanage_dashboard_session (domain=localhost, path=/, httpOnly=true)
Navigating to http://localhost:8000/admin/templates...
Current URL after navigation: http://localhost:5173/login
Session check status: 401
Session check response: {"success":false,"error":"Not authenticated","code":"UNAUTHENTICATED"}
```

## 🔗 Related Files

- `tests/e2e/template-apply.spec.ts` - E2E test file
- `app/Http/Controllers/Api/Auth/AuthenticationController.php` - Login controller
- `app/Http/Controllers/Admin/TemplateSetController.php` - Admin templates controller
- `app/Http/Middleware/AdminOnlyMiddleware.php` - Admin authentication middleware
- `config/session.php` - Session configuration
- `routes/web.php` - Web routes

## 📌 Notes

- The test is part of the Task Templates feature implementation
- Feature flag: `FEATURE_TASK_TEMPLATES=true` is set in `playwright.config.ts`
- Database: `zenamanage_e2e` (MySQL)
- Admin user: `admin@zena.local` / `password` (role: `super_admin`)
- Sample template file: `resources/templates/sample.aec-intl.json`

---

**Created:** 2025-11-15  
**Last Updated:** 2025-11-15  
**Status:** 🔴 BLOCKED - Awaiting session authentication fix

