# RBAC Overview

**Version**: 1.0  
**Last Updated**: January 2025  
**Status**: Active  
**Purpose**: Comprehensive guide to ZenaManage's Role-Based Access Control (RBAC) system

---

## Overview

ZenaManage implements a comprehensive RBAC system that controls access to features and actions based on user roles. Permissions are defined in `config/permissions.php` and enforced at multiple layers: middleware, controllers, and policies.

---

## Core Concepts

### Roles

Roles define a set of permissions that users can have. ZenaManage has the following roles:

1. **super_admin** - System-wide administrator with all permissions
2. **org_admin** - Organization/tenant administrator (tenant-scoped)
3. **admin** - General administrator (can be system-wide or tenant-scoped)
4. **pm** - Project Manager
5. **member** - Team member
6. **client** - Client user (read-only access)

### Permissions

Permissions follow the pattern: `{resource}.{action}`

**Examples**:
- `projects.view` - View projects
- `projects.create` - Create projects
- `tasks.update` - Update tasks
- `admin.access.tenant` - Access tenant admin features

### Permission Groups

Related permissions are grouped for easier management:

- `user_management` - User CRUD operations
- `project_management` - Project operations
- `task_management` - Task operations
- `document_management` - Document operations
- `client_management` - Client operations
- `quote_management` - Quote operations
- `template_management` - Template operations
- `analytics` - Dashboard and analytics
- `notifications` - Notification management
- `settings` - System settings
- `admin_tenant_management` - Tenant admin features

---

## Role Permissions Matrix

### super_admin

**Permissions**: `*` (All permissions)

**Access Level**: System-wide

**Use Case**: System administrators who manage the entire platform

**Key Capabilities**:
- Access all tenants
- Manage system settings
- View system-wide analytics
- Manage all users across tenants
- Bypass tenant isolation (with audit logging)

---

### org_admin (Tenant Admin)

**Permissions**:
- `admin.access.tenant` - Access tenant admin panel
- `admin.members.manage` - Manage tenant members
- `admin.templates.manage` - Manage templates
- `admin.projects.read` - Read all projects (read-only)
- `admin.projects.force_ops` - Force operations on projects
- `admin.settings.tenant` - Manage tenant settings
- `admin.analytics.tenant` - View tenant analytics
- `admin.activities.tenant` - View tenant audit log

**Access Level**: Tenant-scoped

**Use Case**: Organization administrators who manage their tenant

**Key Capabilities**:
- Manage tenant members and users
- View all projects (read-only)
- Manage tenant settings
- View tenant analytics and audit logs
- Manage templates

**Example**: "Tenant Admin can view all projects but cannot delete them (unless they have `admin.projects.force_ops`)"

---

### admin

**Permissions**: Full CRUD on most resources

**Access Level**: Can be system-wide or tenant-scoped

**Use Case**: General administrators

**Key Capabilities**:
- **Users**: Full CRUD, manage roles and permissions
- **Tenants**: View, update, manage settings, users, billing, analytics
- **Projects**: Full CRUD, manage members, settings
- **Tasks**: Full CRUD, assign, manage assignees
- **Documents**: Full CRUD, share, manage permissions
- **Clients**: Full CRUD, manage lifecycle
- **Quotes**: Full CRUD, send, approve, reject
- **Templates**: Full CRUD, share
- **Dashboard & Analytics**: View dashboard, analytics, generate/export reports
- **Notifications**: Full CRUD
- **Settings**: View, update, manage integrations

**Example**: "Admin can create projects, assign tasks, and manage team members"

---

### pm (Project Manager)

**Permissions**: Project and task management focused

**Access Level**: Tenant-scoped

**Use Case**: Project managers who oversee projects and tasks

