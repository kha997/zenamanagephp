# Admin API Endpoints Documentation

## 🔌 ADMIN API REFERENCE

**Version**: 1.0  
**Last Updated**: 2025-01-XX  
**Base URL**: `/admin/*` (Web routes) and `/api/v1/*` (API routes)

---

## 🎯 TABLE OF CONTENTS

1. [Overview](#overview)
2. [Authentication & Authorization](#authentication--authorization)
3. [Admin Roles](#admin-roles)
4. [Endpoints](#endpoints)
5. [Error Handling](#error-handling)
6. [Examples](#examples)

---

## 📋 OVERVIEW

The Admin API provides endpoints for system-wide administration and governance. It is separated from the application API (`/app/*`) which handles daily operational tasks.

### Architecture Principles

- **`/app/*` = Execution**: Daily operational tasks (projects, tasks, documents)
- **`/admin/*` = Governance**: System configuration, templates, audit, analytics

### Technology Stack

- **Frontend**: Blade Templates (server-side rendered)
- **Backend**: Laravel Controllers
- **Authentication**: Session-based (web) or Sanctum tokens (API)

---

## 🔐 AUTHENTICATION & AUTHORIZATION

### Web Routes (`/admin/*`)

**Authentication**: Session-based authentication  
**Middleware**: `auth`, `EnsureAdminAccess` or `EnsureSystemAdmin`

```http
GET /admin/dashboard
Cookie: zenamanage_session=your-session-token
```

### API Routes (`/api/v1/*`)

**Authentication**: Sanctum Bearer Token  
**Middleware**: `auth:sanctum`, `ability:tenant` or `ability:admin`

```http
GET /api/v1/me/nav
Authorization: Bearer your-sanctum-token
```

---

## 👥 ADMIN ROLES

### Super Admin

- **Scope**: System-wide (all tenants)
- **Permissions**: `admin.access` (all permissions)
- **Access**: All `/admin/*` routes including system-only routes

### Org Admin (Organization Admin)

- **Scope**: Tenant-scoped (single tenant)
- **Permissions**: `admin.access.tenant` + specific permissions
- **Access**: Tenant-admin routes only (excludes system-only routes)

### Permissions Matrix

| Permission | Super Admin | Org Admin | Description |
|------------|-------------|-----------|-------------|
| `admin.access` | ✅ | ❌ | Full system access |
| `admin.access.tenant` | ✅ | ✅ | Tenant-scoped admin access |
| `admin.templates.manage` | ✅ | ✅ | Manage WBS templates |
| `admin.projects.read` | ✅ | ✅ | Read projects portfolio |
| `admin.projects.force_ops` | ✅ | ✅ | Force operations (freeze, archive) |
| `admin.settings.tenant` | ✅ | ✅ | Manage tenant settings |
| `admin.analytics.tenant` | ✅ | ✅ | View tenant analytics |
| `admin.activities.tenant` | ✅ | ✅ | View tenant audit log |
| `admin.members.manage` | ❌ | ✅ | Manage tenant members (invite, kick, change role) |

---

## 📡 ENDPOINTS

### Navigation API

#### GET `/api/v1/me/nav`

Get navigation menu items filtered by user permissions.

**Authentication**: Required (Sanctum token)  
**Authorization**: `ability:tenant`

**Response**:

```json
{
  "navigation": [
    {
      "path": "/app/dashboard",
      "label": "Dashboard",
      "icon": "Gauge",
      "perm": "view_dashboard"
    },
    {
      "path": "/admin/dashboard",
      "label": "Admin Dashboard",
      "icon": "Shield",
      "admin": true,
      "perm": "admin.access"
    },
    {
      "path": "/admin/users",
      "label": "Users",
      "icon": "Users",
      "admin": true,
      "system_only": true,
      "perm": "admin.access"
    }
  ],
  "role": "super_admin",
  "permissions": ["*"],
  "admin_access": {
    "is_super_admin": true,
    "is_org_admin": false
  }
}
```

**Notes**:
- `system_only: true` items are only visible to Super Admin
- Org Admin sees tenant-admin items only
- Regular users see no admin items

---

### Admin Dashboard API

#### GET `/api/admin/dashboard/summary`

Get system-wide dashboard statistics and overview.

**Authentication**: Required (Sanctum token)  
**Authorization**: `ability:admin` (Super Admin only)  
**Permissions**: `admin.access`

**Response**:

```json
{
  "success": true,
  "data": {
    "stats": {
      "total_users": 150,
      "total_projects": 45,
      "total_tasks": 320,
      "active_sessions": 25
    },
    "recent_activities": [
      {
        "id": "user_123",
        "type": "user",
        "action": "registered",
        "description": "User John Doe (john@example.com) registered",
        "timestamp": "2025-01-15T10:30:00Z",
        "user": {
          "id": "123",
          "name": "John Doe"
        }
      },
      {
        "id": "project_456",
        "type": "project",
        "action": "created",
        "description": "Project 'Website Redesign' was created",
        "timestamp": "2025-01-15T09:15:00Z"
      }
    ],
    "system_health": "good"
  }
}
```

**Response Fields**:
- `stats.total_users`: Total number of users across all tenants
- `stats.total_projects`: Total number of projects system-wide
- `stats.total_tasks`: Total number of tasks system-wide
- `stats.active_sessions`: Number of active user sessions (last 30 minutes)
- `recent_activities`: Array of recent system-wide activities (user registrations, tenant creations, project creations)
- `system_health`: System health status (`good`, `warning`, or `critical`)

**System Health Calculation**:
- `good`: All systems operational (health score ≥ 90)
- `warning`: Degraded performance (health score 70-89)
- `critical`: System issues detected (health score < 70)

**Notes**:
- All statistics are system-wide (not tenant-scoped)
- Data is cached for 60 seconds to improve performance
- Recent activities are limited to 10 most recent items
- System health is calculated based on database connectivity, cache status, and error rates

---

### Projects Portfolio

#### GET `/admin/projects`

List all projects (read-only portfolio view).

**Authentication**: Required (Session)  
**Authorization**: `EnsureAdminAccess`  
**Permissions**: `admin.projects.read`

**Query Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `tenant_id` | integer | No | Filter by tenant (Super Admin only) |
| `status` | string | No | Filter by status |
| `page` | integer | No | Page number |
| `per_page` | integer | No | Items per page |

**Response**:

```json
{
  "projects": [
    {
      "id": 1,
      "name": "Project Alpha",
      "code": "PRJ-001",
      "status": "active",
      "tenant_id": 1,
      "tenant_name": "Acme Corp",
      "created_at": "2025-01-01T00:00:00Z"
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 100
    }
  }
}
```

**Tenant Scoping**:
- **Super Admin**: Sees all projects (can filter by `tenant_id`)
- **Org Admin**: Sees only projects from their tenant

---

#### POST `/admin/projects/{id}/freeze`

Freeze a project (force action).

**Authentication**: Required (Session)  
**Authorization**: `EnsureAdminAccess`  
**Permissions**: `admin.projects.force_ops`

**Request Body**:

```json
{
  "reason": "Emergency freeze due to compliance issue"
}
```

**Response**:

```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "frozen",
    "frozen_at": "2025-01-08T10:30:00Z",
    "frozen_reason": "Emergency freeze due to compliance issue"
  }
}
```

**Tenant Scoping**:
- **Super Admin**: Can freeze any project
- **Org Admin**: Can only freeze projects from their tenant

---

#### POST `/admin/projects/{id}/archive`

Archive a project (force action).

**Authentication**: Required (Session)  
**Authorization**: `EnsureAdminAccess`  
**Permissions**: `admin.projects.force_ops`

**Request Body**:

```json
{
  "reason": "Project completed and archived"
}
```

---

#### POST `/admin/projects/{id}/emergency-suspend`

Emergency suspend a project (force action).

**Authentication**: Required (Session)  
**Authorization**: `EnsureAdminAccess`  
**Permissions**: `admin.projects.force_ops`

---

### Templates Management

#### GET `/admin/templates`

List WBS template sets.

**Authentication**: Required (Session)  
**Authorization**: `EnsureAdminAccess`  
**Permissions**: `admin.templates.manage`

**Response**:

```json
{
  "templates": [
    {
      "id": 1,
      "code": "GLOBAL-BUILDING",
      "name": "Global Building Template",
      "version": "1.0",
      "is_global": true,
      "tenant_id": null,
      "created_at": "2025-01-01T00:00:00Z"
    },
    {
      "id": 2,
      "code": "TENANT-CUSTOM",
      "name": "Custom Template",
      "version": "1.0",
      "is_global": false,
      "tenant_id": 1,
      "created_at": "2025-01-01T00:00:00Z"
    }
  ]
}
```

**Tenant Scoping**:
- **Super Admin**: Sees all templates (global + tenant-specific)
- **Org Admin**: Sees global templates + templates from their tenant

---

#### POST `/admin/templates`

Create a new template set.

**Authentication**: Required (Session)  
**Authorization**: `EnsureAdminAccess`  
**Permissions**: `admin.templates.manage`

**Request Body**:

```json
{
  "code": "TEMPLATE-001",
  "name": "New Template",
  "version": "1.0",
  "tenant_id": 1,
  "is_global": false
}
```

**Rules**:
- **Super Admin**: Can create global templates (`tenant_id: null`, `is_global: true`)
- **Org Admin**: Can only create tenant-specific templates (`tenant_id: <their_tenant_id>`, `is_global: false`)

---

### Analytics

#### GET `/admin/analytics`

Get analytics dashboard data.

**Authentication**: Required (Session)  
**Authorization**: `EnsureAdminAccess`  
**Permissions**: `admin.analytics.tenant`

**Query Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `start_date` | date | No | Start date filter |
| `end_date` | date | No | End date filter |
| `tenant_id` | integer | No | Filter by tenant (Super Admin only) |

**Response**:

```json
{
  "kpis": {
    "total_projects": 100,
    "active_projects": 75,
    "total_tasks": 500,
    "completed_tasks": 300,
    "total_users": 50,
    "revenue": 1000000.00
  },
  "project_status_distribution": {
    "active": 75,
    "on_hold": 10,
    "completed": 10,
    "cancelled": 5
  },
  "task_trends": [
    {
      "date": "2025-01-01",
      "total": 500,
      "completed": 300
    }
  ],
  "user_activity": 45
}
```

**Tenant Scoping**:
- **Super Admin**: Can view analytics for any tenant (can filter by `tenant_id`)
- **Org Admin**: Sees only analytics for their tenant

---

### Activities (Audit Log)

#### GET `/admin/activities`

Get audit log entries.

**Authentication**: Required (Session)  
**Authorization**: `EnsureAdminAccess`  
**Permissions**: `admin.activities.tenant`

**Query Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `user_id` | integer | No | Filter by user |
| `action` | string | No | Filter by action |
| `entity_type` | string | No | Filter by entity type |
| `start_date` | date | No | Start date filter |
| `end_date` | date | No | End date filter |
| `page` | integer | No | Page number |
| `per_page` | integer | No | Items per page |

**Response**:

```json
{
  "activities": [
    {
      "id": 1,
      "user_id": 1,
      "user_name": "John Doe",
      "action": "project.created",
      "entity_type": "Project",
      "entity_id": 1,
      "project_id": 1,
      "project_name": "Project Alpha",
      "ip_address": "192.168.1.1",
      "created_at": "2025-01-08T10:30:00Z"
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 1000
    }
  }
}
```

**Tenant Scoping**:
- **Super Admin**: Sees all activities (can filter by `tenant_id`)
- **Org Admin**: Sees only activities from their tenant

---

### Settings

#### GET `/admin/settings`

Get admin settings page.

**Authentication**: Required (Session)  
**Authorization**: `EnsureAdminAccess`  
**Permissions**: `admin.settings.tenant`

**Response**: HTML page with tabs:
- **System Settings** (Super Admin only)
- **Tenant Settings** (Super Admin + Org Admin)

---

#### POST `/admin/settings/system`

Update system settings.

**Authentication**: Required (Session)  
**Authorization**: `EnsureSystemAdmin` (Super Admin only)  
**Permissions**: `admin.access`

**Request Body**:

```json
{
  "feature_flags": {
    "enable_wbs_templates": true,
    "enable_kanban": true
  },
  "system_limits": {
    "max_projects_per_tenant": 100,
    "max_users_per_tenant": 50
  },
  "maintenance_mode": false
}
```

**Response**:

```json
{
  "success": true,
  "data": {
    "message": "System settings updated successfully"
  }
}
```

---

#### POST `/admin/settings/tenant`

Update tenant settings.

**Authentication**: Required (Session)  
**Authorization**: `EnsureAdminAccess`  
**Permissions**: `admin.settings.tenant`

**Request Body**:

```json
{
  "tenant_id": 1,
  "branding": {
    "company_name": "Acme Corp",
    "logo_url": "https://example.com/logo.png"
  },
  "document_numbering": {
    "format": "{PREFIX}-{YEAR}-{NUMBER}",
    "starting_number": 1
  },
  "sla_settings": {
    "response_time": 24,
    "resolution_time": 72
  },
  "i18n": {
    "default_language": "en",
    "default_timezone": "UTC"
  }
}
```

**Tenant Scoping**:
- **Super Admin**: Can update any tenant's settings
- **Org Admin**: Can only update their own tenant's settings

---

### System Users Management (System-wide)

#### GET `/admin/users`

List all users across all tenants (system-wide view).

**Authentication**: Required (Session)  
**Authorization**: `EnsureSystemAdmin` (Super Admin only)  
**Permissions**: `admin.access`

**Query Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `tenant_id` | integer | No | Filter by tenant (optional) |
| `search` | string | No | Search by name or email |
| `role` | string | No | Filter by role |
| `status` | string | No | Filter by status (active/inactive) |
| `sort_by` | string | No | Sort field (default: created_at) |
| `sort_direction` | string | No | Sort direction (asc/desc, default: desc) |
| `page` | integer | No | Page number |
| `per_page` | integer | No | Items per page (max 100) |

**Response**:

```json
{
  "success": true,
  "data": {
    "users": [
      {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "member",
        "is_active": true,
        "tenant_id": 1,
        "tenant": {
          "id": 1,
          "name": "Acme Corp"
        },
        "last_login_at": "2025-01-08T10:30:00Z",
        "created_at": "2025-01-01T00:00:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 20,
      "total": 100
    },
    "filters": {},
    "tenants": [
      {"id": 1, "name": "Acme Corp"},
      {"id": 2, "name": "TechCorp"}
    ]
  }
}
```

**Notes**:
- System-wide: Returns users from all tenants
- Super Admin can optionally filter by `tenant_id`
- Org Admin cannot access this endpoint (403 with suggestion to use `/admin/members`)

---

#### GET `/api/v1/admin/users`

API endpoint for system-wide user management.

**Authentication**: Required (Sanctum token)  
**Authorization**: `ability:admin` (Super Admin only)

**Query Parameters**: Same as web route above

**Response**: Same JSON structure as web route

---

### Tenant Members Management (Tenant-scoped)

#### GET `/admin/members`

List members within the current tenant (tenant-scoped view).

**Authentication**: Required (Session)  
**Authorization**: `EnsureAdminAccess`  
**Permissions**: `admin.members.manage` (Org Admin only)

**Query Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `search` | string | No | Search by name or email |
| `role` | string | No | Filter by role (tenant roles only) |
| `status` | string | No | Filter by status (active/inactive) |
| `sort_by` | string | No | Sort field (default: created_at) |
| `sort_direction` | string | No | Sort direction (asc/desc, default: desc) |
| `page` | integer | No | Page number |
| `per_page` | integer | No | Items per page (max 100) |

**Response**:

```json
{
  "success": true,
  "data": {
    "users": [
      {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "member",
        "is_active": true,
        "tenant_id": 1,
        "last_login_at": "2025-01-08T10:30:00Z",
        "created_at": "2025-01-01T00:00:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 2,
      "per_page": 20,
      "total": 35
    },
    "filters": {}
  }
}
```

**Notes**:
- Tenant-scoped: Only returns users from the current tenant
- Org Admin can invite, change roles, and remove members
- Super Admin cannot access this endpoint (403)

---

#### GET `/api/v1/admin/members`

API endpoint for tenant-scoped member management.

**Authentication**: Required (Sanctum token)  
**Authorization**: `ability:tenant`, `can:admin.members.manage` (Org Admin only)

**Query Parameters**: Same as web route above

**Response**: Same JSON structure as web route

---

#### POST `/api/v1/admin/members/invite`

Invite a new member to the tenant.

**Authentication**: Required (Sanctum token)  
**Authorization**: `ability:tenant`, `can:admin.members.manage`

**Request Body**:

```json
{
  "first_name": "Jane",
  "last_name": "Doe",
  "email": "jane@example.com",
  "role": "member",
  "is_active": true
}
```

**Response**:

```json
{
  "success": true,
  "data": {
    "message": "Member invitation sent successfully",
    "user": {
      "id": 123,
      "name": "Jane Doe",
      "email": "jane@example.com",
      "role": "member",
      "tenant_id": 1
    }
  }
}
```

---

#### PATCH `/api/v1/admin/members/{id}/role`

Update a member's role within the tenant.

**Authentication**: Required (Sanctum token)  
**Authorization**: `ability:tenant`, `can:admin.members.manage`

**Request Body**:

```json
{
  "role": "project_manager"
}
```

**Response**:

```json
{
  "success": true,
  "data": {
    "message": "Member role updated successfully",
    "user": {
      "id": 123,
      "role": "project_manager"
    }
  }
}
```

---

#### DELETE `/api/v1/admin/members/{id}`

Remove a member from the tenant.

**Authentication**: Required (Sanctum token)  
**Authorization**: `ability:tenant`, `can:admin.members.manage`

**Response**:

```json
{
  "success": true,
  "data": {
    "message": "Member removed successfully"
  }
}
```

**Notes**:
- Cannot remove self
- Cannot remove members from other tenants
- Org Admin only

---

## ⚠️ ERROR HANDLING

### Error Response Format

```json
{
  "success": false,
  "error": {
    "id": "err_abc123",
    "code": "E403.FORBIDDEN",
    "message": "You do not have permission to access this resource",
    "details": {
      "required_permission": "admin.access",
      "user_permissions": ["admin.access.tenant"]
    },
    "timestamp": "2025-01-08T10:30:00Z"
  }
}
```

### Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `E401.UNAUTHORIZED` | 401 | User not authenticated |
| `E403.FORBIDDEN` | 403 | User lacks required permission |
| `E404.NOT_FOUND` | 404 | Resource not found (may be tenant-scoped) |
| `E422.VALIDATION_ERROR` | 422 | Validation failed |
| `E500.INTERNAL_ERROR` | 500 | Internal server error |

---

## 📝 EXAMPLES

### Example 1: Super Admin Accessing All Projects

```bash
curl -X GET "https://zenamanage.com/admin/projects" \
  -H "Cookie: zenamanage_session=your-session-token"
```

### Example 2: Org Admin Freezing Own Tenant Project

```bash
curl -X POST "https://zenamanage.com/admin/projects/1/freeze" \
  -H "Cookie: zenamanage_session=your-session-token" \
  -H "Content-Type: application/json" \
  -d '{"reason": "Compliance issue"}'
```

### Example 3: Getting Navigation Menu (API)

```bash
curl -X GET "https://zenamanage.com/api/v1/me/nav" \
  -H "Authorization: Bearer your-sanctum-token"
```

---

## 🔒 SECURITY NOTES

1. **Tenant Isolation**: All queries automatically filter by `tenant_id` for Org Admin
2. **Permission Checks**: Every endpoint checks permissions via Policies
3. **Audit Logging**: All admin actions are logged in the audit log
4. **CSRF Protection**: Web routes require CSRF tokens
5. **Rate Limiting**: API routes are rate-limited

---

**Last Updated**: 2025-01-XX  
**Maintained By**: ZenaManage Development Team

