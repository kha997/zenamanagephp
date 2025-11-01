# ZENAMANAGE DEPLOYMENT GUIDE

## 🚀 COMPREHENSIVE DEPLOYMENT GUIDE

**Version**: 2.0  
**Last Updated**: 2025-01-08  
**Status**: Production Ready

---

## 🎯 TABLE OF CONTENTS

1. [Deployment Overview](#deployment-overview)
2. [System Requirements](#system-requirements)
3. [Pre-Deployment Checklist](#pre-deployment-checklist)
4. [Environment Setup](#environment-setup)
5. [Database Setup](#database-setup)
6. [Application Deployment](#application-deployment)
7. [Web Server Configuration](#web-server-configuration)
8. [SSL/TLS Configuration](#ssltls-configuration)
9. [Performance Optimization](#performance-optimization)
10. [Security Configuration](#security-configuration)
11. [Monitoring Setup](#monitoring-setup)
12. [Backup Strategy](#backup-strategy)
13. [Scaling Configuration](#scaling-configuration)
14. [Troubleshooting](#troubleshooting)
15. [Maintenance Procedures](#maintenance-procedures)

---

## 🔍 DEPLOYMENT OVERVIEW

### Deployment Architecture
```
┌─────────────────────────────────────────────────────────────┐
│                    Production Environment                   │
├─────────────────────────────────────────────────────────────┤
│  Load Balancer (Nginx)  │  Application Servers (Laravel)   │
├─────────────────────────────────────────────────────────────┤
│  Database Cluster (MySQL)  │  Cache Cluster (Redis)        │
├─────────────────────────────────────────────────────────────┤
│  File Storage (S3)  │  Monitoring (Custom)  │  Logs (ELK) │
└─────────────────────────────────────────────────────────────┘
```

### Deployment Methods
- **Manual Deployment**: Step-by-step manual setup
- **Automated Deployment**: CI/CD pipeline deployment
- **Container Deployment**: Docker/Kubernetes deployment
- **Cloud Deployment**: AWS/Azure/GCP deployment

### Deployment Phases
1. **Preparation**: Environment setup and configuration
2. **Database**: Database creation and migration
3. **Application**: Code deployment and configuration
4. **Web Server**: Nginx configuration and SSL setup
5. **Optimization**: Performance and security optimization
6. **Monitoring**: Monitoring and alerting setup
7. **Testing**: Production testing and validation
8. **Go-Live**: Final deployment and monitoring

---

## 💻 SYSTEM REQUIREMENTS

### Minimum Requirements
- **OS**: Ubuntu 20.04 LTS or CentOS 8+
- **RAM**: 4GB (8GB recommended)
- **CPU**: 2 cores (4 cores recommended)
- **Storage**: 50GB SSD (100GB recommended)
- **Network**: 100Mbps connection

### Software Requirements
- **PHP**: 8.2+ with extensions
- **MySQL**: 8.0+ or MariaDB 10.6+
- **Redis**: 6.0+ for caching and sessions
- **Nginx**: 1.18+ for web server
- **Composer**: 2.0+ for PHP dependencies
- **Node.js**: 18+ for frontend assets

### PHP Extensions Required
```bash
# Required PHP extensions
php-mysql
php-redis
php-curl
php-gd
php-mbstring
php-xml
php-zip
php-bcmath
php-intl
php-fileinfo
php-openssl
php-tokenizer
php-json
php-pdo
php-pdo_mysql
```

### System Dependencies
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y nginx mysql-server redis-server
sudo apt install -y php8.2-fpm php8.2-mysql php8.2-redis
sudo apt install -y php8.2-curl php8.2-gd php8.2-mbstring
sudo apt install -y php8.2-xml php8.2-zip php8.2-bcmath
sudo apt install -y php8.2-intl php8.2-fileinfo php8.2-openssl
sudo apt install -y composer nodejs npm

# CentOS/RHEL
sudo yum update
sudo yum install -y nginx mysql-server redis
sudo yum install -y php82-fpm php82-mysql php82-redis
sudo yum install -y php82-curl php82-gd php82-mbstring
sudo yum install -y php82-xml php82-zip php82-bcmath
sudo yum install -y php82-intl php82-fileinfo php82-openssl
sudo yum install -y composer nodejs npm
```

---

## ✅ PRE-DEPLOYMENT CHECKLIST

### Environment Preparation
- [ ] **Server Provisioning**: Server allocated and configured
- [ ] **Domain Setup**: Domain name registered and DNS configured
- [ ] **SSL Certificate**: SSL certificate obtained (Let's Encrypt or commercial)
- [ ] **Database Server**: MySQL/MariaDB server configured
- [ ] **Cache Server**: Redis server configured
- [ ] **File Storage**: File storage configured (local or S3)

### Security Preparation
- [ ] **Firewall Configuration**: Firewall rules configured
- [ ] **SSH Access**: SSH access configured with key-based authentication
- [ ] **User Accounts**: System users created with appropriate permissions
- [ ] **Security Updates**: System packages updated to latest versions
- [ ] **Backup Strategy**: Backup strategy planned and configured

### Application Preparation
- [ ] **Code Repository**: Code repository cloned and configured
- [ ] **Environment Variables**: Environment variables prepared
- [ ] **Dependencies**: PHP and Node.js dependencies installed
- [ ] **Database Schema**: Database schema prepared
- [ ] **File Permissions**: File permissions configured

### Testing Preparation
- [ ] **Test Environment**: Test environment configured
- [ ] **Test Data**: Test data prepared
- [ ] **Test Scenarios**: Test scenarios defined
- [ ] **Rollback Plan**: Rollback plan prepared
- [ ] **Monitoring**: Monitoring tools configured

---

## 🔧 ENVIRONMENT SETUP

### Server Configuration
```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Create application user
sudo adduser zenamanage
sudo usermod -aG www-data zenamanage

# Create application directory
sudo mkdir -p /var/www/zenamanage
sudo chown -R zenamanage:www-data /var/www/zenamanage
sudo chmod -R 755 /var/www/zenamanage

# Create log directory
sudo mkdir -p /var/log/zenamanage
sudo chown -R zenamanage:www-data /var/log/zenamanage
sudo chmod -R 755 /var/log/zenamanage
```

### PHP Configuration
```bash
# Configure PHP-FPM
sudo nano /etc/php/8.2/fpm/php.ini

# Key PHP settings
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
post_max_size = 100M
upload_max_filesize = 100M
max_file_uploads = 20
date.timezone = UTC
session.gc_maxlifetime = 7200
session.cookie_secure = 1
session.cookie_httponly = 1
session.use_strict_mode = 1

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

### MySQL Configuration
```bash
# Configure MySQL
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf

# Key MySQL settings
[mysqld]
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
max_connections = 200
query_cache_size = 64M
query_cache_type = 1
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2

# Restart MySQL
sudo systemctl restart mysql
```

### Redis Configuration
```bash
# Configure Redis
sudo nano /etc/redis/redis.conf

# Key Redis settings
maxmemory 512mb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
tcp-keepalive 300
timeout 300

# Restart Redis
sudo systemctl restart redis
```

---

## 🗄️ DATABASE SETUP

### Database Creation
```sql
-- Create database and user
CREATE DATABASE zenamanage_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'zenamanage_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON zenamanage_prod.* TO 'zenamanage_user'@'localhost';
FLUSH PRIVILEGES;

-- Create database for testing
CREATE DATABASE zenamanage_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON zenamanage_test.* TO 'zenamanage_user'@'localhost';
FLUSH PRIVILEGES;
```

### Database Migration
```bash
# Navigate to application directory
cd /var/www/zenamanage

# Run database migrations
php artisan migrate --force

# Seed initial data
php artisan db:seed --force

# Create database indexes
php artisan migrate:status
```

### Database Optimization
```sql
-- Optimize database tables
OPTIMIZE TABLE users;
OPTIMIZE TABLE projects;
OPTIMIZE TABLE tasks;
OPTIMIZE TABLE clients;
OPTIMIZE TABLE security_audit_logs;

-- Analyze tables for query optimization
ANALYZE TABLE users;
ANALYZE TABLE projects;
ANALYZE TABLE tasks;
ANALYZE TABLE clients;
```

### Database Backup
```bash
# Create backup script
sudo nano /usr/local/bin/backup-database.sh

#!/bin/bash
BACKUP_DIR="/var/backups/zenamanage"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="zenamanage_prod"
DB_USER="zenamanage_user"
DB_PASS="secure_password"

mkdir -p $BACKUP_DIR
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/zenamanage_$DATE.sql
gzip $BACKUP_DIR/zenamanage_$DATE.sql

# Keep only last 7 days of backups
find $BACKUP_DIR -name "zenamanage_*.sql.gz" -mtime +7 -delete

# Make script executable
sudo chmod +x /usr/local/bin/backup-database.sh

# Add to crontab for daily backups
echo "0 2 * * * /usr/local/bin/backup-database.sh" | sudo crontab -
```

---

## 📱 APPLICATION DEPLOYMENT

### Code Deployment
```bash
# Clone repository
cd /var/www
sudo git clone https://github.com/your-org/zenamanage.git
sudo chown -R zenamanage:www-data zenamanage
cd zenamanage

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node.js dependencies
npm install --production

# Build frontend assets
npm run build

# Set file permissions
sudo chown -R zenamanage:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Environment Configuration
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure environment variables
nano .env

# Key environment variables
APP_NAME="ZenaManage"
APP_ENV=production
APP_KEY=base64:generated_key
APP_DEBUG=false
APP_URL=https://zenamanage.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=zenamanage_prod
DB_USERNAME=zenamanage_user
DB_PASSWORD=secure_password

REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=null

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@zenamanage.com
MAIL_FROM_NAME="ZenaManage"

# Security settings
SANCTUM_STATEFUL_DOMAINS=zenamanage.com
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict
```

### Application Optimization
```bash
# Clear and cache configuration
php artisan config:clear
php artisan config:cache

# Clear and cache routes
php artisan route:clear
php artisan route:cache

# Clear and cache views
php artisan view:clear
php artisan view:cache

# Clear and cache events
php artisan event:clear
php artisan event:cache

# Optimize autoloader
composer dump-autoload --optimize

# Create symbolic link for storage
php artisan storage:link
```

### Queue Configuration
```bash
# Configure queue worker
sudo nano /etc/supervisor/conf.d/zenamanage-worker.conf

[program:zenamanage-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/zenamanage/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=zenamanage
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/zenamanage/worker.log
stopwaitsecs=3600

# Start supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start zenamanage-worker:*
```

---

## 🌐 WEB SERVER CONFIGURATION

### Nginx Configuration
```bash
# Create Nginx configuration
sudo nano /etc/nginx/sites-available/zenamanage

server {
    listen 80;
    server_name zenamanage.com www.zenamanage.com;
    root /var/www/zenamanage/public;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private must-revalidate auth;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss application/javascript application/json;

    # Handle PHP requests
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Handle static files
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Handle Laravel routes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ /(storage|bootstrap/cache) {
        deny all;
    }

    # Logging
    access_log /var/log/nginx/zenamanage_access.log;
    error_log /var/log/nginx/zenamanage_error.log;
}

# Enable site
sudo ln -s /etc/nginx/sites-available/zenamanage /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### SSL/TLS Configuration
```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --nginx -d zenamanage.com -d www.zenamanage.com

# Auto-renewal setup
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

### Load Balancer Configuration
```bash
# Configure load balancer
sudo nano /etc/nginx/nginx.conf

upstream zenamanage_backend {
    server 127.0.0.1:8000;
    server 127.0.0.1:8001;
    server 127.0.0.1:8002;
}

server {
    listen 80;
    server_name zenamanage.com;
    
    location / {
        proxy_pass http://zenamanage_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

## ⚡ PERFORMANCE OPTIMIZATION

### PHP-FPM Optimization
```bash
# Configure PHP-FPM pool
sudo nano /etc/php/8.2/fpm/pool.d/zenamanage.conf

[zenamanage]
user = zenamanage
group = www-data
listen = /var/run/php/php8.2-fpm-zenamanage.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 1000

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

### Redis Optimization
```bash
# Configure Redis for production
sudo nano /etc/redis/redis.conf

# Memory optimization
maxmemory 1gb
maxmemory-policy allkeys-lru

# Persistence optimization
save 900 1
save 300 10
save 60 10000

# Network optimization
tcp-keepalive 300
timeout 300

# Restart Redis
sudo systemctl restart redis
```

### Database Optimization
```sql
-- Configure MySQL for production
SET GLOBAL innodb_buffer_pool_size = 1073741824; -- 1GB
SET GLOBAL innodb_log_file_size = 268435456; -- 256MB
SET GLOBAL innodb_flush_log_at_trx_commit = 2;
SET GLOBAL innodb_flush_method = O_DIRECT;
SET GLOBAL max_connections = 200;
SET GLOBAL query_cache_size = 67108864; -- 64MB
SET GLOBAL query_cache_type = 1;
SET GLOBAL slow_query_log = 1;
SET GLOBAL long_query_time = 2;
```

### Caching Strategy
```bash
# Configure application caching
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Configure Redis caching
redis-cli
CONFIG SET maxmemory 1gb
CONFIG SET maxmemory-policy allkeys-lru
```

---

## 🔒 SECURITY CONFIGURATION

### Firewall Configuration
```bash
# Configure UFW firewall
sudo ufw enable
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 3306/tcp
sudo ufw allow 6379/tcp
sudo ufw status
```

### SSL/TLS Security
```bash
# Configure SSL/TLS security
sudo nano /etc/nginx/sites-available/zenamanage

# SSL configuration
ssl_protocols TLSv1.2 TLSv1.3;
ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
ssl_prefer_server_ciphers off;
ssl_session_cache shared:SSL:10m;
ssl_session_timeout 10m;
ssl_stapling on;
ssl_stapling_verify on;
```

### Application Security
```bash
# Configure application security
sudo nano /var/www/zenamanage/.env

# Security settings
APP_DEBUG=false
APP_ENV=production
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict
SANCTUM_STATEFUL_DOMAINS=zenamanage.com
```

### File Permissions
```bash
# Set secure file permissions
sudo chown -R zenamanage:www-data /var/www/zenamanage
sudo chmod -R 755 /var/www/zenamanage
sudo chmod -R 775 /var/www/zenamanage/storage
sudo chmod -R 775 /var/www/zenamanage/bootstrap/cache
sudo chmod 644 /var/www/zenamanage/.env
```

---

## 📊 MONITORING SETUP

### Application Monitoring
```bash
# Install monitoring tools
sudo apt install htop iotop nethogs

# Configure log rotation
sudo nano /etc/logrotate.d/zenamanage

/var/log/zenamanage/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 zenamanage www-data
}
```

### Performance Monitoring
```bash
# Create monitoring script
sudo nano /usr/local/bin/monitor-performance.sh

#!/bin/bash
LOG_FILE="/var/log/zenamanage/performance.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')

# CPU usage
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)

# Memory usage
MEMORY_USAGE=$(free | grep Mem | awk '{printf("%.2f"), $3/$2 * 100.0}')

# Disk usage
DISK_USAGE=$(df -h / | awk 'NR==2{print $5}' | cut -d'%' -f1)

# Database connections
DB_CONNECTIONS=$(mysql -u zenamanage_user -psecure_password -e "SHOW STATUS LIKE 'Threads_connected';" | awk 'NR==2{print $2}')

# Log metrics
echo "$DATE - CPU: $CPU_USAGE%, Memory: $MEMORY_USAGE%, Disk: $DISK_USAGE%, DB Connections: $DB_CONNECTIONS" >> $LOG_FILE

# Make script executable
sudo chmod +x /usr/local/bin/monitor-performance.sh

# Add to crontab
echo "*/5 * * * * /usr/local/bin/monitor-performance.sh" | sudo crontab -
```

### Log Monitoring
```bash
# Configure log monitoring
sudo nano /etc/rsyslog.d/zenamanage.conf

# Application logs
:programname, isequal, "zenamanage" /var/log/zenamanage/application.log
:programname, isequal, "zenamanage-worker" /var/log/zenamanage/worker.log

# Restart rsyslog
sudo systemctl restart rsyslog
```

---

## 💾 BACKUP STRATEGY

### Database Backup
```bash
# Create automated backup script
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

### File Storage Backup
```bash
# Create file storage backup script
sudo nano /usr/local/bin/backup-storage.sh

#!/bin/bash
BACKUP_DIR="/var/backups/zenamanage/storage"
DATE=$(date +%Y%m%d_%H%M%S)
STORAGE_DIR="/var/www/zenamanage/storage"

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup storage directory
tar -czf $BACKUP_DIR/storage_$DATE.tar.gz $STORAGE_DIR

# Keep only last 30 days of storage backups
find $BACKUP_DIR -name "storage_*.tar.gz" -mtime +30 -delete

# Make script executable
sudo chmod +x /usr/local/bin/backup-storage.sh

# Add to crontab for weekly backups
echo "0 3 * * 0 /usr/local/bin/backup-storage.sh" | sudo crontab -
```

### Backup Verification
```bash
# Create backup verification script
sudo nano /usr/local/bin/verify-backup.sh

#!/bin/bash
BACKUP_DIR="/var/backups/zenamanage"
LATEST_DB=$(ls -t $BACKUP_DIR/database_*.sql.gz | head -n1)
LATEST_APP=$(ls -t $BACKUP_DIR/application_*.tar.gz | head -n1)

# Verify database backup
if [ -f "$LATEST_DB" ]; then
    echo "Verifying database backup: $LATEST_DB"
    gunzip -t $LATEST_DB
    if [ $? -eq 0 ]; then
        echo "Database backup is valid"
    else
        echo "Database backup is corrupted"
    fi
fi

# Verify application backup
if [ -f "$LATEST_APP" ]; then
    echo "Verifying application backup: $LATEST_APP"
    tar -tzf $LATEST_APP > /dev/null
    if [ $? -eq 0 ]; then
        echo "Application backup is valid"
    else
        echo "Application backup is corrupted"
    fi
fi

# Make script executable
sudo chmod +x /usr/local/bin/verify-backup.sh

# Add to crontab for daily verification
echo "0 4 * * * /usr/local/bin/verify-backup.sh" | sudo crontab -
```

---

## 📈 SCALING CONFIGURATION

### Horizontal Scaling
```bash
# Configure multiple application servers
sudo nano /etc/nginx/nginx.conf

upstream zenamanage_backend {
    server 192.168.1.10:8000 weight=3;
    server 192.168.1.11:8000 weight=3;
    server 192.168.1.12:8000 weight=2;
    server 192.168.1.13:8000 weight=2;
}

server {
    listen 80;
    server_name zenamanage.com;
    
    location / {
        proxy_pass http://zenamanage_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Database Scaling
```sql
-- Configure MySQL replication
-- Master server configuration
[mysqld]
server-id = 1
log-bin = mysql-bin
binlog-do-db = zenamanage_prod

-- Slave server configuration
[mysqld]
server-id = 2
relay-log = mysql-relay-bin
read-only = 1
```

### Cache Scaling
```bash
# Configure Redis cluster
sudo nano /etc/redis/redis.conf

# Master server
bind 0.0.0.0
port 6379

# Slave server
bind 0.0.0.0
port 6379
slaveof 192.168.1.10 6379
```

### Load Balancing
```bash
# Configure HAProxy
sudo nano /etc/haproxy/haproxy.cfg

global
    daemon
    maxconn 4096

defaults
    mode http
    timeout connect 5000ms
    timeout client 50000ms
    timeout server 50000ms

frontend zenamanage_frontend
    bind *:80
    bind *:443 ssl crt /etc/ssl/certs/zenamanage.pem
    redirect scheme https if !{ ssl_fc }
    default_backend zenamanage_backend

backend zenamanage_backend
    balance roundrobin
    server app1 192.168.1.10:8000 check
    server app2 192.168.1.11:8000 check
    server app3 192.168.1.12:8000 check
```

---

## 🔧 TROUBLESHOOTING

### Common Issues

#### 1. Application Not Loading
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

#### 2. Database Connection Issues
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

#### 3. Cache Issues
```bash
# Test Redis connection
redis-cli ping

# Check Redis status
sudo systemctl status redis

# Clear application cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

#### 4. Performance Issues
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

### Debugging Tools
```bash
# Install debugging tools
sudo apt install strace ltrace gdb

# Debug PHP processes
sudo strace -p $(pgrep php-fpm)

# Debug MySQL queries
mysql -u zenamanage_user -p -e "SET profiling = 1;"
mysql -u zenamanage_user -p -e "SHOW PROFILES;"

# Debug Redis operations
redis-cli monitor
```

### Log Analysis
```bash
# Analyze access logs
sudo awk '{print $1}' /var/log/nginx/zenamanage_access.log | sort | uniq -c | sort -nr

# Analyze error logs
sudo grep "ERROR" /var/log/zenamanage/application.log | tail -20

# Analyze slow queries
sudo mysqldumpslow /var/log/mysql/slow.log
```

---

## 🔄 MAINTENANCE PROCEDURES

### Daily Maintenance
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

# Check memory usage
MEMORY_USAGE=$(free | grep Mem | awk '{printf("%.2f"), $3/$2 * 100.0}')
if [ $(echo "$MEMORY_USAGE > 80" | bc) -eq 1 ]; then
    echo "$DATE - WARNING: Memory usage is $MEMORY_USAGE%" >> $LOG_FILE
fi

echo "$DATE - Daily maintenance completed" >> $LOG_FILE

# Make script executable
sudo chmod +x /usr/local/bin/daily-maintenance.sh

# Add to crontab
echo "0 1 * * * /usr/local/bin/daily-maintenance.sh" | sudo crontab -
```

### Weekly Maintenance
```bash
# Create weekly maintenance script
sudo nano /usr/local/bin/weekly-maintenance.sh

#!/bin/bash
LOG_FILE="/var/log/zenamanage/maintenance.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')

echo "$DATE - Starting weekly maintenance" >> $LOG_FILE

# Update system packages
apt update && apt upgrade -y
echo "$DATE - System packages updated" >> $LOG_FILE

# Clean old logs
find /var/log -name "*.log" -mtime +7 -delete
echo "$DATE - Old logs cleaned" >> $LOG_FILE

# Clean old backups
find /var/backups/zenamanage -name "*.sql.gz" -mtime +30 -delete
find /var/backups/zenamanage -name "*.tar.gz" -mtime +30 -delete
echo "$DATE - Old backups cleaned" >> $LOG_FILE

# Analyze database tables
mysql -u zenamanage_user -psecure_password -e "ANALYZE TABLE users, projects, tasks, clients;"
echo "$DATE - Database tables analyzed" >> $LOG_FILE

echo "$DATE - Weekly maintenance completed" >> $LOG_FILE

# Make script executable
sudo chmod +x /usr/local/bin/weekly-maintenance.sh

# Add to crontab
echo "0 2 * * 0 /usr/local/bin/weekly-maintenance.sh" | sudo crontab -
```

### Monthly Maintenance
```bash
# Create monthly maintenance script
sudo nano /usr/local/bin/monthly-maintenance.sh

#!/bin/bash
LOG_FILE="/var/log/zenamanage/maintenance.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')

echo "$DATE - Starting monthly maintenance" >> $LOG_FILE

# Security updates
apt update && apt upgrade -y
echo "$DATE - Security updates applied" >> $LOG_FILE

# Database maintenance
mysql -u zenamanage_user -psecure_password -e "CHECK TABLE users, projects, tasks, clients;"
echo "$DATE - Database integrity checked" >> $LOG_FILE

# Performance analysis
php /var/www/zenamanage/artisan tinker -c "App\Services\PerformanceMonitoringService::getAllMetrics();"
echo "$DATE - Performance metrics collected" >> $LOG_FILE

# SSL certificate check
certbot certificates
echo "$DATE - SSL certificates checked" >> $LOG_FILE

echo "$DATE - Monthly maintenance completed" >> $LOG_FILE

# Make script executable
sudo chmod +x /usr/local/bin/monthly-maintenance.sh

# Add to crontab
echo "0 3 1 * * /usr/local/bin/monthly-maintenance.sh" | sudo crontab -
```

---

## 📋 DEPLOYMENT CHECKLIST

### Pre-Deployment
- [ ] **Server Provisioning**: Server allocated and configured
- [ ] **Domain Setup**: Domain name registered and DNS configured
- [ ] **SSL Certificate**: SSL certificate obtained and configured
- [ ] **Database Setup**: MySQL/MariaDB server configured
- [ ] **Cache Setup**: Redis server configured
- [ ] **File Storage**: File storage configured
- [ ] **Security**: Firewall and security measures configured
- [ ] **Monitoring**: Monitoring tools configured
- [ ] **Backup**: Backup strategy implemented

### Deployment
- [ ] **Code Deployment**: Application code deployed
- [ ] **Dependencies**: PHP and Node.js dependencies installed
- [ ] **Configuration**: Environment variables configured
- [ ] **Database**: Database schema created and migrated
- [ ] **Web Server**: Nginx configured and SSL enabled
- [ ] **Application**: Application optimized and cached
- [ ] **Queue**: Queue workers configured
- [ ] **Permissions**: File permissions set correctly

### Post-Deployment
- [ ] **Testing**: Application functionality tested
- [ ] **Performance**: Performance metrics validated
- [ ] **Security**: Security measures verified
- [ ] **Monitoring**: Monitoring systems operational
- [ ] **Backup**: Backup systems tested
- [ ] **Documentation**: Deployment documentation updated
- [ ] **Team**: Team notified of deployment
- [ ] **Monitoring**: Continuous monitoring enabled

---

## 🆘 EMERGENCY PROCEDURES

### Rollback Procedure
```bash
# Create rollback script
sudo nano /usr/local/bin/rollback.sh

#!/bin/bash
BACKUP_DIR="/var/backups/zenamanage"
LATEST_BACKUP=$(ls -t $BACKUP_DIR/application_*.tar.gz | head -n1)

echo "Rolling back to: $LATEST_BACKUP"

# Stop services
sudo systemctl stop nginx
sudo systemctl stop php8.2-fpm

# Restore application
sudo tar -xzf $LATEST_BACKUP -C /

# Restore database
LATEST_DB=$(ls -t $BACKUP_DIR/database_*.sql.gz | head -n1)
gunzip -c $LATEST_DB | mysql -u zenamanage_user -psecure_password zenamanage_prod

# Restart services
sudo systemctl start php8.2-fpm
sudo systemctl start nginx

echo "Rollback completed"

# Make script executable
sudo chmod +x /usr/local/bin/rollback.sh
```

### Emergency Contacts
- **System Administrator**: admin@zenamanage.com
- **Database Administrator**: dba@zenamanage.com
- **Security Team**: security@zenamanage.com
- **Development Team**: dev@zenamanage.com

---

**ZenaManage Deployment Guide v2.0**  
*Last Updated: January 8, 2025*  
*For deployment support, contact deployment@zenamanage.com or visit our technical documentation center.*
