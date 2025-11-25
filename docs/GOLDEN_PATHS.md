# Golden Paths - Critical User Flows

**Version**: 1.0  
**Last Updated**: January 2025  
**Status**: Active  
**Purpose**: Document the 4 critical user flows that must work flawlessly in production

---

## Overview

These are the "golden paths" - the most important user journeys that must be reliable, monitorable, and rollback-safe. Each path is thoroughly tested and monitored in production.

---

## Golden Path 1: Auth + Tenant Selection + Dashboard

### Flow Description

User logs in → system determines tenant → user accesses dashboard with appropriate RBAC.

### API Endpoints Called

1. **Login** (`POST /api/v1/auth/login`)
   - Input: `{ email, password }`
   - Output: `{ ok: true, data: { user, token } }`
   - Middleware: `web`, `throttle:login`, `brute.force.protection`

2. **Get User Context** (`GET /api/v1/me`)
   - Input: Bearer token in Authorization header
   - Output: `{ user: {...}, permissions: [...], abilities: ['tenant'|'admin'] }`
   - Middleware: `auth:sanctum`

3. **Get Navigation** (`GET /api/v1/me/nav`)
   - Input: Bearer token
   - Output: Navigation menu based on user permissions
   - Middleware: `auth:sanctum`

4. **Get Dashboard Data** (`GET /api/v1/dashboard/metrics`)
   - Input: Bearer token
   - Output: Dashboard metrics and KPIs
   - Middleware: `auth:sanctum`, `ability:tenant`

### Expected Responses

- **Login Success**: 200 OK with user data and token
- **Login Failure**: 401 Unauthorized with error envelope
- **Dashboard Access**: 200 OK with metrics data
- **Unauthorized Access**: 403 Forbidden if user lacks permissions

### Error Scenarios & Handling

1. **Invalid Credentials** (401)
   - Error: `{ ok: false, code: "UNAUTHORIZED", message: "Invalid credentials" }`
   - UX: Show error message, allow retry

2. **Account Locked** (429)
   - Error: `{ ok: false, code: "RATE_LIMIT_EXCEEDED", message: "Too many login attempts" }`
   - UX: Show lockout message with retry-after time

3. **No Tenant Access** (403)
   - Error: `{ ok: false, code: "NO_TENANT_ACCESS", message: "User is not assigned to any tenant" }`
   - UX: Redirect to tenant selection or contact admin

4. **RBAC Mismatch**
   - Regular user: No admin menu items in navigation
   - Super admin: Admin menu items visible
   - UX: Navigation adapts automatically based on `/me` response

### Tenant Isolation Checkpoints

- ✅ User `tenant_id` is set from `user.tenant_id` after login
- ✅ All dashboard queries filtered by `tenant_id` via `BelongsToTenant` trait
- ✅ Navigation menu filtered by user permissions and tenant context
- ✅ Super admin can bypass tenant isolation (with audit logging)

### Monitoring Metrics

- Login success rate
- Login latency (p95 < 300ms)
- Dashboard load time (p95 < 500ms)
- Authentication failures
- Tenant resolution time

---

## Golden Path 2: Projects → Tasks (Kanban)

### Flow Description

User creates project → opens project → creates task → drags task in Kanban → changes task status.

### API Endpoints Called

1. **Create Project** (`POST /api/v1/app/projects`)
   - Input: `{ name, description, ... }`
   - Output: `{ ok: true, data: { project } }`
   - Middleware: `auth:sanctum`, `ability:tenant`, `tenant.scope`

2. **Get Project** (`GET /api/v1/app/projects/{id}`)
   - Input: Project ID
   - Output: `{ ok: true, data: { project } }`
   - Middleware: `auth:sanctum`, `ability:tenant`, `tenant.scope`

3. **Create Task** (`POST /api/v1/app/tasks`)
   - Input: `{ project_id, name, status, ... }`
   - Output: `{ ok: true, data: { task } }`
   - Middleware: `auth:sanctum`, `ability:tenant`, `tenant.scope`

4. **Get Tasks for Kanban** (`GET /api/v1/app/tasks?project_id={id}&view=kanban`)
   - Input: Project ID, view=kanban
   - Output: `{ ok: true, data: { tasks: [...] } }` grouped by status
   - Middleware: `auth:sanctum`, `ability:tenant`, `tenant.scope`

5. **Move Task** (`PATCH /api/v1/app/tasks/{id}/move`)
   - Input: `{ status, position }`
   - Output: `{ ok: true, data: { task } }`
   - Middleware: `auth:sanctum`, `ability:tenant`, `tenant.scope`

6. **Update Task Status** (`PATCH /api/v1/app/tasks/{id}/status`)
   - Input: `{ status }`
   - Output: `{ ok: true, data: { task } }`
   - Middleware: `auth:sanctum`, `ability:tenant`, `tenant.scope`

### Expected Responses

- **Create Project**: 201 Created with project data
- **Create Task**: 201 Created with task data
- **Move Task**: 200 OK with updated task
- **Status Transition**: 200 OK if valid, 409/422 if invalid

