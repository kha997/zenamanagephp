# Authentication & Tenant Flow

**Version**: 1.0  
**Last Updated**: January 2025  
**Status**: Active  
**Purpose**: Single source of truth for authentication and tenant resolution in ZenaManage

---

## Overview

This document describes how authentication and tenant resolution work in ZenaManage. It serves as the definitive guide for developers to understand the auth/tenant system.

---

## Authentication Architecture

### Guard Decision Matrix

| Route Group | Guard | Middleware | Use Case |
|------------|-------|------------|----------|
| `/api/v1/app/*` | `auth:sanctum` | `ability:tenant` + `tenant.isolation` | Tenant-scoped API endpoints |
| `/api/v1/admin/*` | `auth:sanctum` | `ability:admin` | System-wide admin endpoints |
| `/web` (Blade) | `auth:web` | Session-based | Legacy Blade views, admin pages |
| Legacy/Mobile | `auth:sanctum` | Token-based | Mobile apps, external integrations |

---

## SPA Authentication Flow (`/app/*`)

### Primary Mechanism: Sanctum Stateful (Session-based)

The React SPA (`/app/*`) uses **Sanctum stateful authentication**, which combines session-based auth with API token support.

### Flow Diagram

```
1. User visits /app/dashboard
   ↓
2. Frontend checks for session token
   ↓
3. If no token:
   a. Redirect to /login
   b. User enters credentials
   c. POST /api/v1/auth/login (with CSRF cookie)
   d. Backend creates session + returns token
   ↓
4. Frontend stores token in localStorage
   ↓
5. Frontend includes token in Authorization header for API calls
   ↓
6. Backend validates token via auth:sanctum middleware
   ↓
7. User accesses protected routes
```

### Step-by-Step Flow

#### Step 1: Get CSRF Cookie

**Frontend:**
```typescript
// Before login, get CSRF cookie
await axios.get('/sanctum/csrf-cookie');
```

**Backend:**
- Route: `/sanctum/csrf-cookie` (Laravel Sanctum built-in)
- Middleware: `web` (session required)
- Response: Sets `XSRF-TOKEN` cookie

#### Step 2: Login

**Frontend:**
```typescript
const response = await axios.post('/api/v1/auth/login', {
  email: 'user@example.com',
  password: 'password',
});
// Response: { ok: true, data: { user, token } }
```

**Backend:**
- Route: `POST /api/v1/auth/login`
- Middleware: `web`, `throttle:login`, `brute.force.protection`
- Process:
  1. Validate credentials
  2. Create session (for CSRF protection)
  3. Generate Sanctum token
  4. Return user data + token

#### Step 3: Get Session Token (Alternative)

If user is already authenticated via web session, frontend can get API token:

**Frontend:**
```typescript
const response = await axios.get('/api/v1/auth/session-token');
// Response: { ok: true, data: { token } }
```

**Backend:**
- Route: `GET /api/v1/auth/session-token`
- Middleware: `web`, `auth:web`
- Process: Generate token from existing session

#### Step 4: Use Token for API Calls

**Frontend:**
```typescript
axios.get('/api/v1/me', {
  headers: {
    'Authorization': `Bearer ${token}`,
  },
});
```

**Backend:**
- All `/api/v1/app/*` routes use `auth:sanctum` middleware
- Sanctum validates Bearer token
- User is authenticated

---

## Blade/Legacy Authentication Flow

### Primary Mechanism: Session-based (`auth:web`)

Blade views and legacy routes use **session-based authentication only**. No token exchange.

### Flow Diagram

```
1. User visits /admin/dashboard
   ↓
2. Backend checks session
   ↓
3. If no session:
   a. Redirect to /login
   b. User enters credentials
   c. POST /api/auth/login (web middleware)
   d. Backend creates session
   ↓
4. User accesses protected Blade routes
   ↓
5. All subsequent requests use session cookie
```

### Routes

- `/admin/*` - Admin panel (Blade views)
- Legacy routes - Old Blade-based pages
- Public pages - No authentication required

