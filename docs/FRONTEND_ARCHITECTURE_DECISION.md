# Frontend Architecture Decision

**Version**: 1.0  
**Last Updated**: January 2025  
**Status**: Active  
**Purpose**: Defines the clear separation between React SPA and Blade templates

---

## Overview

ZenaManage uses a **hybrid frontend architecture** with clear separation:
- **React SPA** (`frontend/`) is the source of truth for `/app/*` routes
- **Blade Templates** are used only for public pages, admin legacy pages, and debug

---

## Architecture Principles

### 1. React SPA is Source of Truth for `/app/*`

**Location:** `frontend/src/`

**Routes:** All `/app/*` routes are handled by React Router

**Configuration:** `config/frontend.php` - `'active' => 'react'`

**Entry Point:** Server serves `index.html` for all `/app/*` routes, React Router handles client-side routing

### 2. Blade Only for Specific Use Cases

**Use Blade for:**
- Public pages (login, register, password reset)
- Admin legacy pages (`/admin/*`)
- Debug/test pages
- Email templates

**Do NOT use Blade for:**
- `/app/*` routes (use React SPA)
- New feature development (use React)

---

## Routing Strategy

### Server-Level Routing (Nginx/Apache)

The server handles routing at the infrastructure level:

```
/admin/* → Laravel/Blade (server routes directly)
/api/* → Laravel API (server routes directly)
/app/* → React SPA (server fallback to /app/index.html)
```

### No Frontend Redirects

**Avoid:** Client-side redirects between Blade and React  
**Prefer:** Server-level routing configuration

### Deep Linking Support

**Requirement:** F5/refresh must work for all routes

**Implementation:**
1. Server serves `index.html` for all `/app/*` routes
2. React Router handles client-side routing
3. No 404 errors on refresh

**Example:**
```
User visits: /app/projects/123
F5/Refresh: Server serves index.html, React Router navigates to /app/projects/123
```

---

## Route Configuration

### `/app/*` Routes (React SPA)

**Backend:** `routes/web.php`
```php
// React SPA Entry Point - Catch-all for /app/* routes
Route::get('/app/{any}', [AppController::class, 'handle'])
    ->where('any', '.*')
    ->middleware([AppModuleRoutingMiddleware::class])
    ->name('app.spa');
```

**Frontend:** `frontend/src/app/router.tsx`
```typescript
<Route path="/app/dashboard" element={<DashboardPage />} />
<Route path="/app/projects" element={<ProjectsListPage />} />
// ... all /app/* routes
```

### `/admin/*` Routes (Blade)

**Backend:** `routes/web.php`
```php
Route::middleware(['web', 'auth:web', 'admin.system'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
    // ... admin routes
});
```

**Views:** `resources/views/admin/*.blade.php`

### Public Routes (Blade)

**Backend:** `routes/web.php`
```php
Route::get('/login', [LoginController::class, 'showLoginForm']);
Route::get('/register', [RegisterController::class, 'showRegistrationForm']);
```

**Views:** `resources/views/auth/*.blade.php`

---

## Migration Checklist

### From Blade to React

When migrating a Blade page to React:

1. **Create React Component**
   - Location: `frontend/src/pages/` or `frontend/src/features/{domain}/pages/`
   - Follow Apple-style UI guidelines

2. **Add Route**
   - Add route to `frontend/src/app/router.tsx`
   - Ensure route matches Blade route pattern

3. **Update Backend Route**
   - Remove Blade route from `routes/web.php` or `routes/app.php`
   - Ensure `/app/*` catch-all route handles the path

4. **Mark Blade Route as Deprecated**
   - Move to `routes/archived/` or add `@deprecated` comment
   - Update documentation

5. **Test**
   - Verify React route works
   - Verify F5/refresh works
   - Verify deep linking works

---

## Deprecated Routes

### Blade Routes to Remove

The following Blade routes are deprecated and should be migrated to React:

**Location:** `routes/app.php` (marked as legacy)

- `/app-legacy/tasks/*` - Migrated to React
- `/app-legacy/clients/*` - Migrated to React
- `/app-legacy/quotes/*` - Migrated to React
- `/app-legacy/documents/*` - Migrated to React

**Action:** These routes are in `routes/app.php` under `app-legacy` prefix and will be removed after full migration.

---

## Authentication & RBAC

### React SPA (`/app/*`)

**Auth:** Sanctum stateful (session + token)  
**RBAC:** API-based (`/api/v1/me`, `/api/v1/me/nav`)  
**Navigation:** Dynamic based on user permissions

### Blade (`/admin/*`)

**Auth:** Session-based (`auth:web`)  
**RBAC:** Server-side checks  
**Navigation:** Static menu in Blade views

### Consistency

Both React and Blade read from the same API endpoints:
- `GET /api/v1/me` - User info + permissions
- `GET /api/v1/me/nav` - Navigation menu

This ensures consistency across both frontends.

---

## Configuration

### Frontend Config

**File:** `config/frontend.php`

```php
'active' => env('FRONTEND_ACTIVE', 'react'),

'systems' => [
    'react' => [
        'enabled' => true,
        'base_url' => env('FRONTEND_REACT_URL', 'http://localhost:5173'),
        'routes' => ['/app/*'],
    ],
    'blade' => [
        'enabled' => false, // MUST BE FALSE if React is active
        'routes' => ['/admin/*'],
    ],
],
```

**Rule:** Only ONE frontend system can be active at a time.

---

## Development Workflow

### React Development

1. Start React dev server: `cd frontend && npm run dev`
2. React runs on `http://localhost:5173`
3. Backend API runs on `http://localhost:8000`
4. React proxies API calls to backend

### Blade Development

1. Start Laravel server: `php artisan serve`
2. Blade views served directly from Laravel
3. No separate frontend server needed

---

## Testing

### React Routes

- E2E tests: `tests/E2E/**/*.spec.ts` (Playwright)
- Component tests: `frontend/src/**/__tests__/*.test.ts` (Vitest)

### Blade Routes

- Feature tests: `tests/Feature/**/*Test.php` (PHPUnit)
- Browser tests: `tests/Browser/**/*Test.php` (Laravel Dusk)

---

## Troubleshooting

### Issue: React route returns 404

**Cause:** Server not configured to serve `index.html` for `/app/*`  
**Solution:** Ensure catch-all route in `routes/web.php` handles `/app/*`

### Issue: Blade and React conflict

**Cause:** Both systems trying to handle same route  
**Solution:** Ensure `config/frontend.php` has only one system active

### Issue: F5/refresh doesn't work

**Cause:** Server not serving `index.html` for deep links  
**Solution:** Configure server (Nginx/Apache) to serve `index.html` for `/app/*`

---

## References

- [Authentication & Tenant Flow](AUTH_TENANT_FLOW.md)
- [Service Catalog](SERVICE_CATALOG.md)
- [Middleware Stack](MIDDLEWARE_STACK.md)

---

*This document should be updated whenever frontend architecture changes.*
