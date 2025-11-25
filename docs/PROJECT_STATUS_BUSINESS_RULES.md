# Project Status & Archive Business Rules

**Version**: 1.0  
**Last Updated**: January 2025  
**Status**: Production  
**Purpose**: Complete documentation of project status transitions, archive logic, and related business rules

---

## Table of Contents

1. [Status Values](#status-values)
2. [Status Transition Matrix](#status-transition-matrix)
3. [Archive Logic](#archive-logic)
4. [Conditional Transitions](#conditional-transitions)
5. [Task Impact on Project Status](#task-impact-on-project-status)
6. [Delete Rules](#delete-rules)
7. [Implementation Details](#implementation-details)

---

## Status Values

The system uses the following standardized status values for projects:

| Status | Value | Description |
|--------|-------|-------------|
| Planning | `planning` | Project is in planning phase, not yet started |
| Active | `active` | Project is actively being worked on |
| On Hold | `on_hold` | Project is temporarily on hold |
| Completed | `completed` | Project is completed |
| Cancelled | `cancelled` | Project has been cancelled |
| Archived | `archived` | Project is archived (read-only, terminal state) |

**Note**: Archive is implemented as a **status value** (`archived`), not a separate flag. When a project is archived, its `status` field is set to `archived`.

---

## Status Transition Matrix

### Single Source of Truth

All status transition logic is centralized in `ProjectStatusTransitionService`. This is the **only** place where transition rules are defined.

### Allowed Transitions

| From Status | To Status | Notes |
|-------------|-----------|-------|
| `planning` | `active` | Start the project |
| `planning` | `completed` | Conditional: only if no unfinished tasks |
| `planning` | `cancelled` | Cancel before starting |
| `active` | `planning` | Conditional: only if no in_progress/done tasks |
| `active` | `on_hold` | Temporarily pause the project |
| `active` | `completed` | Complete the project |
| `active` | `cancelled` | Cancel the project |
| `on_hold` | `active` | Resume the project |
| `on_hold` | `completed` | Complete from on_hold |
| `on_hold` | `cancelled` | Cancel from on_hold |
| `completed` | `archived` | Archive completed project |
| `cancelled` | `archived` | Archive cancelled project |
| `archived` | *(none)* | Terminal state - no transitions allowed |

### Transition Rules

1. **Same Status**: Transitioning to the same status is always allowed (no-op)
2. **Invalid Transitions**: All other transitions are **not allowed** and will return 422 error
3. **Terminal States**: `archived` is a terminal state (cannot transition from it)

---

## Archive Logic

### Archive Implementation

- **Archive = Status Value**: Archive is implemented as `status = 'archived'`, not a separate `archived_at` flag
- **Metadata Storage**: When archiving, additional metadata is stored in `settings` JSON field:
  ```json
  {
    "archived_at": "2025-01-15T10:30:00Z",
    "archived_by": "user_id",
    "archived_reason": "Project completed and archived"
  }
  ```

### Archive Rules

1. **Only from Terminal States**: Projects can only be archived from `completed` or `cancelled` status
   - ❌ **Cannot archive** from `planning`, `active`, or `on_hold`
   - ✅ **Can archive** from `completed` or `cancelled`

2. **No Task Restrictions**: There are **no restrictions** on archiving projects with tasks
   - Projects with tasks can be archived (since they must be `completed` or `cancelled` first)
   - Archived projects are read-only, so tasks cannot be modified

3. **Terminal State**: Once archived, projects cannot be unarchived or transitioned to any other status
   - Archived projects are read-only
   - Tasks in archived projects cannot be modified

### Archive Behavior

- **Read-Only**: Archived projects are read-only
  - Cannot modify project details
  - Cannot modify tasks
  - Cannot change status
  - Can view project and tasks

- **Visibility**: Archived projects are typically hidden from default views but visible in "All" or "Archived" filters

---

## Conditional Transitions

### Planning → Completed

**Condition**: Project can only be completed from planning if it has **no unfinished tasks**.

**Unfinished Tasks Definition**: Tasks with status `in_progress`, `blocked`, or `done`.

**Allowed Task Statuses**: Only `backlog` or `canceled` tasks are allowed.

**Error**: If project has unfinished tasks, transition is blocked with error:
```
"Cannot complete project from planning status: project has unfinished tasks. 
All tasks must be in backlog or canceled status before completing a planning project."
```

**Error Code**: `HAS_UNFINISHED_TASKS`

### Active → Planning

**Condition**: Project can only be moved back to planning if it has **no active tasks**.

**Active Tasks Definition**: Tasks with status `in_progress` or `done`.

**Allowed Task Statuses**: Only `backlog`, `blocked`, or `canceled` tasks are allowed.

**Error**: If project has active tasks, transition is blocked with error:
```
"Cannot move project back to planning status: project has active tasks (in_progress or done). 
All tasks must be in backlog, blocked, or canceled status before moving project back to planning."
```

**Error Code**: `HAS_ACTIVE_TASKS`

---

## Task Impact on Project Status

### Task Status Restrictions by Project Status

| Project Status | Task Operations Allowed |
|----------------|------------------------|
| `planning` | ✅ All operations (create, update, move, delete) |
| `active` | ✅ All operations (create, update, move, delete) |
| `on_hold` | ⚠️ Limited operations (can block/unblock, cancel) |
| `completed` | ❌ No status changes (read-only) |
| `cancelled` | ❌ No status changes (read-only) |
| `archived` | ❌ No changes (read-only) |

### Automatic Task Status Changes

When a project's status changes, tasks are automatically updated according to these rules:

| Project Status | Task Status Change | Conditions |
|----------------|-------------------|------------|
| `completed` | All tasks → `done` | Except tasks already `done` or `canceled` |
| `cancelled` | All tasks → `canceled` | Except tasks already `done` or `canceled` |
| `on_hold` | `in_progress` tasks → `blocked` | Only affects in-progress tasks |
| `planning` | All tasks → `backlog` | Except tasks already `done` or `canceled` |
| `active` | No automatic change | Tasks remain in current status |
| `archived` | No automatic change | Tasks remain in current status (read-only) |

---

## Delete Rules

### Delete Conditions

**Rule**: Projects **cannot be deleted** if they have **any tasks** (including soft-deleted tasks).

**Implementation**: The system checks for tasks using `$project->tasks()->withTrashed()->exists()`

**Error Response**:
- **HTTP Status**: `409 CONFLICT`
- **Error Code**: `PROJECT_HAS_TASKS` (or `PROJECT_DELETE_BLOCKED`)
- **Error Message**: "Không thể xoá dự án vì vẫn còn công việc đang tồn tại. Vui lòng xoá hoặc hoàn thành tất cả công việc trước khi xoá dự án."

### Delete Process

1. **Soft Delete**: Projects are soft-deleted (using `SoftDeletes` trait)
   - `deleted_at` timestamp is set
   - Project is hidden from default queries
   - Can be restored if needed

2. **Pre-Delete Validation**: Before deletion, system checks:
   - Project has no tasks (including soft-deleted)
   - If tasks exist, deletion is blocked

3. **Post-Delete**: After successful deletion:
   - Project is soft-deleted
   - Audit log entry is created
   - Related data (tasks, documents) remain but are orphaned

### Restore

- Soft-deleted projects can be restored
- Restored projects return to their previous status
- Tasks remain associated with restored projects

---

## Implementation Details

### Service Layer

**Primary Service**: `ProjectStatusTransitionService`
- Location: `app/Services/ProjectStatusTransitionService.php`
- Methods:
  - `canTransition(string $from, string $to): bool` - Check if transition is allowed
  - `validateTransition(Model $project, string $newStatus): ValidationResult` - Validate with all business rules
  - `hasActiveTasks(Model $project): bool` - Check for active tasks
  - `hasUnfinishedTasks(Model $project): bool` - Check for unfinished tasks
  - `isTerminal(string $status): bool` - Check if status is terminal

### Model Constants

**Location**: `app/Models/Project.php`

```php
public const STATUS_PLANNING = 'planning';
public const STATUS_ACTIVE = 'active';
public const STATUS_ON_HOLD = 'on_hold';
public const STATUS_COMPLETED = 'completed';
public const STATUS_CANCELLED = 'cancelled';
public const STATUS_ARCHIVED = 'archived';
```

### Error Codes

| Error Code | Description | HTTP Status |
|------------|-------------|-------------|
| `INVALID_TRANSITION` | Transition not allowed | 422 |
| `HAS_ACTIVE_TASKS` | Project has active tasks blocking transition | 422 |
| `HAS_UNFINISHED_TASKS` | Project has unfinished tasks blocking transition | 422 |
| `PROJECT_HAS_TASKS` | Project has tasks blocking deletion | 409 |

---

## Related Documentation

- [Task Status Business Rules](TASK_STATUS_BUSINESS_RULES.md) - Task status transitions and rules
- [Project Management Service](../app/Services/ProjectManagementService.php) - Project CRUD operations
- [Project Status Transition Service](../app/Services/ProjectStatusTransitionService.php) - Status transition logic

---

*This document should be updated whenever project status or archive rules change.*

