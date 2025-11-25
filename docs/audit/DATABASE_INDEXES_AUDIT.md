# Database Indexes Audit Report

## Status: ✅ COMPLETED

## Summary

Audit of database queries to identify missing indexes that can improve query performance, especially for multi-tenant queries.

## Query Patterns Analysis

### Projects Table

**Common Query Patterns**:
1. `WHERE tenant_id = ?` - ✅ Has index
2. `WHERE tenant_id = ? AND status = ?` - ✅ Has composite index `(tenant_id, status)`
3. `WHERE tenant_id = ? AND priority = ?` - ⚠️ Missing composite index
4. `WHERE tenant_id = ? AND client_id = ?` - ⚠️ Missing composite index
5. `WHERE tenant_id = ? AND owner_id = ?` - ⚠️ Missing composite index
6. `WHERE tenant_id = ? AND start_date >= ?` - ⚠️ Missing composite index
7. `WHERE tenant_id = ? AND end_date <= ?` - ⚠️ Missing composite index
8. `WHERE tenant_id = ? ORDER BY order ASC, updated_at DESC` - ⚠️ Missing composite index
9. `WHERE tenant_id = ? AND end_date < NOW() AND status != 'completed'` - ⚠️ Missing composite index for overdue queries

**Foreign Keys**:
- `client_id` - ✅ Should have FK index (auto-created)
- `owner_id` - ✅ Should have FK index (auto-created)

### Tasks Table

