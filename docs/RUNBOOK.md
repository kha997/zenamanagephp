# Runbook

**Version**: 1.0  
**Last Updated**: January 2025  
**Status**: Active  
**Purpose**: Troubleshooting guide for common operational issues

---

## Overview

This runbook provides step-by-step troubleshooting procedures for common issues in ZenaManage. Each issue includes symptoms, diagnosis steps, and resolution procedures.

---

## Dashboard Performance Issues

### Issue: Dashboard loads slowly (> 500ms)

**Symptoms**:
- Dashboard takes > 500ms to load
- Users report slow dashboard
- Metrics show p95 > 500ms

**Diagnosis Steps**:

1. **Check Database Queries**:
   ```bash
   # Enable query logging
   DB::enableQueryLog();
   # Load dashboard
   # Check logs
   Log::info(DB::getQueryLog());
   ```

2. **Check Cache**:
   ```bash
   # Check cache hit rate
   php artisan tinker
   >>> app(\App\Services\MetricsService::class)->getCacheHitRate()
   ```

3. **Check KPI Cache**:
   ```bash
   # Verify KPI cache is working
   Cache::get('kpi:tenant_123:dashboard')
   ```

**Resolution**:

1. **Optimize Queries**:
   - Add eager loading: `Project::with('tasks', 'members')->get()`
   - Add indexes: `INDEX (tenant_id, status)`
   - Use projections: Select only needed fields

2. **Increase Cache TTL**:
   ```php
   // config/cache.php
   'kpi_cache_ttl' => 120, // Increase from 60 to 120 seconds
   ```

3. **Check N+1 Queries**:
   ```bash
   # Use Laravel Debugbar or Telescope
   # Identify N+1 queries
   # Add eager loading
   ```

---

### Issue: Dashboard shows incorrect data

**Symptoms**:
- Dashboard shows stale data
- KPI values don't match actual data
- Cache not refreshing

**Diagnosis Steps**:

1. **Check Cache Freshness**:
   ```bash
   php artisan tinker
   >>> Cache::get('kpi:tenant_123:dashboard')
   >>> // Check timestamp
   ```

2. **Check Cache Invalidation**:
   ```bash
   # Check if cache is invalidated on mutations
   # Look for CacheInvalidationService calls
   ```

**Resolution**:

