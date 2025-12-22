# Production Deployment Guide

## Overview
This guide provides comprehensive instructions for deploying ZenaManage to production environments.

## Prerequisites

### System Requirements
- **PHP**: 8.2+ with required extensions
- **Database**: MySQL 8.0+ or PostgreSQL 13+
- **Cache**: Redis 6.0+ or Memcached
- **Web Server**: Nginx 1.18+ or Apache 2.4+
- **SSL Certificate**: Valid SSL certificate for HTTPS

### Required PHP Extensions
```bash
php -m | grep -E "(bcmath|ctype|fileinfo|json|mbstring|openssl|pdo|tokenizer|xml|zip|gd|curl|intl)"
```

### Server Configuration
- **Memory**: Minimum 2GB RAM, Recommended 4GB+
- **Storage**: Minimum 20GB SSD, Recommended 50GB+
- **CPU**: Minimum 2 cores, Recommended 4+ cores

## Environment Setup

### 1. Clone Repository
```bash
git clone https://github.com/your-org/zenamanage.git
cd zenamanage
```

### 2. Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
npm install --production
npm run build
```

### 3. Environment Configuration
```bash
cp config/production.env.example .env
# Edit .env with production values
```

### 4. Generate Application Key
```bash
php artisan key:generate
```

### 5. Database Setup
```bash
php artisan migrate --force
php artisan db:seed --force
```

### 6. Storage Setup
```bash
php artisan storage:link
chmod -R 755 storage bootstrap/cache
```

### 7. Cache Configuration
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Production Configuration

### Database Configuration
```php
// config/database.php
'mysql' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
    'engine' => 'InnoDB',
    'options' => [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ],
],
```

### Cache Configuration
```php
// config/cache.php
'default' => env('CACHE_DRIVER', 'redis'),
'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_CACHE_DB', 1),
    ],
],
```

### Session Configuration
```php
// config/session.php
'driver' => env('SESSION_DRIVER', 'redis'),
'lifetime' => env('SESSION_LIFETIME', 120),
'expire_on_close' => true,
'encrypt' => true,
'secure' => true,
'http_only' => true,
'same_site' => 'strict',
```

## Web Server Configuration

### Nginx Configuration
```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    root /path/to/zenamanage/public;
    index index.php;
    
    # SSL Configuration
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;
    
    # Security Headers
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    
    # PHP-FPM Configuration
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Static Files
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ /(storage|bootstrap/cache) {
        deny all;
    }
}
```

### Apache Configuration
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    Redirect permanent / https://your-domain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /path/to/zenamanage/public
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    
    # Security Headers
    Header always set X-Frame-Options "DENY"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    
    # PHP Configuration
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php8.2-fpm.sock|fcgi://localhost"
    </FilesMatch>
    
    # Static Files
    <FilesMatch "\.(css|js|png|jpg|jpeg|gif|ico|svg)$">
        ExpiresActive On
        ExpiresDefault "access plus 1 year"
        Header set Cache-Control "public, immutable"
    </FilesMatch>
    
    # Deny access to sensitive files
    <FilesMatch "^\.">
        Require all denied
    </FilesMatch>
    
    <Directory "/path/to/zenamanage/storage">
        Require all denied
    </Directory>
    
    <Directory "/path/to/zenamanage/bootstrap/cache">
        Require all denied
    </Directory>
</VirtualHost>
```

## Database Optimization

### MySQL Configuration
```ini
# /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
query_cache_size = 256M
query_cache_type = 1
max_connections = 200
slow_query_log = 1
long_query_time = 1
```

### PostgreSQL Configuration
```ini
# /etc/postgresql/13/main/postgresql.conf
shared_buffers = 256MB
effective_cache_size = 1GB
work_mem = 4MB
maintenance_work_mem = 64MB
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100
```

## Cache Configuration

### Redis Configuration
```ini
# /etc/redis/redis.conf
maxmemory 512mb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

### Memcached Configuration
```ini
# /etc/memcached.conf
-m 512
-u memcache
-l 127.0.0.1
-p 11211
-c 1024
```

## Monitoring Setup

### 1. Health Check Endpoints
```bash
# Basic health check
curl https://your-domain.com/api/v1/health/basic

# Detailed health check
curl https://your-domain.com/api/v1/health/detailed

# Production readiness
curl https://your-domain.com/api/v1/health/production-readiness
```

### 2. Log Monitoring
```bash
# Monitor application logs
tail -f storage/logs/laravel.log

# Monitor security logs
tail -f storage/logs/security.log

# Monitor error logs
tail -f storage/logs/error.log
```

### 3. Performance Monitoring
```bash
# Monitor system resources
htop
iotop
nethogs

