# API v1 Contract

**Version**: 1.0  
**Last Updated**: January 2025  
**Status**: Stable  
**Purpose**: Defines the stable API v1 contract, versioning strategy, and deprecation policy

---

## Overview

API v1 (`/api/v1/app/*`) is the **stable, production-ready API** for ZenaManage. All endpoints in this version follow strict versioning and deprecation policies to ensure backward compatibility.

---

## Base URL

```
Production: https://api.zenamanage.com/api/v1
Development: http://localhost:8000/api/v1
```

---

## Authentication

All `/api/v1/app/*` endpoints require:

1. **Bearer Token Authentication** (Laravel Sanctum)
   ```
   Authorization: Bearer {token}
   ```

2. **Tenant Ability** (`ability:tenant` middleware)
   - User must have tenant-scoped access
   - Tenant context is automatically set via middleware

3. **Tenant Isolation** (`tenant.scope` middleware)
   - All queries are automatically filtered by `tenant_id`
   - Cross-tenant access is prevented

---

## Stable Endpoints

### Authentication & User Context

| Method | Endpoint | Description | Idempotency |
|--------|----------|-------------|-------------|
| `GET` | `/me` | Get current user info + permissions | No |
| `GET` | `/me/nav` | Get navigation menu (filtered by permissions) | No |
| `GET` | `/me/tenants` | Get available tenants for user | No |
| `POST` | `/me/tenants/{tenantId}/select` | Select active tenant | Required |

### Projects

| Method | Endpoint | Description | Idempotency |
|--------|----------|-------------|-------------|
| `GET` | `/app/projects` | List projects | No |
| `POST` | `/app/projects` | Create project | **Required** |
| `GET` | `/app/projects/{id}` | Get project details | No |
| `PUT` | `/app/projects/{id}` | Update project | **Required** |
| `PATCH` | `/app/projects/{id}` | Partial update project | **Required** |
| `DELETE` | `/app/projects/{id}` | Delete project | No |
| `GET` | `/app/projects/{id}/kpis` | Get project KPIs | No |
| `GET` | `/app/projects/{id}/alerts` | Get project alerts | No |
| `GET` | `/app/projects/{id}/tasks` | Get tasks for project | No |
| `POST` | `/app/projects/{id}/tasks` | Create task in project | **Required** |
| `GET` | `/app/projects/{id}/documents` | Get project documents | No |
| `GET` | `/app/projects/{id}/team-members` | Get project team members | No |
| `POST` | `/app/projects/{id}/team-members` | Add team member | **Required** |
| `DELETE` | `/app/projects/{id}/team-members/{userId}` | Remove team member | No |
| `GET` | `/app/projects/{project}/history` | Get project history | No |

### Tasks

| Method | Endpoint | Description | Idempotency |
|--------|----------|-------------|-------------|
| `GET` | `/app/tasks` | List tasks | No |
| `POST` | `/app/tasks` | Create task | **Required** |
| `GET` | `/app/tasks/{id}` | Get task details | No |
| `PUT` | `/app/tasks/{id}` | Update task | **Required** |
| `PATCH` | `/app/tasks/{id}` | Partial update task | **Required** |
| `DELETE` | `/app/tasks/{id}` | Delete task | No |
| `POST` | `/app/tasks/{task}/assign` | Assign task | **Required** |
| `POST` | `/app/tasks/{task}/unassign` | Unassign task | No |
| `POST` | `/app/tasks/{task}/progress` | Update task progress | **Required** |
| `PATCH` | `/app/tasks/{task}/move` | Move task (status/kanban) | **Required** |
| `GET` | `/app/tasks/{task}/documents` | Get task documents | No |
| `GET` | `/app/tasks/{task}/history` | Get task history | No |
| `POST` | `/app/tasks/bulk-delete` | Bulk delete tasks | **Required** |
| `POST` | `/app/tasks/bulk-status` | Bulk update task status | **Required** |
| `POST` | `/app/tasks/bulk-assign` | Bulk assign tasks | **Required** |

### Task Comments

| Method | Endpoint | Description | Idempotency |
|--------|----------|-------------|-------------|
| `GET` | `/app/task-comments/task/{taskId}` | Get comments for task | No |
| `POST` | `/app/task-comments` | Create comment | Required |
| `GET` | `/app/task-comments/{id}` | Get comment | No |
| `PUT` | `/app/task-comments/{id}` | Update comment | Required |
| `DELETE` | `/app/task-comments/{id}` | Delete comment | No |
| `PATCH` | `/app/task-comments/{id}/pin` | Toggle pin comment | Required |