1. **Clear Cache**:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   ```

2. **Verify Cache Invalidation**:
   ```php
   // Ensure mutations call cache invalidation
   CacheInvalidationService::invalidateKpi($tenantId);
   ```

---

## API Performance Issues

### Issue: API endpoint slow (> 300ms)

**Symptoms**:
- API endpoint takes > 300ms
- Metrics show p95 > 300ms
- Users report slow API

**Diagnosis Steps**:

1. **Check Metrics**:
   ```bash
   # Get p95 latency for endpoint
   curl http://localhost:8000/api/v1/metrics
   ```

2. **Check Database Queries**:
   ```bash
   # Enable query logging
   # Check for slow queries
   ```

3. **Check Cache**:
   ```bash
   # Verify cache is being used
   # Check cache hit rate
   ```

**Resolution**:

1. **Optimize Database**:
   - Add indexes
   - Optimize queries
   - Use eager loading

2. **Add Caching**:
   ```php
   // Cache expensive operations
   Cache::remember("key", 60, function() {
       return expensiveOperation();
   });
   ```

3. **Use Queue for Heavy Operations**:
   ```php
   // Move heavy operations to queue
   ProcessHeavyOperationJob::dispatch($data);
   ```

---

### Issue: API errors increasing

**Symptoms**:
- Error rate > 1%
- Logs show many errors
- Users report errors

**Diagnosis Steps**:

1. **Check Error Logs**:
   ```bash
   # Check Laravel logs
   tail -f storage/logs/laravel.log | grep ERROR
   ```

2. **Check Error Rate**:
   ```bash
   # Get error rate from metrics
   curl http://localhost:8000/api/v1/metrics
   ```

3. **Check Specific Errors**:
   ```bash
   # Group errors by code
   # Check most common errors
   ```

**Resolution**:

1. **Fix Root Cause**:
   - Identify most common error
   - Fix underlying issue
   - Deploy fix

2. **Add Error Handling**:
   ```php
   // Add try-catch blocks
   // Use ErrorEnvelopeService for consistent errors
   ```

3. **Monitor**:
   - Set up alerts for error spikes
   - Monitor error trends

---

## Database Issues

### Issue: Slow queries (> 100ms)

**Symptoms**:
- Queries take > 100ms
- Database CPU high
- Slow page loads

**Diagnosis Steps**:

1. **Check Slow Query Log**:
   ```bash
   # MySQL slow query log
   # Check for queries > 300ms
   ```

2. **Check Indexes**:
   ```sql
   -- Check if indexes exist
   SHOW INDEXES FROM projects;
   ```

3. **Check Query Plans**:
   ```sql
   EXPLAIN SELECT * FROM projects WHERE tenant_id = ?;
   ```

**Resolution**:

1. **Add Indexes**:
   ```sql
   -- Add composite index
   CREATE INDEX idx_tenant_status ON projects(tenant_id, status);
   ```

2. **Optimize Queries**:
   ```php
   // Use eager loading
   Project::with('tasks')->get();
   
   // Use select() to limit fields
   Project::select('id', 'name')->get();
   ```

3. **Check N+1 Queries**:
   ```php
   // Use Laravel Debugbar to identify
   // Add eager loading
   ```

---

### Issue: Database connection errors

**Symptoms**:
- "Connection refused" errors
- "Too many connections" errors
- Database unavailable

**Diagnosis Steps**:

1. **Check Database Status**:
   ```bash
   # Check if database is running
   mysqladmin ping
   ```

2. **Check Connection Pool**:
   ```bash
   # Check active connections
   SHOW PROCESSLIST;
   ```

3. **Check Health Endpoint**:
   ```bash
   curl http://localhost:8000/api/v1/health
   ```

**Resolution**:

1. **Restart Database**:
   ```bash
   # Restart MySQL
   sudo systemctl restart mysql
   ```

2. **Increase Connection Limit**:
   ```ini
   # my.cnf
   max_connections = 200
   ```

3. **Check for Connection Leaks**:
   ```php
   // Ensure connections are closed
   // Use connection pooling
   ```

---

## Cache Issues

### Issue: Cache hit rate low (< 80%)

**Symptoms**:
- Cache hit rate < 80%
- Slow performance
- High database load

**Diagnosis Steps**:

1. **Check Cache Hit Rate**:
   ```bash
   php artisan tinker
   >>> app(\App\Services\MetricsService::class)->getCacheHitRate()
   ```

2. **Check Cache Keys**:
   ```bash
   # Check Redis keys
   redis-cli KEYS "kpi:*"
   ```

3. **Check Cache TTL**:
   ```php
   // Verify cache TTL is appropriate
   Cache::get('kpi:tenant_123:dashboard')
   ```

**Resolution**:

1. **Increase Cache TTL**:
   ```php
   // Increase TTL for stable data
   Cache::remember('key', 300, function() { ... }); // 5 minutes
   ```

2. **Cache More Data**:
   ```php
   // Cache expensive queries
   // Cache API responses
   ```

3. **Check Cache Invalidation**:
   ```php
   // Ensure cache is invalidated correctly
   // Don't invalidate too aggressively
   ```

---

### Issue: Cache not working

**Symptoms**:
- Cache always misses
- Cache returns null
- Performance degraded

**Diagnosis Steps**:

1. **Check Cache Connection**:
   ```bash
   # Test Redis connection
   redis-cli ping
   ```

2. **Check Cache Configuration**:
   ```bash
   # Check .env
   CACHE_DRIVER=redis
   REDIS_HOST=127.0.0.1
   ```

3. **Check Cache Service**:
   ```bash
   php artisan tinker
   >>> Cache::put('test', 'value', 60);
   >>> Cache::get('test');
   ```

**Resolution**:

1. **Restart Cache**:
   ```bash
   # Restart Redis
   sudo systemctl restart redis
   ```

2. **Clear Cache**:
   ```bash
   php artisan cache:clear
   ```

3. **Check Cache Driver**:
   ```bash
   # Verify CACHE_DRIVER is set correctly
   # Use Redis for production
   ```

---

## Queue Issues

### Issue: Queue backlog increasing

**Symptoms**:
- Queue backlog > 1000 jobs
- Jobs not processing
- Slow job processing

**Diagnosis Steps**:

1. **Check Queue Status**:
   ```bash
   # Check pending jobs
   php artisan queue:work --queue=default
   ```

2. **Check Failed Jobs**:
   ```bash
   # Check failed jobs table
   php artisan queue:failed
   ```

3. **Check Queue Workers**:
   ```bash
   # Check if workers are running
   ps aux | grep queue:work
   ```

**Resolution**:

1. **Restart Queue Workers**:
   ```bash
   # Restart workers
   php artisan queue:restart
   ```

2. **Increase Workers**:
   ```bash
   # Run more workers
   php artisan queue:work --workers=4
   ```

3. **Process Failed Jobs**:
   ```bash
   # Retry failed jobs
   php artisan queue:retry all
   ```

---

### Issue: Jobs failing

**Symptoms**:
- Many failed jobs
- Jobs not completing
- Error logs show job failures

**Diagnosis Steps**:

1. **Check Failed Jobs**:
   ```bash
   php artisan queue:failed
   ```

2. **Check Job Logs**:
   ```bash
   # Check Laravel logs
   tail -f storage/logs/laravel.log | grep "failed"
   ```

3. **Check Job Payload**:
   ```bash
   # Inspect failed job payload
   # Check for invalid data
   ```

**Resolution**:

1. **Fix Job Code**:
   - Identify error in job
   - Fix underlying issue
   - Redeploy

2. **Retry Failed Jobs**:
   ```bash
   # Retry specific job
   php artisan queue:retry {job_id}
   ```

3. **Add Error Handling**:
   ```php
   // Add try-catch in jobs
   // Log errors properly
   ```

---

## WebSocket Issues

### Issue: WebSocket not connecting

**Symptoms**:
- WebSocket connection fails
- Clients cannot connect
- Connection timeout

**Diagnosis Steps**:

1. **Check WebSocket Service**:
   ```bash
   # Check if WebSocket server is running
   ps aux | grep websocket
   ```

2. **Check Health Endpoint**:
   ```bash
   curl http://localhost:8000/api/v1/ws/health
   ```

3. **Check Authentication**:
   ```bash
   # Verify token is valid
   # Check AuthGuard logs
   ```

**Resolution**:

1. **Restart WebSocket Server**:
   ```bash
   # Restart WebSocket server
   php artisan websocket:serve
   ```

2. **Check Port**:
   ```bash
   # Verify port is not blocked
   # Check firewall rules
   ```

3. **Check Authentication**:
   ```php
   // Verify token validation
   // Check AuthGuard implementation
   ```

---

### Issue: WebSocket messages not received

**Symptoms**:
- Connection established but no messages
- Messages sent but not delivered
- Subscription not working

**Diagnosis Steps**:

1. **Check Subscription**:
   ```bash
   # Verify subscription was successful
   # Check channel format
   ```

2. **Check Tenant Isolation**:
   ```bash
   # Verify tenant_id matches
   # Check cross-tenant blocking
   ```

3. **Check Permissions**:
   ```bash
   # Verify user has permission
   # Check RBAC logs
   ```

**Resolution**:

1. **Verify Channel Format**:
   ```javascript
   // Use correct format
   channel: `tenant:${tenantId}:tasks:${taskId}`
   ```

2. **Check Tenant ID**:
   ```javascript
   // Ensure tenant_id matches user's tenant
   ```

3. **Check Permissions**:
   ```php
   // Verify user has required permission
   $user->can('tasks.view');
   ```

---

## Tenant Isolation Issues

### Issue: User cannot see their data

**Symptoms**:
- User cannot see projects/tasks
- Data appears empty
- 404 errors for existing resources

**Diagnosis Steps**:

1. **Check Tenant ID**:
   ```bash
   # Verify user has tenant_id
   php artisan tinker
   >>> $user = User::find('user_id');
   >>> $user->tenant_id;
   ```

2. **Check Middleware**:
   ```bash
   # Verify TenantIsolationMiddleware is applied
   # Check route middleware
   ```

3. **Check Model Trait**:
   ```php
   // Verify model uses BelongsToTenant
   use App\Models\Concerns\BelongsToTenant;
   ```

**Resolution**:

1. **Set Tenant ID**:
   ```php
   // Ensure user has tenant_id
   $user->tenant_id = $tenantId;
   $user->save();
   ```

2. **Verify Middleware**:
   ```php
   // Ensure middleware is in route group
   Route::middleware(['tenant.isolation'])->group(...);
   ```

3. **Check Model**:
   ```php
   // Ensure model uses BelongsToTenant trait
   class Project extends Model {
       use BelongsToTenant;
   }
   ```

---

### Issue: Cross-tenant access detected

**Symptoms**:
- Logs show "cross-tenant access attempt"
- User can see other tenant's data
- Security violation

**Diagnosis Steps**:

1. **Check Logs**:
   ```bash
   # Check for cross-tenant attempts
   tail -f storage/logs/laravel.log | grep "cross-tenant"
   ```

2. **Check Global Scope**:
   ```php
   // Verify BelongsToTenant trait is applied
   // Check Global Scope is active
   ```

3. **Check Middleware**:
   ```bash
   # Verify TenantIsolationMiddleware is applied
   # Check all API routes
   ```

**Resolution**:

1. **Fix Immediately**:
   - Block access immediately
   - Investigate root cause
   - Fix security issue

2. **Verify Isolation**:
   ```php
   // Run tenant isolation tests
   php artisan test --filter TenantIsolation
   ```

3. **Audit Access**:
   ```bash
   # Check audit logs
   # Identify all cross-tenant access
   ```

---

## Authentication Issues

### Issue: Users cannot log in

**Symptoms**:
- Login fails
- "Invalid credentials" error
- Token generation fails

**Diagnosis Steps**:

1. **Check User Status**:
   ```bash
   php artisan tinker
   >>> $user = User::where('email', 'user@example.com')->first();
   >>> $user->is_active;
   ```

2. **Check Password**:
   ```bash
   # Verify password hash
   Hash::check('password', $user->password);
   ```

3. **Check Sanctum**:
   ```bash
   # Verify Sanctum is configured
   # Check token generation
   ```

**Resolution**:

1. **Reset Password**:
   ```bash
   php artisan tinker
   >>> $user->password = Hash::make('new_password');
   >>> $user->save();
   ```

2. **Check User Status**:
   ```php
   // Ensure user is active
   $user->is_active = true;
   $user->save();
   ```

3. **Verify Sanctum**:
   ```bash
   # Check Sanctum configuration
   # Verify token generation works
   ```

---

## Error Tracking

### Issue: Errors not being logged

**Symptoms**:
- Errors occur but not logged
- No error traces
- Debugging difficult

**Diagnosis Steps**:

1. **Check Log Configuration**:
   ```bash
   # Check .env
   LOG_CHANNEL=stack
   LOG_LEVEL=debug
   ```

2. **Check Log Permissions**:
   ```bash
   # Verify log directory is writable
   chmod -R 755 storage/logs
   ```

3. **Check Error Handler**:
   ```php
   // Verify app/Exceptions/Handler.php
   // Check error logging
   ```

**Resolution**:

1. **Fix Log Configuration**:
   ```env
   LOG_CHANNEL=stack
   LOG_LEVEL=error
   ```

2. **Fix Permissions**:
   ```bash
   chmod -R 755 storage/logs
   ```

3. **Verify Error Handler**:
   ```php
   // Ensure errors are logged
   Log::error($exception->getMessage(), [
       'exception' => $exception,
   ]);
   ```

---

## Performance Monitoring

### Issue: SLO violations

**Symptoms**:
- SLO alerts triggered
- Performance degraded
- Metrics show violations

**Diagnosis Steps**:

1. **Check SLO Status**:
   ```bash
   php artisan slo:check
   ```

2. **Check Metrics**:
   ```bash
   curl http://localhost:8000/api/v1/metrics
   ```

3. **Check Alerts**:
   ```bash
   # Check alert logs
   # Review SLO violations
   ```

**Resolution**:

1. **Identify Root Cause**:
   - Check which SLO is violated
   - Identify performance bottleneck
   - Fix underlying issue

2. **Optimize**:
   - Add caching
   - Optimize queries
   - Use queues

3. **Monitor**:
   - Set up alerts
   - Track trends
   - Review regularly

---

## Emergency Procedures

### System Down

**Symptoms**:
- All requests failing
- 500 errors
- Service unavailable

**Immediate Actions**:

1. **Check Health**:
   ```bash
   curl http://localhost:8000/api/v1/health
   ```

2. **Check Logs**:
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Check Database**:
   ```bash
   mysqladmin ping
   ```

4. **Restart Services**:
   ```bash
   # Restart PHP-FPM
   sudo systemctl restart php-fpm
   
   # Restart Nginx
   sudo systemctl restart nginx
   ```

---

### Data Corruption

**Symptoms**:
- Data inconsistencies
- Foreign key violations
- Orphaned records

**Immediate Actions**:

1. **Stop Writes**:
   ```bash
   # Put system in maintenance mode
   php artisan down
   ```

2. **Backup Database**:
   ```bash
   mysqldump -u user -p database > backup.sql
   ```

3. **Fix Data**:
   ```sql
   -- Fix orphaned records
   -- Restore foreign keys
   ```

4. **Verify**:
   ```bash
   # Run data integrity checks
   php artisan db:check-integrity
   ```

---

## Escalation

### When to Escalate

- **Critical**: System down, data corruption, security breach
- **High**: SLO violations, performance degradation, errors increasing
- **Medium**: Minor issues, optimization opportunities

### Escalation Contacts

- **On-Call Engineer**: Check PagerDuty/Slack
- **Team Lead**: For architecture decisions
- **Security Team**: For security incidents

---

## References

- [Observability](OBSERVABILITY.md)
- [Multi-Tenant Architecture](MULTI_TENANT_ARCHITECTURE.md)
- [WebSocket Security Checklist](WEBSOCKET_SECURITY_CHECKLIST.md)
- [Security Environment Matrix](SECURITY_ENVIRONMENT_MATRIX.md)

---

*This runbook should be updated as new issues are discovered and resolved.*

