# PERFORMANCE GUIDE

This document outlines performance optimization strategies and quick wins for the ZenaManage application.

## Quick Wins

### 1. KPI Caching
- **Implementation**: Cache KPI data for 60 seconds per tenant
- **Location**: `app/Repositories/DashboardRepository.php`
- **Benefit**: Reduces database queries for dashboard and list views
- **Cache Key**: `kpi:{tenant_id}`

### 2. Pagination
- **Implementation**: Use `paginate(20)` for all list views
- **Location**: All `*Controller::index()` methods
- **Benefit**: Reduces memory usage and improves page load times
- **Views**: Include `{{ $items->links() }}` in Blade templates

### 3. Database Indexes
- **Implementation**: Composite indexes on `(tenant_id, foreign_key)`
- **Location**: `database/migrations/2025_10_06_000001_add_indexes.php`
- **Benefit**: Faster queries for tenant-scoped data
- **Tables**: `projects`, `tasks`, `clients`, `quotes`

### 4. Eager Loading
- **Implementation**: Use `with()` to prevent N+1 queries
- **Location**: Controller methods that load related models
- **Benefit**: Reduces database round trips
- **Example**: `Project::with('tasks', 'client')->paginate(20)`

## Performance Budgets

- **Page Load Time**: p95 < 500ms (20–50 rows)
- **API Response Time**: p95 < 300ms
- **Database Queries**: < 10 queries per page load
- **Memory Usage**: < 50MB per request

## Monitoring

### Database Query Analysis
Use `EXPLAIN` to analyze slow queries:

```sql
EXPLAIN SELECT * FROM projects WHERE tenant_id = ? AND status = ?;
```

### Laravel Debugbar
Enable Laravel Debugbar in development to monitor:
- Query count and execution time
- Memory usage
- Route resolution time
- View rendering time

### Performance Metrics
The `LogPerformanceMetrics` middleware logs:
- Request duration
- Memory usage
- Database query count
- Route name and method

## Optimization Checklist

- [ ] KPI data is cached (60s TTL)
- [ ] List views use pagination (20 items per page)
- [ ] Database indexes exist for tenant_id and foreign keys
- [ ] Eager loading prevents N+1 queries
- [ ] Views include pagination links
- [ ] Performance budgets are met
- [ ] No unnecessary database queries

## Common Performance Issues

### N+1 Queries
**Problem**: Loading related models without eager loading
```php
// Bad
foreach ($projects as $project) {
    echo $project->client->name; // N+1 query
}

// Good
$projects = Project::with('client')->get();
foreach ($projects as $project) {
    echo $project->client->name; // No additional queries
}
```

### Missing Indexes
**Problem**: Slow queries on tenant_id and foreign keys
```sql
-- Add indexes
ALTER TABLE projects ADD INDEX idx_tenant_status (tenant_id, status);
ALTER TABLE tasks ADD INDEX idx_tenant_project (tenant_id, project_id);
```

### Unpaginated Lists
**Problem**: Loading all records at once
```php
// Bad
$clients = Client::where('tenant_id', $tenantId)->get();

// Good
$clients = Client::where('tenant_id', $tenantId)->paginate(20);
```

## Testing Performance

### Unit Tests
- Test KPI caching behavior
- Verify pagination implementation
- Check database query count

### Integration Tests
- Measure page load times
- Verify security headers
- Test with realistic data volumes

### Load Testing
- Use tools like Apache Bench or Artillery
- Test with multiple concurrent users
- Monitor database performance under load

---

*Last Updated: 2025-10-06*
