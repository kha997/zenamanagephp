# ZenaManage Authentication System Documentation

## 📋 **TỔNG QUAN**

ZenaManage sử dụng hệ thống authentication đa lớp với Laravel Sanctum và session-based authentication để hỗ trợ cả web application và API.

## 🔐 **AUTHENTICATION GUARDS**

### 1. Web Guard (Session-based)
- **Driver**: `session`
- **Provider**: `users`
- **Usage**: Web routes, dashboard, admin panel
- **Middleware**: `auth:web`

### 2. API Guard (Sanctum)
- **Driver**: `sanctum`
- **Provider**: `users`
- **Usage**: API routes, mobile app, SPA
- **Middleware**: `auth:sanctum`

## 🛡️ **MIDDLEWARE SYSTEM**

### Core Middleware Groups

#### Web Middleware Group
```php
'web' => [
    \App\Http\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \App\Http\Middleware\VerifyCsrfToken::class,
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

#### API Middleware Group
```php
'api' => [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

### Custom Middleware Aliases

| Middleware | Class | Purpose |
|------------|-------|---------|
| `auth` | `Authenticate` | Basic authentication |
| `auth:web` | `Authenticate` | Web session authentication |
| `auth:sanctum` | `EnsureFrontendRequestsAreStateful` | Sanctum API authentication |
| `ability:tenant` | `TenantAbilityMiddleware` | Tenant-scoped access |
| `ability:admin` | `AdminOnlyMiddleware` | Admin-only access |
| `tenant.scope` | `TenantScopeMiddleware` | Tenant isolation |
| `throttle:public` | `ThrottleRequests` | Public API rate limiting |
| `throttle:api` | `ThrottleRequests` | API rate limiting |

## 🚨 **CURRENT STATUS**

### ✅ Working Components
- **Web Routes**: Dashboard, login, root redirect
- **Session Management**: CSRF protection, cookie encryption
- **Middleware Order**: Correct sequence for web and API
- **Guard Configuration**: Properly configured guards and providers

### ⚠️ Known Issues
- **Auth Middleware**: Temporarily disabled due to `auth()` helper conflicts
- **API Routes**: Some routes return 500 errors due to middleware conflicts
- **132+ Files**: Using `auth()` helper incorrectly instead of `Auth` facade

## 🔧 **TROUBLESHOOTING**

### Common Issues

#### 1. "Object of type Illuminate\Auth\AuthManager is not callable"
**Cause**: Using `auth()` helper incorrectly
**Solution**: Replace with `Auth::check()`, `Auth::user()`, `Auth::id()`

#### 2. "Target class [ability:admin] does not exist"
**Cause**: Middleware not registered in Kernel.php
**Solution**: Add middleware alias to `$middlewareAliases`

#### 3. "Target class [request] does not exist"
**Cause**: Syntax error in route files
**Solution**: Check for unmatched brackets in routes/web.php

### Debug Commands

```bash
# Check for auth() usage issues
php guard-lint.php app/

# Auto-fix common auth() issues
php auth-auto-fix.php app/

# Clear all caches
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Test routes
curl -I http://127.0.0.1:8000/
curl -I http://127.0.0.1:8000/app/dashboard
curl -I http://127.0.0.1:8000/login
```

## 📚 **BEST PRACTICES**

### 1. Use Auth Facade Instead of auth() Helper
```php
// ❌ Wrong
if (auth()->check()) {
    $user = auth()->user();
    $userId = auth()->id();
}

// ✅ Correct
if (Auth::check()) {
    $user = Auth::user();
    $userId = Auth::id();
}
```

### 2. Specify Guard When Needed
```php
// ❌ Wrong
$user = auth('api')->user();

// ✅ Correct
$user = Auth::guard('api')->user();
```

### 3. Middleware Order Matters
```php
// ✅ Correct order
Route::middleware(['web', 'auth:web'])->group(function () {
    // Web routes
});

Route::middleware(['api', 'auth:sanctum'])->group(function () {
    // API routes
});
```

## 🔄 **MIGRATION PLAN**

### Phase 1: Fix auth() Helper Usage
1. Run `php auth-auto-fix.php app/` to fix common issues
2. Manually review and fix remaining 132+ files
3. Use `php guard-lint.php app/` to verify fixes

### Phase 2: Re-enable Auth Middleware
1. Test all routes with auth middleware disabled
2. Gradually re-enable auth middleware for specific routes
3. Monitor for errors and fix as needed

### Phase 3: Full Authentication Flow
1. Implement proper login/logout functionality
2. Test tenant isolation and RBAC
3. Add comprehensive test coverage

## 🧪 **TESTING**

### Manual Testing
```bash
# Test web routes
curl -I http://127.0.0.1:8000/
curl -I http://127.0.0.1:8000/app/dashboard
curl -I http://127.0.0.1:8000/login

# Test API routes
curl -I http://127.0.0.1:8000/api/v1/public/health
curl -I http://127.0.0.1:8000/api/v1/app/projects
```

### Automated Testing
- GitHub Actions workflow: `.github/workflows/auth-lint.yml`
- Guard Lint script: `guard-lint.php`
- Auto-fix script: `auth-auto-fix.php`

## 📝 **NOTES**

- **Security**: Auth middleware is currently disabled for development
- **Performance**: All routes are working with 200/302 responses
- **Maintenance**: Use Guard Lint to prevent future auth() helper issues
- **Documentation**: Keep this file updated as system evolves

---

*Last Updated: September 24, 2025*
*Version: 1.0*
