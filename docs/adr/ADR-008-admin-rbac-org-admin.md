# ADR-008: Admin RBAC with Org Admin Role

## Status
Accepted

## Date
2025-01-XX

## Context

The ZenaManage system requires a clear separation between system-wide administration and tenant-scoped administration. Previously, the system only had a single "Super Admin" role with full system access, which was too restrictive for organizations that needed administrative control over their own tenant without requiring full system-wide access.

Key requirements:
1. **Super Admin**: System-wide administration (all tenants)
2. **Org Admin**: Tenant-scoped administration (single tenant)
3. Clear separation of `/app/*` (Execution) vs `/admin/*` (Governance)
4. Tenant isolation must be enforced at all layers
5. Navigation should reflect user's access level

The system needed a role that allows organizations to:
- Manage their own tenant's templates
- Oversee their tenant's project portfolio
- View tenant-specific analytics and audit logs
- Configure tenant settings
- Without accessing system-wide functions (users, tenants, security, maintenance)

## Decision

Implement a two-tier admin RBAC system:

### 1. Super Admin Role

**Scope**: System-wide (all tenants)  
**Permission**: `admin.access` (all permissions)  
**Access**: All `/admin/*` routes including system-only routes

**Capabilities**:
- Manage all tenants and users
- Create and manage global templates
- View system-wide analytics
- Access security and maintenance functions
- Configure system settings

### 2. Org Admin Role

**Scope**: Tenant-scoped (single tenant)  
**Permission**: `admin.access.tenant` + specific permissions  
**Access**: Tenant-admin routes only (excludes system-only routes)

**Capabilities**:
- Create and manage tenant-specific templates (cannot create global templates)
- View tenant's project portfolio (read-only, can perform force actions)
- View tenant-specific analytics
- View tenant audit log
- Configure tenant settings (cannot access system settings)

**Restrictions**:
- Cannot access `/admin/users`, `/admin/tenants`, `/admin/security`, `/admin/maintenance`
- Cannot create or modify global templates
- Cannot view data from other tenants
- Cannot modify system settings

### 3. Permission Structure

New permissions introduced:
- `admin.access`: Full system access (Super Admin only)
- `admin.access.tenant`: Tenant-scoped admin access (Org Admin)
- `admin.templates.manage`: Manage WBS templates
- `admin.projects.read`: Read projects portfolio
- `admin.projects.force_ops`: Force operations (freeze, archive, suspend)
- `admin.settings.tenant`: Manage tenant settings
- `admin.analytics.tenant`: View tenant analytics
- `admin.activities.tenant`: View tenant audit log

### 4. Middleware Implementation

Two middleware layers:
- **`EnsureAdminAccess`**: Allows both Super Admin and Org Admin
  - Checks for `admin.access` OR `admin.access.tenant`
  - Enforces tenant scoping for Org Admin
- **`EnsureSystemAdmin`**: Super Admin only
  - Checks for `admin.access` only
  - Used for system-only routes

### 5. Tenant Scoping

Automatic tenant scoping via `ScopesByAdminAccess` trait:
- Applied to models: `Project`, `TemplateSet`, `User`, `AuditLog`
- Super Admin: No scoping (sees all)
- Org Admin: Automatic `where('tenant_id', $user->tenant_id)`

### 6. Navigation API

Updated `/api/v1/me/nav` endpoint:
- Returns different menu items based on admin access level
- Super Admin sees all admin items including system-only items
- Org Admin sees only tenant-admin items
- Regular users see no admin items

## Rules

### For Super Admin:
- ✅ Can access all `/admin/*` routes
- ✅ Can create global templates (`tenant_id: null`)
- ✅ Can view all tenants' data
- ✅ Can access system-only routes (`/admin/users`, `/admin/tenants`, etc.)
- ✅ Can modify system settings

### For Org Admin:
- ✅ Can access tenant-admin routes (`/admin/dashboard`, `/admin/projects`, `/admin/templates`, `/admin/analytics`, `/admin/activities`, `/admin/settings`)
- ✅ Can create tenant-specific templates only
- ✅ Can view only their tenant's data
- ✅ Can perform force actions on their tenant's projects
- ❌ Cannot access system-only routes
- ❌ Cannot create global templates
- ❌ Cannot view other tenants' data
- ❌ Cannot modify system settings

### For Regular Users:
- ❌ Cannot access any `/admin/*` routes
- ❌ Cannot see admin items in navigation

### Tenant Isolation:
- **Mandatory**: Every query must filter by `tenant_id` for Org Admin
- **Enforcement**: At repository/service layer via `ScopesByAdminAccess` trait
- **Testing**: Explicit tests to prove tenant A cannot read B
- **Indexes**: Composite indexes on `(tenant_id, foreign_key)`

