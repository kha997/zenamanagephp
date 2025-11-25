# RBAC Security Audit Report

## Status: ✅ FIXED

## Summary

Audit of Role-Based Access Control (RBAC) implementation to identify security vulnerabilities and missing authorization checks.

## RBAC Architecture

### Middleware
- ✅ **AbilityMiddleware** - Handles `ability:tenant` and `ability:admin` checks
- ✅ **ProjectAccessMiddleware** - Validates project access
- ✅ **ProjectOwnershipMiddleware** - Validates project ownership
- ✅ **RBACMiddleware** - Permission-based access control
- ✅ **CheckPermission** - Permission checking middleware

### Policies
- ✅ **ProjectPolicy** - Project authorization policies
- ✅ **TaskPolicy** - Task authorization policies
- ✅ **UserPolicy** - User authorization policies
- ✅ **TenantPolicy** - Tenant authorization policies
- ✅ **And 20+ other policies**

### Roles
- `super_admin` - Full system access
- `admin` - Admin access within tenant
- `project_manager` / `pm` - Project management access
- `member` - Basic member access
- `site_engineer`, `design_lead`, `client_rep`, `qc_inspector` - Role-specific access

## Security Issues Found

### ✅ FIXED: Missing Middleware Protection

#### 1. Projects API Routes (routes/api.php lines 501-503)
**Status**: ✅ FIXED - Added middleware protection

**Fixed Code**:
```php
Route::prefix('projects')
    ->middleware(['auth:sanctum', 'ability:tenant'])  // ✅ ADDED
    ->group(function () {
        Route::get('/', [ProjectManagementController::class, 'getProjects']);
        Route::post('/', [ProjectManagementController::class, 'createProject']);
        // ... more routes
    });
```

**Protection Added**:
- ✅ `auth:sanctum` - Requires authentication
- ✅ `ability:tenant` - Requires tenant membership and valid role

#### 2. Tasks API Routes (routes/api.php lines 554-556)
**Status**: ✅ FIXED - Added middleware protection

**Fixed Code**:
```php
Route::prefix('tasks')
    ->middleware(['auth:sanctum', 'ability:tenant'])  // ✅ ADDED
    ->group(function () {
        Route::get('/', [TaskManagementController::class, 'getTasks']);
        Route::post('/', [TaskManagementController::class, 'createTask']);
        // ... more routes
    });
```

**Protection Added**:
- ✅ `auth:sanctum` - Requires authentication
- ✅ `ability:tenant` - Requires tenant membership and valid role

### ⚠️ MEDIUM: Missing Policy Checks in Controllers

#### 1. ProjectManagementController
**Status**: ⚠️ No explicit `authorize()` calls found

**Current**: Controllers rely on service layer tenant validation, but don't use Laravel Policies

**Recommendation**: Add policy checks:
```php
public function update(ProjectManagementRequest $request, Project|string $project): JsonResponse
{
    $this->authorize('update', $project);  // ADD THIS
    // ... rest of method
}
```

#### 2. TaskManagementController
**Status**: ⚠️ No explicit `authorize()` calls found

**Recommendation**: Add policy checks for sensitive operations

### ✅ GOOD: Existing Protections

#### 1. Service Layer Tenant Validation
- ✅ `ProjectManagementService::validateTenantAccess()` - Checks tenant access
- ✅ `TaskManagementService::validateTenantAccess()` - Checks tenant access
- ✅ All service methods validate tenant_id

#### 2. Policies Exist
- ✅ `ProjectPolicy` - Has view, create, update, delete, assignUsers methods
- ✅ `TaskPolicy` - Has view, create, update, delete methods
- ✅ Multi-tenant isolation checks in policies

#### 3. Some Routes Protected
- ✅ `/api/v1/app/dashboard/*` - Has `ability:tenant` middleware
- ✅ `/api/v1/app/users/*` - Has `ability:tenant` middleware
- ✅ `/api/admin/*` - Has `ability:admin` middleware
- ✅ `/api/users/*` - Has `ability:tenant` middleware

## Recommendations

### Priority CRITICAL (Fix Immediately)

1. **Add Middleware to Projects Routes**:
   ```php
   Route::prefix('projects')
       ->middleware(['auth:sanctum', 'ability:tenant'])
       ->group(function () {
           // ... all project routes
       });
   ```

2. **Add Middleware to Tasks Routes**:
   ```php
   Route::prefix('tasks')
       ->middleware(['auth:sanctum', 'ability:tenant'])
       ->group(function () {
           // ... all task routes
       });
   ```

### Priority HIGH (Should Fix)

3. **Add Policy Checks in Controllers**:
   - Add `$this->authorize('update', $project)` in update methods
   - Add `$this->authorize('delete', $project)` in delete methods
   - Add `$this->authorize('create', Project::class)` in create methods

4. **Verify All API Routes Have Middleware**:
   - Audit all routes in `routes/api.php`
   - Ensure all CRUD operations have `auth:sanctum` + `ability:tenant` or `ability:admin`

### Priority MEDIUM (Best Practice)

5. **Enhance Policy Methods**:
   - Add role-based checks in policies (not just tenant checks)
   - Add project-specific permission checks
   - Add task assignment permission checks

6. **Add Resource-Level Authorization**:
   - Check if user can access specific project
   - Check if user can modify specific task
   - Check if user has project role

## Implementation Plan

### Phase 1: Fix Critical Issues (IMMEDIATE)
1. Add middleware to projects routes
2. Add middleware to tasks routes
3. Test that unauthenticated requests are rejected
4. Test that cross-tenant access is blocked

### Phase 2: Add Policy Checks (HIGH)
1. Add `authorize()` calls in ProjectManagementController
2. Add `authorize()` calls in TaskManagementController
3. Test policy enforcement
4. Verify role-based access works

### Phase 3: Enhance Security (MEDIUM)
1. Review and enhance all policies
2. Add resource-level permission checks
3. Add audit logging for authorization failures
4. Document RBAC rules

## Testing Checklist

After fixes:
- [ ] Unauthenticated users cannot access projects/tasks endpoints
- [ ] Users from Tenant A cannot access Tenant B's projects
- [ ] Users without proper roles are denied access
- [ ] Policy checks work correctly
- [ ] Admin endpoints require admin role
- [ ] Tenant endpoints require tenant membership
- [ ] Resource-level permissions are enforced

## Notes

- Current architecture relies heavily on service-layer validation
- Policies exist but are not consistently used in controllers
- Middleware protection is missing for critical routes
- Tenant isolation is enforced at service layer, but route-level protection is better