### Middleware Stack

```php
'web' => [
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    VerifyCsrfToken::class,
    SubstituteBindings::class,
],
```

---

## Tenant Resolution

### How Tenant ID is Determined

Tenant ID is resolved in the following priority order:

1. **User's `tenant_id`** (from authenticated user)
2. **Request attribute** (`tenant_id` set by middleware)
3. **App binding** (`current_tenant_id` in service container)

### Flow Diagram

```
1. User authenticates
   ↓
2. TenantIsolationMiddleware runs
   ↓
3. Gets user.tenant_id
   ↓
4. Sets request attribute: tenant_id
   ↓
5. Sets app binding: current_tenant_id
   ↓
6. BelongsToTenant trait uses tenant_id for all queries
```

### Middleware: TenantIsolationMiddleware

**Location:** `app/Http/Middleware/TenantIsolationMiddleware.php`

**Process:**
1. Check if user is authenticated
2. If super admin: bypass tenant isolation (set `tenant_id = null`)
3. If regular user: ensure `user.tenant_id` exists
4. Set `tenant_id` in request attributes
5. Set `current_tenant_id` in app container

**Usage:**
```php
Route::middleware(['auth:sanctum', 'ability:tenant', 'tenant.isolation'])
    ->prefix('app')
    ->group(function () {
        // All routes here are tenant-scoped
    });
```

### Model Trait: BelongsToTenant

**Location:** `app/Models/Concerns/BelongsToTenant.php`

**Features:**
- **Global Scope**: Automatically filters all queries by `tenant_id`
- **Auto-Set**: Automatically sets `tenant_id` when creating models
- **Fail-Safe**: Logs warnings if tenant context is missing

**Usage:**
```php
use App\Models\Concerns\BelongsToTenant;

class Project extends Model
{
    use BelongsToTenant;
    // tenant_id is automatically filtered and set
}
```

---

## Guard Configuration

### Standard Guards

#### 1. `auth:web` (Session-based)

**Config:** `config/auth.php`
```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
],
```

**Usage:**
- Blade views (`/admin/*`)
- Legacy routes
- Web forms

**Middleware:** `auth:web`

#### 2. `auth:sanctum` (Token-based)

**Config:** `config/auth.php`
```php
'guards' => [
    'api' => [
        'driver' => 'sanctum',
        'provider' => 'users',
    ],
],
```

**Usage:**
- API endpoints (`/api/v1/app/*`, `/api/v1/admin/*`)
- React SPA API calls
- Mobile apps

**Middleware:** `auth:sanctum`

### Deprecated Guards

The following guards exist but are **deprecated** and should not be used in new code:

- `SimpleJwtGuard` - Use `auth:sanctum` instead
- `SimpleTokenAuth` - Use `auth:sanctum` instead
- `SimpleSessionAuth` - Use `auth:web` instead

**Migration:**
- Replace `SimpleJwtGuard` → `auth:sanctum`
- Replace `SimpleTokenAuth` → `auth:sanctum`
- Replace `SimpleSessionAuth` → `auth:web`

---

## Route Groups & Middleware

### `/api/v1/app/*` (Tenant-scoped API)

**Middleware Stack:**
```
RequestId → Tracing → Metrics → Auth (sanctum) → Tenant (isolation) → RBAC (ability:tenant) → Security → ErrorEnvelope
```

**Example:**
```php
Route::middleware(['auth:sanctum', 'ability:tenant', 'tenant.isolation'])
    ->prefix('app')
    ->group(function () {
        Route::get('/projects', [ProjectsController::class, 'index']);
    });
```

### `/api/v1/admin/*` (System-wide Admin API)

**Middleware Stack:**
```
RequestId → Tracing → Metrics → Auth (sanctum) → Admin (ability:admin) → Security → ErrorEnvelope
```

**Example:**
```php
Route::middleware(['auth:sanctum', 'ability:admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/tenants', [TenantsController::class, 'index']);
    });
```

