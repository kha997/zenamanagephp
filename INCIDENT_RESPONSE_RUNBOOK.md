# ZenaManage Incident Response Runbook

## 🚨 **Emergency Contacts**
- **Primary On-Call**: admin@zenamanage.com
- **Secondary**: devops@zenamanage.com
- **Escalation**: CTO (cto@zenamanage.com)

## 📋 **Incident Severity Levels**

### **P0 - Critical (Response: 15 minutes)**
- Complete service outage
- Data loss or corruption
- Security breach
- Database unavailable

### **P1 - High (Response: 1 hour)**
- Major feature unavailable
- Performance degradation >50%
- High error rate >10%
- Authentication issues

### **P2 - Medium (Response: 4 hours)**
- Minor feature issues
- Performance degradation 20-50%
- Error rate 5-10%
- UI/UX problems

### **P3 - Low (Response: 24 hours)**
- Cosmetic issues
- Minor performance impact
- Error rate <5%
- Enhancement requests

## 🔍 **Initial Response Checklist**

### **1. Acknowledge & Assess**
- [ ] Acknowledge incident within SLA
- [ ] Determine severity level
- [ ] Check monitoring dashboards
- [ ] Gather initial information

### **2. Immediate Actions**
- [ ] Check system health: `./monitor-production.sh monitor`
- [ ] Review recent deployments
- [ ] Check error logs: `tail -f storage/logs/laravel.log`
- [ ] Verify database connectivity
- [ ] Check cache status

### **3. Communication**
- [ ] Notify team via Slack/Email
- [ ] Update status page if applicable
- [ ] Document incident timeline

## 🛠️ **Common Incident Scenarios**

### **Database Issues**

#### **Symptoms:**
- 500 errors
- Slow response times
- Connection timeouts
- "Database connection failed" alerts

#### **Response:**
```bash
# 1. Check database status
php artisan tinker
>>> DB::connection()->getPdo();

# 2. Check connection count
mysql -u root -p -e "SHOW PROCESSLIST;"

# 3. Check slow queries
mysql -u root -p -e "SHOW STATUS LIKE 'Slow_queries';"

# 4. Restart database if needed
sudo systemctl restart mysql
# or
brew services restart mysql
```

#### **Recovery:**
- Restart database service
- Kill long-running queries
- Increase connection pool
- Scale database resources

### **High Memory Usage**

#### **Symptoms:**
- Memory usage >85%
- "Memory limit exceeded" errors
- Slow response times
- OOM kills

#### **Response:**
```bash
# 1. Check memory usage
./monitor-performance.sh monitor

# 2. Check PHP memory
php -r "echo ini_get('memory_limit');"

# 3. Check running processes
ps aux --sort=-%mem | head -10

# 4. Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

#### **Recovery:**
- Clear application caches
- Restart PHP-FPM/Apache
- Increase memory limits
- Optimize queries
- Scale horizontally

### **High CPU Usage**

#### **Symptoms:**
- CPU usage >80%
- Slow response times
- High load average
- Timeout errors

#### **Response:**
```bash
# 1. Check CPU usage
top -o cpu

# 2. Check load average
uptime

# 3. Check PHP processes
ps aux | grep php

# 4. Check queue workers
php artisan queue:work --timeout=60
```

#### **Recovery:**
- Restart queue workers
- Optimize database queries
- Scale application servers
- Enable caching
- Review cron jobs

### **Authentication Issues**

#### **Symptoms:**
- Login failures
- Session timeouts
- 401/403 errors
- Token validation failures

#### **Response:**
```bash
# 1. Check session configuration
php artisan config:show session

# 2. Check Sanctum tokens
php artisan tinker
>>> \Laravel\Sanctum\PersonalAccessToken::count();

# 3. Check user sessions
php artisan tinker
>>> DB::table('sessions')->count();

# 4. Test authentication
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@zenamanage.com","password":"password"}'
```

#### **Recovery:**
- Clear session storage
- Regenerate application key
- Restart authentication services
- Check token expiration

### **File Upload Issues**

#### **Symptoms:**
- Upload failures
- "File too large" errors
- Storage errors
- Permission denied

#### **Response:**
```bash
# 1. Check storage permissions
ls -la storage/app/

# 2. Check disk space
df -h

