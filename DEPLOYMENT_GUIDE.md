# ZenaManage Deployment Guide

## Overview
This guide provides comprehensive instructions for deploying ZenaManage in various environments, from development to production.

## Prerequisites

### System Requirements
- **PHP**: 8.2 or higher
- **MySQL**: 8.0 or higher
- **Redis**: 6.0 or higher
- **Node.js**: 18.0 or higher
- **Composer**: Latest version
- **NPM**: Latest version

### Server Requirements
- **RAM**: Minimum 2GB, Recommended 4GB+
- **Storage**: Minimum 20GB SSD
- **CPU**: Minimum 2 cores, Recommended 4+ cores
- **Network**: Stable internet connection

## Environment Setup

### 1. Development Environment

#### Local Development with XAMPP
```bash
# Navigate to XAMPP htdocs
cd /Applications/XAMPP/xamppfiles/htdocs

# Clone repository
git clone https://github.com/your-org/zenamanage.git

# Navigate to project
cd zenamanage

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zenamanage
DB_USERNAME=root
DB_PASSWORD=

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Compile assets
npm run dev

# Start development server
php artisan serve --port=8002
```

#### Docker Development
```bash
# Create docker-compose.yml
version: '3.8'
services:
  app:
    build: .
    ports:
      - "8002:8000"
    volumes:
      - .:/var/www
    environment:
      - DB_HOST=mysql
      - REDIS_HOST=redis
    depends_on:
      - mysql
      - redis

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: zenamanage
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql

  redis:
    image: redis:6.0-alpine
    ports:
      - "6379:6379"

volumes:
  mysql_data:
```

```bash
# Start Docker containers
docker-compose up -d

# Install dependencies
docker-compose exec app composer install
docker-compose exec app npm install

# Run migrations
docker-compose exec app php artisan migrate

# Seed database
docker-compose exec app php artisan db:seed
```

### 2. Staging Environment

#### Server Setup
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.2
sudo apt install software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.2 php8.2-fpm php8.2-mysql php8.2-redis php8.2-mbstring php8.2-xml php8.2-gd php8.2-curl php8.2-zip php8.2-bcmath

# Install MySQL
sudo apt install mysql-server

# Install Redis
sudo apt install redis-server

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install nodejs

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Nginx
sudo apt install nginx
```

#### Application Deployment
```bash
# Create application directory
sudo mkdir -p /var/www/zenamanage
sudo chown -R $USER:$USER /var/www/zenamanage

# Clone repository
git clone https://github.com/your-org/zenamanage.git /var/www/zenamanage

# Navigate to project
cd /var/www/zenamanage

# Install dependencies
composer install --no-dev --optimize-autoloader
npm install
npm run build

# Set permissions
sudo chown -R www-data:www-data /var/www/zenamanage
sudo chmod -R 755 /var/www/zenamanage
sudo chmod -R 775 /var/www/zenamanage/storage
sudo chmod -R 775 /var/www/zenamanage/bootstrap/cache
```

#### Nginx Configuration
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/zenamanage/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 3. Production Environment

#### Production Server Setup
```bash
# Install additional security packages
sudo apt install fail2ban ufw

# Configure firewall
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw enable

# Configure fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

#### SSL Certificate Setup
```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --nginx -d your-domain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

#### Production Configuration
```bash
# Update .env for production
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zenamanage_production
DB_USERNAME=zenamanage_user
DB_PASSWORD=your_db_password_here

# Cache configuration
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Mail configuration
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
```

#### Production Optimization
```bash
# Optimize Composer autoloader
composer install --no-dev --optimize-autoloader

# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize database
php artisan optimize
```

## Database Setup

### MySQL Configuration
```sql
-- Create database
CREATE DATABASE zenamanage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'zenamanage_user'@'localhost' IDENTIFIED BY 'your_db_password_here';

-- Grant privileges
GRANT ALL PRIVILEGES ON zenamanage.* TO 'zenamanage_user'@'localhost';
FLUSH PRIVILEGES;

-- Optimize MySQL configuration
[mysqld]
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
```

### Redis Configuration
```bash
# Configure Redis
sudo nano /etc/redis/redis.conf

# Set memory limit
maxmemory 256mb
maxmemory-policy allkeys-lru

# Enable persistence
save 900 1
save 300 10
save 60 10000

# Restart Redis
sudo systemctl restart redis
```

## Monitoring & Logging

### Application Monitoring
```bash
# Install monitoring tools
sudo apt install htop iotop nethogs

# Configure log rotation
sudo nano /etc/logrotate.d/zenamanage

/var/www/zenamanage/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    create 644 www-data www-data
}
```

### Performance Monitoring
```bash
# Install monitoring tools
sudo apt install nginx-module-njs

# Configure Nginx monitoring
location /nginx_status {
    stub_status on;
    access_log off;
    allow 127.0.0.1;
    deny all;
}
```

## Backup Strategy

### Database Backup
```bash
#!/bin/bash
# backup-database.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/zenamanage"
DB_NAME="zenamanage"
DB_USER="zenamanage_user"
DB_PASS="your_db_password_here"

mkdir -p $BACKUP_DIR

# Create database backup
mysqldump -u$DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/database_$DATE.sql

# Compress backup
gzip $BACKUP_DIR/database_$DATE.sql

