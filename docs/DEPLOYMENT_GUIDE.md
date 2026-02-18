# Z.E.N.A Project Management - Production Deployment Guide

## 📖 Mục lục
1. [Yêu cầu hệ thống](#yêu-cầu-hệ-thống)
2. [Chuẩn bị môi trường](#chuẩn-bị-môi-trường)
3. [Deployment bằng Docker](#deployment-bằng-docker)
4. [Deployment thủ công](#deployment-thủ-công)
5. [Cấu hình CI/CD](#cấu-hình-cicd)
6. [Monitoring và Backup](#monitoring-và-backup)
7. [Checklist Go-Live](#checklist-go-live)
8. [Troubleshooting](#troubleshooting)

## 🖥️ Yêu cầu hệ thống

### Minimum Requirements
- **CPU**: 2 cores
- **RAM**: 4GB
- **Storage**: 50GB SSD
- **OS**: Ubuntu 20.04+ / CentOS 8+ / RHEL 8+

### Recommended Requirements
- **CPU**: 4+ cores
- **RAM**: 8GB+
- **Storage**: 100GB+ SSD
- **Network**: 1Gbps

### Software Requirements
- **PHP**: 8.0+
- **MySQL**: 8.0+
- **Nginx**: 1.18+
- **Node.js**: 16+
- **Redis**: 6.0+
- **Docker**: 20.10+ (nếu sử dụng Docker)

## 🔧 Chuẩn bị môi trường

### 1. Cập nhật hệ thống
```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Cài đặt dependencies
```bash
# PHP và extensions
sudo apt install php8.0-fpm php8.0-mysql php8.0-redis php8.0-xml php8.0-curl php8.0-mbstring php8.0-zip php8.0-gd php8.0-intl

# MySQL
sudo apt install mysql-server-8.0

# Nginx
sudo apt install nginx

# Redis
sudo apt install redis-server

# Node.js
curl -fsSL https://deb.nodesource.com/setup_16.x | sudo -E bash -
sudo apt install nodejs

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 3. Tạo user và database
```sql
-- Kết nối MySQL
mysql -u root -p

-- Tạo database
CREATE DATABASE zena_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tạo user
CREATE USER 'zena_user'@'localhost' IDENTIFIED BY 'your_db_password_here';
GRANT ALL PRIVILEGES ON zena_production.* TO 'zena_user'@'localhost';
FLUSH PRIVILEGES;
```

## 🐳 Deployment bằng Docker

### 1. Clone repository
```bash
git clone https://github.com/your-org/zena-project.git
cd zena-project
```

### 2. Cấu hình environment
```bash
cp .env.example .env.production
```

### 3. Cập nhật .env.production
```env
APP_NAME="Z.E.N.A Project Management"
APP_ENV=production
APP_KEY=base64:your_generated_key
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=zena_production
DB_USERNAME=zena_user
DB_PASSWORD=your_db_password_here

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls

JWT_SECRET=your_jwt_secret
JWT_TTL=60

WEBSOCKET_HOST=0.0.0.0
WEBSOCKET_PORT=3001
```

### 4. Build và deploy
```bash
# Build images
docker-compose -f docker-compose.prod.yml build

# Start services
docker-compose -f docker-compose.prod.yml up -d

# Run migrations
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force

# Seed initial data
docker-compose -f docker-compose.prod.yml exec app php artisan db:seed --class=ProductionSeeder

# Generate application key
docker-compose -f docker-compose.prod.yml exec app php artisan key:generate

# Cache configuration
docker-compose -f docker-compose.prod.yml exec app php artisan config:cache
docker-compose -f docker-compose.prod.yml exec app php artisan route:cache
docker-compose -f docker-compose.prod.yml exec app php artisan view:cache
```

## 🔨 Deployment thủ công

### 1. Clone và setup
```bash
# Clone repository
git clone https://github.com/your-org/zena-project.git /var/www/zena
cd /var/www/zena

# Set permissions
sudo chown -R www-data:www-data /var/www/zena
sudo chmod -R 755 /var/www/zena
sudo chmod -R 775 /var/www/zena/storage
sudo chmod -R 775 /var/www/zena/bootstrap/cache
```

### 2. Install dependencies
```bash
# PHP dependencies
composer install --no-dev --optimize-autoloader

# Node.js dependencies
npm ci --production
npm run build
```

### 3. Cấu hình environment
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Database setup
```bash
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder
```

### 5. Optimize application
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

### 6. Cấu hình Nginx
```nginx
# /etc/nginx/sites-available/zena
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name your-domain.com;
    root /var/www/zena/public;

    # SSL Configuration
    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    index index.php;
    charset utf-8;

    # Handle Laravel routes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM configuration
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # WebSocket proxy
    location /socket.io/ {
        proxy_pass http://127.0.0.1:3001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Static files
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Security
    location ~ /\. {
        deny all;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### 7. Enable site
```bash
sudo ln -s /etc/nginx/sites-available/zena /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 8. Setup services
```bash
# WebSocket service
sudo cp scripts/websocket.service /etc/systemd/system/
sudo systemctl enable websocket
sudo systemctl start websocket

# Queue worker service
sudo cp scripts/queue-worker.service /etc/systemd/system/
sudo systemctl enable queue-worker
sudo systemctl start queue-worker

# Scheduler cron
echo "* * * * * cd /var/www/zena && php artisan schedule:run >> /dev/null 2>&1" | sudo crontab -u www-data -
```

## 🔄 Cấu hình CI/CD

### GitHub Actions Workflow
```yaml:%2F.github%2Fworkflows%2Fdeploy.yml
name: Deploy to Production

on:
  push:
    branches: [ main ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: zena_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
      
      redis:
        image: redis:6.0
        ports:
          - 6379:6379
        options: --health-cmd="redis-cli ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.0'
        extensions: mbstring, xml, ctype, iconv, intl, pdo_mysql, dom, filter, gd, json, redis
        coverage: none

    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '16'
        cache: 'npm'

    - name: Copy .env
      run: php -r "file_exists('.env') || copy('.env.example', '.env');"

    - name: Install PHP Dependencies
      run: composer install -q --no-ansi --no-interaction --no-scripts --no-progress --prefer-dist

    - name: Install Node Dependencies
      run: npm ci

    - name: Generate key
      run: php artisan key:generate

    - name: Directory Permissions
      run: chmod -R 777 storage bootstrap/cache

    - name: Build Assets
      run: npm run build

    - name: Run Database Migrations
      env:
        DB_CONNECTION: mysql
        DB_HOST: 127.0.0.1
        DB_PORT: 3306
        DB_DATABASE: zena_test
        DB_USERNAME: root
        DB_PASSWORD: password
      run: php artisan migrate --force

    - name: Run Tests
      env:
        DB_CONNECTION: mysql
        DB_HOST: 127.0.0.1
        DB_PORT: 3306
        DB_DATABASE: zena_test
        DB_USERNAME: root
        DB_PASSWORD: password
        REDIS_HOST: 127.0.0.1
        REDIS_PORT: 6379
      run: vendor/bin/phpunit --coverage-text

  deploy:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    
    steps:
    - name: Deploy to server
      uses: appleboy/ssh-action@v0.1.5
      with:
        host: ${{ secrets.HOST }}
        username: ${{ secrets.USERNAME }}
        key: ${{ secrets.KEY }}
        script: |
          cd /var/www/zena
          git pull origin main
          composer install --no-dev --optimize-autoloader
          npm ci --production
          npm run build
          php artisan migrate --force
          php artisan config:cache
          php artisan route:cache
          php artisan view:cache
          php artisan queue:restart
          sudo systemctl reload nginx
          sudo systemctl restart websocket
          sudo systemctl restart queue-worker
```

### GitLab CI/CD
```yaml:%2F.gitlab-ci.yml
stages:
  - test
  - build
  - deploy

variables:
  MYSQL_ROOT_PASSWORD: password
  MYSQL_DATABASE: zena_test
  MYSQL_USER: zena_user
  MYSQL_PASSWORD: password

test:
  stage: test
  image: php:8.0-fpm
  services:
    - mysql:8.0
    - redis:6.0
  before_script:
    - apt-get update -qq && apt-get install -y -qq git curl libmcrypt-dev libjpeg-dev libpng-dev libfreetype6-dev libbz2-dev libzip-dev
    - docker-php-ext-install pdo_mysql zip
    - curl -sS https://getcomposer.org/installer | php
    - php composer.phar install
    - cp .env.example .env
    - php artisan key:generate
  script:
    - php artisan migrate
    - vendor/bin/phpunit

build:
  stage: build
  image: node:16
  script:
    - npm ci
    - npm run build
  artifacts:
    paths:
      - public/build/
    expire_in: 1 hour

deploy_production:
  stage: deploy
  image: alpine:latest
  before_script:
    - apk add --no-cache rsync openssh
    - eval $(ssh-agent -s)
    - echo "$SSH_PRIVATE_KEY" | tr -d '\r' | ssh-add -
    - mkdir -p ~/.ssh
    - chmod 700 ~/.ssh
    - ssh-keyscan $SERVER_IP >> ~/.ssh/known_hosts
    - chmod 644 ~/.ssh/known_hosts
  script:
    - ssh $SERVER_USER@$SERVER_IP "cd /var/www/zena && ./deploy.sh"
  only:
    - main
```

## 📊 Monitoring và Backup

### 1. Setup Monitoring
```bash
# Install monitoring tools
sudo apt install htop iotop nethogs

# Setup log rotation
sudo cp scripts/zena-logrotate /etc/logrotate.d/

# Setup monitoring cron
echo "*/5 * * * * /var/www/zena/scripts/health-check.sh" | sudo crontab -
echo "0 2 * * * /var/www/zena/scripts/backup-database.sh" | sudo crontab -
echo "0 3 * * * /var/www/zena/scripts/backup-files.sh" | sudo crontab -
echo "0 4 * * 0 /var/www/zena/scripts/cleanup-logs.sh" | sudo crontab -
```

### 2. Database Backup Script
```bash:%2Fvar%2Fwww%2Fzena%2Fscripts%2Fbackup-database.sh
#!/bin/bash

# Configuration
DB_NAME="zena_production"
DB_USER="zena_user"
DB_PASS="your_password"
BACKUP_DIR="/var/backups/zena"
DATE=$(date +"%Y%m%d_%H%M%S")

# Create backup directory
mkdir -p $BACKUP_DIR

# Create backup
mysqldump -u$DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_backup_$DATE.sql.gz

# Keep only last 7 days
find $BACKUP_DIR -name "db_backup_*.sql.gz" -mtime +7 -delete

echo "Database backup completed: $BACKUP_DIR/db_backup_$DATE.sql.gz"
```

### 3. Health Check Script
```bash:%2Fvar%2Fwww%2Fzena%2Fscripts%2Fhealth-check.sh
#!/bin/bash

# Check application health
response=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/health)

if [ $response -ne 200 ]; then
    echo "Application health check failed: HTTP $response"
    # Send alert (email, Slack, etc.)
fi

# Check database connection
php /var/www/zena/artisan tinker --execute="DB::connection()->getPdo();"
if [ $? -ne 0 ]; then
    echo "Database connection failed"
fi

# Check Redis connection
redis-cli ping > /dev/null
if [ $? -ne 0 ]; then
    echo "Redis connection failed"
fi

# Check disk space
disk_usage=$(df / | tail -1 | awk '{print $5}' | sed 's/%//')
if [ $disk_usage -gt 80 ]; then
    echo "Disk usage is high: ${disk_usage}%"
fi

# Check memory usage
mem_usage=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100.0}')
if [ $mem_usage -gt 80 ]; then
    echo "Memory usage is high: ${mem_usage}%"
fi
```

## ✅ Checklist Go-Live

### Pre-Deployment
- [ ] **Code Review**: Tất cả code đã được review và approve
- [ ] **Testing**: Tất cả test cases đã pass
- [ ] **Security Scan**: Đã chạy security scan và fix các vulnerability
- [ ] **Performance Test**: Đã test performance với expected load
- [ ] **Backup Strategy**: Đã setup backup cho database và files
- [ ] **SSL Certificate**: Đã cài đặt và cấu hình SSL certificate
- [ ] **Domain Setup**: DNS đã được cấu hình đúng
- [ ] **Environment Variables**: Tất cả env variables đã được set đúng

### Deployment
- [ ] **Database Migration**: Chạy migrations thành công
- [ ] **Seed Data**: Import initial data (nếu cần)
- [ ] **File Permissions**: Set đúng permissions cho files và folders
- [ ] **Web Server**: Nginx/Apache đã được cấu hình và start
- [ ] **PHP-FPM**: PHP-FPM service đang chạy
- [ ] **Queue Workers**: Queue workers đã được start
- [ ] **WebSocket Server**: WebSocket server đang chạy
- [ ] **Cron Jobs**: Scheduler đã được setup
- [ ] **Cache**: Application cache đã được clear và rebuild

### Post-Deployment
- [ ] **Health Check**: Application health endpoint trả về 200
- [ ] **Functional Test**: Các chức năng chính hoạt động bình thường
- [ ] **Authentication**: Login/logout hoạt động đúng
- [ ] **API Endpoints**: Tất cả API endpoints hoạt động
- [ ] **WebSocket**: Real-time notifications hoạt động
- [ ] **File Upload**: Upload/download files hoạt động
- [ ] **Email**: Email notifications được gửi đúng
- [ ] **Database**: Database connections stable
- [ ] **Logs**: Application logs được ghi đúng
- [ ] **Monitoring**: Monitoring tools đang thu thập metrics
- [ ] **Backup**: Backup scripts chạy thành công

### Security
- [ ] **Firewall**: Chỉ mở các ports cần thiết
- [ ] **User Access**: Chỉ authorized users có access
- [ ] **Database Security**: Database user có minimal permissions
- [ ] **File Permissions**: Sensitive files không accessible từ web
- [ ] **HTTPS**: Tất cả traffic được encrypt
- [ ] **Security Headers**: Security headers đã được set
- [ ] **Input Validation**: Tất cả inputs được validate
- [ ] **SQL Injection**: Đã test và prevent SQL injection
- [ ] **XSS Protection**: Đã implement XSS protection
- [ ] **CSRF Protection**: CSRF tokens hoạt động đúng

### Performance
- [ ] **Response Time**: Average response time < 500ms
- [ ] **Database Queries**: Không có N+1 queries
- [ ] **Caching**: Redis cache hoạt động đúng
- [ ] **Static Files**: Static files được serve efficiently
- [ ] **Compression**: Gzip compression enabled
- [ ] **CDN**: CDN đã được setup (nếu cần)
- [ ] **Database Indexing**: Các indexes cần thiết đã được tạo
- [ ] **Memory Usage**: Memory usage trong giới hạn acceptable
- [ ] **CPU Usage**: CPU usage stable
- [ ] **Disk I/O**: Disk I/O performance acceptable

## 🔧 Troubleshooting

### Common Issues

#### 1. 500 Internal Server Error
```bash
# Check application logs
tail -f storage/logs/laravel.log

# Check web server logs
sudo tail -f /var/log/nginx/error.log

# Check PHP-FPM logs
sudo tail -f /var/log/php8.0-fpm.log
```

#### 2. Database Connection Issues
```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();

# Check MySQL status
sudo systemctl status mysql

# Check MySQL logs
sudo tail -f /var/log/mysql/error.log
```

#### 3. Permission Issues
```bash
# Fix Laravel permissions
sudo chown -R www-data:www-data /var/www/zena
sudo chmod -R 755 /var/www/zena
sudo chmod -R 775 /var/www/zena/storage
sudo chmod -R 775 /var/www/zena/bootstrap/cache
```

#### 4. Queue Not Processing
```bash
# Check queue worker status
sudo systemctl status queue-worker

# Restart queue worker
sudo systemctl restart queue-worker

# Check failed jobs
php artisan queue:failed
```

#### 5. WebSocket Issues
```bash
# Check WebSocket server status
sudo systemctl status websocket

# Check WebSocket logs
sudo journalctl -u websocket -f

# Test WebSocket connection
node websocket_test.js
```

### Performance Optimization

#### 1. Database Optimization
```sql
-- Check slow queries
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;

-- Analyze table performance
ANALYZE TABLE projects, tasks, documents;

-- Check index usage
SHOW INDEX FROM projects;
```

#### 2. PHP Optimization
```ini
; /etc/php/8.0/fpm/php.ini
memory_limit = 256M
max_execution_time = 60
max_input_vars = 3000
upload_max_filesize = 50M
post_max_size = 50M

; OPcache settings
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
```

#### 3. Redis Optimization
```redis
# /etc/redis/redis.conf
maxmemory 1gb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

### Monitoring Commands
```bash
# System monitoring
htop                    # CPU and memory usage
iotop                   # Disk I/O
netstat -tulpn         # Network connections
df -h                  # Disk usage
free -h                # Memory usage

# Application monitoring
php artisan queue:work --verbose  # Monitor queue processing
php artisan horizon:status        # Horizon status (if using)
tail -f storage/logs/laravel.log   # Application logs

# Database monitoring
mysql -e "SHOW PROCESSLIST;"       # Active connections
mysql -e "SHOW STATUS LIKE 'Slow_queries';"  # Slow queries
```

---

## 📞 Support

Nếu gặp vấn đề trong quá trình deployment, vui lòng:

1. Kiểm tra logs chi tiết
2. Tham khảo troubleshooting guide
3. Liên hệ team development

**Chúc mừng! Z.E.N.A Project Management đã sẵn sàng cho production! 🎉**