### Task Attachments

| Method | Endpoint | Description | Idempotency |
|--------|----------|-------------|-------------|
| `GET` | `/app/task-attachments/task/{taskId}` | Get attachments for task | No |
| `POST` | `/app/task-attachments` | Upload attachment | Required |
| `GET` | `/app/task-attachments/{id}` | Get attachment | No |
| `DELETE` | `/app/task-attachments/{id}` | Delete attachment | No |
| `GET` | `/app/task-attachments/{id}/download` | Download attachment | No |

### Subtasks

| Method | Endpoint | Description | Idempotency |
|--------|----------|-------------|-------------|
| `GET` | `/app/subtasks/task/{taskId}` | Get subtasks for task | No |
| `GET` | `/app/subtasks/task/{taskId}/statistics` | Get subtask statistics | No |
| `POST` | `/app/subtasks` | Create subtask | **Required** |
| `GET` | `/app/subtasks/{id}` | Get subtask | No |
| `PUT` | `/app/subtasks/{id}` | Update subtask | **Required** |
| `PATCH` | `/app/subtasks/{id}` | Partial update subtask | **Required** |
| `DELETE` | `/app/subtasks/{id}` | Delete subtask | No |
| `PATCH` | `/app/subtasks/{id}/progress` | Update subtask progress | **Required** |

### Project Assignments

| Method | Endpoint | Description | Idempotency |
|--------|----------|-------------|-------------|
| `GET` | `/app/projects/{project}/assignments` | Get all assignments | No |
| `POST` | `/app/projects/{project}/assignments/users` | Assign users | **Required** |
| `DELETE` | `/app/projects/{project}/assignments/users/{user}` | Remove user | No |
| `POST` | `/app/projects/{project}/assignments/users/sync` | Sync users | **Required** |
| `GET` | `/app/projects/{project}/assignments/users` | Get assigned users | No |
| `POST` | `/app/projects/{project}/assignments/teams` | Assign teams | **Required** |
| `DELETE` | `/app/projects/{project}/assignments/teams/{team}` | Remove team | No |
| `POST` | `/app/projects/{project}/assignments/teams/sync` | Sync teams | **Required** |
| `GET` | `/app/projects/{project}/assignments/teams` | Get assigned teams | No |

### Task Assignments

| Method | Endpoint | Description | Idempotency |
|--------|----------|-------------|-------------|
| `GET` | `/app/tasks/{task}/assignments` | Get all assignments | No |
| `POST` | `/app/tasks/{task}/assignments/users` | Assign users | **Required** |
| `DELETE` | `/app/tasks/{task}/assignments/users/{user}` | Remove user | No |
| `GET` | `/app/tasks/{task}/assignments/users` | Get assigned users | No |
| `POST` | `/app/tasks/{task}/assignments/teams` | Assign teams | **Required** |
| `DELETE` | `/app/tasks/{task}/assignments/teams/{team}` | Remove team | No |
| `GET` | `/app/tasks/{task}/assignments/teams` | Get assigned teams | No |

### Clients

| Method | Endpoint | Description | Idempotency |
|--------|----------|-------------|-------------|
| `GET` | `/app/clients` | List clients | No |
| `POST` | `/app/clients` | Create client | **Required** |
| `GET` | `/app/clients/{id}` | Get client | No |
| `PUT` | `/app/clients/{id}` | Update client | **Required** |
| `DELETE` | `/app/clients/{id}` | Delete client | No |
| `PATCH` | `/app/clients/{client}/lifecycle-stage` | Update lifecycle stage | **Required** |

### Quotes

| Method | Endpoint | Description | Idempotency |
|--------|----------|-------------|-------------|
| `GET` | `/app/quotes` | List quotes | No |
| `POST` | `/app/quotes` | Create quote | **Required** |
| `GET` | `/app/quotes/{id}` | Get quote | No |
| `PUT` | `/app/quotes/{id}` | Update quote | **Required** |
| `DELETE` | `/app/quotes/{id}` | Delete quote | No |
| `POST` | `/app/quotes/{quote}/send` | Send quote | **Required** |
| `POST` | `/app/quotes/{quote}/accept` | Accept quote | **Required** |
| `POST` | `/app/quotes/{quote}/reject` | Reject quote | **Required** |

### Documents

