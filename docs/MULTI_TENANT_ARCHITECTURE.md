# Multi-Tenant Architecture

**Version**: 1.0  
**Last Updated**: January 2025  
**Status**: Active  
**Purpose**: Comprehensive guide to ZenaManage's multi-tenant isolation system

---

## Overview

ZenaManage implements a **row-level security** multi-tenant architecture where all tenant-aware data is automatically filtered by `tenant_id`. This ensures complete data isolation between tenants while maintaining a single database schema.

---

## Core Principles

1. **Automatic Isolation**: All queries are automatically filtered by `tenant_id` via Global Scopes
2. **Fail-Safe**: Missing tenant context triggers warnings in production
3. **Super Admin Bypass**: Super admins can bypass tenant scope (with audit logging)
4. **Middleware Enforcement**: Tenant context is set at the request level via middleware
5. **Model-Level Enforcement**: Global Scopes ensure tenant filtering even if middleware is bypassed

---

## Architecture Components

### 1. BelongsToTenant Trait

**Location**: `app/Models/Concerns/BelongsToTenant.php`

**Purpose**: Provides automatic tenant isolation for Eloquent models

**Features**:
- **Global Scope**: Automatically filters all queries by `tenant_id`
- **Auto-Set**: Automatically sets `tenant_id` when creating models
- **Multi-Source Tenant ID**: Gets tenant ID from request context, Auth user, or app binding
- **Fail-Safe Logging**: Logs warnings in production if tenant context is missing

**Usage**:
```php
use App\Models\Concerns\BelongsToTenant;

class Project extends Model
{
    use BelongsToTenant;
    
    // tenant_id is automatically filtered and set
}
```

**How It Works**:

1. **Global Scope Application**:
   ```php
   static::addGlobalScope('tenant', function (Builder $q) {
       $tenantId = self::getCurrentTenantId();
       if ($tenantId) {
           $q->where($q->getModel()->getTable() . '.tenant_id', $tenantId);
       }
   });
   ```

2. **Tenant ID Resolution** (Priority Order):
   - Request context (set by middleware) - **Most Reliable**
   - Auth user `tenant_id`
   - App instance binding (`current_tenant_id`)

3. **Auto-Set on Create**:
   ```php
   static::creating(function ($model) {
       if (empty($model->tenant_id)) {
           $model->tenant_id = self::getCurrentTenantId();
       }
   });
   ```

### 2. TenantScope Trait (Legacy)

**Location**: `app/Traits/TenantScope.php`

**Status**: Legacy - Use `BelongsToTenant` for new models

**Purpose**: Alternative implementation of tenant isolation (being phased out)

**Migration**: Models using `TenantScope` should migrate to `BelongsToTenant` for consistency

### 3. Tenant Isolation Middleware

**Location**: `app/Http/Middleware/TenantIsolationMiddleware.php`

**Purpose**: Sets tenant context at the request level

**How It Works**:

1. **Authentication Check**: Verifies user is authenticated
2. **Super Admin Bypass**: Super admins can access all tenants
3. **Tenant Context Setting**:
   ```php
   app()->instance('current_tenant_id', $user->tenant_id);
   $request->attributes->set('tenant_id', $user->tenant_id);
   ```

**Middleware Stack**:
```php
// routes/api.php
Route::middleware(['auth:sanctum', 'ability:tenant', 'tenant.isolation'])
    ->prefix('app')
    ->group(function () {
        // All routes here are tenant-scoped
    });
```

### 4. Tenant Scope Middleware

**Location**: `app/Http/Middleware/TenantScopeMiddleware.php`

**Purpose**: Alternative middleware for tenant scoping (similar to TenantIsolationMiddleware)

**Status**: Both middleware provide similar functionality - standardize on one

---

## Tenant-Aware Models

### Models That MUST Be Tenant-Aware

These models belong to tenants and MUST use `BelongsToTenant` trait:

- `Project` - Projects belong to tenants
- `Task` - Tasks belong to tenants
- `Document` - Documents belong to tenants
- `Template` - Templates belong to tenants
- `TemplateSet` - Template sets belong to tenants
- `CalendarEvent` - Calendar events belong to tenants
- `Team` - Teams belong to tenants
- `Client` - Clients belong to tenants
- `Quote` - Quotes belong to tenants
- `TaskComment` - Task comments belong to tenants
- `TaskAttachment` - Task attachments belong to tenants
- `Subtask` - Subtasks belong to tenants
- `TaskAssignment` - Task assignments belong to tenants
- `Invitation` - Invitations belong to tenants
- `ChangeRequest` - Change requests belong to tenants
- `Notification` - Notifications belong to tenants
- `Outbox` - Outbox messages belong to tenants
- `IdempotencyKey` - Idempotency keys belong to tenants

### System-Global Models (NOT Tenant-Aware)

These models are system-wide and do NOT have `tenant_id`:

- `Tenant` - Tenant management (system-wide)
- `Role` - Roles (system-wide, but may have tenant_id for tenant-specific roles)
- `Permission` - Permissions (system-wide)
- `User` - Users (special case: has `tenant_id` for assignment but not tenant-scoped)

---

## Database Schema

### Tenant-Aware Tables

All tenant-aware tables MUST have:
- `tenant_id` column (ULID, nullable for system models)
- Foreign key constraint: `FOREIGN KEY (tenant_id) REFERENCES tenants(id)`
- Index: `INDEX (tenant_id)` or composite index `INDEX (tenant_id, other_key)`

**Example**:
```sql
CREATE TABLE projects (
    id VARCHAR(255) PRIMARY KEY,
    tenant_id VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    -- other columns
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_tenant_status (tenant_id, status)
);
```