# 3. Check upload limits
php -r "echo ini_get('upload_max_filesize');"
php -r "echo ini_get('post_max_size');"

# 4. Test file upload
curl -X POST http://localhost:8000/api/v1/documents \
  -H "Authorization: Bearer TOKEN" \
  -F "file=@test.txt"
```

#### **Recovery:**
- Fix storage permissions
- Increase upload limits
- Clear disk space
- Restart web server

## 📊 **Monitoring & Alerting**

### **Key Metrics to Monitor:**
- **Response Time**: <300ms (P95)
- **Error Rate**: <2%
- **CPU Usage**: <80%
- **Memory Usage**: <85%
- **Database Connections**: <100
- **Queue Size**: <1000

### **Alert Thresholds:**
- **Critical**: Service down, DB unavailable
- **Warning**: High resource usage, slow queries
- **Info**: Deployment notifications, maintenance

### **Dashboard URLs:**
- **Grafana**: http://localhost:3000 (admin/admin123)
- **Prometheus**: http://localhost:9090
- **Alertmanager**: http://localhost:9093

## 🔄 **Post-Incident Process**

### **1. Immediate Post-Incident**
- [ ] Verify service restoration
- [ ] Monitor for 30 minutes
- [ ] Update stakeholders
- [ ] Document initial findings

### **2. Post-Incident Review (Within 24 hours)**
- [ ] Schedule post-mortem meeting
- [ ] Gather timeline of events
- [ ] Identify root cause
- [ ] Document lessons learned

### **3. Follow-up Actions**
- [ ] Create improvement tickets
- [ ] Update monitoring/alerting
- [ ] Update runbook
- [ ] Share findings with team

## 🛡️ **Prevention Measures**

### **Daily Checks:**
- Monitor performance metrics
- Review error logs
- Check disk space
- Verify backups

### **Weekly Checks:**
- Review security logs
- Update dependencies
- Performance optimization
- Capacity planning

### **Monthly Checks:**
- Security audit
- Disaster recovery test
- Performance review
- Documentation update

## 📞 **Escalation Procedures**

### **Level 1 - On-Call Engineer**
- Initial response
- Basic troubleshooting
- Escalate if unresolved in 30 minutes

### **Level 2 - Senior Engineer**
- Complex technical issues
- Architecture decisions
- Escalate if unresolved in 2 hours

### **Level 3 - Engineering Manager**
- Business impact decisions
- Resource allocation
- External communication

### **Level 4 - CTO**
- Strategic decisions
- External vendor issues
- Major architectural changes

## 📝 **Incident Documentation Template**

```markdown
## Incident Report: [INCIDENT-ID]

**Date**: [DATE]
**Severity**: [P0/P1/P2/P3]
**Duration**: [START-TIME] - [END-TIME]
**Impact**: [DESCRIPTION]

### Timeline
- [TIME] - Incident detected
- [TIME] - Response started
- [TIME] - Root cause identified
- [TIME] - Resolution implemented
- [TIME] - Service restored

### Root Cause
[DESCRIPTION]

### Resolution
[DESCRIPTION]

### Lessons Learned
- [LESSON 1]
- [LESSON 2]

### Action Items
- [ ] [ACTION 1]
- [ ] [ACTION 2]
```

## 🔗 **Useful Commands**

### **System Health**
```bash
# Overall system health
./monitor-production.sh monitor

# Database health
php artisan tinker
>>> DB::connection()->getPdo();

# Cache health
php artisan cache:clear && php artisan config:cache

# Queue health
php artisan queue:work --once
```

### **Logs & Debugging**
```bash
# Application logs
tail -f storage/logs/laravel.log

# Error logs
tail -f storage/logs/laravel.log | grep ERROR

# Performance logs
tail -f storage/logs/performance-monitoring.log

# Database logs
tail -f /var/log/mysql/error.log
```

### **Emergency Commands**
```bash
# Restart services
sudo systemctl restart apache2
sudo systemctl restart mysql
php artisan queue:restart

# Clear all caches
php artisan optimize:clear

# Emergency maintenance mode
php artisan down --message="Emergency maintenance"
php artisan up
```

---

**Last Updated**: 2025-10-15
**Version**: 1.0
**Next Review**: 2025-11-15
