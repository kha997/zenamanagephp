# Projects API Documentation

## Overview
The Projects API provides comprehensive project management functionality with multi-tenant support, role-based access control, and audit logging.

## Base URL
```
/api/app/projects
```

## Authentication
All endpoints require authentication via Laravel Sanctum token:
```
Authorization: Bearer {token}
```

## Rate Limiting
- **General**: 60 requests per minute
- **Read operations**: 100 requests per minute  
- **Write operations**: 20 requests per minute
- **Delete operations**: 10 requests per minute
- **Export operations**: 5 requests per minute
- **KPI/Analytics**: 200 requests per minute

## Endpoints

### 1. List Projects
**GET** `/api/app/projects`

Retrieve all projects for the authenticated user's tenant.

#### Query Parameters
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `page` | integer | Page number for pagination | `1` |
| `per_page` | integer | Items per page (max 100) | `20` |
| `search` | string | Search in name and description | `"urgent project"` |
| `status` | string | Filter by status | `"active"` |
| `priority` | string | Filter by priority | `"high"` |
| `owner_id` | string | Filter by owner ID | `"01h1234567890123456789012"` |
| `sort` | string | Sort field | `"name"`, `"created_at"`, `"due_date"` |
| `direction` | string | Sort direction | `"asc"`, `"desc"` |

#### Response
```json
{
  "success": true,
  "data": {
    "projects": [
      {
        "id": "01h1234567890123456789012",
        "name": "Website Redesign",
        "code": "WR-001",
        "description": "Complete website redesign project",
        "status": "active",
        "priority": "high",
        "progress_pct": 75,
        "budget_total": 50000.00,
        "start_date": "2025-01-01",
        "due_date": "2025-06-30",
        "owner": {
          "id": "01h1234567890123456789013",
          "name": "John Doe",
          "email": "john@example.com"
        },
        "tags": ["urgent", "frontend"],
        "created_at": "2025-01-01T00:00:00Z",
        "updated_at": "2025-01-15T10:30:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 45,
      "last_page": 3
    }
  }
}
```

### 2. Create Project
**POST** `/api/app/projects`

Create a new project.

#### Request Body
```json
{
  "name": "Website Redesign",
  "code": "WR-001",
  "description": "Complete website redesign project",
  "owner_id": "01h1234567890123456789013",
  "start_date": "2025-01-01",
  "due_date": "2025-06-30",
  "priority": "high",
  "budget_total": 50000.00,
  "tags": ["urgent", "frontend"]
}
```

#### Required Fields
- `name` (string): Project name

#### Optional Fields
- `code` (string): Project code (auto-generated if not provided)
- `description` (string): Project description
- `owner_id` (string): Project owner user ID
- `start_date` (date): Project start date
- `due_date` (date): Project due date
- `priority` (string): Priority level (`low`, `normal`, `high`, `urgent`)
- `budget_total` (number): Total project budget
- `tags` (array): Project tags

#### Response
```json
{
  "success": true,
  "message": "Project created successfully",
  "data": {
    "id": "01h1234567890123456789012",
    "name": "Website Redesign",
    "code": "WR-001",
    "status": "active",
    "progress_pct": 0,
    "owner": {
      "id": "01h1234567890123456789013",
      "name": "John Doe",
      "email": "john@example.com"
    },
    "created_at": "2025-01-01T00:00:00Z"
  }
}
```

### 3. Get Project
**GET** `/api/app/projects/{id}`

Retrieve a specific project by ID.

#### Path Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | string | Project ULID |

#### Response
```json
{
  "success": true,
  "data": {
    "id": "01h1234567890123456789012",
    "name": "Website Redesign",
    "code": "WR-001",
    "description": "Complete website redesign project",
    "status": "active",
    "priority": "high",
    "progress_pct": 75,
    "budget_total": 50000.00,
    "budget_planned": 45000.00,
    "budget_actual": 35000.00,
    "estimated_hours": 400.00,
    "actual_hours": 300.00,
    "start_date": "2025-01-01",
    "due_date": "2025-06-30",
    "owner": {
      "id": "01h1234567890123456789013",
      "name": "John Doe",
      "email": "john@example.com"
    },
    "tags": ["urgent", "frontend"],
    "created_at": "2025-01-01T00:00:00Z",
    "updated_at": "2025-01-15T10:30:00Z"
  }
}
```

### 4. Update Project
**PATCH** `/api/app/projects/{id}`

Update an existing project.

#### Path Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | string | Project ULID |

#### Request Body
```json
{
  "name": "Website Redesign v2",
  "description": "Updated project description",
  "priority": "urgent",
  "budget_total": 60000.00,
  "due_date": "2025-07-31",
  "tags": ["urgent", "frontend", "backend"]
}
```

#### Response
```json
{
  "success": true,
  "message": "Project updated successfully",
  "data": {
    "id": "01h1234567890123456789012",
    "name": "Website Redesign v2",
    "code": "WR-001",
    "status": "active",
    "priority": "urgent",
    "budget_total": 60000.00,
    "updated_at": "2025-01-15T10:30:00Z"
  }
}
```

