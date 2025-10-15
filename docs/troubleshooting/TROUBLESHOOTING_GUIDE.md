# ZENAMANAGE TROUBLESHOOTING GUIDE

## 🔧 COMPREHENSIVE TROUBLESHOOTING GUIDE

**Version**: 2.0  
**Last Updated**: 2025-01-08  
**Status**: Production Ready

---

## 🎯 TABLE OF CONTENTS

1. [Troubleshooting Overview](#troubleshooting-overview)
2. [Common Issues](#common-issues)
3. [Application Issues](#application-issues)
4. [Database Issues](#database-issues)
5. [Performance Issues](#performance-issues)
6. [Security Issues](#security-issues)
7. [Network Issues](#network-issues)
8. [File System Issues](#file-system-issues)
9. [Log Analysis](#log-analysis)
10. [Debugging Tools](#debugging-tools)
11. [Emergency Procedures](#emergency-procedures)
12. [Prevention Strategies](#prevention-strategies)

---

## 🔍 TROUBLESHOOTING OVERVIEW

### Troubleshooting Methodology
1. **Identify**: Determine the nature and scope of the issue
2. **Isolate**: Narrow down the problem to specific components
3. **Diagnose**: Use tools and logs to identify root cause
4. **Resolve**: Implement appropriate fix
5. **Verify**: Confirm the issue is resolved
6. **Document**: Record the issue and solution for future reference

### Issue Classification
- **Critical**: System down, data loss, security breach
- **High**: Major functionality affected, performance degradation
- **Medium**: Minor functionality affected, workarounds available
- **Low**: Cosmetic issues, minor inconveniences

### Response Times
- **Critical**: Immediate response (within 15 minutes)
- **High**: Response within 1 hour
- **Medium**: Response within 4 hours
- **Low**: Response within 24 hours

---

## 🚨 COMMON ISSUES

### 1. Application Not Loading

#### Symptoms
- Blank page or "500 Internal Server Error"
- "Service Unavailable" message
- Application loads but shows errors

#### Diagnosis Steps
```bash
# Check Nginx status
sudo systemctl status nginx

# Check PHP-FPM status
sudo systemctl status php8.2-fpm

# Check application logs
tail -f /var/log/nginx/zenamanage_error.log
tail -f /var/log/zenamanage/application.log

# Check file permissions
ls -la /var/www/zenamanage/
```

#### Common Causes
- **File Permissions**: Incorrect ownership or permissions
- **PHP-FPM**: Service not running or misconfigured
- **Nginx**: Configuration errors or service down
- **Application**: Missing dependencies or configuration errors

#### Solutions
```bash
# Fix file permissions
sudo chown -R zenamanage:www-data /var/www/zenamanage
sudo chmod -R 755 /var/www/zenamanage
sudo chmod -R 775 /var/www/zenamanage/storage
sudo chmod -R 775 /var/www/zenamanage/bootstrap/cache

# Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx

# Clear application cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### 2. Database Connection Issues

#### Symptoms
- "Database connection failed" errors
- "SQLSTATE[HY000] [2002] Connection refused"
- Application loads but database operations fail

#### Diagnosis Steps
```bash
# Test database connection
mysql -u zenamanage_user -p -h localhost zenamanage_prod

# Check MySQL status
sudo systemctl status mysql

# Check MySQL logs
tail -f /var/log/mysql/error.log

# Check database configuration
php artisan tinker
DB::connection()->getPdo();
```

#### Common Causes
- **MySQL Service**: Service not running
- **Credentials**: Incorrect username/password
- **Network**: Connection blocked by firewall
- **Configuration**: Wrong host/port settings

#### Solutions
```bash
# Start MySQL service
sudo systemctl start mysql

# Check MySQL configuration
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf

# Verify database credentials
mysql -u zenamanage_user -p -e "SELECT 1;"

# Check firewall rules
sudo ufw status
sudo ufw allow 3306/tcp
```

### 3. Cache Issues

#### Symptoms
- Slow page loads
- "Cache connection failed" errors
- Data not updating properly

#### Diagnosis Steps
```bash
# Test Redis connection
redis-cli ping

# Check Redis status
sudo systemctl status redis

# Check Redis logs
tail -f /var/log/redis/redis-server.log

# Test cache functionality
php artisan tinker
Cache::put('test', 'value', 60);
Cache::get('test');
```

#### Common Causes
- **Redis Service**: Service not running
- **Memory**: Redis out of memory
- **Configuration**: Incorrect Redis settings
- **Network**: Connection issues

#### Solutions
```bash
# Start Redis service
sudo systemctl start redis

# Check Redis memory usage
redis-cli info memory

# Clear Redis cache
redis-cli flushall

# Restart Redis
sudo systemctl restart redis

# Clear application cache
php artisan cache:clear
```

### 4. Performance Issues

#### Symptoms
- Slow page load times
- High server resource usage
- Timeout errors

#### Diagnosis Steps
```bash
# Check system resources
htop
iotop
nethogs

# Check database performance
mysql -u zenamanage_user -p -e "SHOW PROCESSLIST;"
mysql -u zenamanage_user -p -e "SHOW STATUS LIKE 'Slow_queries';"

# Check application performance
php artisan tinker
App\Services\PerformanceMonitoringService::getAllMetrics();
```

#### Common Causes
- **Database**: Slow queries or missing indexes
- **Memory**: Insufficient RAM
- **CPU**: High CPU usage
- **Network**: Slow network connections

#### Solutions
```bash
# Optimize database
mysql -u zenamanage_user -p -e "OPTIMIZE TABLE users, projects, tasks, clients;"

# Add database indexes
mysql -u zenamanage_user -p -e "CREATE INDEX idx_projects_tenant_status ON projects(tenant_id, status);"

# Clear caches
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
```

---

## 📱 APPLICATION ISSUES

### 1. Authentication Issues

#### Symptoms
- Users cannot log in
- "Invalid credentials" errors
- Session timeouts

#### Diagnosis Steps
```bash
# Check authentication logs
tail -f /var/log/zenamanage/application.log | grep -i auth

# Check session configuration
php artisan tinker
config('session.driver');
config('session.lifetime');

# Test authentication
php artisan tinker
Auth::attempt(['email' => 'test@example.com', 'password' => 'password']);
```

#### Common Causes
- **Session Driver**: Incorrect session driver configuration
- **Database**: User table issues
- **Password**: Password hashing issues
- **Configuration**: Incorrect authentication settings

#### Solutions
```bash
# Check session configuration
sudo nano /var/www/zenamanage/.env
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Clear session cache
php artisan session:table
php artisan migrate

# Reset user passwords
php artisan tinker
$user = User::where('email', 'test@example.com')->first();
$user->password = Hash::make('newpassword');
$user->save();
```

### 2. API Issues

#### Symptoms
- API endpoints returning errors
- "Unauthorized" responses
- Rate limiting issues

#### Diagnosis Steps
```bash
# Test API endpoints
curl -X GET https://zenamanage.com/api/projects \
  -H "Authorization: Bearer your-token" \
  -H "Accept: application/json"

# Check API logs
tail -f /var/log/nginx/zenamanage_access.log | grep api

# Test API authentication
php artisan tinker
$user = User::first();
$token = $user->createToken('test-token');
echo $token->plainTextToken;
```

#### Common Causes
- **Authentication**: Invalid or expired tokens
- **Rate Limiting**: Too many requests
- **Permissions**: Insufficient permissions
- **Configuration**: Incorrect API settings

#### Solutions
```bash
# Check API configuration
sudo nano /var/www/zenamanage/.env
SANCTUM_STATEFUL_DOMAINS=zenamanage.com

# Clear API cache
php artisan config:clear
php artisan route:clear

# Test API permissions
php artisan tinker
$user = User::first();
$user->can('projects.view');
```

### 3. File Upload Issues

#### Symptoms
- File uploads failing
- "File too large" errors
- Upload timeouts

#### Diagnosis Steps
```bash
# Check PHP upload settings
php -i | grep upload

# Check file permissions
ls -la /var/www/zenamanage/storage/app/

# Check disk space
df -h

# Test file upload
php artisan tinker
Storage::disk('local')->put('test.txt', 'test content');
```

#### Common Causes
- **PHP Settings**: Upload limits too low
- **Permissions**: Incorrect file permissions
- **Disk Space**: Insufficient storage
- **Configuration**: Incorrect upload settings

#### Solutions
```bash
# Update PHP settings
sudo nano /etc/php/8.2/fpm/php.ini
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300

# Fix file permissions
sudo chown -R zenamanage:www-data /var/www/zenamanage/storage
sudo chmod -R 775 /var/www/zenamanage/storage

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

---

## 🗄️ DATABASE ISSUES

### 1. Connection Issues

#### Symptoms
- "Connection refused" errors
- Database timeout errors
- "Too many connections" errors

#### Diagnosis Steps
```bash
# Check MySQL status
sudo systemctl status mysql

# Check MySQL configuration
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf

# Check connection limits
mysql -u zenamanage_user -p -e "SHOW VARIABLES LIKE 'max_connections';"
mysql -u zenamanage_user -p -e "SHOW STATUS LIKE 'Threads_connected';"
```

#### Common Causes
- **Service**: MySQL service not running
- **Configuration**: Incorrect connection settings
- **Limits**: Too many concurrent connections
- **Network**: Firewall blocking connections

#### Solutions
```bash
# Start MySQL service
sudo systemctl start mysql

# Increase connection limit
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
max_connections = 200

# Check firewall
sudo ufw status
sudo ufw allow 3306/tcp

# Restart MySQL
sudo systemctl restart mysql
```

### 2. Query Performance Issues

#### Symptoms
- Slow page loads
- Database timeout errors
- High CPU usage

#### Diagnosis Steps
```bash
# Check slow queries
mysql -u zenamanage_user -p -e "SHOW STATUS LIKE 'Slow_queries';"
sudo mysqldumpslow /var/log/mysql/slow.log

# Check query cache
mysql -u zenamanage_user -p -e "SHOW STATUS LIKE 'Qcache%';"

# Analyze table performance
mysql -u zenamanage_user -p -e "ANALYZE TABLE users, projects, tasks, clients;"
```

#### Common Causes
- **Indexes**: Missing or inefficient indexes
- **Queries**: Inefficient SQL queries
- **Data**: Large datasets without optimization
- **Configuration**: Suboptimal MySQL settings

#### Solutions
```bash
# Add missing indexes
mysql -u zenamanage_user -p -e "CREATE INDEX idx_projects_tenant_status ON projects(tenant_id, status);"
mysql -u zenamanage_user -p -e "CREATE INDEX idx_tasks_tenant_project ON tasks(tenant_id, project_id);"

# Optimize tables
mysql -u zenamanage_user -p -e "OPTIMIZE TABLE users, projects, tasks, clients;"

# Update MySQL configuration
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
innodb_buffer_pool_size = 1G
query_cache_size = 64M
```

### 3. Data Integrity Issues

#### Symptoms
- Data corruption errors
- Inconsistent data
- Foreign key constraint errors

#### Diagnosis Steps
```bash
# Check table integrity
mysql -u zenamanage_user -p -e "CHECK TABLE users, projects, tasks, clients;"

# Check foreign key constraints
mysql -u zenamanage_user -p -e "SHOW CREATE TABLE projects;"

# Check data consistency
mysql -u zenamanage_user -p -e "SELECT COUNT(*) FROM projects WHERE tenant_id IS NULL;"
```

#### Common Causes
- **Constraints**: Missing foreign key constraints
- **Data**: Invalid data in tables
- **Migrations**: Incomplete or failed migrations
- **Backups**: Corrupted backup restoration

#### Solutions
```bash
# Repair tables
mysql -u zenamanage_user -p -e "REPAIR TABLE users, projects, tasks, clients;"

# Add foreign key constraints
mysql -u zenamanage_user -p -e "ALTER TABLE projects ADD CONSTRAINT fk_projects_client FOREIGN KEY (client_id) REFERENCES clients(id);"

# Run migrations
php artisan migrate --force

# Restore from backup
gunzip -c /var/backups/zenamanage/database_latest.sql.gz | mysql -u zenamanage_user -p zenamanage_prod
```

---

## ⚡ PERFORMANCE ISSUES

### 1. Slow Page Loads

#### Symptoms
- Pages taking more than 3 seconds to load
- High server response times
- User complaints about speed

#### Diagnosis Steps
```bash
# Check response times
curl -w "@curl-format.txt" -o /dev/null -s https://zenamanage.com

# Check server resources
htop
iotop
nethogs

# Check application performance
php artisan tinker
App\Services\PerformanceMonitoringService::getAllMetrics();
```

#### Common Causes
- **Database**: Slow queries or missing indexes
- **Cache**: Cache not working properly
- **Resources**: Insufficient server resources
- **Configuration**: Suboptimal application settings

#### Solutions
```bash
# Optimize database
mysql -u zenamanage_user -p -e "OPTIMIZE TABLE users, projects, tasks, clients;"

# Enable caching
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize PHP-FPM
sudo nano /etc/php/8.2/fpm/pool.d/zenamanage.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35

# Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
```

### 2. High Memory Usage

#### Symptoms
- Server running out of memory
- "Memory limit exceeded" errors
- Slow performance due to swapping

#### Diagnosis Steps
```bash
# Check memory usage
free -h
htop

# Check PHP memory usage
php -i | grep memory_limit

# Check MySQL memory usage
mysql -u zenamanage_user -p -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';"
```

#### Common Causes
- **PHP**: Memory limit too low
- **MySQL**: Buffer pool too large
- **Applications**: Memory leaks
- **Configuration**: Suboptimal memory settings

#### Solutions
```bash
# Increase PHP memory limit
sudo nano /etc/php/8.2/fpm/php.ini
memory_limit = 512M

# Optimize MySQL memory usage
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
innodb_buffer_pool_size = 1G

# Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart mysql
```

### 3. High CPU Usage

#### Symptoms
- Server CPU usage above 80%
- Slow response times
- System becoming unresponsive

#### Diagnosis Steps
```bash
# Check CPU usage
top
htop

# Check process CPU usage
ps aux --sort=-%cpu | head -10

# Check MySQL CPU usage
mysql -u zenamanage_user -p -e "SHOW PROCESSLIST;"
```

#### Common Causes
- **Queries**: Inefficient database queries
- **Applications**: CPU-intensive operations
- **Configuration**: Suboptimal server settings
- **Resources**: Insufficient CPU power

#### Solutions
```bash
# Optimize database queries
mysql -u zenamanage_user -p -e "SHOW PROCESSLIST;"
mysql -u zenamanage_user -p -e "KILL QUERY process_id;"

# Add database indexes
mysql -u zenamanage_user -p -e "CREATE INDEX idx_projects_tenant_status ON projects(tenant_id, status);"

# Optimize PHP-FPM
sudo nano /etc/php/8.2/fpm/pool.d/zenamanage.conf
pm.max_children = 30
pm.start_servers = 3
pm.min_spare_servers = 3
pm.max_spare_servers = 20

# Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart mysql
```

---

## 🔒 SECURITY ISSUES

### 1. Authentication Bypass

#### Symptoms
- Users accessing unauthorized resources
- "Permission denied" errors not working
- Security audit logs showing violations

#### Diagnosis Steps
```bash
# Check authentication logs
tail -f /var/log/zenamanage/application.log | grep -i auth

# Check permission system
php artisan tinker
$user = User::first();
$user->can('projects.view');

# Check RBAC configuration
php artisan tinker
config('permissions.roles');
```

#### Common Causes
- **Configuration**: Incorrect authentication settings
- **Permissions**: Missing or incorrect permissions
- **Middleware**: Authentication middleware not working
- **Sessions**: Session management issues

#### Solutions
```bash
# Check authentication configuration
sudo nano /var/www/zenamanage/.env
SANCTUM_STATEFUL_DOMAINS=zenamanage.com
SESSION_SECURE_COOKIE=true

# Clear authentication cache
php artisan config:clear
php artisan route:clear

# Test authentication
php artisan tinker
Auth::attempt(['email' => 'test@example.com', 'password' => 'password']);
```

### 2. SQL Injection

#### Symptoms
- Unexpected database errors
- Data corruption
- Security audit logs showing SQL injection attempts

#### Diagnosis Steps
```bash
# Check database logs
tail -f /var/log/mysql/error.log

# Check application logs
tail -f /var/log/zenamanage/application.log | grep -i sql

# Test database queries
php artisan tinker
DB::select('SELECT * FROM users WHERE id = ?', [1]);
```

#### Common Causes
- **Queries**: Raw SQL queries without parameter binding
- **Input**: Unvalidated user input
- **Configuration**: Incorrect database settings
- **Code**: Vulnerable application code

#### Solutions
```bash
# Use parameterized queries
php artisan tinker
DB::select('SELECT * FROM users WHERE id = ?', [1]);

# Validate user input
php artisan tinker
$request->validate(['id' => 'required|integer']);

# Check database configuration
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
sql_mode = STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO
```

### 3. Cross-Site Scripting (XSS)

#### Symptoms
- Malicious scripts executing in browser
- Unexpected page content
- Security audit logs showing XSS attempts

#### Diagnosis Steps
```bash
# Check application logs
tail -f /var/log/zenamanage/application.log | grep -i xss

# Check input validation
php artisan tinker
$request->validate(['name' => 'required|string|max:255']);

# Test output encoding
php artisan tinker
htmlspecialchars('<script>alert("xss")</script>');
```

#### Common Causes
- **Input**: Unvalidated user input
- **Output**: Unescaped output
- **Configuration**: Incorrect security settings
- **Code**: Vulnerable application code

#### Solutions
```bash
# Validate user input
php artisan tinker
$request->validate(['name' => 'required|string|max:255']);

# Escape output
php artisan tinker
htmlspecialchars($user->name);

# Check security headers
sudo nano /etc/nginx/sites-available/zenamanage
add_header X-XSS-Protection "1; mode=block" always;
add_header X-Content-Type-Options "nosniff" always;
```

---

## 🌐 NETWORK ISSUES

### 1. Connection Timeouts

#### Symptoms
- "Connection timeout" errors
- Slow network responses
- Intermittent connectivity issues

#### Diagnosis Steps
```bash
# Test network connectivity
ping google.com
traceroute google.com

# Check DNS resolution
nslookup zenamanage.com

# Check network configuration
ip addr show
route -n
```

#### Common Causes
- **Network**: Network connectivity issues
- **DNS**: DNS resolution problems
- **Firewall**: Firewall blocking connections
- **Configuration**: Incorrect network settings

#### Solutions
```bash
# Check network configuration
sudo nano /etc/netplan/01-netcfg.yaml

# Check DNS configuration
sudo nano /etc/resolv.conf

# Check firewall rules
sudo ufw status
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Restart network services
sudo systemctl restart networking
```

### 2. SSL/TLS Issues

#### Symptoms
- "SSL certificate error" messages
- "Connection not secure" warnings
- SSL handshake failures

#### Diagnosis Steps
```bash
# Check SSL certificate
openssl x509 -in /etc/ssl/certs/zenamanage.crt -text -noout

# Test SSL connection
openssl s_client -connect zenamanage.com:443

# Check certificate expiration
openssl x509 -in /etc/ssl/certs/zenamanage.crt -dates -noout
```

#### Common Causes
- **Certificate**: Expired or invalid certificate
- **Configuration**: Incorrect SSL settings
- **Chain**: Missing intermediate certificates
- **Renewal**: Certificate renewal issues

#### Solutions
```bash
# Renew SSL certificate
sudo certbot renew

# Check certificate chain
openssl s_client -connect zenamanage.com:443 -showcerts

# Update SSL configuration
sudo nano /etc/nginx/sites-available/zenamanage
ssl_protocols TLSv1.2 TLSv1.3;
ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;

# Restart Nginx
sudo systemctl restart nginx
```

---

## 📁 FILE SYSTEM ISSUES

### 1. Permission Issues

#### Symptoms
- "Permission denied" errors
- Files not accessible
- Upload failures

#### Diagnosis Steps
```bash
# Check file permissions
ls -la /var/www/zenamanage/

# Check ownership
ls -la /var/www/zenamanage/storage/

# Check disk space
df -h

# Check inode usage
df -i
```

#### Common Causes
- **Ownership**: Incorrect file ownership
- **Permissions**: Incorrect file permissions
- **Space**: Insufficient disk space
- **Inodes**: Exhausted inode limit

#### Solutions
```bash
# Fix file ownership
sudo chown -R zenamanage:www-data /var/www/zenamanage

# Fix file permissions
sudo chmod -R 755 /var/www/zenamanage
sudo chmod -R 775 /var/www/zenamanage/storage
sudo chmod -R 775 /var/www/zenamanage/bootstrap/cache

# Clean up disk space
sudo apt autoremove
sudo apt autoclean
sudo find /var/log -name "*.log" -mtime +7 -delete
```

### 2. Disk Space Issues

#### Symptoms
- "No space left on device" errors
- Application failures
- System slowdowns

#### Diagnosis Steps
```bash
# Check disk usage
df -h

# Check largest directories
du -sh /var/www/zenamanage/* | sort -hr

# Check log files
du -sh /var/log/* | sort -hr

# Check temporary files
du -sh /tmp/* | sort -hr
```

#### Common Causes
- **Logs**: Large log files
- **Backups**: Old backup files
- **Cache**: Large cache files
- **Uploads**: Large uploaded files

#### Solutions
```bash
# Clean log files
sudo find /var/log -name "*.log" -mtime +7 -delete

# Clean backup files
sudo find /var/backups -name "*.sql.gz" -mtime +30 -delete

# Clean cache files
php artisan cache:clear
redis-cli flushall

# Clean temporary files
sudo find /tmp -type f -mtime +7 -delete
```

---

## 📊 LOG ANALYSIS

### 1. Application Logs

#### Log Locations
```bash
# Application logs
/var/log/zenamanage/application.log
/var/log/zenamanage/worker.log
/var/log/zenamanage/performance.log

# Web server logs
/var/log/nginx/zenamanage_access.log
/var/log/nginx/zenamanage_error.log

# System logs
/var/log/syslog
/var/log/auth.log
```

#### Log Analysis Commands
```bash
# Search for errors
grep -i "error" /var/log/zenamanage/application.log

# Search for specific patterns
grep -i "database" /var/log/zenamanage/application.log

# Count log entries
grep -c "ERROR" /var/log/zenamanage/application.log

# Monitor logs in real-time
tail -f /var/log/zenamanage/application.log
```

### 2. Database Logs

#### Log Locations
```bash
# MySQL logs
/var/log/mysql/error.log
/var/log/mysql/slow.log
/var/log/mysql/general.log
```

#### Log Analysis Commands
```bash
# Check MySQL errors
tail -f /var/log/mysql/error.log

# Analyze slow queries
sudo mysqldumpslow /var/log/mysql/slow.log

# Check query patterns
grep -i "select" /var/log/mysql/general.log
```

### 3. System Logs

#### Log Locations
```bash
# System logs
/var/log/syslog
/var/log/auth.log
/var/log/kern.log
/var/log/messages
```

#### Log Analysis Commands
```bash
# Check system errors
grep -i "error" /var/log/syslog

# Check authentication logs
grep -i "auth" /var/log/auth.log

# Check kernel messages
grep -i "kernel" /var/log/kern.log
```

---

## 🛠️ DEBUGGING TOOLS

### 1. System Monitoring Tools

#### Resource Monitoring
```bash
# CPU and memory usage
htop
top
free -h

# Disk usage
df -h
du -sh

# Network usage
nethogs
iftop
netstat -tulpn
```

#### Process Monitoring
```bash
# Running processes
ps aux
ps aux --sort=-%cpu
ps aux --sort=-%mem

# Process tree
pstree
pstree -p

# Process details
lsof -p process_id
```

### 2. Database Debugging

#### MySQL Debugging
```bash
# MySQL process list
mysql -u zenamanage_user -p -e "SHOW PROCESSLIST;"

# MySQL status
mysql -u zenamanage_user -p -e "SHOW STATUS;"

# MySQL variables
mysql -u zenamanage_user -p -e "SHOW VARIABLES;"

# MySQL slow queries
mysql -u zenamanage_user -p -e "SHOW STATUS LIKE 'Slow_queries';"
```

#### Query Analysis
```bash
# Explain query execution
mysql -u zenamanage_user -p -e "EXPLAIN SELECT * FROM projects WHERE tenant_id = 1;"

# Analyze table performance
mysql -u zenamanage_user -p -e "ANALYZE TABLE projects;"

# Check table status
mysql -u zenamanage_user -p -e "SHOW TABLE STATUS LIKE 'projects';"
```

### 3. Application Debugging

#### PHP Debugging
```bash
# PHP configuration
php -i
php -m

# PHP error reporting
php -d error_reporting=E_ALL -d display_errors=1

# PHP performance
php -d xdebug.profiler_enable=1
```

#### Laravel Debugging
```bash
# Laravel configuration
php artisan config:show

# Laravel routes
php artisan route:list

# Laravel cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## 🚨 EMERGENCY PROCEDURES

### 1. System Down

#### Immediate Actions
```bash
# Check system status
sudo systemctl status nginx
sudo systemctl status php8.2-fpm
sudo systemctl status mysql
sudo systemctl status redis

# Restart services
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
sudo systemctl restart mysql
sudo systemctl restart redis

# Check logs
tail -f /var/log/nginx/zenamanage_error.log
tail -f /var/log/zenamanage/application.log
```

#### Recovery Steps
```bash
# Restore from backup
gunzip -c /var/backups/zenamanage/database_latest.sql.gz | mysql -u zenamanage_user -p zenamanage_prod

# Restore application files
sudo tar -xzf /var/backups/zenamanage/application_latest.tar.gz -C /

# Restart all services
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
sudo systemctl restart mysql
sudo systemctl restart redis
```

### 2. Data Loss

#### Immediate Actions
```bash
# Stop all services
sudo systemctl stop nginx
sudo systemctl stop php8.2-fpm
sudo systemctl stop mysql

# Check data integrity
mysql -u zenamanage_user -p -e "CHECK TABLE users, projects, tasks, clients;"

# Restore from backup
gunzip -c /var/backups/zenamanage/database_latest.sql.gz | mysql -u zenamanage_user -p zenamanage_prod
```

#### Recovery Steps
```bash
# Verify data restoration
mysql -u zenamanage_user -p -e "SELECT COUNT(*) FROM users;"
mysql -u zenamanage_user -p -e "SELECT COUNT(*) FROM projects;"

# Restart services
sudo systemctl start mysql
sudo systemctl start php8.2-fpm
sudo systemctl start nginx

# Test application
curl -I https://zenamanage.com
```

### 3. Security Breach

#### Immediate Actions
```bash
# Isolate system
sudo ufw deny all
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Check logs
tail -f /var/log/zenamanage/application.log | grep -i security
tail -f /var/log/auth.log

# Check processes
ps aux | grep -v grep
```

#### Recovery Steps
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Change passwords
mysql -u root -p -e "ALTER USER 'zenamanage_user'@'localhost' IDENTIFIED BY 'new_password';"

# Restart services
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
sudo systemctl restart mysql
sudo systemctl restart redis
```

---

## 🛡️ PREVENTION STRATEGIES

### 1. Proactive Monitoring

#### System Monitoring
```bash
# Create monitoring script
sudo nano /usr/local/bin/monitor-system.sh

#!/bin/bash
LOG_FILE="/var/log/zenamanage/monitoring.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')

# Check disk space
DISK_USAGE=$(df -h / | awk 'NR==2{print $5}' | cut -d'%' -f1)
if [ $DISK_USAGE -gt 80 ]; then
    echo "$DATE - WARNING: Disk usage is $DISK_USAGE%" >> $LOG_FILE
fi

# Check memory usage
MEMORY_USAGE=$(free | grep Mem | awk '{printf("%.2f"), $3/$2 * 100.0}')
if [ $(echo "$MEMORY_USAGE > 80" | bc) -eq 1 ]; then
    echo "$DATE - WARNING: Memory usage is $MEMORY_USAGE%" >> $LOG_FILE
fi

# Check service status
if ! systemctl is-active --quiet nginx; then
    echo "$DATE - ERROR: Nginx is not running" >> $LOG_FILE
fi

if ! systemctl is-active --quiet php8.2-fpm; then
    echo "$DATE - ERROR: PHP-FPM is not running" >> $LOG_FILE
fi

if ! systemctl is-active --quiet mysql; then
    echo "$DATE - ERROR: MySQL is not running" >> $LOG_FILE
fi

# Make script executable
sudo chmod +x /usr/local/bin/monitor-system.sh

# Add to crontab
echo "*/5 * * * * /usr/local/bin/monitor-system.sh" | sudo crontab -
```

### 2. Regular Maintenance

#### Daily Maintenance
```bash
# Create daily maintenance script
sudo nano /usr/local/bin/daily-maintenance.sh

#!/bin/bash
LOG_FILE="/var/log/zenamanage/maintenance.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')

echo "$DATE - Starting daily maintenance" >> $LOG_FILE

# Clear application cache
php /var/www/zenamanage/artisan cache:clear
echo "$DATE - Application cache cleared" >> $LOG_FILE

# Optimize database
mysql -u zenamanage_user -psecure_password -e "OPTIMIZE TABLE users, projects, tasks, clients;"
echo "$DATE - Database optimized" >> $LOG_FILE

# Check disk space
DISK_USAGE=$(df -h / | awk 'NR==2{print $5}' | cut -d'%' -f1)
if [ $DISK_USAGE -gt 80 ]; then
    echo "$DATE - WARNING: Disk usage is $DISK_USAGE%" >> $LOG_FILE
fi

echo "$DATE - Daily maintenance completed" >> $LOG_FILE

# Make script executable
sudo chmod +x /usr/local/bin/daily-maintenance.sh

# Add to crontab
echo "0 1 * * * /usr/local/bin/daily-maintenance.sh" | sudo crontab -
```

### 3. Backup Strategy

#### Automated Backups
```bash
# Create backup script
sudo nano /usr/local/bin/backup-system.sh

#!/bin/bash
BACKUP_DIR="/var/backups/zenamanage"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="zenamanage_prod"
DB_USER="zenamanage_user"
DB_PASS="secure_password"

# Create backup directory
mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/database_$DATE.sql
gzip $BACKUP_DIR/database_$DATE.sql

# Application files backup
tar -czf $BACKUP_DIR/application_$DATE.tar.gz /var/www/zenamanage

# Configuration backup
tar -czf $BACKUP_DIR/config_$DATE.tar.gz /etc/nginx /etc/php /etc/mysql

# Keep only last 7 days of backups
find $BACKUP_DIR -name "*.sql.gz" -mtime +7 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete

# Make script executable
sudo chmod +x /usr/local/bin/backup-system.sh

# Add to crontab for daily backups
echo "0 2 * * * /usr/local/bin/backup-system.sh" | sudo crontab -
```

---

## 📞 SUPPORT CONTACTS

### Technical Support
- **System Administrator**: admin@zenamanage.com
- **Database Administrator**: dba@zenamanage.com
- **Security Team**: security@zenamanage.com
- **Development Team**: dev@zenamanage.com

### Emergency Contacts
- **24/7 Support**: +1-555-ZENAMANAGE
- **Emergency Email**: emergency@zenamanage.com
- **On-Call Engineer**: oncall@zenamanage.com

### Documentation
- **Technical Documentation**: docs.zenamanage.com
- **API Documentation**: api.zenamanage.com
- **User Manual**: manual.zenamanage.com
- **Admin Guide**: admin.zenamanage.com

---

**ZenaManage Troubleshooting Guide v2.0**  
*Last Updated: January 8, 2025*  
*For technical support, contact support@zenamanage.com or visit our troubleshooting documentation center.*