# Monitor database performance
mysqladmin processlist
mysqladmin status
```

## Backup Procedures

### 1. Automated Backup Script
```bash
#!/bin/bash
# /usr/local/bin/zenamanage-backup.sh

BACKUP_DIR="/var/backups/zenamanage"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="zenamanage_backup_$DATE"

# Create backup directory
mkdir -p "$BACKUP_DIR/$BACKUP_NAME"

# Database backup
mysqldump -u root -p zenamanage > "$BACKUP_DIR/$BACKUP_NAME/database.sql"

# File backup
tar -czf "$BACKUP_DIR/$BACKUP_NAME/files.tar.gz" /path/to/zenamanage/storage

# Configuration backup
tar -czf "$BACKUP_DIR/$BACKUP_NAME/config.tar.gz" /path/to/zenamanage/.env /path/to/zenamanage/config

# Cleanup old backups (keep 30 days)
find "$BACKUP_DIR" -type d -mtime +30 -exec rm -rf {} \;

echo "Backup completed: $BACKUP_NAME"
```

### 2. Cron Job Setup
```bash
# Add to crontab
0 2 * * * /usr/local/bin/zenamanage-backup.sh
```

## Security Hardening

### 1. File Permissions
```bash
# Set proper permissions
chmod -R 755 /path/to/zenamanage
chmod -R 775 /path/to/zenamanage/storage
chmod -R 775 /path/to/zenamanage/bootstrap/cache
chown -R www-data:www-data /path/to/zenamanage
```

### 2. Firewall Configuration
```bash
# UFW (Ubuntu)
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable

# iptables (CentOS)
iptables -A INPUT -p tcp --dport 22 -j ACCEPT
iptables -A INPUT -p tcp --dport 80 -j ACCEPT
iptables -A INPUT -p tcp --dport 443 -j ACCEPT
iptables -A INPUT -j DROP
```

### 3. SSL/TLS Configuration
```bash
# Generate SSL certificate (Let's Encrypt)
certbot --nginx -d your-domain.com

# Or use existing certificate
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /path/to/private.key \
    -out /path/to/certificate.crt
```

## Deployment Checklist

### Pre-Deployment
- [ ] Environment variables configured
- [ ] Database migrations ready
- [ ] SSL certificate installed
- [ ] Web server configured
- [ ] Cache server configured
- [ ] Backup procedures tested

### Deployment
- [ ] Code deployed to production
- [ ] Dependencies installed
- [ ] Database migrated
- [ ] Cache cleared
- [ ] Permissions set
- [ ] Health checks passing

### Post-Deployment
- [ ] Monitoring configured
- [ ] Backup procedures running
- [ ] Security scan completed
- [ ] Performance testing done
- [ ] Documentation updated

## Troubleshooting

### Common Issues

#### 1. Database Connection Issues
```bash
# Check database connectivity
php artisan tinker
>>> DB::connection()->getPdo();

# Check database configuration
php artisan config:show database
```

#### 2. Cache Issues
```bash
# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Test cache
php artisan tinker
>>> Cache::put('test', 'value');
>>> Cache::get('test');
```

#### 3. Permission Issues
```bash
# Fix permissions
sudo chown -R www-data:www-data /path/to/zenamanage
sudo chmod -R 755 /path/to/zenamanage
sudo chmod -R 775 /path/to/zenamanage/storage
sudo chmod -R 775 /path/to/zenamanage/bootstrap/cache
```

#### 4. Performance Issues
```bash
# Check system resources
htop
df -h
free -h

# Check application performance
php artisan tinker
>>> app('App\Services\PerformanceMonitoringService')->getAllMetrics();
```

## Maintenance Procedures

### Daily Tasks
- [ ] Check system health
- [ ] Monitor error logs
- [ ] Verify backup completion
- [ ] Check disk space

### Weekly Tasks
- [ ] Review security logs
- [ ] Update dependencies
- [ ] Performance analysis
- [ ] Backup verification

### Monthly Tasks
- [ ] Security audit
- [ ] Performance optimization
- [ ] Documentation review
- [ ] Disaster recovery test

## Support and Maintenance

### Contact Information
- **Technical Support**: support@your-domain.com
- **Emergency Contact**: +1-XXX-XXX-XXXX
- **Documentation**: https://docs.your-domain.com

### Escalation Procedures
1. **Level 1**: Basic troubleshooting
2. **Level 2**: Advanced technical issues
3. **Level 3**: Critical system failures
4. **Level 4**: Vendor escalation

### Maintenance Windows
- **Planned Maintenance**: First Sunday of each month, 2:00 AM - 4:00 AM UTC
- **Emergency Maintenance**: As needed with 24-hour notice when possible
- **Security Updates**: Applied within 48 hours of release

---

**Last Updated**: 2025-01-05
**Version**: 1.0
**Author**: ZenaManage Development Team