### 5. Delete Project
**DELETE** `/api/app/projects/{id}`

Delete a project (soft delete).

#### Path Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | string | Project ULID |

#### Response
```json
{
  "success": true,
  "message": "Project deleted successfully"
}
```

### 6. Archive Project
**POST** `/api/app/projects/{id}/archive`

Archive a project.

#### Path Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | string | Project ULID |

#### Response
```json
{
  "success": true,
  "message": "Project archived successfully",
  "data": {
    "id": "01h1234567890123456789012",
    "status": "archived"
  }
}
```

### 7. Restore Project
**POST** `/api/app/projects/{id}/restore`

Restore an archived project.

#### Path Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | string | Project ULID |

#### Response
```json
{
  "success": true,
  "message": "Project restored successfully",
  "data": {
    "id": "01h1234567890123456789012",
    "status": "active"
  }
}
```

### 8. Get Project KPIs
**GET** `/api/app/projects/kpis`

Retrieve key performance indicators for projects.

#### Query Parameters
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `period` | string | Time period | `"30d"`, `"90d"`, `"1y"` |
| `status` | string | Filter by status | `"active"` |

#### Response
```json
{
  "success": true,
  "data": {
    "total_projects": 45,
    "active_projects": 32,
    "completed_projects": 10,
    "overdue_projects": 3,
    "total_budget": 2500000.00,
    "average_progress": 65.5,
    "projects_by_status": {
      "active": 32,
      "completed": 10,
      "archived": 3
    },
    "projects_by_priority": {
      "urgent": 5,
      "high": 15,
      "normal": 20,
      "low": 5
    }
  }
}
```

### 9. Get Project Owners
**GET** `/api/app/projects/owners`

Retrieve list of project owners.

#### Response
```json
{
  "success": true,
  "data": [
    {
      "id": "01h1234567890123456789013",
      "name": "John Doe",
      "email": "john@example.com",
      "projects_count": 5
    },
    {
      "id": "01h1234567890123456789014",
      "name": "Jane Smith",
      "email": "jane@example.com",
      "projects_count": 3
    }
  ]
}
```

### 10. Export Projects
**POST** `/api/app/projects/export`

Export projects data.

#### Request Body
```json
{
  "format": "csv",
  "filters": {
    "status": "active",
    "priority": "high"
  },
  "fields": ["name", "code", "status", "priority", "owner", "due_date"]
}
```

#### Response
```json
{
  "success": true,
  "message": "Export initiated",
  "data": {
    "export_id": "exp_123456789",
    "download_url": "/api/exports/exp_123456789/download",
    "expires_at": "2025-01-01T12:00:00Z"
  }
}
```

## Error Responses

### 401 Unauthorized
```json
{
  "error": "Authentication required",
  "message": "User must be authenticated with a valid tenant"
}
```

### 403 Forbidden
```json
{
  "error": "Access denied",
  "message": "You do not have permission to perform this action"
}
```

### 404 Not Found
```json
{
  "error": "Project not found",
  "message": "The requested project could not be found"
}
```

### 422 Validation Error
```json
{
  "error": "Validation failed",
  "message": "The given data was invalid",
  "errors": {
    "name": ["The name field is required"],
    "due_date": ["The due date must be after start date"]
  }
}
```

### 429 Too Many Requests
```json
{
  "error": "Too Many Requests",
  "message": "Rate limit exceeded. Please try again later.",
  "retry_after": 60,
  "limit": 100,
  "remaining": 0
}
```

## Status Codes

| Status | Description |
|--------|-------------|
| `active` | Project is currently active |
| `archived` | Project has been archived |
| `completed` | Project has been completed |
| `on_hold` | Project is on hold |
| `cancelled` | Project has been cancelled |
| `planning` | Project is in planning phase |

## Priority Levels

| Priority | Description |
|----------|-------------|
| `low` | Low priority |
| `normal` | Normal priority |
| `high` | High priority |
| `urgent` | Urgent priority |

## Audit Logging

All project operations are automatically logged for audit purposes. Audit logs include:
- User who performed the action
- Timestamp of the action
- IP address and user agent
- Request details
- Changes made (for updates)

## Security Features

- **Multi-tenant isolation**: Users can only access projects from their tenant
- **Role-based access control**: Permissions based on user roles
- **Rate limiting**: Prevents abuse and ensures fair usage
- **Audit logging**: Complete trail of all project operations
- **Input validation**: All inputs are validated and sanitized
- **SQL injection protection**: Parameterized queries prevent SQL injection

## Best Practices

1. **Always include authentication headers**
2. **Use appropriate HTTP methods** (GET for read, POST for create, PATCH for update, DELETE for delete)
3. **Handle rate limiting** by implementing exponential backoff
4. **Use pagination** for large datasets
5. **Cache responses** when appropriate to reduce API calls
6. **Monitor audit logs** for security and compliance
7. **Validate data** on the client side before sending requests
