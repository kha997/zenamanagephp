# Architecture Overview

**Version**: 1.0  
**Last Updated**: January 2025  
**Status**: Active  
**Purpose**: Single source of truth for ZenaManage system architecture, domain structure, and service organization

---

## Overview

ZenaManage follows a domain-driven architecture with clear separation between business domains, infrastructure concerns, and cross-cutting utilities. This document describes the current architecture and provides guidance for future development.

---

## Architecture Principles

1. **Domain-Driven Design**: Business logic organized by domain (Projects, Tasks, Documents, etc.)
2. **Service Layering**: Clear boundaries between Domain, Infrastructure, and Cross-cutting services
3. **Multi-Tenant Isolation**: All tenant-aware data automatically filtered by `tenant_id`
4. **API-First**: All business operations exposed via RESTful API endpoints
5. **Single Responsibility**: Each service has a clear, focused purpose

---

## Domain Structure

### 1. Projects Domain

**Purpose**: Project lifecycle management, project data, project analytics

**Location**: `app/Services/`, `app/Models/Project.php`, `app/Repositories/ProjectRepository.php`

**Facade Service**: `ProjectManagementService`  
**Primary Services**:
- `ProjectService` - Core project operations
- `ProjectManagementService` - Facade for project operations
- `ProjectAnalyticsService` - Project metrics and analytics
- `ProjectAuditService` - Project audit trail

**API Endpoints** (`/api/v1/app/projects/*`):
- `GET /projects` - List projects
- `POST /projects` - Create project
- `GET /projects/{id}` - Get project details
- `PUT /projects/{id}` - Update project
- `DELETE /projects/{id}` - Delete project
- `GET /projects/stats` - Get project statistics
- `GET /projects/{id}/kpis` - Get project KPIs
- `GET /projects/{id}/alerts` - Get project alerts
- `POST /projects/bulk-delete` - Bulk delete projects
- `POST /projects/bulk-archive` - Bulk archive projects

**Models**: `Project`, `ProjectActivity`, `ProjectMember`

**Dependencies**:
- Can use: `TaskService`, `DocumentService`, `NotificationService`
- Cannot use: Infrastructure services directly (use interfaces)

---

### 2. Tasks Domain

**Purpose**: Task management, task assignments, task status transitions, task dependencies

**Location**: `app/Services/`, `app/Models/Task.php`, `app/Repositories/TaskRepository.php`

**Facade Service**: `TaskManagementService`  
**Primary Services**:
- `TaskService` - Core task operations
- `TaskManagementService` - Facade for task operations
- `TaskStatusTransitionService` - Task status workflow management
- `TaskAssignmentService` - Task assignment logic
- `TaskDependencyService` - Task dependency management
- `TaskCommentManagementService` - Task comments

**API Endpoints** (`/api/v1/app/tasks/*`):
- `GET /tasks` - List tasks
- `POST /tasks` - Create task
- `GET /tasks/{id}` - Get task details
- `PUT /tasks/{id}` - Update task
- `DELETE /tasks/{id}` - Delete task
- `POST /tasks/{id}/assign` - Assign task
- `POST /tasks/{id}/status` - Update task status
- `POST /tasks/{id}/comments` - Add comment

**Models**: `Task`, `TaskComment`, `TaskAssignment`, `TaskDependency`

**Dependencies**:
- Can use: `ProjectService`, `NotificationService`, `UserManagementService`
- Cannot use: Infrastructure services directly

---

### 3. Documents Domain

**Purpose**: Document management, file uploads, document versioning, document sharing

**Location**: `app/Services/`, `app/Models/Document.php`

**Facade Service**: `DocumentService`  
**Primary Services**:
- `DocumentService` - Core document operations
- `FileManagementService` - File storage operations
- `SecureFileUploadService` - Secure file upload handling
- `MediaService` - Media file operations (images, videos)