### Error Scenarios & Handling

1. **Invalid Status Transition** (409 Conflict)
   - Error: `{ ok: false, code: "TASK_STATUS_CONFLICT", message: "Cannot transition from {old} to {new}", details: { allowed_transitions: [...] } }`
   - UX: Show clear message explaining why transition is blocked, highlight allowed statuses

2. **Validation Failed** (422 Unprocessable Entity)
   - Error: `{ ok: false, code: "VALIDATION_FAILED", message: "Validation failed", details: { validation: {...} } }`
   - UX: Show field-level errors, highlight invalid fields

3. **Project Not Found** (404)
   - Error: `{ ok: false, code: "PROJECT_NOT_FOUND", message: "Project with ID {id} not found" }`
   - UX: Show error, redirect to projects list

4. **Task Not Found** (404)
   - Error: `{ ok: false, code: "TASK_NOT_FOUND", message: "Task with ID {id} not found" }`
   - UX: Show error, refresh Kanban board

### Tenant Isolation Checkpoints

- ✅ Project created with `tenant_id` from authenticated user
- ✅ Task created with `tenant_id` from project
- ✅ All queries filtered by `tenant_id` via `BelongsToTenant` trait
- ✅ User cannot access projects/tasks from other tenants
- ✅ Kanban board only shows tasks from user's tenant

### Status Transition Rules

- **backlog** → **todo**, **in_progress**
- **todo** → **in_progress**, **on_hold**, **backlog**
- **in_progress** → **on_hold**, **completed**, **todo**
- **on_hold** → **in_progress**, **cancelled**
- **completed** → (no transitions allowed)
- **cancelled** → (no transitions allowed)

### Monitoring Metrics

- Project creation success rate
- Task creation latency (p95 < 300ms)
- Kanban drag-drop latency (p95 < 500ms)
- Status transition success rate
- Invalid transition attempts

---

## Golden Path 3: Documents (Upload, List, Download)

### Flow Description

User uploads file to project → lists documents for project → downloads document.

### API Endpoints Called

1. **Upload Document** (`POST /api/v1/app/documents`)
   - Input: Multipart form with `file`, `project_id`, `name`, `description`
   - Output: `{ ok: true, data: { document } }`
   - Middleware: `auth:sanctum`, `ability:tenant`, `tenant.scope`

2. **List Documents** (`GET /api/v1/app/documents?project_id={id}`)
   - Input: Project ID query parameter
   - Output: `{ ok: true, data: { documents: [...] } }`
   - Middleware: `auth:sanctum`, `ability:tenant`, `tenant.scope`

3. **Download Document** (`GET /api/v1/app/documents/{id}/download`)
   - Input: Document ID
   - Output: File download with appropriate headers
   - Middleware: `auth:sanctum`, `ability:tenant`, `tenant.scope`

### Expected Responses

- **Upload Success**: 201 Created with document metadata
- **List Success**: 200 OK with document array
- **Download Success**: 200 OK with file stream and Content-Disposition header

### Error Scenarios & Handling

1. **File Too Large** (413 Payload Too Large)
   - Error: `{ ok: false, code: "FILE_TOO_LARGE", message: "File size exceeds maximum allowed size" }`
   - UX: Show file size limit, allow user to select smaller file

2. **Invalid File Type** (422)
   - Error: `{ ok: false, code: "INVALID_FILE_TYPE", message: "File type not allowed", details: { allowed_types: [...] } }`
   - UX: Show allowed file types, highlight invalid file

3. **Document Not Found** (404)
   - Error: `{ ok: false, code: "DOCUMENT_NOT_FOUND", message: "Document with ID {id} not found" }`
   - UX: Show error, refresh document list

4. **Download Permission Denied** (403)
   - Error: `{ ok: false, code: "FORBIDDEN", message: "You do not have permission to download this document" }`
   - UX: Show permission error, hide download button if no permission

### Tenant Isolation Checkpoints

- ✅ Document uploaded with `tenant_id` from project
- ✅ All document queries filtered by `tenant_id`
- ✅ User tenant A cannot download document from tenant B
- ✅ File storage path includes `tenant_id` for isolation
- ✅ Download endpoint validates tenant ownership before serving file

### Security Checks

- File virus scanning (if enabled)
- File type validation (whitelist)
- File size limits enforced
- Storage path isolation by tenant
- Download URL expiration (if TTL links enabled)

### Monitoring Metrics

- Upload success rate
- Upload latency (p95 < 1000ms for large files)
- Download success rate
- File size distribution
- Storage usage per tenant

---

## Golden Path 4: Admin / Tenant Management

### Flow Description

Tenant admin views users in tenant → assigns role to user → deactivates user.

### API Endpoints Called

