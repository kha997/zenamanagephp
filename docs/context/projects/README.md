# Projects Context

**Last Updated**: 2025-01-XX  
**Status**: Active

---

## Overview

The Projects context handles all project-related functionality including project creation, updates, team management, and project settings.

---

## Key Components

### Services

- **`ProjectService`** (`app/Services/ProjectService.php`)
  - Core business logic for project operations
  - Project creation, updates, status management
  - Team member management
  - Project permissions

- **`ProjectRepository`** (`app/Repositories/ProjectRepository.php`)
  - Data access layer for projects
  - Query building and filtering
  - Tenant-scoped queries

### Controllers

- **`Api\V1\App\ProjectsController`** (`app/Http/Controllers/Api/V1/App/ProjectsController.php`)
  - API endpoints for project operations
  - RESTful CRUD operations

### Models

- **`Project`** (`app/Models/Project.php`)
  - Main project model
  - Relationships: Tasks, Documents, Team Members
  - Tenant-scoped via global scope

### Policies

- **`ProjectPolicy`** (`app/Policies/ProjectPolicy.php`)
  - Authorization rules for projects
  - view, create, update, delete, manage permissions

---

## API Endpoints

- `GET /api/v1/app/projects` - List projects
- `GET /api/v1/app/projects/:id` - Get project detail
- `POST /api/v1/app/projects` - Create project
- `PUT /api/v1/app/projects/:id` - Update project
- `DELETE /api/v1/app/projects/:id` - Delete project

---

## Cache Invalidation

Cache invalidation via `CacheInvalidationService::forProjectUpdate()`:

- Project cache: `project:{project_id}`
- Project list cache: `projects:*`
- Project KPIs: `project:{project_id}:kpis`

---

## Test Organization

```bash
# Run all project tests
php artisan test --group=projects

# Run project feature tests
php artisan test --testsuite=projects-feature
```

---

## References

- [Architecture Layering Guide](../ARCHITECTURE_LAYERING_GUIDE.md)
- [Cache Invalidation Map](../CACHE_INVALIDATION_MAP.md)