**Common Query Patterns**:
1. `WHERE tenant_id = ?` - ✅ Has index
2. `WHERE tenant_id = ? AND project_id = ?` - ✅ Has composite index `(tenant_id, project_id)`
3. `WHERE tenant_id = ? AND status = ?` - ✅ Has composite index `(tenant_id, status)`
4. `WHERE tenant_id = ? AND assignee_id = ?` - ⚠️ Missing composite index
5. `WHERE tenant_id = ? AND priority = ?` - ⚠️ Missing composite index
6. `WHERE tenant_id = ? AND end_date < NOW() AND status NOT IN ('done', 'canceled')` - ⚠️ Missing composite index for overdue queries
7. `WHERE project_id = ? AND tenant_id = ?` - ✅ Has index (same as #2)

**Foreign Keys**:
- `project_id` - ✅ Should have FK index (auto-created)
- `assignee_id` - ✅ Should have FK index (auto-created)
- `creator_id` - ✅ Should have FK index (auto-created)

### Users Table

**Common Query Patterns**:
1. `WHERE tenant_id = ?` - ✅ Has index
2. `WHERE tenant_id = ? AND status = ?` - ✅ Has composite index `(tenant_id, status)`
3. `WHERE email = ?` - ✅ Has index
4. `WHERE tenant_id = ? AND is_active = ?` - ⚠️ Missing composite index

## Missing Indexes

### Priority HIGH (Frequently Used Queries)

1. **Projects Table**:
   - `idx_projects_tenant_priority` - `(tenant_id, priority)` - For filtering by priority
   - `idx_projects_tenant_client` - `(tenant_id, client_id)` - For filtering by client
   - `idx_projects_tenant_owner` - `(tenant_id, owner_id)` - For filtering by owner
   - `idx_projects_tenant_dates` - `(tenant_id, start_date, end_date)` - For date range queries
   - `idx_projects_tenant_overdue` - `(tenant_id, end_date, status)` - For overdue projects query

2. **Tasks Table**:
   - `idx_tasks_tenant_assignee` - `(tenant_id, assignee_id)` - For filtering by assignee
   - `idx_tasks_tenant_priority` - `(tenant_id, priority)` - For filtering by priority
   - `idx_tasks_tenant_overdue` - `(tenant_id, end_date, status)` - For overdue tasks query
   - `idx_tasks_project_status` - `(project_id, status)` - For project task filtering

3. **Users Table**:
   - `idx_users_tenant_active` - `(tenant_id, is_active)` - For filtering active users

### Priority MEDIUM (Less Frequently Used)

4. **Projects Table**:
   - `idx_projects_tenant_order` - `(tenant_id, status, order)` - For Kanban board ordering
   - `idx_projects_tenant_created` - `(tenant_id, created_at)` - ✅ Already exists

5. **Tasks Table**:
   - `idx_tasks_tenant_dates` - `(tenant_id, start_date, end_date)` - For date range queries
   - `idx_tasks_tenant_created` - `(tenant_id, created_at)` - For recent tasks

## Implementation Plan

### Migration: Add Performance Indexes

✅ **Created migration**: `2025_11_14_152506_add_performance_indexes.php`

**Indexes to Add**:

1. **Projects**:
   - `(tenant_id, priority)`
   - `(tenant_id, client_id)`
   - `(tenant_id, owner_id)`
   - `(tenant_id, start_date, end_date)`
   - `(tenant_id, end_date, status)` - For overdue queries
   - `(tenant_id, status, order)` - For Kanban ordering

2. **Tasks**:
   - `(tenant_id, assignee_id)`
   - `(tenant_id, priority)`
   - `(tenant_id, end_date, status)` - For overdue queries
   - `(project_id, status)` - For project task filtering
   - `(tenant_id, created_at)` - For recent tasks

3. **Users**:
   - `(tenant_id, is_active)`

## Notes

- All indexes use composite pattern with `tenant_id` first (for multi-tenant isolation)
- Indexes are ordered by selectivity (most selective first after tenant_id)
- Date indexes use `(tenant_id, date_column)` pattern for range queries
- Status indexes use `(tenant_id, status)` for filtering

## Indexes Added

### Projects Table (6 indexes)
1. ✅ `idx_projects_tenant_priority` - `(tenant_id, priority)` - For filtering by priority
2. ✅ `idx_projects_tenant_client` - `(tenant_id, client_id)` - For filtering by client
3. ✅ `idx_projects_tenant_owner` - `(tenant_id, owner_id)` - For filtering by owner
4. ✅ `idx_projects_tenant_dates` - `(tenant_id, start_date, end_date)` - For date range queries
5. ✅ `idx_projects_tenant_overdue` - `(tenant_id, end_date, status)` - For overdue projects query
6. ✅ `idx_projects_tenant_order` - `(tenant_id, status, order)` - For Kanban board ordering

### Tasks Table (6 indexes)
1. ✅ `idx_tasks_tenant_assignee` - `(tenant_id, assignee_id)` - For filtering by assignee
2. ✅ `idx_tasks_tenant_priority` - `(tenant_id, priority)` - For filtering by priority
3. ✅ `idx_tasks_tenant_overdue` - `(tenant_id, end_date, status)` - For overdue tasks query
4. ✅ `idx_tasks_project_status` - `(project_id, status)` - For project task filtering
5. ✅ `idx_tasks_tenant_created` - `(tenant_id, created_at)` - For recent tasks
6. ✅ `idx_tasks_tenant_dates` - `(tenant_id, start_date, end_date)` - For date range queries

### Users Table (1 index)
1. ✅ `idx_users_tenant_active` - `(tenant_id, is_active)` - For filtering active users

## Migration Details

**File**: `database/migrations/2025_11_14_152506_add_performance_indexes.php`

**Features**:
- Database-agnostic (supports MySQL, PostgreSQL, SQLite)
- Safe index creation (checks if index exists before creating)
- Proper rollback support in `down()` method
- Error handling for test environments

**To Run Migration**:
```bash
php artisan migrate
```

**To Rollback**:
```bash
php artisan migrate:rollback --step=1
```

## Testing

After adding indexes:
- [ ] Test project filtering by priority, client, owner
- [ ] Test task filtering by assignee, priority
- [ ] Test overdue queries (projects and tasks)
- [ ] Test Kanban board ordering
- [ ] Monitor query performance (EXPLAIN queries)
- [ ] Check index usage statistics
- [ ] Run migration in test environment
- [ ] Verify indexes are created correctly

