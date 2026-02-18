# Z.E.N.A Project Management - Installation Guide

## System Requirements

### Server Requirements
- **PHP**: 8.0 or higher
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Database**: MySQL 8.0+ or MariaDB 10.4+
- **Memory**: Minimum 512MB RAM (2GB recommended)
- **Storage**: Minimum 1GB free space

### PHP Extensions
- BCMath PHP Extension
- Ctype PHP Extension
- cURL PHP Extension
- DOM PHP Extension
- Fileinfo PHP Extension
- JSON PHP Extension
- Mbstring PHP Extension
- OpenSSL PHP Extension
- PCRE PHP Extension
- PDO PHP Extension
- Tokenizer PHP Extension
- XML PHP Extension
- GD PHP Extension (for image processing)
- Redis PHP Extension (optional, for caching)

## Installation Steps

### 1. Download and Extract

```bash
# Clone repository
git clone https://github.com/your-org/zena-project.git
cd zena-project

# Or download and extract ZIP file
wget https://github.com/your-org/zena-project/archive/main.zip
unzip main.zip
cd zena-project-main
```

### 2. Install Dependencies

```bash
# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Install Node.js dependencies (for frontend)
npm install
npm run build
```

### 3. Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Generate JWT secret
php artisan jwt:secret
```

### 4. Database Setup

#### Create Database
```sql
CREATE DATABASE zena_project CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'zena_user'@'localhost' IDENTIFIED BY 'your_db_password_here';
GRANT ALL PRIVILEGES ON zena_project.* TO 'zena_user'@'localhost';
FLUSH PRIVILEGES;
```

#### Configure Database Connection
Edit `.env` file:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zena_project
DB_USERNAME=zena_user
DB_PASSWORD=your_db_password_here
```

#### Run Migrations
```bash
# Run database migrations
php artisan migrate

# Seed initial data
php artisan db:seed
```

### 5. File Permissions

```bash
# Set proper permissions
chmod -R 755 storage
chmod -R 755 bootstrap/cache
chown -R www-data:www-data storage
chown -R www-data:www-data bootstrap/cache
```

### 6. Web Server Configuration

#### Apache Configuration

Create virtual host in `/etc/apache2/sites-available/zena-project.conf`:

```apache
<VirtualHost *:80>
    ServerName zena-project.local
    DocumentRoot /var/www/zena-project/public
    
    <Directory /var/www/zena-project/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/zena-project_error.log
    CustomLog ${APACHE_LOG_DIR}/zena-project_access.log combined
</VirtualHost>
```

Enable site:
```bash
sudo a2ensite zena-project.conf
sudo a2enmod rewrite
sudo systemctl reload apache2
```

#### Nginx Configuration

Create configuration in `/etc/nginx/sites-available/zena-project`:

```nginx
server {
    listen 80;
    server_name zena-project.local;
    root /var/www/zena-project/public;
    
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    
    index index.php;
    
    charset utf-8;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    
    error_page 404 /index.php;
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/zena-project /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 7. SSL Configuration (Production)

#### Using Let's Encrypt
```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache

# Get SSL certificate
sudo certbot --apache -d your-domain.com
```

### 8. Caching Configuration

#### Redis Setup (Optional)
```bash
# Install Redis
sudo apt install redis-server

# Configure in .env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

#### Optimize Application
```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize
```

### 9. Queue Configuration

#### Supervisor Setup
Create `/etc/supervisor/conf.d/zena-worker.conf`:

```ini
[program:zena-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/zena-project/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/zena-project/storage/logs/worker.log
stopwaitsecs=3600
```

Start supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start zena-worker:*
```

### 10. Cron Jobs

Add to crontab:
```bash
# Edit crontab
crontab -e

# Add Laravel scheduler
* * * * * cd /var/www/zena-project && php artisan schedule:run >> /dev/null 2>&1
```

## Post-Installation

### 1. Create Admin User

```bash
php artisan tinker
```

```php
$user = new App\Models\User();
$user->name = 'Admin User';
$user->email = 'admin@example.com';
$user->password = Hash::make('your_db_password_here');
$user->tenant_id = 1; // Assuming tenant exists
$user->save();

// Assign admin role
$adminRole = Src\RBAC\Models\Role::where('name', 'Super Admin')->first();
$user->systemRoles()->attach($adminRole->id);
```

### 2. Test Installation

```bash
# Run tests
php artisan test

# Check application status
php artisan about
```

### 3. Access Application

- **Frontend**: http://your-domain.com
- **API**: http://your-domain.com/api/v1
- **Admin Login**: Use created admin credentials

## Docker Installation

### 1. Using Docker Compose

```yaml
# docker-compose.yml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "8000:80"
    environment:
      - APP_ENV=production
      - DB_HOST=mysql
    depends_on:
      - mysql
      - redis
    volumes:
      - ./storage:/var/www/html/storage
      
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: zena_project
      MYSQL_USER: zena_user
      MYSQL_PASSWORD: your_db_password_here
    volumes:
      - mysql_data:/var/lib/mysql
      
  redis:
    image: redis:alpine
    
volumes:
  mysql_data:
```

### 2. Run with Docker

```bash
# Build and start containers
docker-compose up -d

# Run migrations
docker-compose exec app php artisan migrate

# Seed data
docker-compose exec app php artisan db:seed
```

## Troubleshooting

### Common Issues

#### Permission Errors
```bash
# Fix storage permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

#### Database Connection Issues
- Check database credentials in `.env`
- Ensure database server is running
- Verify firewall settings

#### 500 Internal Server Error
- Check Laravel logs in `storage/logs/`
- Verify file permissions
- Check web server error logs

#### JWT Token Issues
```bash
# Regenerate JWT secret
php artisan jwt:secret --force
```

### Performance Optimization

#### Enable OPcache
Add to `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
```

#### Database Optimization
```sql
-- Add indexes for better performance
CREATE INDEX idx_projects_tenant_id ON projects(tenant_id);
CREATE INDEX idx_tasks_project_id ON tasks(project_id);
CREATE INDEX idx_interaction_logs_project_id ON interaction_logs(project_id);
```

## Maintenance

### Regular Tasks

```bash
# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimize application
php artisan optimize

# Update dependencies
composer update
npm update
```

### Backup

```bash
# Database backup
mysqldump -u zena_user -p zena_project > backup_$(date +%Y%m%d).sql

# File backup
tar -czf files_backup_$(date +%Y%m%d).tar.gz storage/app/public
```

### Monitoring

- Monitor disk space in `storage/logs/`
- Check queue worker status
- Monitor database performance
- Review error logs regularly