**API Endpoints** (`/api/v1/app/documents/*`):
- `GET /documents` - List documents
- `POST /documents` - Upload document
- `GET /documents/{id}` - Get document
- `PUT /documents/{id}` - Update document metadata
- `DELETE /documents/{id}` - Delete document
- `GET /documents/{id}/download` - Download document
- `POST /documents/{id}/share` - Share document

**Models**: `Document`, `DocumentVersion`

**Dependencies**:
- Can use: `ProjectService`, `TaskService` (for linking documents)
- Uses Infrastructure: `MediaService` (via interface)

---

### 4. Tenants Domain

**Purpose**: Multi-tenant isolation, tenant management, tenant provisioning

**Location**: `app/Services/`, `app/Models/Tenant.php`, `app/Http/Middleware/`

**Facade Service**: `TenantService` (if exists) or `TenantProvisioningService`  
**Primary Services**:
- `TenantProvisioningService` - Tenant creation and setup
- `TenantContext` - Tenant context management
- `TenantCacheService` - Tenant-specific caching

**Middleware**:
- `TenantIsolationMiddleware` - Ensures tenant isolation on API routes
- `TenantScopeMiddleware` - Applies tenant scope to queries

**Traits**:
- `BelongsToTenant` - Global scope for tenant filtering

**API Endpoints** (`/api/v1/admin/tenants/*` - Admin only):
- `GET /admin/tenants` - List tenants
- `POST /admin/tenants` - Create tenant
- `GET /admin/tenants/{id}` - Get tenant
- `PUT /admin/tenants/{id}` - Update tenant
- `DELETE /admin/tenants/{id}` - Delete tenant

**Models**: `Tenant`, `TenantSettings`

**Dependencies**:
- Core domain - used by all other domains
- No dependencies on other domains

---

### 5. RBAC Domain

**Purpose**: Role-Based Access Control, permissions management, authorization

**Location**: `app/Services/`, `app/Models/Role.php`, `app/Models/Permission.php`, `config/permissions.php`

**Facade Service**: `PermissionService`  
**Primary Services**:
- `PermissionService` - Permission checking facade
- `RBACManager` - RBAC operations manager
- `RBACSyncService` - Syncs permissions from `config/permissions.php` to database
- `PermissionMatrixService` - Permission matrix calculations
- `AbilityMatrixService` - Ability matrix calculations

**Configuration**: `config/permissions.php` - Single source of truth for permissions

**API Endpoints** (`/api/v1/me`, `/api/v1/me/nav`):
- `GET /me` - Get current user info + permissions
- `GET /me/nav` - Get navigation menu (filtered by permissions)

**Models**: `Role`, `Permission`, `UserRole` (pivot)

**Dependencies**:
- Core domain - used by all other domains for authorization
- No dependencies on other domains

---

### 6. Core Domain (Cross-Cutting)

**Purpose**: Shared utilities, error handling, logging, tracing, observability

**Location**: `app/Services/`, `app/Support/`

**Key Services**:
- `ErrorEnvelopeService` - Standardized error response format
- `TracingService` - Request tracing and correlation IDs
- `ObservabilityService` - Metrics and observability
- `CacheKeyService` - Cache key generation
- `RequestCorrelationService` - Request ID correlation

**Support Classes**:
- `ApiResponse` (`app/Support/ApiResponse.php`) - Standardized API responses

**Middleware**:
- `ErrorEnvelopeMiddleware` - Wraps errors in standard format
- `RequestCorrelationMiddleware` - Adds correlation IDs

**Dependencies**:
- Can be used by any domain
- Should not contain business logic
- Stateless or minimal state

---

### 7. Infrastructure Domain

**Purpose**: External systems, file storage, email, queues, caching

**Location**: `app/Services/Infrastructure/` or `app/Services/`

**Key Services**:
- `EmailService` - Email sending
- `MediaService` - File storage operations
- `QueueService` - Job queuing (if exists)
- `CacheService` - Caching operations
- `AdvancedCacheService` - Advanced caching features

**Rules**:
- Should implement interfaces defined in Domain layer
- Can be called by Domain Services via interfaces only
- Can use external libraries/packages

