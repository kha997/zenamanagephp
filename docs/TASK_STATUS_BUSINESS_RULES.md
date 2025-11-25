# Task Status Business Rules Documentation

## Overview

This document defines the complete business rules for task status transitions, project status impacts, dependencies validation, and related business logic in the ZenaManage system.

**Version**: 1.0  
**Last Updated**: 2025-11-14  
**Status**: Production

---

## Table of Contents

1. [Status Standardization](#status-standardization)
2. [Status Transition Matrix](#status-transition-matrix)
3. [Project Status Impact](#project-status-impact)
4. [Dependencies Rules](#dependencies-rules)
5. [Progress Consistency Rules](#progress-consistency-rules)
6. [Required Reasons](#required-reasons)
7. [Date Validation Rules](#date-validation-rules)
8. [Implementation Details](#implementation-details)

---

## Status Standardization

### Task Statuses

The system uses **standardized status values** for tasks:

| Status | Value | Description |
|--------|-------|-------------|
| Backlog | `backlog` | Task is in the backlog, not yet started |
| In Progress | `in_progress` | Task is currently being worked on |
| Blocked | `blocked` | Task is blocked and cannot proceed |
| Done | `done` | Task is completed |
| Canceled | `canceled` | Task has been canceled |

### Legacy Status Mapping

For backward compatibility, the following legacy status values are automatically mapped:

| Legacy Status | Standardized Status |
|---------------|---------------------|
| `pending` | `backlog` |
| `completed` | `done` |
| `cancelled` | `canceled` |
| `on_hold` | `blocked` |

**Note**: After migration, legacy status values are no longer accepted. All new requests must use standardized values.

### Project Statuses

Project statuses remain unchanged:

| Status | Value | Description |
|--------|-------|-------------|
| Planning | `planning` | Project is in planning phase |
| Active | `active` | Project is actively being worked on |
| On Hold | `on_hold` | Project is temporarily on hold |
| Completed | `completed` | Project is completed |
| Cancelled | `cancelled` | Project has been cancelled |
| Archived | `archived` | Project is archived (read-only) |

---

## Status Transition Matrix

### Single Source of Truth

All status transition logic is centralized in `TaskStatusTransitionService`. This is the **only** place where transition rules are defined.

### Allowed Transitions

| From Status | To Status | Notes |
|-------------|-----------|-------|
| `backlog` | `in_progress` | Start working on task |
| `backlog` | `canceled` | Cancel before starting |
| `in_progress` | `done` | Complete the task |
| `in_progress` | `blocked` | Block due to external dependency |
| `in_progress` | `canceled` | Cancel while in progress |
| `in_progress` | `backlog` | Rollback to backlog (rare, but allowed) |
| `blocked` | `in_progress` | Unblock and resume work |
| `blocked` | `canceled` | Cancel while blocked |
| `done` | `in_progress` | Reopen completed task |
| `canceled` | `backlog` | Reactivate canceled task |

### Transition Rules

1. **Same Status**: Transitioning to the same status is always allowed (no-op)
2. **Invalid Transitions**: All other transitions are **not allowed** and will return 422 error
3. **Terminal States**: `done` and `canceled` are considered terminal states (cannot be auto-changed by project status)

---

## Project Status Impact

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

### Project Status Restrictions

Tasks can only be modified when the project is in certain statuses:

| Project Status | Task Operations Allowed |
|----------------|------------------------|
| `planning` | ✅ All operations (create, update, move, delete) |
| `active` | ✅ All operations (create, update, move, delete) |
| `on_hold` | ⚠️ Limited operations (can block/unblock, cancel) |
| `completed` | ❌ No status changes (read-only) |
| `cancelled` | ❌ No status changes (read-only) |
| `archived` | ❌ No changes (read-only) |

### Terminal State Protection

Tasks in terminal states (`done`, `canceled`) are **protected** from automatic changes:

- ✅ **Protected**: Tasks already `done` or `canceled` are not changed when project status changes
- ✅ **Preserved**: Completed/canceled work is preserved for historical accuracy
- ⚠️ **Manual Override**: Users can manually reopen (`done` → `in_progress`) or reactivate (`canceled` → `backlog`)

---

## Dependencies Rules

### Starting Tasks (Moving to `in_progress`)

**Rule**: A task cannot be moved to `in_progress` if any of its dependencies are not completed.

**Validation**:
- Check all tasks listed in `task.dependencies` array
- All dependent tasks must have status = `done`
- If any dependency is not `done`, transition is blocked with error:
  > "Cannot start task: one or more dependencies are not completed. All dependent tasks must be in 'done' status before starting this task."

**Example**:
```
Task A depends on [Task B, Task C]
- Task B status: done ✅
- Task C status: in_progress ❌
→ Cannot move Task A to in_progress
```

### Canceling Tasks (Moving to `canceled`)

**Rule**: Canceling a task that has active dependents generates a warning (not an error).

**Validation**:
- Check all tasks that depend on this task (dependents)
- If any dependent has status = `in_progress`, show warning:
  > "Task has {N} active dependent task(s). Canceling this task may affect their progress."

**Behavior**:
- ⚠️ Warning is shown, but operation **proceeds**
- User can choose to proceed or cancel the operation
- This is a warning, not a blocker, to allow flexibility

**Example**:
```
Task A is being canceled
- Task B depends on Task A, status: in_progress
- Task C depends on Task A, status: backlog
→ Warning shown: "Task has 1 active dependent task(s)..."
→ Operation proceeds if user confirms
```

### Dependency Cycle Detection

**Rule**: Circular dependencies are not allowed.

**Validation**:
- When updating task dependencies, check for cycles
- If a cycle would be created, validation fails with error:
  > "Cannot create circular dependency"

**Example**:
```
Task A depends on Task B
Task B depends on Task C
Task C depends on Task A ❌ → Cycle detected, validation fails
```

---

## Progress Consistency Rules

### Progress and Status Consistency

The system enforces consistency between `progress_percent` and `status`:

| Status | Progress Rule | Auto-Update |
|--------|---------------|-------------|
| `backlog` | Must be `0%` | ✅ Auto-set to 0% when moving to backlog |
| `in_progress` | Can be `0-99%` | No auto-update |
| `blocked` | Preserves current progress | No auto-update |
| `done` | Must be `100%` | ✅ Auto-set to 100% when moving to done |
| `canceled` | Preserves current progress | No auto-update |

### Automatic Progress Updates

When status changes, progress is automatically updated:

1. **Moving to `done`**:
   - If `progress_percent < 100`, automatically set to `100%`
   - This ensures completed tasks always show 100% progress

2. **Moving to `backlog`**:
   - If `progress_percent > 0`, automatically set to `0%`
   - This ensures backlog tasks start fresh

3. **Reopening (`done` → `in_progress`)**:
   - Progress is preserved (stays at 100% or can be manually adjusted)
   - Alternative: Can clamp to 90% if you want to ensure "not fully done"

### Validation Rules

1. **Task with 100% progress must be `done`**:
   - If `progress_percent == 100` and `status != done`, validation fails
   - Error: "Task có tiến độ 100% phải ở trạng thái done."

2. **Backlog task cannot have progress**:
   - If `status == backlog` and `progress_percent > 0`, validation fails
   - Error: "Task backlog không thể có tiến độ > 0%."

3. **Done task must have 100% progress**:
   - If `status == done` and `progress_percent < 100`, validation fails
   - Error: "Task hoàn thành phải có tiến độ 100%."

---

## Required Reasons

### Statuses Requiring Reason

When moving a task to certain statuses, a **reason is required**:

| Status | Reason Required | Max Length |
|--------|----------------|------------|
| `blocked` | ✅ Yes | 500 characters |
| `canceled` | ✅ Yes | 500 characters |
| All others | ❌ No | N/A |

### Reason Validation

- **Required**: Cannot be empty or whitespace-only
- **Max Length**: 500 characters
- **Purpose**: Provides context for why task was blocked/canceled
- **Storage**: Stored in activity log and task history

### Example Reasons

**Blocked:**
- "Waiting for client approval on design mockups"
- "Dependency on external API integration"
- "Resource allocation pending"

**Canceled:**
- "Client requested feature cancellation"
- "Out of scope for current sprint"
- "Superseded by alternative solution"

---

## Date Validation Rules

### Task Dates vs Project Dates

Task dates must fall within the project's date range:

| Rule | Validation |
|------|------------|
| Task start date | Must be ≥ project start date (if project has start date) |
| Task end date | Must be ≤ project end date (if project has end date) |
| Task end date | Must be ≥ task start date |

### Null Handling

- **Null dates are allowed**: Tasks and projects can have null dates
- **Validation only applies when both dates are present**
- **Flexible planning**: Allows tasks to be created without immediate date planning

### Error Messages

- "Ngày bắt đầu task không được trước ngày bắt đầu dự án."
- "Ngày kết thúc task không được sau ngày kết thúc dự án."
- "End date must be after or equal to start date."

---

## Implementation Details

### Service Layer

All business rules are implemented in `TaskStatusTransitionService`:

```php
// Single source of truth for transitions
TaskStatusTransitionService::canTransition($from, $to)

// Complete validation with all business rules
TaskStatusTransitionService::validateTransition($task, $newStatus, $reason)

// Progress calculation
TaskStatusTransitionService::calculateProgress($newStatus, $currentProgress)
```

### Database Constraints

1. **CHECK Constraint** (MySQL 8.0.16+ / PostgreSQL):
   ```sql
   CHECK (status IN ('backlog', 'in_progress', 'blocked', 'done', 'canceled'))
   ```

2. **Composite Index**:
   ```sql
   INDEX idx_tasks_project_status_order (project_id, status, order)
   ```

3. **Version Field**:
   - Type: `unsigned integer`
   - Default: `1`
   - Purpose: Optimistic locking

4. **Order Field**:
   - Type: `decimal(18,6)`
   - Default: `1000000`
   - Purpose: Midpoint positioning strategy

### Event System

When a task is moved, the following events are fired:

1. **TaskMoved Event**:
   - Contains: task, old_status, new_status, reason, old_position, new_position
   - Listeners: NotificationService, AuditService, ActivityFeed

2. **Activity Logging**:
   - Logs: old_status → new_status, user_id, reason, timestamps, request_id
   - Format: Structured JSON logs

### API Endpoint

**Endpoint**: `PATCH /api/v1/app/tasks/{task}/move`

**Request Body**:
```json
{
  "to_status": "in_progress",
  "before_id": "optional-task-id",
  "after_id": "optional-task-id",
  "reason": "optional-reason-for-blocked-canceled",
  "version": 1
}
```

**Response**:
```json
{
  "success": true,
  "data": { /* updated task */ },
  "message": "Task moved successfully",
  "warning": null
}
```

See [Task Move API Documentation](./api/TASK_MOVE_API.md) for complete API reference.

---

## Edge Cases and Special Scenarios

### Scenario 1: Reopening Completed Task

**Situation**: Task is `done`, user wants to reopen it.

**Allowed**: ✅ `done` → `in_progress`

**Progress Handling**:
- Progress remains at 100% (or can be manually adjusted)
- Alternative: System can clamp to 90% to indicate "not fully done"

**Use Case**: Found a bug in completed work, need to fix it.

### Scenario 2: Reactivating Canceled Task

**Situation**: Task is `canceled`, user wants to reactivate it.

**Allowed**: ✅ `canceled` → `backlog`

**Progress Handling**:
- Progress is reset to 0% (backlog tasks start fresh)

**Use Case**: Client changed mind, wants to proceed with previously canceled feature.

### Scenario 3: Project Completed While Tasks In Progress

**Situation**: Project status changes to `completed` while some tasks are `in_progress`.

**Automatic Action**: All non-terminal tasks → `done`

**Progress Handling**:
- Progress is automatically set to 100% for all affected tasks
- Terminal tasks (`done`, `canceled`) are preserved

**Use Case**: Project deadline reached, all remaining work marked as done.

### Scenario 4: Project On Hold

**Situation**: Project status changes to `on_hold`.

**Automatic Action**: Only `in_progress` tasks → `blocked`

**Other Tasks**: Remain unchanged (backlog stays backlog, done stays done)

**Use Case**: Client requested pause, active work is blocked, but planning can continue.

### Scenario 5: Concurrent Modifications

**Situation**: Two users try to move the same task simultaneously.

**Handling**: Optimistic locking with `version` field

**Flow**:
1. User A fetches task (version = 1)
2. User B fetches task (version = 1)
3. User A moves task (version = 1 → 2) ✅
4. User B tries to move task (version = 1) ❌ → 409 Conflict
5. User B refreshes, gets version = 2, retries ✅

---

## Testing Scenarios

### Unit Tests Required

1. **Transition Validation**:
   - ✅ All valid transitions pass
   - ✅ All invalid transitions fail
   - ✅ Same status transition (no-op) passes

2. **Dependencies Validation**:
   - ✅ Cannot start when dependencies not done
   - ✅ Can start when all dependencies done
   - ✅ Warning when canceling with active dependents

3. **Progress Consistency**:
   - ✅ Done status auto-sets progress to 100%
   - ✅ Backlog status auto-sets progress to 0%
   - ✅ Validation fails when progress/status mismatch

4. **Project Status Impact**:
   - ✅ Archived project blocks all task changes
   - ✅ Completed project blocks task status changes
   - ✅ On hold project allows limited operations

### Integration Tests Required

1. **End-to-End Flow**:
   - Move task through complete lifecycle
   - Verify position updates
   - Verify version increments
   - Verify event firing

2. **Concurrent Access**:
   - Simulate concurrent moves
   - Verify optimistic locking works
   - Verify conflict detection

3. **Project Status Sync**:
   - Change project status
   - Verify automatic task status updates
   - Verify terminal state protection

---

## Migration Notes

### Status Normalization

During migration, existing status values are normalized:

```sql
UPDATE tasks SET status = 'backlog' WHERE status = 'pending';
UPDATE tasks SET status = 'done' WHERE status = 'completed';
UPDATE tasks SET status = 'canceled' WHERE status = 'cancelled';
UPDATE tasks SET status = 'blocked' WHERE status = 'on_hold';
```

### Breaking Changes

1. **API Breaking Change**: Legacy status values (`pending`, `completed`, `cancelled`, `on_hold`) are no longer accepted after migration
2. **Frontend Update Required**: Frontend must use status mapper to convert between display and API values
3. **Database Constraint**: CHECK constraint enforces valid statuses at database level

See [Migration Guide](./TASK_STATUS_MIGRATION_GUIDE.md) for complete migration instructions.

---

## Related Documentation

- [Task Move API Documentation](./api/TASK_MOVE_API.md) - Complete API reference
- [Migration Guide](./TASK_STATUS_MIGRATION_GUIDE.md) - Migration instructions
- [Architecture Documentation](./architecture/ARCHITECTURE_DOCUMENTATION.md) - System architecture

---

## Support

For questions or clarifications:
- **Technical Support**: support@zenamanage.com
- **Documentation Issues**: docs@zenamanage.com
- **Business Rule Questions**: product@zenamanage.com