**Key Capabilities**:
- **Users**: View only
- **Projects**: View, create, update, manage members and settings
- **Tasks**: View, create, update, assign, manage assignees
- **Documents**: View, create, update, share
- **Clients**: View, create, update, manage lifecycle
- **Quotes**: View, create, update, send
- **Templates**: View, create, update
- **Dashboard & Analytics**: View dashboard, analytics, generate reports
- **Notifications**: View, create, update
- **Settings**: View only

**Limitations**:
- Cannot delete projects or tasks
- Cannot manage user roles
- Cannot access tenant admin features
- Cannot manage system integrations

**Example**: "PM can create tasks but cannot delete projects"

---

### member

**Permissions**: Limited to assigned work

**Access Level**: Tenant-scoped

**Use Case**: Team members who work on assigned projects and tasks

**Key Capabilities**:
- **Users**: View own profile only
- **Projects**: View, update (only assigned projects)
- **Tasks**: View, create, update (only assigned tasks), assign (to other members)
- **Documents**: View, create, update (only own documents)
- **Clients**: View only
- **Quotes**: View only
- **Templates**: View only
- **Dashboard & Analytics**: View dashboard, limited analytics
- **Notifications**: View, create
- **Settings**: View own settings only

**Limitations**:
- Cannot delete projects or tasks
- Cannot manage project members
- Cannot create quotes
- Cannot manage templates
- Cannot access admin features

**Example**: "Member can update assigned tasks but cannot delete projects"

---

### client

**Permissions**: Read-only access to assigned resources

**Access Level**: Tenant-scoped

**Use Case**: Client users who need to view project progress

**Key Capabilities**:
- **Users**: View own profile only
- **Projects**: View only (only assigned projects)
- **Tasks**: View only (only assigned tasks)
- **Documents**: View only (only shared documents)
- **Clients**: View and update own client record
- **Quotes**: View and update own quotes (limited fields)
- **Dashboard**: View dashboard only
- **Notifications**: View only
- **Settings**: View own settings only

**Limitations**:
- Cannot create or modify projects
- Cannot create or modify tasks
- Cannot upload documents
- Cannot create quotes
- Cannot access analytics or reports

**Example**: "Client can view assigned projects and tasks but cannot create new ones"

---

## Common Use Cases

### Use Case 1: Tenant Admin Managing Organization

**Scenario**: Tenant Admin wants to:
- View all projects in the organization
- Manage team members
- View analytics

**Permissions Needed**:
- `admin.access.tenant` ✅
- `admin.members.manage` ✅
- `admin.projects.read` ✅
- `admin.analytics.tenant` ✅

**Result**: ✅ Allowed - Tenant Admin has all required permissions

---

### Use Case 2: PM Creating a Task

**Scenario**: PM wants to:
- Create a new task in a project
- Assign it to a team member

**Permissions Needed**:
- `tasks.create` ✅
- `tasks.assign` ✅

**Result**: ✅ Allowed - PM has both permissions

---

### Use Case 3: PM Deleting a Project

**Scenario**: PM wants to delete a project

**Permissions Needed**:
- `projects.delete` ❌

**Result**: ❌ Denied - PM does not have `projects.delete` permission

**Note**: Only `admin` role has `projects.delete` permission. PM can only view, create, and update projects.

---

### Use Case 4: Member Updating Assigned Task

**Scenario**: Member wants to:
- Update a task assigned to them
- Add a comment

**Permissions Needed**:
- `tasks.update` ✅ (for assigned tasks)
- `tasks.create` ✅ (for comments)

**Result**: ✅ Allowed - Member can update assigned tasks and create comments

---

### Use Case 5: Member Deleting a Project

**Scenario**: Member wants to delete a project

**Permissions Needed**:
- `projects.delete` ❌

**Result**: ❌ Denied - Member does not have `projects.delete` permission

**Note**: Members can only view and update (assigned) projects, not delete them.

---

### Use Case 6: Client Viewing Project Progress

**Scenario**: Client wants to:
- View assigned projects
- View project tasks
- View shared documents

