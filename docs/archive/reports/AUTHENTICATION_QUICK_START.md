# 🚀 Authentication Module - Quick Start Guide

## 📋 **Overview**

The Authentication Module provides secure user authentication, registration, and management with multi-tenant support, RBAC, and comprehensive security features.

## ⚡ **Quick Setup (5 Minutes)**

### 1. **Environment Configuration**
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Update database configuration in .env
DB_DATABASE=zenamanage
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 2. **Database Setup**
```bash
# Run migrations
php artisan migrate

# Create admin user
php artisan tinker
```

```php
// In tinker console
$tenant = App\Models\Tenant::create([
    'name' => 'Admin Company',
    'slug' => 'admin-company',
    'status' => 'active',
    'plan' => 'enterprise'
]);

$user = App\Models\User::create([
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'password' => Hash::make('admin123'),
    'tenant_id' => $tenant->id,
    'role' => 'super_admin',
    'is_active' => true,
    'email_verified_at' => now()
]);
```

### 3. **Start Development Server**
```bash
# Start Laravel development server
php artisan serve

# Access the application
# http://localhost:8000
```

## 🔐 **Authentication Endpoints**

### **Public Endpoints**
```bash
# User Registration
POST /api/public/auth/register
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "Password123!",
    "password_confirmation": "Password123!",
    "tenant_name": "John's Company",
    "terms": true
}

# Login
POST /api/auth/login
{
    "email": "john@example.com",
    "password": "Password123!",
    "remember": false
}

# Password Reset Request
POST /api/auth/password/forgot
{
    "email": "john@example.com"
}

# Password Reset
POST /api/auth/password/reset
{
    "email": "john@example.com",
    "password": "NewPassword123!",
    "password_confirmation": "NewPassword123!",
    "token": "reset_token_here"
}
```

### **Protected Endpoints**
```bash
# Get Current User
GET /api/auth/me
Authorization: Bearer {token}

# Get User Permissions
GET /api/auth/permissions
Authorization: Bearer {token}

# Logout
POST /api/auth/logout
Authorization: Bearer {token}

# Refresh Token
POST /api/auth/refresh
Authorization: Bearer {token}
```

## 👥 **User Management**

### **Tenant-Scoped User Management**
```bash
# Get Users (Tenant-scoped)
GET /api/app/users
Authorization: Bearer {token}

# Create User
POST /api/app/users
Authorization: Bearer {token}
{
    "name": "Jane Doe",
    "email": "jane@example.com",
    "password": "Password123!",
    "password_confirmation": "Password123!",
    "role": "member"
}

# Update User
PUT /api/app/users/{id}
Authorization: Bearer {token}

# Delete User
DELETE /api/app/users/{id}
Authorization: Bearer {token}
```

### **Admin Cross-Tenant Management**
```bash
# Get All Users (Admin only)
GET /api/admin/users
Authorization: Bearer {admin_token}

# Create User (Admin)
POST /api/admin/users
Authorization: Bearer {admin_token}
{
    "name": "Admin User",
    "email": "admin@example.com",
    "password": "Password123!",
    "password_confirmation": "Password123!",
    "role": "admin",
    "tenant_id": "tenant_id_here"
}
```

## 🛡️ **Security Features**

### **Password Policy**
- Minimum 8 characters
- Must contain uppercase, lowercase, number, and special character
- Cannot be common passwords
- Cannot contain sequential characters
- Cannot contain repeated characters

### **Rate Limiting**
- Login: 5 attempts per minute
- Registration: 3 attempts per minute
- Password reset: 3 attempts per minute

### **Multi-Tenant Isolation**
- All queries automatically filtered by tenant_id
- Users can only access data within their tenant
- Super admins can access cross-tenant data

### **RBAC (Role-Based Access Control)**
- **super_admin**: Full system access
- **admin**: Tenant management access
- **pm**: Project management access
- **member**: Limited access
- **client**: Read-only access

