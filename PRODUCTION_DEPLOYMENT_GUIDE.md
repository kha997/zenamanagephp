# 🚀 ZenaManage Production Deployment Guide

## 📋 Prerequisites

### System Requirements
- **PHP**: 8.1+ (recommended: 8.2)
- **MySQL**: 8.0+ or **PostgreSQL**: 13+
- **Redis**: 6.0+ (for queues and caching)
- **Web Server**: Nginx or Apache
- **SSL Certificate**: Required for production
- **Domain**: Configured with DNS

### Server Requirements
- **RAM**: Minimum 2GB (recommended: 4GB+)
- **Storage**: Minimum 20GB SSD
- **CPU**: 2+ cores
- **Network**: Stable internet connection

---

## 🔧 Environment Setup

### 1. Clone Repository
```bash
git clone https://github.com/your-org/zenamanage.git
cd zenamanage
```

### 2. Install Dependencies
```bash
composer install --optimize-autoloader --no-dev
npm install && npm run build
```

### 3. Environment Configuration
```bash
cp production.env.example .env
```

Keep `production.env` local-only. The repository only tracks `production.env.example`, and `production.env` is gitignored so you must never commit secrets from your production configuration.

Update `.env` with your production values:
```env
APP_NAME="ZenaManage"
APP_ENV=production
APP_KEY=base64:YOUR_GENERATED_KEY
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zenamanage_production
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password_here

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="ZenaManage"

# Queue Configuration
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Cache Configuration
CACHE_DRIVER=redis
SESSION_DRIVER=redis
```

### 4. Generate Application Key
```bash
php artisan key:generate
```

---

## 🗄️ Database Setup

### 1. Create Database
```sql
CREATE DATABASE zenamanage_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'zenamanage_user'@'localhost' IDENTIFIED BY 'your_db_password_here';
GRANT ALL PRIVILEGES ON zenamanage_production.* TO 'zenamanage_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Run Migrations
```bash
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder
```

---

## 📧 Email Configuration

### 1. SMTP Provider Setup

#### Gmail Configuration
1. Enable 2-Factor Authentication
2. Generate App Password
3. Use App Password in `MAIL_PASSWORD`

#### SendGrid Configuration
1. Create SendGrid account
2. Generate API Key
3. Configure:
```env
MAIL_HOST=smtp.sendgrid.net
MAIL_USERNAME=apikey
MAIL_PASSWORD=your_sendgrid_api_key
```

#### Mailgun Configuration
1. Create Mailgun account
2. Add domain
3. Configure:
```env
MAIL_HOST=smtp.mailgun.org
MAIL_USERNAME=your_mailgun_username
MAIL_PASSWORD=your_mailgun_password
```

### 2. Test Email Configuration
```bash
php artisan email:test your-email@example.com
```

---

## 🚀 Queue Workers Setup

### 1. Redis Installation
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install redis-server

# CentOS/RHEL
sudo yum install redis

# Start Redis
sudo systemctl start redis
sudo systemctl enable redis
```

### 2. Queue Worker Configuration
```bash
# Start email queue workers
php artisan email:queue-worker --daemon

# Or use supervisor for production
sudo apt install supervisor
```

### 3. Supervisor Configuration
Create `/etc/supervisor/conf.d/zenamanage-worker.conf`:
```ini
[program:zenamanage-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/zenamanage/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/zenamanage/storage/logs/worker.log
stopwaitsecs=3600
```

Start supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start zenamanage-worker:*
```

---

## 💾 Cache Configuration

### 1. Redis Cache Setup
```bash
# Test Redis connection
redis-cli ping
```

### 2. Warm Up Email Cache
```bash
php artisan email:warm-cache
```

### 3. Cache Optimization
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 📊 Monitoring Setup

### 1. Email Monitoring
```bash
# Run monitoring command
php artisan email:monitor --send-alerts

# Set up cron job for regular monitoring
crontab -e
# Add: */5 * * * * cd /path/to/zenamanage && php artisan email:monitor --send-alerts
```

### 2. Log Monitoring
```bash
# Set up log rotation
sudo nano /etc/logrotate.d/zenamanage
```

Add:
```
/path/to/zenamanage/storage/logs/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
```

### 3. System Monitoring
Install monitoring tools:
```bash
# Install htop for process monitoring
sudo apt install htop

# Install iotop for disk I/O monitoring
sudo apt install iotop

# Install nethogs for network monitoring
sudo apt install nethogs
```

---

## 🔒 Security Configuration

### 1. File Permissions
```bash
# Set proper permissions
sudo chown -R www-data:www-data /path/to/zenamanage
sudo chmod -R 755 /path/to/zenamanage
sudo chmod -R 775 /path/to/zenamanage/storage
sudo chmod -R 775 /path/to/zenamanage/bootstrap/cache
```

### 2. SSL Certificate
```bash
# Using Let's Encrypt
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

### 3. Firewall Configuration
```bash
# Configure UFW
sudo ufw allow 22
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable
```

---

## 🌐 Web Server Configuration