## Implementation Details

### 1. RBAC Configuration

**File**: `config/permissions.php`

```php
'roles' => [
    'super_admin' => [
        'admin.access', // All permissions
    ],
    'org_admin' => [
        'admin.access.tenant',
        'admin.templates.manage',
        'admin.projects.read',
        'admin.projects.force_ops',
        'admin.settings.tenant',
        'admin.analytics.tenant',
        'admin.activities.tenant',
    ],
],
```

### 2. Middleware

**Files**: 
- `app/Http/Middleware/EnsureAdminAccess.php`
- `app/Http/Middleware/EnsureSystemAdmin.php`

**Registration**: `app/Http/Kernel.php`

### 3. Policies

**Files**:
- `app/Policies/AdminProjectPolicy.php`
- `app/Policies/AdminSettingsPolicy.php`
- `app/Policies/AdminAnalyticsPolicy.php`
- `app/Policies/AdminActivitiesPolicy.php`
- `app/Policies/TemplateSetPolicy.php` (updated)

### 4. Controllers

**Files**:
- `app/Http/Controllers/Admin/AdminProjectsController.php` (updated)
- `app/Http/Controllers/Admin/TemplateSetController.php` (updated)
- `app/Http/Controllers/Admin/AdminAnalyticsController.php` (new)
- `app/Http/Controllers/Admin/AdminActivitiesController.php` (new)
- `app/Http/Controllers/Admin/AdminSettingsController.php` (new)

### 5. Routes

**File**: `routes/web.php`

```php
// System-only routes (Super Admin only)
Route::middleware(['auth', 'EnsureSystemAdmin'])->prefix('admin')->group(function () {
    Route::get('/users', ...);
    Route::get('/tenants', ...);
    Route::get('/security', ...);
    Route::get('/maintenance', ...);
});

// Tenant-admin routes (Super Admin + Org Admin)
Route::middleware(['auth', 'EnsureAdminAccess'])->prefix('admin')->group(function () {
    Route::get('/dashboard', ...);
    Route::get('/projects', ...);
    Route::get('/templates', ...);
    Route::get('/analytics', ...);
    Route::get('/activities', ...);
    Route::get('/settings', ...);
});
```

## Consequences

### Positive

1. **Clear Role Separation**
   - Super Admin for system-wide administration
   - Org Admin for tenant-scoped administration
   - Clear boundaries and responsibilities

2. **Improved Security**
   - Tenant isolation enforced at multiple layers
   - Principle of least privilege applied
   - Reduced attack surface for Org Admin

3. **Better User Experience**
   - Org Admin sees only relevant data
   - Navigation reflects access level
   - No confusion about what can/cannot be accessed

4. **Scalability**
   - Supports multi-tenant architecture
   - Allows delegation of administrative tasks
   - Enables self-service for organizations

5. **Compliance**
   - Audit trail for all admin actions
   - Tenant data isolation
   - Clear permission boundaries

### Negative

1. **Complexity**
   - Additional middleware layer
   - More policies to maintain
   - More tests required

2. **Performance**
   - Additional permission checks
   - Tenant scoping adds query overhead (minimal with proper indexes)

3. **Maintenance**
   - More code to maintain
   - Need to keep permissions in sync
   - Documentation required

### Risks

1. **Tenant Isolation Violations**
   - **Mitigation**: Comprehensive tests, trait-based scoping, policy checks

2. **Permission Confusion**
   - **Mitigation**: Clear documentation, explicit permission names, role-based navigation

3. **Performance Degradation**
   - **Mitigation**: Proper database indexes, query optimization, caching

## Testing

### Unit Tests
- Middleware tests: `EnsureAdminAccessTest`, `EnsureSystemAdminTest`
- Policy tests: All admin policies tested

### Feature Tests
- Admin pages: `AdminProjectsTest`, `AdminTemplatesTest`, `AdminAnalyticsTest`, `AdminActivitiesTest`, `AdminSettingsTest`
- Navigation API: `NavigationApiTest`

### Integration Tests
- Tenant isolation: `TenantIsolationTest`
- Role-based access: `RoleBasedAccessTest`

### E2E Tests
- Super Admin access: `super-admin-access.spec.ts`
- Org Admin access: `org-admin-access.spec.ts`
- Regular user blocked: `regular-user-blocked.spec.ts`

## References

- [Admin API Documentation](../api/admin-endpoints.md)
- [Admin Guide](../admin/ADMIN_GUIDE.md)
- [RBAC Configuration](../../config/permissions.php)
- [Navigation API](../../app/Http/Controllers/Api/NavigationController.php)

---

**Last Updated**: 2025-01-XX  
**Maintained By**: ZenaManage Development Team