### `/web` (Blade Views)

**Middleware Stack:**
```
EncryptCookies → Session → CSRF → Bindings → Performance
```

**Example:**
```php
Route::middleware(['web', 'auth:web'])
    ->group(function () {
        Route::get('/admin/dashboard', [AdminDashboardController::class, 'index']);
    });
```

---

## RBAC Integration

### Abilities

Abilities determine what the user can access:

- **`tenant`**: User can access tenant-scoped endpoints (`/api/v1/app/*`)
- **`admin`**: User can access admin endpoints (`/api/v1/admin/*`)

### Ability Resolution

**Endpoint:** `GET /api/v1/me`

**Response:**
```json
{
  "user": { ... },
  "permissions": [ ... ],
  "abilities": ["tenant"]  // or ["tenant", "admin"] for super admin
}
```

**Logic:**
- Super admin (`is_admin = true` OR `can('admin.access')`): `["admin"]`
- Org admin (`can('admin.access.tenant')`): `["tenant"]`
- Regular user (has `tenant_id`): `["tenant"]`
- User without tenant: `[]` (no abilities)

### Navigation Menu

**Endpoint:** `GET /api/v1/me/nav`

**Response:**
```json
{
  "menu": [
    {
      "label": "Dashboard",
      "path": "/app/dashboard",
      "permission": null
    },
    {
      "label": "Admin",
      "path": "/admin/dashboard",
      "permission": "admin.access"  // Only shown if user has this permission
    }
  ]
}
```

**Filtering:**
- Regular users: Only see items without `permission` or items they have permission for
- Super admin: Sees all items including admin menu

---

## Security Considerations

### CSRF Protection

- **SPA**: Uses Sanctum CSRF cookie (`/sanctum/csrf-cookie`)
- **Blade**: Uses Laravel CSRF token (included in forms)

### Token Security

- Tokens are stored in `personal_access_tokens` table
- Tokens can be revoked
- Tokens expire based on `sanctum.expiration` config

### Tenant Isolation

- **Mandatory**: All tenant-aware queries filtered by `tenant_id`
- **Enforcement**: Global Scope + Middleware
- **Super Admin**: Can bypass tenant isolation (with audit logging)

---

## Troubleshooting

### Issue: "Unauthorized" on API calls

**Check:**
1. Token is included in `Authorization: Bearer {token}` header
2. Token is valid (not expired, not revoked)
3. User has required ability (`tenant` or `admin`)

### Issue: "No Tenant Access" (403)

**Check:**
1. User has `tenant_id` set
2. `TenantIsolationMiddleware` is applied
3. User is not super admin (super admin bypasses tenant check)

### Issue: CSRF token mismatch

**Check:**
1. Frontend calls `/sanctum/csrf-cookie` before login
2. CSRF cookie is included in login request
3. Session is maintained between requests

---

## Migration Guide

### From Deprecated Guards

#### SimpleJwtGuard → auth:sanctum

**Before:**
```php
Route::middleware(['auth:simple-jwt'])->group(...);
```

**After:**
```php
Route::middleware(['auth:sanctum'])->group(...);
```

#### SimpleTokenAuth → auth:sanctum

**Before:**
```php
Route::middleware([SimpleTokenAuth::class])->group(...);
```

**After:**
```php
Route::middleware(['auth:sanctum'])->group(...);
```

#### SimpleSessionAuth → auth:web

**Before:**
```php
Route::middleware([SimpleSessionAuth::class])->group(...);
```

**After:**
```php
Route::middleware(['auth:web'])->group(...);
```

---

## References

- [Multi-Tenant Architecture](MULTI_TENANT_ARCHITECTURE.md)
- [RBAC Documentation](RBAC_DOCUMENTATION.md)
- [Error Envelope Contract](ERROR_ENVELOPE_CONTRACT.md)
- [Middleware Stack](MIDDLEWARE_STACK.md)

---

*This document should be updated whenever authentication or tenant resolution logic changes.*