### Nginx Configuration
Create `/etc/nginx/sites-available/zenamanage`:
```nginx
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

    root /path/to/zenamanage/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

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

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/zenamanage /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 🔄 Deployment Process

### 1. Automated Deployment Script
Create `deploy.sh`:
```bash
#!/bin/bash

# Configuration
PROJECT_PATH="/path/to/zenamanage"
BACKUP_PATH="/path/to/backups"
DATE=$(date +%Y%m%d_%H%M%S)

echo "🚀 Starting deployment..."

# Backup current version
echo "📦 Creating backup..."
tar -czf "$BACKUP_PATH/zenamanage_$DATE.tar.gz" -C "$PROJECT_PATH" .

# Pull latest changes
echo "📥 Pulling latest changes..."
cd "$PROJECT_PATH"
git pull origin main

# Install dependencies
echo "📦 Installing dependencies..."
composer install --optimize-autoloader --no-dev
npm install && npm run build

# Run migrations
echo "🗄️ Running migrations..."
php artisan migrate --force

# Clear caches
echo "🧹 Clearing caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Warm up email cache
echo "📧 Warming up email cache..."
php artisan email:warm-cache

# Restart queue workers
echo "🚀 Restarting queue workers..."
php artisan queue:restart

# Set permissions
echo "🔒 Setting permissions..."
sudo chown -R www-data:www-data "$PROJECT_PATH"
sudo chmod -R 755 "$PROJECT_PATH"
sudo chmod -R 775 "$PROJECT_PATH/storage"
sudo chmod -R 775 "$PROJECT_PATH/bootstrap/cache"

echo "✅ Deployment completed successfully!"
```

Make executable:
```bash
chmod +x deploy.sh
```

### 2. CI/CD Pipeline (GitHub Actions)
Create `.github/workflows/deploy.yml`:
```yaml
name: Deploy to Production

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Deploy to server
      uses: appleboy/ssh-action@v0.1.5
      with:
        host: ${{ secrets.HOST }}
        username: ${{ secrets.USERNAME }}
        key: ${{ secrets.SSH_KEY }}
        script: |
          cd /path/to/zenamanage
          ./deploy.sh
```

---

## 📈 Performance Optimization

### 1. PHP-FPM Optimization
Edit `/etc/php/8.2/fpm/pool.d/www.conf`:
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 1000
```

### 2. Redis Optimization
Edit `/etc/redis/redis.conf`:
```conf
maxmemory 256mb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

### 3. Database Optimization
```sql
-- Add indexes for better performance
ALTER TABLE email_tracking ADD INDEX idx_organization_status (organization_id, status);
ALTER TABLE email_tracking ADD INDEX idx_created_at (created_at);
ALTER TABLE invitations ADD INDEX idx_organization_status (organization_id, status);
```

---

## 🚨 Troubleshooting

### Common Issues

#### 1. Queue Workers Not Processing
```bash
# Check queue status
php artisan queue:work --once

# Check Redis connection
redis-cli ping

# Restart workers
php artisan queue:restart
```

#### 2. Email Sending Issues
```bash
# Test email configuration
php artisan email:test your-email@example.com

# Check email logs
tail -f storage/logs/laravel.log

# Monitor email queue
php artisan email:monitor
```

#### 3. Cache Issues
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Warm up cache
php artisan email:warm-cache
```

#### 4. Database Issues
```bash
# Check database connection
php artisan tinker
>>> DB::connection()->getPdo();

# Run migrations
php artisan migrate:status
php artisan migrate --force
```

---

## 📊 Monitoring & Alerts

### 1. Health Checks
```bash
# Create health check endpoint
php artisan make:controller HealthController
```

### 2. Monitoring Dashboard
- Set up monitoring tools (Grafana, Prometheus)
- Configure alerts for:
  - High failure rates
  - Queue backlog
  - Memory usage
  - Disk space

### 3. Log Analysis
```bash
# Install log analysis tools
sudo apt install logwatch

# Configure log monitoring
sudo nano /etc/logwatch/conf/logwatch.conf
```

---

## 🔄 Backup Strategy

### 1. Database Backup
```bash
# Daily backup script
#!/bin/bash
mysqldump -u username -p password zenamanage_production > /backups/db_$(date +%Y%m%d).sql
```

### 2. File Backup
```bash
# Weekly file backup
tar -czf /backups/files_$(date +%Y%m%d).tar.gz /path/to/zenamanage
```

### 3. Automated Backup
```bash
# Add to crontab
0 2 * * * /path/to/backup-script.sh
```

---

## ✅ Production Checklist

- [ ] Environment variables configured
- [ ] Database created and migrated
- [ ] SSL certificate installed
- [ ] Email configuration tested
- [ ] Queue workers running
- [ ] Cache warmed up
- [ ] File permissions set
- [ ] Firewall configured
- [ ] Monitoring set up
- [ ] Backup strategy implemented
- [ ] Health checks working
- [ ] Performance optimized
- [ ] Security hardened
- [ ] Documentation updated

---

## 📞 Support

For production support:
- **Email**: support@your-domain.com
- **Slack**: #zenamanage-support
- **Documentation**: https://docs.your-domain.com
- **Status Page**: https://status.your-domain.com

---

**🎉 Congratulations! Your ZenaManage application is now ready for production!**
