# Task Status Migration Guide

## Overview

This guide documents the migration from legacy task status values to standardized status values, including database schema changes, data migration, and breaking changes.

**Version**: 1.0  
**Last Updated**: 2025-11-14  
**Migration Date**: 2025-11-14

---

## Table of Contents

1. [Migration Summary](#migration-summary)
2. [Status Mapping](#status-mapping)
3. [Database Changes](#database-changes)
4. [Migration Steps](#migration-steps)
5. [Breaking Changes](#breaking-changes)
6. [Rollback Procedure](#rollback-procedure)
7. [Testing Checklist](#testing-checklist)
8. [Troubleshooting](#troubleshooting)

---

## Migration Summary

### What Changed

1. **Status Standardization**: Legacy status values are normalized to standardized values
2. **Database Constraints**: CHECK constraint added to enforce valid statuses
3. **Schema Updates**: Added `version` column, changed `order` to decimal, added composite index
4. **API Changes**: Legacy status values no longer accepted in API requests
5. **Frontend Updates**: Status mapper utility handles conversion between display and API values

### Impact

- **Database**: Data migration required for existing tasks
- **API**: Breaking change - legacy status values rejected
- **Frontend**: Must use status mapper for backward compatibility
- **Backward Compatibility**: Frontend mapper provides compatibility layer

---

## Status Mapping

### Legacy to Standardized Mapping

| Legacy Status | Standardized Status | Notes |
|---------------|-------------------|-------|
| `pending` | `backlog` | Tasks waiting to be started |
| `completed` | `done` | Completed tasks |
| `cancelled` | `canceled` | Canceled tasks (spelling standardized) |
| `on_hold` | `blocked` | Tasks that are blocked |
| `in_progress` | `in_progress` | No change (already standardized) |

### Standardized Status Values

After migration, only these values are valid:

- `backlog`
- `in_progress`
- `blocked`
- `done`
- `canceled`

---

## Database Changes

### Migration Files

Three migration files are executed in order:

1. **`2025_11_14_085503_normalize_task_statuses.php`**
   - Normalizes existing status data
   - Adds CHECK constraint (if database supports it)

2. **`2025_11_14_085522_add_task_constraints_and_version.php`**
   - Changes `order` column from `integer` to `decimal(18,6)`
   - Adds `version` column (unsigned integer, default 1)
   - Adds composite index `idx_tasks_project_status_order`

3. **`2025_11_14_085535_initialize_task_positions.php`**
   - Initializes `order` values using ROW_NUMBER()
   - Sets `version = 1` for all existing tasks

### Schema Changes

#### Before Migration

```sql
CREATE TABLE tasks (
    id VARCHAR(26) PRIMARY KEY,
    status VARCHAR(50),  -- No constraint
    `order` INTEGER DEFAULT 0,  -- Integer type
    -- ... other fields
);
```

#### After Migration

```sql
CREATE TABLE tasks (
    id VARCHAR(26) PRIMARY KEY,
    status VARCHAR(50) CHECK (status IN ('backlog', 'in_progress', 'blocked', 'done', 'canceled')),
    `order` DECIMAL(18,6) DEFAULT 1000000,  -- Decimal for midpoint strategy
    version INT UNSIGNED DEFAULT 1,  -- Optimistic locking
    -- ... other fields
);

-- Composite index for efficient Kanban queries
CREATE INDEX idx_tasks_project_status_order ON tasks (project_id, status, `order`);
```

---

## Migration Steps

### Pre-Migration Checklist

- [ ] Backup database
- [ ] Review current task status distribution
- [ ] Notify team of maintenance window
- [ ] Test migrations on staging environment
- [ ] Verify database supports CHECK constraints (MySQL 8.0.16+ or PostgreSQL)

### Step 1: Backup Database

```bash
# MySQL
mysqldump -u username -p zenamanage > backup_before_migration.sql

# PostgreSQL
pg_dump -U username zenamanage > backup_before_migration.sql
```

### Step 2: Review Current Data

```sql
-- Check current status distribution
SELECT status, COUNT(*) as count 
FROM tasks 
GROUP BY status;

-- Check for any unexpected status values
SELECT DISTINCT status 
FROM tasks 
WHERE status NOT IN ('pending', 'in_progress', 'completed', 'cancelled', 'on_hold', 'backlog', 'blocked', 'done', 'canceled');
```

### Step 3: Run Migrations

```bash
# Run migrations in order
php artisan migrate

# Or run specific migrations
php artisan migrate --path=database/migrations/2025_11_14_085503_normalize_task_statuses.php
php artisan migrate --path=database/migrations/2025_11_14_085522_add_task_constraints_and_version.php
php artisan migrate --path=database/migrations/2025_11_14_085535_initialize_task_positions.php
```

### Step 4: Verify Migration

```sql
-- Verify status normalization
SELECT status, COUNT(*) as count 
FROM tasks 
GROUP BY status;
-- Should only show: backlog, in_progress, blocked, done, canceled

-- Verify version column
SELECT COUNT(*) as total, 
       COUNT(version) as with_version,
       MIN(version) as min_version,
       MAX(version) as max_version
FROM tasks;
-- All tasks should have version = 1

-- Verify order column
SELECT COUNT(*) as total,
       COUNT(`order`) as with_order,
       MIN(`order`) as min_order,
       MAX(`order`) as max_order
FROM tasks;
-- All tasks should have order values

-- Verify index exists
SHOW INDEX FROM tasks WHERE Key_name = 'idx_tasks_project_status_order';
```

### Step 5: Update Application Code

1. **Backend**: Already updated to use `TaskStatus` enum
2. **Frontend**: Use status mapper utility
3. **API Clients**: Update to use standardized status values

### Step 6: Clear Caches

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## Breaking Changes

### API Breaking Changes

#### Before Migration

```http
PATCH /api/v1/app/tasks/{id}
{
  "status": "pending"  // ✅ Accepted
}
```

#### After Migration

```http
PATCH /api/v1/app/tasks/{id}
{
  "status": "pending"  // ❌ Rejected (422 error)
}

PATCH /api/v1/app/tasks/{id}
{
  "status": "backlog"  // ✅ Accepted
}
```

**Error Response**:
```json
{
  "success": false,
  "error": {
    "message": "The status field must be one of: backlog, in_progress, blocked, done, canceled.",
    "status": 422
  }
}
```

### Frontend Compatibility

The frontend uses a **status mapper** to maintain backward compatibility:

```typescript
// Frontend can still use legacy values internally
const frontendStatus = 'pending';

// Mapper converts to backend value
const backendStatus = mapToBackendStatus(frontendStatus); // 'backlog'

// API call uses standardized value
await tasksApi.moveTask(taskId, { to_status: backendStatus });
```

### Database Constraints

**MySQL 8.0.16+ / PostgreSQL**:
- CHECK constraint enforces valid statuses at database level
- Invalid status values will be rejected by database

**MySQL < 8.0.16 / SQLite**:
- CHECK constraint not supported
- Validation happens at application level only

---

## Rollback Procedure

### If Migration Fails

1. **Stop the migration**:
   ```bash
   # If migration is in progress, it will rollback automatically on error
   ```

2. **Manual Rollback** (if needed):
   ```bash
   php artisan migrate:rollback --step=3
   ```

3. **Restore from Backup**:
   ```bash
   # MySQL
   mysql -u username -p zenamanage < backup_before_migration.sql
   
   # PostgreSQL
   psql -U username zenamanage < backup_before_migration.sql
   ```

### Rollback Considerations

⚠️ **Important**: Rolling back will:
- Revert status values to legacy format
- Remove `version` column
- Revert `order` column to integer
- Remove composite index

**Data Loss Risk**: If new data was created after migration, rolling back may cause data inconsistencies.

---

## Testing Checklist

### Pre-Migration Testing

- [ ] Verify backup is complete and restorable
- [ ] Test migrations on staging database
- [ ] Verify status distribution before migration
- [ ] Test API with legacy status values (should work before migration)

### Post-Migration Testing

- [ ] Verify all status values are normalized
- [ ] Verify version column exists and has values
- [ ] Verify order column is decimal type
- [ ] Verify composite index exists
- [ ] Test API with standardized status values
- [ ] Test API with legacy status values (should fail with 422)
- [ ] Test task move endpoint with new API
- [ ] Test optimistic locking (version conflicts)
- [ ] Test status transitions (all valid transitions)
- [ ] Test invalid transitions (should fail)
- [ ] Test reason requirement for blocked/canceled
- [ ] Test dependencies validation
- [ ] Test project status impact on tasks
- [ ] Test frontend Kanban drag-and-drop
- [ ] Test reason modal for blocked/canceled moves

### Integration Testing

- [ ] Test complete task lifecycle (backlog → in_progress → done)
- [ ] Test task reopening (done → in_progress)
- [ ] Test task reactivation (canceled → backlog)
- [ ] Test concurrent task moves (optimistic locking)
- [ ] Test project status change impact
- [ ] Test dependencies blocking task start
- [ ] Test progress auto-updates (done → 100%, backlog → 0%)

---

## Troubleshooting

### Issue 1: Migration Fails with CHECK Constraint Error

**Symptom**:
```
SQLSTATE[HY000]: General error: 3819 Check constraint 'tasks_status_check' is violated.
```

**Cause**: Existing data contains invalid status values

**Solution**:
1. Check for invalid statuses:
   ```sql
   SELECT DISTINCT status FROM tasks 
   WHERE status NOT IN ('pending', 'in_progress', 'completed', 'cancelled', 'on_hold', 'backlog', 'blocked', 'done', 'canceled');
   ```
2. Manually fix invalid statuses before running migration
3. Re-run migration

### Issue 2: Version Column Missing After Migration

**Symptom**: `version` column doesn't exist in tasks table

**Cause**: Migration didn't run or failed silently

**Solution**:
1. Check migration status:
   ```bash
   php artisan migrate:status
   ```
2. Manually run the migration:
   ```bash
   php artisan migrate --path=database/migrations/2025_11_14_085522_add_task_constraints_and_version.php
   ```

### Issue 3: Order Values Not Initialized

**Symptom**: All tasks have `order = 0` or `NULL`

**Cause**: Position initialization migration didn't run

**Solution**:
1. Manually run initialization:
   ```bash
   php artisan migrate --path=database/migrations/2025_11_14_085535_initialize_task_positions.php
   ```
2. Or manually initialize:
   ```sql
   UPDATE tasks SET `order` = 1000000 WHERE `order` = 0 OR `order` IS NULL;
   UPDATE tasks SET version = 1 WHERE version IS NULL OR version = 0;
   ```

### Issue 4: API Rejects Legacy Status Values

**Symptom**: API returns 422 when using `pending`, `completed`, etc.

**Expected Behavior**: ✅ This is correct after migration

**Solution**: 
- Update API clients to use standardized values
- Use frontend status mapper for conversion
- See [Breaking Changes](#breaking-changes) section

### Issue 5: Frontend Shows Wrong Status

**Symptom**: Frontend displays old status values

**Cause**: Frontend not using status mapper

**Solution**:
1. Import status mapper:
   ```typescript
   import { mapToFrontendStatus, mapToBackendStatus } from '@/shared/utils/taskStatusMapper';
   ```
2. Use mapper when displaying/updating statuses

---

## Data Migration Script

If you need to manually migrate data (outside of Laravel migrations):

```sql
-- Step 1: Normalize status values
UPDATE tasks SET status = 'backlog' WHERE status = 'pending';
UPDATE tasks SET status = 'done' WHERE status = 'completed';
UPDATE tasks SET status = 'canceled' WHERE status = 'cancelled';
UPDATE tasks SET status = 'blocked' WHERE status = 'on_hold';

-- Step 2: Add version column (if not exists)
ALTER TABLE tasks ADD COLUMN version INT UNSIGNED DEFAULT 1;

-- Step 3: Initialize version
UPDATE tasks SET version = 1 WHERE version IS NULL OR version = 0;

-- Step 4: Change order column type (MySQL)
ALTER TABLE tasks MODIFY COLUMN `order` DECIMAL(18,6) DEFAULT 1000000;

-- Step 5: Initialize order values (simplified - use migration for proper ROW_NUMBER())
UPDATE tasks SET `order` = 1000000 WHERE `order` = 0 OR `order` IS NULL;

-- Step 6: Add CHECK constraint (MySQL 8.0.16+)
ALTER TABLE tasks ADD CONSTRAINT tasks_status_check 
CHECK (status IN ('backlog', 'in_progress', 'blocked', 'done', 'canceled'));

-- Step 7: Add composite index
CREATE INDEX idx_tasks_project_status_order ON tasks (project_id, status, `order`);
```

---

## Post-Migration Tasks

### 1. Update API Documentation

- [ ] Update API docs with standardized status values
- [ ] Document breaking changes
- [ ] Update example requests/responses

### 2. Update Frontend

- [ ] Verify status mapper is used everywhere
- [ ] Update status filter options
- [ ] Update status display labels
- [ ] Test Kanban drag-and-drop

### 3. Update Tests

- [ ] Update unit tests to use standardized values
- [ ] Update feature tests
- [ ] Update E2E tests
- [ ] Add tests for status mapper

### 4. Monitor

- [ ] Monitor error logs for invalid status values
- [ ] Monitor API 422 errors
- [ ] Check for any remaining legacy status references
- [ ] Verify optimistic locking is working

---

## Support

For migration issues:
- **Technical Support**: support@zenamanage.com
- **Database Issues**: dba@zenamanage.com
- **Migration Questions**: devops@zenamanage.com

---

## Related Documentation

- [Task Move API Documentation](./api/TASK_MOVE_API.md) - API reference
- [Business Rules Documentation](./TASK_STATUS_BUSINESS_RULES.md) - Complete business rules
- [Architecture Documentation](./architecture/ARCHITECTURE_DOCUMENTATION.md) - System architecture