| Method | Endpoint | Description | Idempotency |
|--------|----------|-------------|-------------|
| `GET` | `/app/documents` | List documents | No |
| `POST` | `/app/documents` | Upload document | **Required** |
| `GET` | `/app/documents/{id}` | Get document | No |
| `PUT` | `/app/documents/{id}` | Update document | Required |
| `PATCH` | `/app/documents/{id}` | Partial update document | Required |
| `DELETE` | `/app/documents/{id}` | Delete document | No |
| `GET` | `/app/documents/{document}/download` | Download document | No |
| `GET` | `/app/documents/approvals` | Get pending approvals | No |
| `POST` | `/app/documents/{document}/ttl-link` | Generate TTL download link | **Required** |
| `GET` | `/app/documents/download/ttl/{token}` | Download via TTL link | No |

### Change Requests

| Method | Endpoint | Description | Idempotency |
|--------|----------|-------------|-------------|
| `GET` | `/app/change-requests` | List change requests | No |
| `POST` | `/app/change-requests` | Create change request | Required |
| `GET` | `/app/change-requests/{id}` | Get change request | No |
| `PUT` | `/app/change-requests/{id}` | Update change request | Required |
| `PATCH` | `/app/change-requests/{id}` | Partial update | Required |
| `DELETE` | `/app/change-requests/{id}` | Delete change request | No |
| `POST` | `/app/change-requests/{changeRequest}/submit` | Submit for approval | **Required** |
| `POST` | `/app/change-requests/{changeRequest}/approve` | Approve change request | **Required** |
| `POST` | `/app/change-requests/{changeRequest}/reject` | Reject change request | **Required** |

### Users

| Method | Endpoint | Description | Idempotency |
|--------|----------|-------------|-------------|
| `GET` | `/app/users` | List users | No |
| `POST` | `/app/users` | Create user | Required |
| `GET` | `/app/users/{id}` | Get user | No |
| `PUT` | `/app/users/{id}` | Update user | Required |
| `PATCH` | `/app/users/{id}` | Partial update user | Required |
| `DELETE` | `/app/users/{id}` | Delete user | No |

### Templates

| Method | Endpoint | Description | Idempotency |
|--------|----------|-------------|-------------|
| `GET` | `/app/templates` | List templates | No |
| `POST` | `/app/templates` | Create template | **Required** |
| `GET` | `/app/templates/{id}` | Get template | No |
| `PUT` | `/app/templates/{id}` | Update template | **Required** |
| `DELETE` | `/app/templates/{id}` | Delete template | No |
| `GET` | `/app/templates/library` | Get template library | No |
| `GET` | `/app/templates/builder` | Get template builder | No |

### Dashboard

| Method | Endpoint | Description | Idempotency |
|--------|----------|-------------|-------------|
| `GET` | `/app/dashboard` | Get dashboard data | No |
| `GET` | `/app/dashboard/stats` | Get dashboard statistics | No |
| `GET` | `/app/dashboard/recent-projects` | Get recent projects | No |
| `GET` | `/app/dashboard/recent-tasks` | Get recent tasks | No |
| `GET` | `/app/dashboard/recent-activity` | Get recent activity | No |
| `GET` | `/app/dashboard/metrics` | Get dashboard metrics | No |
| `GET` | `/app/dashboard/team-status` | Get team status | No |
| `GET` | `/app/dashboard/charts/{type}` | Get chart data | No |
| `GET` | `/app/dashboard/alerts` | Get alerts | No |
| `PUT` | `/app/dashboard/alerts/{id}/read` | Mark alert as read | **Required** |
| `PUT` | `/app/dashboard/alerts/read-all` | Mark all alerts as read | **Required** |
| `GET` | `/app/dashboard/widgets` | Get available widgets | No |
| `GET` | `/app/dashboard/widgets/{id}/data` | Get widget data | No |
| `POST` | `/app/dashboard/widgets` | Add widget | **Required** |
| `DELETE` | `/app/dashboard/widgets/{id}` | Remove widget | No |
| `PUT` | `/app/dashboard/widgets/{id}` | Update widget config | **Required** |
| `PUT` | `/app/dashboard/layout` | Update dashboard layout | **Required** |
| `POST` | `/app/dashboard/preferences` | Save user preferences | **Required** |
| `POST` | `/app/dashboard/reset` | Reset to default | **Required** |

### Search

| Method | Endpoint | Description | Idempotency |
|--------|----------|-------------|-------------|
| `GET` | `/app/search` | Global search | No |

### Settings