---

## Query Behavior

### Automatic Filtering

All queries are automatically filtered by `tenant_id`:

```php
// This query automatically adds WHERE tenant_id = ?
$projects = Project::all();

// Equivalent to:
// SELECT * FROM projects WHERE tenant_id = ? AND deleted_at IS NULL
```

### Explicit Tenant Filtering

You can explicitly filter by tenant:

```php
// Filter for specific tenant
$projects = Project::forTenant($tenantId)->get();

// Filter for current tenant
$projects = Project::forCurrentTenant()->get();
```

### Bypassing Tenant Scope (Super Admin Only)

**⚠️ Use with extreme caution - all usage is logged**

```php
// Only super-admin can bypass tenant scope
$allProjects = Project::withoutTenantScope()->get();

// This will throw AuthorizationException if user is not super-admin
```

---

## Security Guarantees

### 1. Query-Level Isolation

Even if middleware is bypassed, Global Scopes ensure tenant filtering:

```php
// Even if middleware fails, Global Scope still applies
$projects = Project::all(); // Still filtered by tenant_id
```

### 2. Cross-Tenant Access Prevention

Users from Tenant A **cannot** access data from Tenant B:

```php
// User from Tenant A tries to access Tenant B's project
$project = Project::find($tenantBProjectId);

// Result: null (not found) - NOT an empty array
// This is important: 404 vs empty list
```

### 3. Super Admin Access

Super admins can access all tenants but:
- All access is logged for audit trail
- Must explicitly use `withoutTenantScope()` to bypass
- Regular queries still respect tenant scope

---

## Testing Tenant Isolation

### Compliance Test

Run `tests/Unit/TenantIsolationComplianceTest.php` to verify:
- All tenant-aware models have `tenant_id` column
- All tenant-aware models use `BelongsToTenant` trait
- Global scopes are properly applied

### Integration Test Example

```php
public function test_user_cannot_access_other_tenant_data(): void
{
    // Create two tenants
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    
    // Create users
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
    
    // Create projects
    $projectA = Project::factory()->create(['tenant_id' => $tenantA->id]);
    $projectB = Project::factory()->create(['tenant_id' => $tenantB->id]);
    
    // User A can only see Tenant A's project
    $this->actingAs($userA);
    $this->assertNotNull(Project::find($projectA->id));
    $this->assertNull(Project::find($projectB->id)); // 404, not found
    
    // User B can only see Tenant B's project
    $this->actingAs($userB);
    $this->assertNull(Project::find($projectA->id)); // 404, not found
    $this->assertNotNull(Project::find($projectB->id));
}
```

---

## Common Patterns

### Creating Tenant-Aware Models

```php
// tenant_id is automatically set from current context
$project = Project::create([
    'name' => 'New Project',
    // tenant_id is automatically set
]);

// Or explicitly set
$project = Project::create([
    'tenant_id' => $tenantId,
    'name' => 'New Project',
]);
```

### Querying Across Tenants (Super Admin Only)

```php
// Super admin can query all tenants
if ($user->isSuperAdmin()) {
    $allProjects = Project::withoutTenantScope()->get();
}
```

### Checking Tenant Ownership

```php
$project = Project::find($id);

if ($project->belongsToTenant($currentTenantId)) {
    // Project belongs to current tenant
}
```

---

## Troubleshooting

### Issue: "Tenant scope applied without tenant context"

**Cause**: Model uses `BelongsToTenant` but no tenant context is set

**Solution**:
1. Ensure middleware is applied: `TenantIsolationMiddleware` or `TenantScopeMiddleware`
2. Ensure user has `tenant_id` set
3. Check that route has `ability:tenant` middleware

### Issue: "User cannot see their own data"

**Cause**: Tenant context not set correctly

**Solution**:
1. Verify middleware is in route group
2. Check user's `tenant_id` is set
3. Verify model uses `BelongsToTenant` trait

### Issue: "Super admin cannot access all tenants"

**Cause**: Super admin check not working

**Solution**:
1. Verify user has `isSuperAdmin()` method or `is_admin` property
2. Use `withoutTenantScope()` explicitly
3. Check audit logs for bypass attempts

---

## Migration Guide

### Migrating from TenantScope to BelongsToTenant

1. **Update Trait Import**:
   ```php
   // Old
   use App\Traits\TenantScope;
   
   // New
   use App\Models\Concerns\BelongsToTenant;
   ```

2. **Update Usage**:
   ```php
   // Old
   class Project extends Model
   {
       use TenantScope;
   }
   
   // New
   class Project extends Model
   {
       use BelongsToTenant;
   }
   ```

3. **Verify Behavior**: Run tests to ensure same behavior

---

## Best Practices

1. **Always Use BelongsToTenant**: For all tenant-aware models
2. **Never Bypass Without Reason**: Only super-admin should bypass tenant scope
3. **Test Cross-Tenant Access**: Verify users cannot access other tenants' data
4. **Log Bypass Usage**: All tenant scope bypasses are logged
5. **Use Composite Indexes**: Index `(tenant_id, other_key)` for performance
6. **Fail-Safe Design**: Missing tenant context should log warnings, not fail silently

---

## References

- [Architecture Overview](ARCHITECTURE_OVERVIEW.md)
- [Tenant Isolation Compliance Test](../../tests/Unit/TenantIsolationComplianceTest.php)
- [BelongsToTenant Trait](../../app/Models/Concerns/BelongsToTenant.php)
- [Tenant Isolation Middleware](../../app/Http/Middleware/TenantIsolationMiddleware.php)

---

*This document should be updated whenever tenant isolation implementation changes.*