# Remove old backups (keep 30 days)
find $BACKUP_DIR -name "database_*.sql.gz" -mtime +30 -delete

echo "Database backup completed: $BACKUP_DIR/database_$DATE.sql.gz"
```

### File Backup
```bash
#!/bin/bash
# backup-files.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/zenamanage"
APP_DIR="/var/www/zenamanage"

mkdir -p $BACKUP_DIR

# Create file backup
tar -czf $BACKUP_DIR/files_$DATE.tar.gz -C $APP_DIR storage/app/public

# Remove old backups (keep 30 days)
find $BACKUP_DIR -name "files_*.tar.gz" -mtime +30 -delete

echo "File backup completed: $BACKUP_DIR/files_$DATE.tar.gz"
```

### Automated Backup
```bash
# Add to crontab
sudo crontab -e

# Daily backups at 2 AM
0 2 * * * /var/www/zenamanage/scripts/backup-database.sh
0 2 * * * /var/www/zenamanage/scripts/backup-files.sh
```

## Security Configuration

### Application Security
```bash
# Set secure file permissions
sudo chown -R www-data:www-data /var/www/zenamanage
sudo chmod -R 755 /var/www/zenamanage
sudo chmod -R 775 /var/www/zenamanage/storage
sudo chmod -R 775 /var/www/zenamanage/bootstrap/cache

# Remove sensitive files
rm /var/www/zenamanage/.env.example
rm /var/www/zenamanage/README.md
```

### Server Security
```bash
# Configure SSH
sudo nano /etc/ssh/sshd_config

# Disable root login
PermitRootLogin no

# Use key-based authentication
PasswordAuthentication no

# Restart SSH
sudo systemctl restart ssh
```

### Firewall Configuration
```bash
# Configure UFW
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

## Troubleshooting

### Common Issues

#### Permission Issues
```bash
# Fix file permissions
sudo chown -R www-data:www-data /var/www/zenamanage
sudo chmod -R 755 /var/www/zenamanage
sudo chmod -R 775 /var/www/zenamanage/storage
sudo chmod -R 775 /var/www/zenamanage/bootstrap/cache
```

#### Database Connection Issues
```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();

# Check database configuration
php artisan config:show database
```

#### Cache Issues
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

#### Performance Issues
```bash
# Check server resources
htop
df -h
free -h

# Check Nginx logs
sudo tail -f /var/log/nginx/error.log

# Check application logs
tail -f /var/www/zenamanage/storage/logs/laravel.log
```

### Debugging Tools
```bash
# Enable debug mode temporarily
php artisan config:clear
# Edit .env: APP_DEBUG=true
php artisan config:cache

# Check application status
php artisan about

# Check queue status
php artisan queue:work --verbose
```

## Maintenance

### Regular Maintenance Tasks
```bash
# Weekly maintenance script
#!/bin/bash

# Clear old logs
find /var/www/zenamanage/storage/logs -name "*.log" -mtime +7 -delete

# Optimize database
php artisan optimize

# Clear old cache
php artisan cache:clear

# Update dependencies (if needed)
composer update --no-dev --optimize-autoloader

echo "Weekly maintenance completed"
```

### Monitoring Scripts
```bash
# Health check script
#!/bin/bash

# Check application status
curl -f http://localhost/health || echo "Application is down"

# Check database connection
php artisan tinker --execute="DB::connection()->getPdo();" || echo "Database connection failed"

# Check Redis connection
redis-cli ping || echo "Redis connection failed"

# Check disk space
df -h | grep -E '^/dev/' | awk '{print $5}' | sed 's/%//' | while read usage; do
    if [ $usage -gt 80 ]; then
        echo "Disk usage is high: $usage%"
    fi
done
```

## Scaling

### Horizontal Scaling
```bash
# Load balancer configuration
upstream zenamanage {
    server 192.168.1.10:8000;
    server 192.168.1.11:8000;
    server 192.168.1.12:8000;
}

server {
    listen 80;
    server_name your-domain.com;
    
    location / {
        proxy_pass http://zenamanage;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Vertical Scaling
```bash
# Increase PHP memory limit
sudo nano /etc/php/8.2/fpm/php.ini
memory_limit = 512M

# Increase MySQL buffer pool
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
innodb_buffer_pool_size = 2G

# Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart mysql
```

## Disaster Recovery

### Recovery Procedures
```bash
# Restore database
gunzip /var/backups/zenamanage/database_20250924_020000.sql.gz
mysql -u zenamanage_user -p zenamanage < /var/backups/zenamanage/database_20250924_020000.sql

# Restore files
tar -xzf /var/backups/zenamanage/files_20250924_020000.tar.gz -C /var/www/zenamanage/storage/app/public

# Restart services
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
sudo systemctl restart mysql
sudo systemctl restart redis
```

## Support

### Documentation
- **API Documentation**: `/API_DOCUMENTATION.md`
- **User Guide**: `/USER_DOCUMENTATION.md`
- **Developer Guide**: `/DEVELOPER_DOCUMENTATION.md`

### Contact Information
- **Technical Support**: support@zenamanage.com
- **Emergency Contact**: +1 (555) 123-4567
- **Documentation**: https://docs.zenamanage.com

---

*Last updated: September 24, 2025*
*Version: 1.0*
