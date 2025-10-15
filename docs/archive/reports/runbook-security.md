# Security Center Runbook

## Overview
This runbook provides operational procedures for the ZenaManage Security Center, including deployment, monitoring, troubleshooting, and incident response.

## Table of Contents
1. [Deployment](#deployment)
2. [Smoke Tests](#smoke-tests)
3. [Monitoring](#monitoring)
4. [Troubleshooting](#troubleshooting)
5. [Incident Response](#incident-response)
6. [Maintenance](#maintenance)

## Deployment

### Pre-deployment Checklist
- [ ] Database migrations up to date
- [ ] Redis server running and accessible
- [ ] Environment variables configured
- [ ] SSL certificates valid
- [ ] Backup completed

### Deployment Steps
```bash
# 1. Pull latest code
git pull origin main

# 2. Install dependencies
composer install --no-dev --optimize-autoloader

# 3. Run migrations
php artisan migrate --force

# 4. Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 5. Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Restart services
php artisan queue:restart
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
```

### Post-deployment Verification
```bash
# Run smoke tests
./scripts/smoke-test.sh

# Check service status
sudo systemctl status nginx
sudo systemctl status php8.2-fpm
sudo systemctl status redis-server
```

## Smoke Tests

### Automated Smoke Test Script
```bash
#!/bin/bash
# scripts/smoke-test.sh

set -e

BASE_URL="http://localhost:8000"
TOKEN="${ADMIN_TOKEN}"

echo "🔍 Running Security Center smoke tests..."

# Test 1: KPIs endpoint
echo "Testing KPIs endpoint..."
RESPONSE=$(curl -s -w "%{http_code}" -H "Authorization: Bearer $TOKEN" \
  "$BASE_URL/api/admin/security/kpis?period=30d")
HTTP_CODE="${RESPONSE: -3}"
if [ "$HTTP_CODE" = "200" ]; then
  echo "✅ KPIs endpoint: OK"
else
  echo "❌ KPIs endpoint: FAILED ($HTTP_CODE)"
  exit 1
fi

# Test 2: MFA users endpoint
echo "Testing MFA users endpoint..."
RESPONSE=$(curl -s -w "%{http_code}" -H "Authorization: Bearer $TOKEN" \
  "$BASE_URL/api/admin/security/mfa?per_page=5")
HTTP_CODE="${RESPONSE: -3}"
if [ "$HTTP_CODE" = "200" ]; then
  echo "✅ MFA users endpoint: OK"
else
  echo "❌ MFA users endpoint: FAILED ($HTTP_CODE)"
  exit 1
fi

# Test 3: Audit logs endpoint
echo "Testing audit logs endpoint..."
RESPONSE=$(curl -s -w "%{http_code}" -H "Authorization: Bearer $TOKEN" \
  "$BASE_URL/api/admin/security/audit?per_page=5")
HTTP_CODE="${RESPONSE: -3}"
if [ "$HTTP_CODE" = "200" ]; then
  echo "✅ Audit logs endpoint: OK"
else
  echo "❌ Audit logs endpoint: FAILED ($HTTP_CODE)"
  exit 1
fi

# Test 4: CSV export
echo "Testing CSV export..."
RESPONSE=$(curl -s -w "%{http_code}" -H "Authorization: Bearer $TOKEN" \
  "$BASE_URL/api/admin/security/audit/export" -o /tmp/audit.csv)
HTTP_CODE="${RESPONSE: -3}"
if [ "$HTTP_CODE" = "200" ]; then
  echo "✅ CSV export: OK"
  rm -f /tmp/audit.csv
else
  echo "❌ CSV export: FAILED ($HTTP_CODE)"
  exit 1
fi

# Test 5: Test event
echo "Testing WebSocket event..."
RESPONSE=$(curl -s -w "%{http_code}" -X POST -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"event":"login_failed"}' \
  "$BASE_URL/api/admin/security/test-event")
HTTP_CODE="${RESPONSE: -3}"
if [ "$HTTP_CODE" = "200" ]; then
  echo "✅ WebSocket event: OK"
else
  echo "❌ WebSocket event: FAILED ($HTTP_CODE)"
  exit 1
fi

echo "🎉 All smoke tests passed!"
```

### Manual Smoke Tests
```bash
# Set environment variables
export TOKEN="${ADMIN_TOKEN}"
export BASE_URL="http://localhost:8000"

# 1. KPIs endpoint
curl -sS -H "Authorization: Bearer $TOKEN" \
  "$BASE_URL/api/admin/security/kpis?period=30d" | jq '.data.mfaAdoption.value'

# 2. MFA users list
curl -sS -H "Authorization: Bearer $TOKEN" \
  "$BASE_URL/api/admin/security/mfa?per_page=5" | jq '.meta.total'

# 3. Audit logs with ETag
ETAG=$(curl -sI -H "Authorization: Bearer $TOKEN" \
  "$BASE_URL/api/admin/security/audit?severity=high" | grep ETag | cut -d' ' -f2)
curl -sI -H "Authorization: Bearer $TOKEN" -H "If-None-Match: $ETAG" \
  "$BASE_URL/api/admin/security/audit?severity=high"

# 4. CSV export (check rate limit & headers)
curl -sS -D - -H "Authorization: Bearer $TOKEN" \
  "$BASE_URL/api/admin/security/audit/export" -o audit.csv
head -5 audit.csv

# 5. Test event (check Redis fallback)
curl -sS -X POST -H "Authorization: Bearer $TOKEN" \
  -d '{"event":"login_failed"}' \
  "$BASE_URL/api/admin/security/test-event" | jq '.broadcast_status'

# 6. Test 422 validation error
curl -sS -H "Authorization: Bearer $TOKEN" \
  "$BASE_URL/api/admin/security/kpis?period=invalid" | jq '.error.code'

# 7. Test 304 ETag
ETAG=$(curl -sI -H "Authorization: Bearer $TOKEN" \
  "$BASE_URL/api/admin/security/audit?per_page=5" | grep ETag | cut -d' ' -f2)
curl -sI -H "Authorization: Bearer $TOKEN" -H "If-None-Match: $ETAG" \
  "$BASE_URL/api/admin/security/audit?per_page=5" | grep "304"

# 8. Test CSV injection protection
curl -sS -H "Authorization: Bearer $TOKEN" \
  "$BASE_URL/api/admin/security/audit/export?action==1+2" -o /tmp/injection.csv
head -3 /tmp/injection.csv
```

## Monitoring

### Key Metrics to Monitor

#### Application Metrics
- **HTTP 5xx error rate**: < 1%
- **API response time p95**: < 300ms
- **Memory usage**: < 100MB
- **CPU usage**: < 50%

#### Security Metrics
- **Failed login attempts**: Monitor for spikes
- **MFA adoption rate**: Track progress
- **Export rate limit hits**: < 5%
- **WebSocket connection drops**: < 2%

#### Infrastructure Metrics
- **Redis memory usage**: < 80%
- **Database connection pool**: < 80%
- **Queue length**: < 1000 jobs
- **Event backlog size**: < 500 events
- **Disk space**: > 20% free

### Monitoring Commands
```bash
# Check application health
curl -s "$BASE_URL/api/admin/security/kpis" | jq '.meta.generatedAt'

# Monitor Redis
redis-cli info memory | grep used_memory_human
redis-cli info clients | grep connected_clients

# Check queue status
php artisan queue:monitor

# Monitor database
mysql -e "SHOW PROCESSLIST;" | grep -v Sleep

# Check event backlog
redis-cli llen "security_events"

# Check logs with correlation ID
tail -f storage/logs/laravel.log | grep -E "(ERROR|CRITICAL)" | grep -E "X-Request-Id"
```

### Alert Thresholds
- **5xx errors** > 5% in 5 minutes
- **API p95** > 500ms for 10 minutes
- **Export 429** > 20% in 1 hour
- **Memory** > 150MB
- **CPU** > 80% for 5 minutes
- **Redis memory** > 90%
- **Queue length** > 5000 jobs

## Troubleshooting

### Common Issues

#### 1. Export 429 Rate Limit
**Symptoms**: Users getting "Too many exports" error  
**Cause**: Rate limit exceeded (10 req/min)  
**Diagnosis**:
```bash
# Check current rate limit status
redis-cli get "security_export:user_id"

# Check rate limit configuration
grep -r "enforceRateLimit" app/Http/Controllers/Admin/
```
**Solution**:
```bash
# Reset rate limit (emergency)
redis-cli del "security_export:user_id"

# Increase quota via environment (permanent)
# Set RATE_LIMIT_EXPORT_PER_MIN=20 in .env
# Restart application
```

#### 2. Realtime Connection Lost
**Symptoms**: "Offline" status, no real-time updates  
**Diagnosis**:
```bash
# Verify Redis connection
redis-cli ping

# Check queue worker
php artisan queue:work --once

# Verify broadcaster config
php artisan config:show broadcasting

# Check WebSocket server status
ps aux | grep websocket
```
**Solution**:
```bash
# Restart queue worker
php artisan queue:restart

# Clear broadcast cache
php artisan cache:clear

# Check Redis memory
redis-cli info memory

# Restart Redis if needed
sudo systemctl restart redis-server
```

#### 3. Authentication Errors
**Symptoms**: 401/403 errors, "AuthManager not callable"  
**Diagnosis**:
```bash
# Check token validity
php artisan tinker
>>> $user = User::find(1);
>>> $user->tokens;

# Verify middleware
php artisan route:list --name=security

# Check auth config
php artisan config:show auth
```
**Solution**:
```bash
# Regenerate app key
php artisan key:generate

# Clear config cache
php artisan config:clear

# Check Sanctum configuration
php artisan config:show sanctum
```

#### 4. Performance Issues
**Symptoms**: Slow API responses, high memory usage  
**Diagnosis**:
```bash
# Check slow queries
tail -f storage/logs/laravel.log | grep "slow"

# Monitor memory
php artisan tinker
>>> memory_get_usage(true)

# Check database indexes
php artisan tinker
>>> DB::select('SHOW INDEX FROM audit_logs');
```
**Solution**:
```bash
# Optimize database
php artisan db:optimize

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
```

#### 5. Chart.js Loading Issues
**Symptoms**: Charts not rendering, JavaScript errors  
**Diagnosis**:
```bash
# Check CDN availability
curl -I https://cdn.jsdelivr.net/npm/chart.js

# Check browser console
# Look for Chart.js errors
```
**Solution**:
```bash
# Bundle Chart.js locally
npm install chart.js
# Update resources/js/security/charts.js
# Remove CDN dependency
```

### Database Issues

#### Slow Queries
```sql
-- Check slow query log
SHOW VARIABLES LIKE 'slow_query_log';
SHOW VARIABLES LIKE 'long_query_time';

-- Analyze query performance
EXPLAIN SELECT * FROM audit_logs WHERE created_at >= '2025-09-01' AND action = 'login_failed';

-- Check index usage
SHOW INDEX FROM audit_logs;
```

#### Connection Issues
```bash
# Check database connections
mysql -e "SHOW PROCESSLIST;" | grep -v Sleep

# Monitor connection pool
mysql -e "SHOW STATUS LIKE 'Threads_connected';"
mysql -e "SHOW STATUS LIKE 'Max_used_connections';"
```

### Redis Issues

#### Memory Usage
```bash
# Check Redis memory
redis-cli info memory

# Monitor memory usage
redis-cli --latency-history -i 1

# Clear expired keys
redis-cli --scan --pattern "*" | xargs redis-cli del
```

#### Connection Issues
```bash
# Check Redis status
redis-cli ping

# Monitor connections
redis-cli info clients

# Check configuration
redis-cli config get "*"
```

## Incident Response

### Severity Levels

#### P1 - Critical (Response: 15 minutes)
- Security Center completely down
- Data breach or unauthorized access
- All exports failing
- Database corruption
- Unauthorized access to sensitive data

#### P2 - High (Response: 1 hour)
- Performance degradation > 50%
- Partial functionality loss
- Rate limiting issues
- WebSocket connection failures

#### P3 - Medium (Response: 4 hours)
- Minor performance issues
- Non-critical feature failures
- Monitoring alerts
- Documentation updates

### Incident Response Process

1. **Acknowledge** (5 minutes)
   - Confirm incident
   - Assign severity level
   - Notify stakeholders

2. **Investigate** (15-30 minutes)
   - Check monitoring dashboards
   - Review logs
   - Identify root cause

3. **Resolve** (1-4 hours)
   - Implement fix
   - Test solution
   - Monitor for stability

4. **Post-mortem** (24-48 hours)
   - Document incident
   - Identify improvements
   - Update runbook

### Data Breach Response (P1)

**Immediate Actions (0-15 minutes):**
1. **Disable exports**: Set `RATE_LIMIT_EXPORT_PER_MIN=0`
2. **Rotate API keys**: Generate new admin tokens
3. **Notify SOC**: security@zenamanage.com
4. **Notify customers**: If data exposed
5. **Preserve evidence**: Enable audit logging

**Investigation (15-60 minutes):**
1. **Check access logs**: `grep -E "unauthorized|forbidden" storage/logs/laravel.log`
2. **Review audit trail**: Check for suspicious activities
3. **Identify scope**: What data was accessed?
4. **Document timeline**: When did breach occur?

**Recovery (1-4 hours):**
1. **Patch vulnerability**: Deploy security fix
2. **Reset compromised accounts**: Force password reset
3. **Update security policies**: Strengthen access controls
4. **Monitor for continued attacks**: Enhanced logging

**Communication:**
- **Internal**: Notify all stakeholders within 1 hour
- **External**: Notify affected customers within 24 hours
- **Regulatory**: Report to authorities if required

### Emergency Contacts
- **On-call Engineer**: +1-555-0123
- **Security Team**: security@zenamanage.com
- **DevOps Team**: devops@zenamanage.com
- **Management**: management@zenamanage.com

### Rollback Procedures

#### Emergency Rollback
```bash
# 1. Backup database before rollback
mysqldump -u root -p zenamanage > backup_before_rollback_$(date +%Y%m%d_%H%M%S).sql

# 2. Disable real-time features
export BROADCAST_DRIVER=log
export QUEUE_CONNECTION=sync

# 3. Revert to previous commit
git revert HEAD
git push origin main

# 4. Run migrations
php artisan migrate:rollback --step=1

# 5. Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# 6. Restart services
php artisan queue:restart
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
```

#### Feature Flags
```bash
# Disable auth for testing
export SECURITY_AUTH_BYPASS=true

# Disable real-time
export BROADCAST_DRIVER=log

# Disable background jobs
export QUEUE_CONNECTION=sync
```

## Maintenance

### Daily Tasks
- [ ] Check monitoring dashboards
- [ ] Review error logs
- [ ] Monitor performance metrics
- [ ] Check Redis memory usage

### Weekly Tasks
- [ ] Review security events
- [ ] Check database performance
- [ ] Update documentation
- [ ] Test backup procedures

### Monthly Tasks
- [ ] Security audit
- [ ] Performance optimization
- [ ] Dependency updates
- [ ] Capacity planning

### Quarterly Tasks
- [ ] Penetration testing
- [ ] Backup restoration test
- [ ] API key rotation
- [ ] Security policy review
- [ ] Disaster recovery drill

### Backup Procedures

#### Database Backup
```bash
# Daily backup
mysqldump -u root -p zenamanage > backup_$(date +%Y%m%d).sql

# Compress backup
gzip backup_$(date +%Y%m%d).sql

# Upload to S3
aws s3 cp backup_$(date +%Y%m%d).sql.gz s3://zenamanage-backups/
```

#### Redis Backup
```bash
# Create Redis dump
redis-cli BGSAVE

# Copy dump file
cp /var/lib/redis/dump.rdb backup_redis_$(date +%Y%m%d).rdb

# Upload to S3
aws s3 cp backup_redis_$(date +%Y%m%d).rdb s3://zenamanage-backups/
```

### Log Management

#### Log Rotation
```bash
# Configure logrotate
sudo nano /etc/logrotate.d/zenamanage

# Content:
/var/www/zenamanage/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        sudo systemctl reload php8.2-fpm
    endscript
}
```

#### Log Analysis
```bash
# Analyze error patterns
grep "ERROR" storage/logs/laravel.log | cut -d' ' -f1-3 | sort | uniq -c

# Check performance issues
grep "slow" storage/logs/laravel.log | tail -20

# Monitor security events
grep -E "(login_failed|unauthorized)" storage/logs/laravel.log | tail -20
```

---

## Quick Reference

### Useful Commands
```bash
# Check service status
sudo systemctl status nginx php8.2-fpm redis-server

# View logs
tail -f storage/logs/laravel.log

# Clear caches
php artisan cache:clear && php artisan config:clear

# Restart services
php artisan queue:restart
sudo systemctl restart nginx

# Check Redis
redis-cli ping
redis-cli info memory

# Monitor database
mysql -e "SHOW PROCESSLIST;"
```

### Emergency Procedures
1. **Service Down**: Restart nginx, php-fpm, redis
2. **High Memory**: Clear caches, restart services
3. **Database Issues**: Check connections, restart MySQL
4. **Security Incident**: Disable features, notify team
5. **Performance Issues**: Check indexes, optimize queries

---

*Last Updated: 2025-09-28*  
*Version: 1.0.0*