**Dependencies**:
- Used by Domain Services via interfaces
- No dependencies on Domain Services

---

### 8. Dashboard Domain (Application Service)

**Purpose**: Aggregates data from multiple domains for dashboard display

**Location**: `app/Services/`

**Facade Service**: `DashboardService`  
**Primary Services**:
- `DashboardService` - Main dashboard facade
- `DashboardDataAggregationService` - Aggregates data from multiple sources
- `RealTimeDashboardService` - Real-time dashboard updates
- `DashboardRoleBasedService` - Role-based dashboard customization
- `KpiService` - KPI calculations
- `KpiCacheService` - KPI caching

**API Endpoints** (`/api/v1/app/dashboard/*`):
- `GET /dashboard/stats` - Get dashboard statistics
- `GET /dashboard/recent-projects` - Get recent projects
- `GET /dashboard/recent-tasks` - Get recent tasks
- `GET /dashboard/recent-activity` - Get recent activity
- `GET /dashboard/metrics` - Get dashboard metrics

**Dependencies**:
- Uses: `ProjectService`, `TaskService`, `DocumentService`, `KpiService`
- Orchestrates multiple domain services

---

## Service Organization Rules

### Facade Pattern

Each domain should have **one facade service** that acts as the main entry point:

- **Projects**: `ProjectManagementService`
- **Tasks**: `TaskManagementService`
- **Documents**: `DocumentService`
- **RBAC**: `PermissionService`
- **Dashboard**: `DashboardService`

### Service Naming Conventions

- **Facade Services**: `{Domain}ManagementService` or `{Domain}Service`
- **Internal Services**: `{Domain}{SpecificPurpose}Service` (e.g., `TaskStatusTransitionService`)
- **Infrastructure Services**: `{Purpose}Service` (e.g., `EmailService`, `MediaService`)

### Dependency Rules

1. **Domain Services** → Can call Repository layer, other Domain Services, Cross-cutting Services
2. **Domain Services** → Cannot call Infrastructure Services directly (use interfaces)
3. **Application Services** → Can call multiple Domain Services
4. **Infrastructure Services** → Can be called via interfaces only
5. **Cross-cutting Services** → Can be used by any layer

---

## API Endpoint Organization

### Route Groups

- `/api/v1/app/*` - Tenant-scoped application API (requires `auth:sanctum`, `ability:tenant`)
- `/api/v1/admin/*` - System-wide admin API (requires `auth:sanctum`, `ability:admin`)
- `/api/v1/public/*` - Public API (no authentication)

### Middleware Stack

For `/api/v1/app/*` routes:
1. `auth:sanctum` - Authentication
2. `ability:tenant` - Authorization
3. `TenantIsolationMiddleware` - Tenant isolation
4. `ErrorEnvelopeMiddleware` - Error formatting
5. `RequestCorrelationMiddleware` - Correlation IDs

---

## Future Domain Organization

**Planned Structure** (gradual migration):

```
app/
├── Domains/
│   ├── Projects/
│   │   ├── Services/
│   │   ├── Models/
│   │   └── Repositories/
│   ├── Tasks/
│   ├── Documents/
│   ├── Tenants/
│   └── RBAC/
├── Core/
│   ├── ErrorHandling/
│   ├── Logging/
│   └── Observability/
└── Infrastructure/
    ├── Email/
    ├── Storage/
    └── Queue/
```

**Migration Strategy**:
- Start with least-dependent domain (Tasks)
- Move services gradually, maintaining backward compatibility
- Update namespace and imports incrementally
- Ensure tests pass at each step

---

## References

- [ADR-001: Service Layering Guide](adr/ADR-001-Service-Layering-Guide.md)
- [Architecture Layering Guide](ARCHITECTURE_LAYERING_GUIDE.md)
- [Complete System Documentation](../COMPLETE_SYSTEM_DOCUMENTATION.md)

---

*This document should be updated whenever domain structure or service organization changes.*

