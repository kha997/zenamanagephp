# Query Performance Monitoring Guide

## Status: ✅ READY

## Overview

This guide explains how to monitor and test query performance after adding database indexes.

## Performance Testing Command

### Basic Usage

```bash
# Test with default tenant (first tenant found)
php artisan test:query-performance

# Test with specific tenant
php artisan test:query-performance --tenant-id=your-tenant-id
```

### What It Tests

The command tests 8 common query patterns:

1. **Projects filtered by priority** - Uses `idx_projects_tenant_priority`
2. **Projects filtered by client** - Uses `idx_projects_tenant_client`
3. **Tasks filtered by assignee** - Uses `idx_tasks_tenant_assignee`
4. **Tasks filtered by priority** - Uses `idx_tasks_tenant_priority`
5. **Overdue projects** - Uses `idx_projects_tenant_overdue`
6. **Overdue tasks** - Uses `idx_tasks_tenant_overdue`
7. **Projects with Kanban ordering** - Uses `idx_projects_tenant_order`
8. **Active users** - Uses `idx_users_tenant_active`

### Expected Results

**Performance Targets**:
- ✅ **Fast**: < 100ms
- ⚠️ **OK**: 100-500ms
- ❌ **Slow**: > 500ms

**Query Count**:
- Should be 1 query per test (no N+1 queries)

## Manual Performance Testing

### 1. Enable Query Logging

```php
// In your controller or service
DB::enableQueryLog();

// Run your query
$projects = Project::where('tenant_id', $tenantId)
    ->where('priority', 'high')
    ->get();

// Check results
$queries = DB::getQueryLog();
$lastQuery = end($queries);
echo "Execution time: " . $lastQuery['time'] . "ms\n";
echo "Query: " . $lastQuery['query'] . "\n";
```

### 2. Check Index Usage (MySQL)

```sql
-- Check if index is used
EXPLAIN SELECT * FROM projects 
WHERE tenant_id = 'your-tenant-id' 
AND priority = 'high';

-- Look for "key" column - should show idx_projects_tenant_priority
```

### 3. Monitor Slow Queries

```sql
-- Enable slow query log (if not already enabled)
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1; -- Log queries > 1 second

-- Check slow queries
SELECT * FROM mysql.slow_log 
ORDER BY start_time DESC 
LIMIT 10;
```

## Performance Benchmarks

### Before Indexes (Expected)
- Projects by priority: 200-500ms (full table scan)
- Tasks by assignee: 150-400ms (full table scan)
- Overdue queries: 300-800ms (full table scan)

### After Indexes (Expected)
- Projects by priority: 10-50ms (index scan)
- Tasks by assignee: 10-50ms (index scan)
- Overdue queries: 20-100ms (index scan)

**Improvement**: 5-20x faster

## Monitoring in Production

### 1. Laravel Telescope (if enabled)

```bash
# View queries in Telescope
php artisan telescope:prune
```

### 2. Database Query Logs

```php
// In AppServiceProvider
public function boot()
{
    if (config('app.debug')) {
        DB::listen(function ($query) {
            if ($query->time > 100) { // Log slow queries
                \Log::warning('Slow query detected', [
                    'sql' => $query->sql,
                    'time' => $query->time,
                    'bindings' => $query->bindings,
                ]);
            }
        });
    }
}
```

### 3. Application Performance Monitoring (APM)

If using APM tools (New Relic, Datadog, etc.):
- Monitor query execution times
- Set alerts for queries > 500ms
- Track index usage statistics

## Common Performance Issues

### Issue 1: Index Not Used

**Symptoms**: Query still slow, EXPLAIN shows no index

**Solutions**:
- Check index exists: `SHOW INDEX FROM projects;`
- Verify query matches index columns order
- Check data distribution (indexes less effective with low cardinality)

### Issue 2: Too Many Queries (N+1)

**Symptoms**: Query count is high, execution time increases with data

**Solutions**:
- Use eager loading: `->with(['relation'])`
- Check service methods use eager loading
- Review N+1 queries audit document

### Issue 3: Slow with Large Datasets

**Symptoms**: Queries slow with 1000+ records

**Solutions**:
- Add pagination: `->paginate(50)`
- Use cursor pagination for large datasets
- Consider adding more specific indexes

## Performance Checklist

- [ ] Run `php artisan test:query-performance`
- [ ] All tests show < 100ms execution time
- [ ] Each test uses only 1 query
- [ ] EXPLAIN shows indexes are used
- [ ] No slow query warnings in logs
- [ ] Production queries meet performance targets

## Next Steps

1. **Run Performance Test**: `php artisan test:query-performance`
2. **Review Results**: Check if all queries are fast
3. **Monitor Production**: Set up query logging in production
4. **Optimize Further**: If needed, add more indexes or optimize queries

---

**Last Updated**: 2025-11-14
**Status**: ✅ Ready for Testing