| Method | Endpoint | Description | Idempotency |
|--------|----------|-------------|-------------|
| `GET` | `/app/settings` | Get settings | No |
| `PUT` | `/app/settings/general` | Update general settings | **Required** |
| `PUT` | `/app/settings/notifications` | Update notification settings | **Required** |
| `PUT` | `/app/settings/appearance` | Update appearance settings | **Required** |
| `PUT` | `/app/settings/security` | Update security settings | **Required** |
| `PUT` | `/app/settings/privacy` | Update privacy settings | **Required** |
| `PUT` | `/app/settings/integrations` | Update integration settings | **Required** |

---

## Versioning Strategy

### Current Version: v1

API v1 is the **stable version** and follows these rules:

1. **No Breaking Changes**: Existing endpoints will not change in ways that break client code
2. **Additive Changes Only**: New fields can be added to responses, but existing fields won't be removed
3. **Backward Compatible**: Old clients will continue to work

### When to Bump Version

Version bump (v1 → v2) is required when:
- Removing or renaming endpoints
- Removing or renaming response fields
- Changing request/response structure in breaking ways
- Changing authentication mechanism

Version bump is **NOT** required when:
- Adding new endpoints
- Adding new optional fields to responses
- Adding new query parameters
- Adding new optional request fields

### Versioning Format

```
/api/v1/app/projects  ← Current stable version
/api/v2/app/projects  ← Future version (when breaking changes needed)
```

---

## Deprecation Policy

### Deprecation Timeline

1. **Announcement**: Deprecated endpoints are announced **3 months** before removal
2. **Warning Headers**: Deprecated endpoints return `Deprecation` header
3. **Documentation**: Deprecated endpoints are marked in OpenAPI spec with `x-deprecated: true`
4. **Removal**: After 3 months, deprecated endpoints are removed

### Deprecation Headers

Deprecated endpoints return:
```
Deprecation: true
Sunset: <removal-date> (RFC 8594)
Link: <replacement-endpoint>; rel="successor-version"
```

### Example Deprecation

```http
GET /api/v1/app/projects/old-endpoint HTTP/1.1

HTTP/1.1 200 OK
Deprecation: true
Sunset: Sat, 01 Apr 2025 00:00:00 GMT
Link: </api/v1/app/projects/new-endpoint>; rel="successor-version"
```

---

## Idempotency

### Required Idempotency

Endpoints marked with **"Required"** idempotency MUST include `Idempotency-Key` header:

```http
POST /api/v1/app/projects
Idempotency-Key: project_create_1705123456789_abc123xyz
Content-Type: application/json

{
  "name": "New Project"
}
```

### Idempotency Key Format

```
{resource}_{action}_{timestamp}_{nonce}
```

**Example**: `project_create_1705123456789_abc123xyz`

### Idempotency Behavior

- **First Request**: Processes normally, returns response
- **Duplicate Request**: Returns same response as first request (within 24 hours)
- **Different Payload**: Returns 409 Conflict if payload differs

---

## Error Responses

All errors follow the standardized error envelope format:

```json
{
  "ok": false,
  "code": "PROJECT_NOT_FOUND",
  "message": "Project with ID 123 not found",
  "traceId": "req_abc12345",
  "details": {}
}
```

See [ERROR_ENVELOPE_CONTRACT.md](ERROR_ENVELOPE_CONTRACT.md) for details.

---

## Rate Limiting

- **Default**: 60 requests per minute per user
- **Bulk Operations**: 10 requests per minute per user
- **Rate Limit Headers**:
  ```
  X-RateLimit-Limit: 60
  X-RateLimit-Remaining: 59
  X-RateLimit-Reset: 1705123500
  ```

---

## Internal Endpoints

Endpoints marked with `x-internal: true` in OpenAPI spec are:
- Not documented in public API docs
- Not guaranteed to be stable
- Subject to change without notice
- Excluded from public OpenAPI spec

---

## OpenAPI Specification

Complete OpenAPI 3.1 specification available at:
- `/api/v1/openapi.json` - Public spec (excludes internal endpoints)
- `docs/api/openapi.yaml` - Full spec including internal endpoints

---

## References

- [Error Envelope Contract](ERROR_ENVELOPE_CONTRACT.md)
- [OpenAPI Specification](../api/openapi.yaml)
- [Architecture Overview](ARCHITECTURE_OVERVIEW.md)

---

*This contract is binding. Breaking changes require version bump and 3-month deprecation notice.*

