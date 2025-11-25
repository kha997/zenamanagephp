# 🚀 **ZENAMANAGE DASHBOARD SYSTEM - INSTALLATION GUIDE**

## **Table of Contents**

1. [System Requirements](#system-requirements)
2. [Installation Methods](#installation-methods)
3. [Docker Installation](#docker-installation)
4. [Manual Installation](#manual-installation)
5. [Configuration](#configuration)
6. [Database Setup](#database-setup)
7. [Web Server Configuration](#web-server-configuration)
8. [SSL Certificate Setup](#ssl-certificate-setup)
9. [Post-Installation](#post-installation)
10. [Troubleshooting](#troubleshooting)

---

## 💻 **SYSTEM REQUIREMENTS**

### **Server Requirements**

#### **Minimum Requirements**
- **OS:** Ubuntu 20.04+ / CentOS 8+ / RHEL 8+
- **RAM:** 2GB (4GB recommended)
- **CPU:** 2 cores (4 cores recommended)
- **Storage:** 20GB free space (50GB recommended)
- **Network:** Stable internet connection

#### **Software Requirements**
- **PHP:** 8.2 or higher
- **MySQL:** 8.0 or higher
- **Redis:** 6.0 or higher
- **Nginx:** 1.18+ or Apache 2.4+
- **Composer:** 2.0+
- **Node.js:** 18+ (for frontend build)
- **Git:** Latest version

#### **PHP Extensions**
```bash
php-bcmath
php-cli
php-common
php-curl
php-gd
php-imagick
php-intl
php-json
php-mbstring
php-mysql
php-opcache
php-redis
php-xml
php-zip
```

### **Client Requirements**

#### **Browser Support**
- **Chrome:** 90+
- **Firefox:** 88+
- **Safari:** 14+
- **Edge:** 90+
- **Mobile Browsers:** iOS Safari 14+, Chrome Mobile 90+

---

## 📦 **INSTALLATION METHODS**

### **Installation Options**

1. **Docker Installation** (Recommended)
   - Quick setup với Docker Compose
   - Isolated environment
   - Easy maintenance

2. **Manual Installation**
   - Full control over configuration
   - Custom server setup
   - Production optimization

3. **Cloud Installation**
   - AWS, Google Cloud, Azure
   - Managed services
   - Scalable deployment

---

## 🐳 **DOCKER INSTALLATION**

### **Prerequisites**

#### **Install Docker**
```bash
# Ubuntu/Debian
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh

# CentOS/RHEL
sudo yum install -y docker
sudo systemctl start docker
sudo systemctl enable docker

# Add user to docker group
sudo usermod -aG docker $USER
```

#### **Install Docker Compose**
```bash
# Download Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/download/v2.20.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose

# Make executable
sudo chmod +x /usr/local/bin/docker-compose

# Verify installation
docker-compose --version
```

### **Docker Installation Steps**

#### **1. Clone Repository**
```bash
git clone https://github.com/zenamanage/dashboard.git
cd dashboard
```

#### **2. Environment Configuration**
```bash
# Copy environment file
cp .env.example .env

# Edit environment variables
nano .env
```

#### **3. Configure Environment**
```bash
# Database Configuration
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=zenamanage_dashboard
DB_USERNAME=zenamanage_user
DB_PASSWORD=secure_password_here

# Redis Configuration
REDIS_HOST=redis
REDIS_PASSWORD=redis_password_here
REDIS_PORT=6379

# Application Configuration
APP_NAME="ZenaManage Dashboard"
APP_ENV=production
APP_KEY=base64:your-app-key-here
APP_DEBUG=false
APP_URL=https://dashboard.zenamanage.com

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=noreply@zenamanage.com
MAIL_PASSWORD=mail_password_here
MAIL_ENCRYPTION=tls
```

#### **4. Build và Start Services**
```bash
# Build Docker images
docker-compose build

# Start services
docker-compose up -d

# Check service status
docker-compose ps
```

#### **5. Application Setup**
```bash
# Install dependencies
docker-compose exec app composer install

# Generate application key
docker-compose exec app php artisan key:generate

# Run database migrations
docker-compose exec app php artisan migrate

# Seed database (optional)
docker-compose exec app php artisan db:seed

# Build frontend assets
docker-compose exec app npm install
docker-compose exec app npm run build

# Set permissions
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
```

#### **6. Verify Installation**
```bash
# Check application health
curl http://localhost/health

# Check database connection
docker-compose exec app php artisan tinker --execute="DB::connection()->getPdo();"

# Check Redis connection
docker-compose exec app php artisan tinker --execute="Redis::ping();"
```

---

## 🔧 **MANUAL INSTALLATION**

### **Step 1: Server Setup**

#### **Update System**
```bash
# Ubuntu/Debian
sudo apt update && sudo apt upgrade -y

# CentOS/RHEL
sudo yum update -y
```

#### **Install PHP 8.2**
```bash
# Ubuntu/Debian
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-redis php8.2-gd php8.2-mbstring php8.2-xml php8.2-zip php8.2-curl php8.2-bcmath php8.2-intl php8.2-imagick

# CentOS/RHEL
sudo yum install -y epel-release
sudo yum install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm
sudo yum module enable php:remi-8.2
sudo yum install -y php php-cli php-fpm php-mysqlnd php-redis php-gd php-mbstring php-xml php-zip php-curl php-bcmath php-intl php-imagick
```

#### **Install MySQL 8.0**
```bash
# Ubuntu/Debian
sudo apt install -y mysql-server mysql-client

# CentOS/RHEL
sudo yum install -y mysql-server mysql
sudo systemctl start mysqld
sudo systemctl enable mysqld
```

#### **Install Redis**
```bash
# Ubuntu/Debian
sudo apt install -y redis-server

# CentOS/RHEL
sudo yum install -y redis
sudo systemctl start redis
sudo systemctl enable redis
```

#### **Install Nginx**
```bash
# Ubuntu/Debian
sudo apt install -y nginx

# CentOS/RHEL
sudo yum install -y nginx
sudo systemctl start nginx
sudo systemctl enable nginx
```

#### **Install Composer**
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

#### **Install Node.js**
```bash
# Using NodeSource repository
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# Or using nvm
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
source ~/.bashrc
nvm install 18
nvm use 18
```

### **Step 2: Application Installation**

#### **Clone Repository**
```bash
git clone https://github.com/zenamanage/dashboard.git
cd dashboard
```

#### **Install Dependencies**
```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node.js dependencies
npm install

# Build frontend assets
npm run build
```

#### **Environment Configuration**
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Edit environment variables
nano .env
```

### **Step 3: Database Setup**

#### **Create Database**
```sql
-- Login to MySQL
mysql -u root -p

-- Create database
CREATE DATABASE zenamanage_dashboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'zenamanage_user'@'localhost' IDENTIFIED BY 'secure_password_here';

-- Grant privileges
GRANT ALL PRIVILEGES ON zenamanage_dashboard.* TO 'zenamanage_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### **Run Migrations**
```bash
# Run database migrations
php artisan migrate

# Seed database (optional)
php artisan db:seed
```

### **Step 4: Web Server Configuration**

#### **Nginx Configuration**
```nginx
# Create Nginx configuration
sudo nano /etc/nginx/sites-available/zenamanage

# Add configuration
server {
    listen 80;
    server_name dashboard.zenamanage.com;
    root /var/www/zenamanage/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

# Enable site
sudo ln -s /etc/nginx/sites-available/zenamanage /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# Restart Nginx
sudo systemctl restart nginx
```

#### **PHP-FPM Configuration**
```bash
# Edit PHP-FPM configuration
sudo nano /etc/php/8.2/fpm/pool.d/www.conf

# Update settings
user = www-data
group = www-data
listen = /var/run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

### **Step 5: Set Permissions**

```bash
# Set ownership
sudo chown -R www-data:www-data /var/www/zenamanage

# Set permissions
sudo find /var/www/zenamanage -type f -exec chmod 644 {} \;
sudo find /var/www/zenamanage -type d -exec chmod 755 {} \;

# Set executable permissions
sudo chmod +x /var/www/zenamanage/scripts/*.sh

# Set storage permissions
sudo chmod -R 775 /var/www/zenamanage/storage
sudo chmod -R 775 /var/www/zenamanage/bootstrap/cache
```

---

## ⚙️ **CONFIGURATION**

### **Environment Variables**

#### **Application Configuration**
```bash
APP_NAME="ZenaManage Dashboard"
APP_ENV=production
APP_KEY=base64:your-generated-key
APP_DEBUG=false
APP_URL=https://dashboard.zenamanage.com
```

#### **Database Configuration**
```bash
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zenamanage_dashboard
DB_USERNAME=zenamanage_user
DB_PASSWORD=secure_password_here
```

#### **Cache Configuration**
```bash
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=redis_password_here
REDIS_PORT=6379
```

#### **Session Configuration**
```bash
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=.zenamanage.com
```

#### **Queue Configuration**
```bash
QUEUE_CONNECTION=redis
QUEUE_REDIS_HOST=127.0.0.1
QUEUE_REDIS_PASSWORD=redis_password_here
QUEUE_REDIS_PORT=6379
QUEUE_REDIS_DATABASE=1
```

#### **Mail Configuration**
```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=noreply@zenamanage.com
MAIL_PASSWORD=mail_password_here
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@zenamanage.com
MAIL_FROM_NAME="ZenaManage Dashboard"
```

### **PHP Configuration**

#### **php.ini Settings**
```ini
# Memory and execution limits
memory_limit = 256M
max_execution_time = 300
max_input_time = 300

# File upload limits
upload_max_filesize = 10M
post_max_size = 10M
max_file_uploads = 20

# Error reporting
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
display_errors = Off
log_errors = On

# OPcache settings
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
opcache.revalidate_freq = 0
opcache.save_comments = 0
```

---

## 🗄️ **DATABASE SETUP**

### **MySQL Configuration**

#### **my.cnf Settings**
```ini
[mysqld]
# Basic settings
default-storage-engine = InnoDB
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# Performance settings
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Connection settings
max_connections = 200
max_connect_errors = 1000
wait_timeout = 28800
interactive_timeout = 28800

# Query cache
query_cache_type = 1
query_cache_size = 64M
query_cache_limit = 2M

# Logging
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
```

#### **Create Database User**
```sql
-- Create application user
CREATE USER 'zenamanage_user'@'localhost' IDENTIFIED BY 'secure_password_here';

-- Grant privileges
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER ON zenamanage_dashboard.* TO 'zenamanage_user'@'localhost';

-- Create backup user
CREATE USER 'zenamanage_backup'@'localhost' IDENTIFIED BY 'backup_password_here';
GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER ON zenamanage_dashboard.* TO 'zenamanage_backup'@'localhost';

-- Flush privileges
FLUSH PRIVILEGES;
```

### **Redis Configuration**

#### **redis.conf Settings**
```conf
# Network settings
bind 127.0.0.1
port 6379
timeout 300

# Memory settings
maxmemory 512mb
maxmemory-policy allkeys-lru

# Persistence settings
save 900 1
save 300 10
save 60 10000

# Security
requirepass redis_password_here

# Logging
loglevel notice
logfile /var/log/redis/redis-server.log
```

---

## 🌐 **WEB SERVER CONFIGURATION**

### **Nginx Configuration**

#### **Production Configuration**
```nginx
server {
    listen 80;
    server_name dashboard.zenamanage.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name dashboard.zenamanage.com;
    root /var/www/zenamanage/public;
    index index.php;

    # SSL Configuration
    ssl_certificate /etc/ssl/certs/zenamanage.crt;
    ssl_certificate_key /etc/ssl/private/zenamanage.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # Security Headers
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss application/atom+xml image/svg+xml;

    # Static Files
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Application
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP Processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # FastCGI Settings
        fastcgi_connect_timeout 60s;
        fastcgi_send_timeout 60s;
        fastcgi_read_timeout 60s;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    location ~ /(storage|bootstrap/cache) {
        deny all;
        access_log off;
        log_not_found off;
    }
}
```

### **Apache Configuration**

#### **Virtual Host Configuration**
```apache
<VirtualHost *:80>
    ServerName dashboard.zenamanage.com
    DocumentRoot /var/www/zenamanage/public
    
    <Directory /var/www/zenamanage/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/zenamanage_error.log
    CustomLog ${APACHE_LOG_DIR}/zenamanage_access.log combined
</VirtualHost>

<VirtualHost *:443>
    ServerName dashboard.zenamanage.com
    DocumentRoot /var/www/zenamanage/public
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/zenamanage.crt
    SSLCertificateKeyFile /etc/ssl/private/zenamanage.key
    
    <Directory /var/www/zenamanage/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/zenamanage_ssl_error.log
    CustomLog ${APACHE_LOG_DIR}/zenamanage_ssl_access.log combined
</VirtualHost>
```

---

## 🔒 **SSL CERTIFICATE SETUP**

### **Let's Encrypt (Free)**

#### **Install Certbot**
```bash
# Ubuntu/Debian
sudo apt install -y certbot python3-certbot-nginx

# CentOS/RHEL
sudo yum install -y certbot python3-certbot-nginx
```

#### **Obtain Certificate**
```bash
# Obtain certificate
sudo certbot --nginx -d dashboard.zenamanage.com

# Test renewal
sudo certbot renew --dry-run

# Setup auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

### **Commercial Certificate**

#### **Generate CSR**
```bash
# Generate private key
openssl genrsa -out zenamanage.key 2048

# Generate CSR
openssl req -new -key zenamanage.key -out zenamanage.csr

# Submit CSR to certificate authority
```

#### **Install Certificate**
```bash
# Copy certificate files
sudo cp zenamanage.crt /etc/ssl/certs/
sudo cp zenamanage.key /etc/ssl/private/
sudo chmod 600 /etc/ssl/private/zenamanage.key

# Update Nginx configuration
sudo nano /etc/nginx/sites-available/zenamanage
```

---

## ✅ **POST-INSTALLATION**

### **Application Setup**

#### **Run Setup Commands**
```bash
# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set up queue worker
php artisan queue:work --daemon

# Set up scheduler
php artisan schedule:work
```

#### **Create System User**
```bash
# Create system user for application
sudo useradd -r -s /bin/false zenamanage

# Set ownership
sudo chown -R zenamanage:zenamanage /var/www/zenamanage
```

#### **Setup Systemd Services**
```bash
# Create queue worker service
sudo nano /etc/systemd/system/zenamanage-queue.service

[Unit]
Description=ZenaManage Queue Worker
After=network.target

[Service]
User=zenamanage
Group=zenamanage
WorkingDirectory=/var/www/zenamanage
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target

# Enable and start service
sudo systemctl enable zenamanage-queue
sudo systemctl start zenamanage-queue
```

### **Monitoring Setup**

#### **Install Monitoring Tools**
```bash
# Install monitoring tools
sudo apt install -y htop iotop nethogs

# Setup log rotation
sudo nano /etc/logrotate.d/zenamanage

/var/www/zenamanage/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
```

### **Backup Setup**

#### **Create Backup Script**
```bash
# Create backup directory
sudo mkdir -p /var/backups/zenamanage

# Create backup script
sudo nano /usr/local/bin/zenamanage-backup.sh

#!/bin/bash
BACKUP_DIR="/var/backups/zenamanage"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="zenamanage_dashboard"
DB_USER="zenamanage_backup"
DB_PASS="backup_password_here"

# Database backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/db_backup_$DATE.sql

# Application backup
tar -czf $BACKUP_DIR/app_backup_$DATE.tar.gz /var/www/zenamanage

# Cleanup old backups (keep 30 days)
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete

# Make executable
sudo chmod +x /usr/local/bin/zenamanage-backup.sh

# Setup cron job
sudo crontab -e
# Add: 0 2 * * * /usr/local/bin/zenamanage-backup.sh
```

---

## 🔧 **TROUBLESHOOTING**

### **Common Issues**

#### **Permission Issues**
```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/zenamanage

# Fix permissions
sudo find /var/www/zenamanage -type f -exec chmod 644 {} \;
sudo find /var/www/zenamanage -type d -exec chmod 755 {} \;
sudo chmod -R 775 /var/www/zenamanage/storage
sudo chmod -R 775 /var/www/zenamanage/bootstrap/cache
```

#### **Database Connection Issues**
```bash
# Test database connection
php artisan tinker --execute="DB::connection()->getPdo();"

# Check database status
sudo systemctl status mysql

# Check MySQL logs
sudo tail -f /var/log/mysql/error.log
```

#### **Redis Connection Issues**
```bash
# Test Redis connection
php artisan tinker --execute="Redis::ping();"

# Check Redis status
sudo systemctl status redis

# Check Redis logs
sudo tail -f /var/log/redis/redis-server.log
```

#### **Web Server Issues**
```bash
# Test Nginx configuration
sudo nginx -t

# Check Nginx status
sudo systemctl status nginx

# Check Nginx logs
sudo tail -f /var/log/nginx/error.log
```

#### **PHP Issues**
```bash
# Check PHP-FPM status
sudo systemctl status php8.2-fpm

# Check PHP logs
sudo tail -f /var/log/php8.2-fpm.log

# Test PHP configuration
php -m | grep -E "(mysql|redis|gd|mbstring)"
```

### **Performance Issues**

#### **Slow Response Times**
```bash
# Check system resources
htop
iotop
df -h

# Check database performance
mysql -u root -p -e "SHOW PROCESSLIST;"

# Check Redis performance
redis-cli info stats
```

#### **Memory Issues**
```bash
# Check memory usage
free -h
ps aux --sort=-%mem | head

# Check PHP memory limit
php -i | grep memory_limit

# Check MySQL memory usage
mysql -u root -p -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';"
```

### **Security Issues**

#### **SSL Certificate Issues**
```bash
# Test SSL certificate
openssl s_client -connect dashboard.zenamanage.com:443

# Check certificate expiration
echo | openssl s_client -servername dashboard.zenamanage.com -connect dashboard.zenamanage.com:443 2>/dev/null | openssl x509 -noout -dates
```

#### **Firewall Issues**
```bash
# Check firewall status
sudo ufw status

# Allow necessary ports
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 6001/tcp
```

---

## 📞 **SUPPORT**

### **Getting Help**

#### **Documentation**
- **User Manual:** Complete user guide
- **API Documentation:** Developer resources
- **Installation Guide:** This document
- **Troubleshooting Guide:** Common issues

#### **Support Channels**
- **Email:** support@zenamanage.com
- **Phone:** +1-800-ZENAMANAGE
- **Live Chat:** Available on website
- **Support Portal:** https://support.zenamanage.com
- **Community Forum:** User community

#### **Professional Support**
- **Installation Support:** Professional installation service
- **Configuration Support:** Custom configuration assistance
- **Training:** User và administrator training
- **Maintenance:** Ongoing maintenance support

---

*Installation Guide generated on: January 17, 2025*  
*Version: 1.0.0*  
*Last Updated: January 17, 2025*
