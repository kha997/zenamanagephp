# 🔐 Authentication Module Deployment Checklist

## 📋 **Pre-Deployment Requirements**

### ✅ **Completed Tasks**
- [x] Authentication routes configured
- [x] API controllers implemented
- [x] Services created (AuthenticationService, TenantProvisioningService, etc.)
- [x] Policies implemented (UserPolicy, TenantPolicy)
- [x] Middleware configured (TenantAbilityMiddleware, AdminOnlyMiddleware)
- [x] FormRequests validation created
- [x] UI components implemented
- [x] Tests written
- [x] Database migrations ready

### 🔧 **Configuration Required**

#### 1. **Environment Variables (.env)**
```bash
# Application
APP_NAME="ZenaManage"
APP_ENV=production
APP_KEY=base64:YOUR_APP_KEY_HERE
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zenamanage
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host.com
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"

# Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=your-domain.com,localhost
SESSION_DOMAIN=your-domain.com

# Cache & Queue (Redis recommended)
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Security
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=your-domain.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

#### 2. **Database Setup**
```bash
# Run migrations
php artisan migrate

# Seed initial data (if needed)
php artisan db:seed

# Create admin user
php artisan tinker
>>> $tenant = App\Models\Tenant::create(['name' => 'Admin Tenant', 'slug' => 'admin']);
>>> $user = App\Models\User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('password'), 'tenant_id' => $tenant->id, 'role' => 'super_admin', 'is_active' => true, 'email_verified_at' => now()]);
```

#### 3. **Mail Configuration**
- Set up SMTP server (Gmail, SendGrid, Mailgun, etc.)
- Configure email templates
- Test email delivery

#### 4. **SSL/HTTPS Setup**
- Install SSL certificate
- Configure secure cookies
- Update APP_URL to HTTPS

#### 5. **Redis Setup (Recommended)**
```bash
# Install Redis
sudo apt-get install redis-server

# Start Redis
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Test Redis connection
redis-cli ping
```

## 🚀 **Deployment Steps**

### Step 1: **Environment Setup**
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Clear and cache configuration
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 2: **Database Migration**
```bash
# Run migrations
php artisan migrate --force

# Create storage link
php artisan storage:link
```

### Step 3: **Queue Setup**
```bash
# Start queue worker
php artisan queue:work --daemon

# Or use supervisor for production
sudo apt-get install supervisor
```

### Step 4: **Web Server Configuration**

#### Nginx Configuration
```nginx
server {
    listen 80;
    listen 443 ssl http2;
    server_name your-domain.com;
    root /path/to/zenamanage/public;

    # SSL Configuration
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### Apache Configuration
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/zenamanage/public

    <Directory /path/to/zenamanage/public>
        AllowOverride All
        Require all granted
    </Directory>

    # Security Headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "no-referrer-when-downgrade"
</VirtualHost>
```

## 🧪 **Testing & Validation**

### 1. **Run System Tests**
```bash
# Test database connection
php artisan system:test

# Test authentication endpoints
curl -X POST https://your-domain.com/api/public/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@example.com","password":"Password123!","password_confirmation":"Password123!","tenant_name":"Test Company","terms":true}'
```

### 2. **Security Validation**
- [ ] HTTPS redirect working
- [ ] CSRF protection enabled
- [ ] Rate limiting active
- [ ] Password policy enforced
- [ ] Email verification working
- [ ] Session security configured

### 3. **Performance Testing**
- [ ] API response times < 300ms
- [ ] Page load times < 500ms
- [ ] Database queries optimized
- [ ] Cache working properly

## 📊 **Monitoring & Maintenance**

### 1. **Log Monitoring**
```bash
# Monitor authentication logs
tail -f storage/logs/laravel.log | grep "authentication\|login\|logout"

# Monitor security events
tail -f storage/logs/laravel.log | grep "security\|unauthorized\|forbidden"
```

### 2. **Performance Monitoring**
- Set up application monitoring (New Relic, DataDog, etc.)
- Monitor database performance
- Track API response times
- Monitor queue processing

### 3. **Security Monitoring**
- Monitor failed login attempts
- Track password reset requests
- Monitor email verification rates
- Check for suspicious activity

## 🔧 **Troubleshooting**

### Common Issues

#### 1. **Email Not Sending**
```bash
# Test mail configuration
php artisan tinker
>>> Mail::raw('Test email', function($message) { $message->to('test@example.com')->subject('Test'); });
```

#### 2. **Session Issues**
```bash
# Clear session cache
php artisan session:clear

# Check session configuration
php artisan config:show session
```

#### 3. **Token Issues**
```bash
# Clear Sanctum tokens
php artisan tinker
>>> App\Models\User::find(1)->tokens()->delete();
```

#### 4. **Database Connection Issues**
```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

## 📞 **Support & Documentation**

### Resources
- [Laravel Sanctum Documentation](https://laravel.com/docs/sanctum)
- [Laravel Authentication Documentation](https://laravel.com/docs/authentication)
- [ZenaManage Documentation](COMPLETE_SYSTEM_DOCUMENTATION.md)

### Contact
- Technical Support: support@zenamanage.com
- Security Issues: security@zenamanage.com
- Documentation: docs@zenamanage.com

---

**Last Updated**: January 8, 2025  
**Version**: 1.0  
**Status**: Ready for Production Deployment