## 🎨 **UI Components**

### **Web Routes**
```bash
# Login Page
GET /login

# Registration Page
GET /register

# Password Reset Request
GET /password/reset

# Password Reset Form
GET /password/reset/{token}

# Email Verification
GET /email/verify

# User Management (Protected)
GET /app/users
```

### **Features**
- Real-time form validation
- Loading states and error handling
- Responsive design
- Accessibility compliant
- Mobile-friendly

## 🧪 **Testing**

### **Run Tests**
```bash
# Run all authentication tests
php artisan test --filter=Auth

# Run specific test
php artisan test tests/Feature/Auth/AuthenticationModuleTest.php

# Run unit tests
php artisan test tests/Unit/Services/AuthenticationServiceTest.php
```

### **Test Coverage**
- User registration and login
- Password reset workflow
- Email verification
- Token management
- RBAC enforcement
- Tenant isolation
- Rate limiting
- Password policy

## 🔧 **Configuration**

### **Permissions Configuration**
Edit `config/permissions.php` to customize role permissions:

```php
'roles' => [
    'admin' => [
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        // ... more permissions
    ],
    'member' => [
        'users.view', // Limited to own profile
        // ... limited permissions
    ],
],
```

### **Middleware Configuration**
Edit `app/Http/Kernel.php` to customize middleware:

```php
'routeMiddleware' => [
    'ability:tenant' => \App\Http\Middleware\TenantAbilityMiddleware::class,
    'ability:admin' => \App\Http\Middleware\AdminOnlyMiddleware::class,
    'tenant.scope' => \App\Http\Middleware\TenantScopeMiddleware::class,
],
```

## 📊 **Monitoring**

### **Log Monitoring**
```bash
# Monitor authentication events
tail -f storage/logs/laravel.log | grep "authentication\|login\|logout"

# Monitor security events
tail -f storage/logs/laravel.log | grep "security\|unauthorized\|forbidden"
```

### **Performance Monitoring**
- API response times
- Database query performance
- Cache hit rates
- Queue processing times

## 🚨 **Troubleshooting**

### **Common Issues**

#### **Email Not Sending**
```bash
# Test mail configuration
php artisan tinker
>>> Mail::raw('Test email', function($message) { 
    $message->to('test@example.com')->subject('Test'); 
});
```

#### **Session Issues**
```bash
# Clear session cache
php artisan session:clear
```

#### **Token Issues**
```bash
# Clear user tokens
php artisan tinker
>>> App\Models\User::find(1)->tokens()->delete();
```

## 📚 **Documentation**

### **Related Documentation**
- [Complete System Documentation](COMPLETE_SYSTEM_DOCUMENTATION.md)
- [Deployment Checklist](AUTHENTICATION_DEPLOYMENT_CHECKLIST.md)
- [API Documentation](docs/api/openapi.json)
- [Security Guide](docs/v2/security-guide.md)

### **External Resources**
- [Laravel Sanctum](https://laravel.com/docs/sanctum)
- [Laravel Authentication](https://laravel.com/docs/authentication)
- [Laravel Policies](https://laravel.com/docs/authorization#policies)

## 🆘 **Support**

### **Getting Help**
- Check the [troubleshooting section](#-troubleshooting)
- Review [deployment checklist](AUTHENTICATION_DEPLOYMENT_CHECKLIST.md)
- Check system logs in `storage/logs/`
- Run system tests: `php artisan system:test`

### **Contact**
- Technical Support: support@zenamanage.com
- Security Issues: security@zenamanage.com
- Documentation: docs@zenamanage.com

---

**Ready to go!** 🎉 Your Authentication Module is now configured and ready for use.

**Next Steps:**
1. Configure email settings for verification
2. Set up SSL/HTTPS for production
3. Configure Redis for better performance
4. Set up monitoring and logging
5. Run comprehensive tests