**Permissions Needed**:
- `projects.view` ✅ (for assigned projects)
- `tasks.view` ✅ (for assigned tasks)
- `documents.view` ✅ (for shared documents)

**Result**: ✅ Allowed - Client has read-only access to assigned resources

---

## Permission Checking

### In Controllers

```php
use Illuminate\Support\Facades\Gate;

public function create(Request $request)
{
    // Check permission using Gate
    if (!Gate::allows('projects.create')) {
        abort(403, 'You do not have permission to create projects');
    }
    
    // Or use authorize() helper
    $this->authorize('projects.create');
    
    // Create project...
}
```

### In Middleware

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'ability:tenant'])
    ->group(function () {
        Route::post('/projects', [ProjectController::class, 'create'])
            ->middleware('can:projects.create');
    });
```

### Using PermissionService

```php
use App\Services\PermissionService;

$permissionService = app(PermissionService::class);

if ($permissionService->canUserCreateProjects($userId, $tenantId)) {
    // Create project...
}
```

### Using Laravel Policies

```php
// app/Policies/ProjectPolicy.php
public function create(User $user): bool
{
    return $user->can('projects.create');
}
```

---

## Permission Hierarchy

### Role Hierarchy

Roles have a hierarchy where higher roles inherit permissions from lower roles:

```
super_admin
  └── admin
      └── pm (project_manager)
          └── member
              └── client
```

**Example**: `admin` role inherits permissions from `pm`, `member`, and `client` roles.

### Wildcard Permissions

Some permissions use wildcards:

- `*` - All permissions (super_admin only)
- `projects.*` - All project permissions
- `tasks.*` - All task permissions

**Example**: If a role has `projects.*`, it automatically has `projects.view`, `projects.create`, `projects.update`, `projects.delete`, etc.

---

## Default Permissions

All authenticated users have these default permissions:

- `users.view` - View own profile
- `dashboard.view` - View dashboard
- `notifications.view` - View notifications
- `settings.view` - View own settings

These permissions are granted regardless of role.

---

## Permission Synchronization

Permissions are synchronized from `config/permissions.php` to the database using `RBACSyncService`:

```php
use App\Services\RBACSyncService;

$syncService = app(RBACSyncService::class);
$syncService->syncPermissions();
```

This ensures that:
1. All permissions in config are in the database
2. Roles have correct permissions assigned
3. Database stays in sync with config

---

## Best Practices

1. **Check Permissions Early**: Check permissions in middleware or controller entry points
2. **Use Policies**: Prefer Laravel Policies for resource-based authorization
3. **Cache Permissions**: Permission checks are cached for performance
4. **Audit Logging**: All permission checks are logged for audit trail
5. **Fail Secure**: Default to denying access if permission check fails
6. **Use Gate/Policy**: Prefer Laravel's Gate/Policy system over manual checks

---

## Troubleshooting

### Issue: "User cannot perform action despite having permission"

**Possible Causes**:
1. Permission not synced to database
2. User role not assigned correctly
3. Cache not cleared
4. Permission check in wrong place

**Solution**:
1. Run `RBACSyncService::syncPermissions()`
2. Verify user role assignment
3. Clear permission cache
4. Check permission is checked at correct layer

### Issue: "Permission check always returns false"

**Possible Causes**:
1. User not authenticated
2. Tenant context not set
3. Permission name incorrect

**Solution**:
1. Verify user is authenticated
2. Check tenant isolation middleware is applied
3. Verify permission name matches config

---

## References

- [Permissions Configuration](../../config/permissions.php)
- [PermissionService](../../app/Services/PermissionService.php)
- [RBACManager](../../app/Services/RBACManager.php)
- [RBACSyncService](../../app/Services/RBACSyncService.php)
- [Architecture Overview](ARCHITECTURE_OVERVIEW.md)

---

*This document should be updated whenever RBAC permissions or roles change.*

