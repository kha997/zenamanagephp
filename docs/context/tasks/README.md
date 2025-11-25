# Tasks Context

**Last Updated**: 2025-01-XX  
**Status**: Active

---

## Overview

The Tasks context handles all task-related functionality including task creation, updates, status transitions, assignments, and Kanban board operations.

---

## Key Components

### Services

- **`TaskService`** (`app/Services/TaskService.php`)
  - Core business logic for task operations
  - Task creation, updates, status transitions
  - Task assignment and unassignment
  - Calls `TaskStatusTransitionService` for status validation

- **`TaskStatusTransitionService`** (`app/Services/TaskStatusTransitionService.php`)
  - Validates task status transitions
  - Enforces business rules for status changes
  - Used by `TaskService` for move operations

- **`TaskRepository`** (`app/Repositories/TaskRepository.php`)
  - Data access layer for tasks
  - Query building and filtering
  - Tenant-scoped queries

### Controllers

- **`Api\V1\App\TasksController`** (`app/Http/Controllers/Api/V1/App/TasksController.php`)
  - API endpoints for task operations
  - RESTful CRUD operations
  - Kanban board endpoints

- **`Web\TasksController`** (`app/Http/Controllers/Web/TasksController.php`)
  - Web routes for task views (legacy Blade)
  - Should migrate to React SPA

### Models

- **`Task`** (`app/Models/Task.php`)
  - Main task model
  - Relationships: Project, Assignee, Creator
  - Tenant-scoped via global scope

### Policies

- **`TaskPolicy`** (`app/Policies/TaskPolicy.php`)
  - Authorization rules for tasks
  - view, create, update, delete, assign permissions

---

## API Endpoints

### Task CRUD

- `GET /api/v1/app/tasks` - List tasks (with filters)
- `GET /api/v1/app/tasks/:id` - Get task detail
- `POST /api/v1/app/tasks` - Create task
- `PUT /api/v1/app/tasks/:id` - Update task
- `DELETE /api/v1/app/tasks/:id` - Delete task

### Kanban Operations

- `GET /api/v1/app/tasks/kanban` - Get Kanban board data
- `POST /api/v1/app/tasks/:id/move` - Move task to new status

### Task Assignment

- `POST /api/v1/app/tasks/:id/assign` - Assign task to user
- `POST /api/v1/app/tasks/:id/unassign` - Unassign task

---

## Events

- **`TaskCreated`** - Fired when task is created
- **`TaskUpdated`** - Fired when task is updated
- **`TaskMoved`** - Fired when task status changes
- **`TaskAssigned`** - Fired when task is assigned
- **`TaskUnassigned`** - Fired when task is unassigned

---

## Cache Invalidation

Cache invalidation is handled via `CacheInvalidationService::forTaskUpdate()`:

- Task-specific cache: `task:{task_id}`
- Task list cache: `tasks:project:{project_id}:*`
- Project KPIs: `project:{project_id}:kpis` (when task changes)

**Listeners**:
- `InvalidateTaskCache` - Listens to `TaskUpdated` and `TaskMoved` events

---

## Test Organization

### Test Groups

- **Unit Tests**: `tests/Unit/Services/TaskServiceTest.php`
- **Feature Tests**: `tests/Feature/Api/Tasks/`
- **Integration Tests**: `tests/Integration/TaskStatusSyncTest.php`

### Running Tests

```bash
# Run all task tests
php artisan test --group=tasks

# Run task feature tests
php artisan test --testsuite=tasks-feature

# Run with fixed seed
php artisan test --group=tasks --seed=34567
```

### Test Data

Use `TestDataSeeder::seedTasksDomain(34567)` for task test data.

---

## Common Pitfalls

### 1. Forgetting Tenant Isolation

❌ **Bad**:
```php
$tasks = Task::where('status', 'active')->get();
```

✅ **Good**:
```php
// Global scope automatically filters by tenant_id
$tasks = Task::where('status', 'active')->get();
```

### 2. Bypassing Service Layer

❌ **Bad**:
```php
// In controller
$task = Task::create($request->all());
```

✅ **Good**:
```php
// In controller
$task = $this->taskService->createTask($request->validated());
```

### 3. Not Invalidating Cache

❌ **Bad**:
```php
$task->update($data);
// Cache not invalidated
```

✅ **Good**:
```php
$task->update($data);
event(new TaskUpdated($task));
// Listener invalidates cache via CacheInvalidationService
```

### 4. Status Transition Validation

❌ **Bad**:
```php
$task->status = 'completed';
$task->save();
// No validation
```

✅ **Good**:
```php
$this->taskService->moveTask($task, 'completed');
// Validates transition via TaskStatusTransitionService
```

---

## Status Transitions

Valid status transitions are defined in `TaskStatusTransitionService`:

- `backlog` → `todo`, `in_progress`
- `todo` → `in_progress`, `on_hold`, `backlog`
- `in_progress` → `on_hold`, `completed`, `todo`
- `on_hold` → `in_progress`, `todo`, `cancelled`
- `completed` → (no transitions)
- `cancelled` → (no transitions)

---

## References

- [Architecture Layering Guide](../ARCHITECTURE_LAYERING_GUIDE.md)
- [Cache Invalidation Map](../CACHE_INVALIDATION_MAP.md)
- [Test Groups](../TEST_GROUPS.md)
- [API Documentation](../../api/API_DOCUMENTATION.md)