1. **List Tenant Users** (`GET /api/v1/app/users?tenant_id={id}`)
   - Input: Tenant ID (optional, defaults to user's tenant)
   - Output: `{ ok: true, data: { users: [...] } }`
   - Middleware: `auth:sanctum`, `ability:tenant`, `tenant.scope`

2. **Get User** (`GET /api/v1/app/users/{id}`)
   - Input: User ID
   - Output: `{ ok: true, data: { user } }`
   - Middleware: `auth:sanctum`, `ability:tenant`, `tenant.scope`

3. **Assign Role** (`PATCH /api/v1/app/users/{id}/role`)
   - Input: `{ role }`
   - Output: `{ ok: true, data: { user } }`
   - Middleware: `auth:sanctum`, `ability:tenant`, `tenant.scope`, RBAC policy check

4. **Deactivate User** (`PATCH /api/v1/app/users/{id}/deactivate`)
   - Input: `{ reason? }`
   - Output: `{ ok: true, data: { user } }`
   - Middleware: `auth:sanctum`, `ability:tenant`, `tenant.scope`, RBAC policy check

### Expected Responses

- **List Users**: 200 OK with user array
- **Assign Role**: 200 OK with updated user
- **Deactivate**: 200 OK with updated user

### Error Scenarios & Handling

1. **Permission Denied** (403)
   - Error: `{ ok: false, code: "FORBIDDEN", message: "You do not have permission to manage users" }`
   - UX: Show permission error, hide management buttons

2. **Invalid Role** (422)
   - Error: `{ ok: false, code: "VALIDATION_FAILED", message: "Invalid role", details: { validation: { role: ["Role must be one of: pm, member, client"] } } }`
   - UX: Show role selection with valid options only

3. **Cannot Deactivate Self** (409)
   - Error: `{ ok: false, code: "CANNOT_DEACTIVATE_SELF", message: "You cannot deactivate your own account" }`
   - UX: Disable deactivate button for current user

4. **User Not Found** (404)
   - Error: `{ ok: false, code: "USER_NOT_FOUND", message: "User with ID {id} not found" }`
   - UX: Show error, refresh user list

### Tenant Isolation Checkpoints

- ✅ User list only shows users from same tenant
- ✅ Role assignment only works for users in same tenant
- ✅ Cannot assign roles to users from other tenants
- ✅ Deactivation only affects users in same tenant
- ✅ Super admin can manage users across tenants (with audit logging)

### RBAC Rules

- **Tenant Admin** (`admin.access.tenant`): Can manage users in their tenant
- **Super Admin** (`admin.access`): Can manage users across all tenants
- **Regular Users**: Cannot manage users

### Monitoring Metrics

- User management operations per tenant
- Role assignment success rate
- User deactivation rate
- Permission denial rate

---

## Testing Requirements

### Frontend Tests (Playwright)

- `tests/E2E/golden-paths/auth-to-dashboard.spec.ts`
- `tests/E2E/golden-paths/projects-tasks-kanban.spec.ts`
- `tests/E2E/golden-paths/documents-upload-download.spec.ts`
- `tests/E2E/golden-paths/admin-tenant-management.spec.ts`

### Backend Tests (PHPUnit)

- `tests/Feature/GoldenPaths/AuthToDashboardTest.php`
- `tests/Feature/GoldenPaths/ProjectsTasksKanbanTest.php`
- `tests/Feature/GoldenPaths/DocumentsIsolationTest.php`
- `tests/Feature/GoldenPaths/AdminTenantManagementTest.php`

### Test Coverage Requirements

- ✅ All 4 golden paths have E2E tests
- ✅ All 4 golden paths have Feature tests
- ✅ Tenant isolation verified in all tests
- ✅ Error scenarios covered
- ✅ Performance benchmarks met (p95 latency)

---

## Monitoring & Observability

### Metrics Tracked

Each golden path tracks:
- **Latency**: p95 response time per endpoint
- **Error Rate**: Percentage of failed requests
- **Success Rate**: Percentage of successful requests
- **Throughput**: Requests per minute

### Grafana Dashboards

- Golden Path 1: Auth & Dashboard
- Golden Path 2: Projects & Tasks
- Golden Path 3: Documents
- Golden Path 4: Admin Management

### Alerts

- Error rate > 1% for any golden path
- p95 latency > threshold (300ms API, 500ms pages)
- Tenant isolation violations detected
- Authentication failures spike

---

## Rollback Procedures

If any golden path fails in production:

1. **Immediate**: Check error logs and metrics
2. **Assessment**: Identify root cause (code, config, infrastructure)
3. **Mitigation**: Apply hotfix or feature flag disable
4. **Rollback**: Revert deployment if needed
5. **Verification**: Run golden path tests against rollback version
6. **Post-mortem**: Document issue and prevention measures

---

## References

- [API Documentation](API_DOCUMENTATION.md)
- [Error Envelope Contract](ERROR_ENVELOPE_CONTRACT.md)
- [Multi-Tenant Architecture](MULTI_TENANT_ARCHITECTURE.md)
- [RBAC Documentation](RBAC_DOCUMENTATION.md)

---

*This document should be updated whenever golden paths change or new critical flows are identified.*

