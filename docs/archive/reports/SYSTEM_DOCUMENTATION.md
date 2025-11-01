# 🚀 ZenaManage System Documentation

## 📋 **SYSTEM OVERVIEW**

ZenaManage is a comprehensive project management system with multi-tenancy support, role-based access control, and a modern SPA architecture.

### **🏗️ Architecture**

- **Backend:** Laravel 10+ with PHP 8.1+
- **Frontend:** Blade templates with Alpine.js for SPA functionality
- **Database:** MySQL with ULID primary keys
- **Authentication:** Laravel Auth with custom RBAC
- **Multi-tenancy:** Tenant-scoped data isolation

## 🎯 **ROUTING STRUCTURE**

### **Admin Routes (`/admin/*`)**
- **Access:** Super Admin only
- **Middleware:** `auth`, `admin.only`
- **Purpose:** System-wide administration

```
/admin                    - Super Admin Dashboard
/admin/users            - User Management
/admin/tenants          - Tenant Management
/admin/projects         - Project Oversight
/admin/security         - Security Center
/admin/alerts           - System Alerts
/admin/activities       - Activity Logs
/admin/settings         - System Settings
```

### **App Routes (`/app/*`)**
- **Access:** Tenant users only
- **Middleware:** `auth`, `tenant.scope`
- **Purpose:** Tenant-scoped application

```
/app/dashboard          - Tenant Dashboard
/app/projects           - Project Management
/app/tasks              - Task Management
/app/documents          - Document Management
/app/team               - Team Management
/app/templates          - Template Library
/app/settings           - Account Settings
```

### **API Routes (`/api/v1/*`)**
- **Structure:** RESTful API with versioning
- **Authentication:** Laravel Sanctum (planned)

```
/api/v1/admin/*         - Admin API endpoints
/api/v1/app/*           - App API endpoints
/api/v1/public/*        - Public API endpoints
```

### **Debug Routes (`/_debug/*`)**
- **Access:** Local environment only
- **Purpose:** Development and testing

```
/_debug/info            - System information
/_debug/projects-test   - Project testing
/_debug/users-debug     - User debugging
```

## 🔐 **PERMISSION SYSTEM**

### **Role Hierarchy**

1. **Super Admin (`super_admin`)**
   - Full system access
   - No tenant restrictions
   - Can access `/admin/*`

2. **Admin (`admin`)**
   - Tenant management
   - User management within tenant
   - Can access `/app/*`

3. **Project Manager (`project_manager`)**
   - Project and task management
   - Team coordination
   - Can access `/app/*`

4. **Designer (`designer`)**
   - Design management
   - Project viewing
   - Can access `/app/*`

5. **Site Engineer (`site_engineer`)**
   - Construction management
   - Project viewing
   - Can access `/app/*`

6. **QC Engineer (`qc_engineer`)**
   - Quality control
   - Project viewing
   - Can access `/app/*`

7. **Procurement (`procurement`)**
   - Procurement management
   - Project viewing
   - Can access `/app/*`

8. **Finance (`finance`)**
   - Financial management
   - Reporting
   - Can access `/app/*`

9. **Client (`client`)**
   - Project viewing
   - Reporting
   - Can access `/app/*`

### **Permission Methods**

```php
// Role checking
$user->hasRole('super_admin')
$user->hasAnyRole(['admin', 'project_manager'])
$user->isSuperAdmin()
$user->isAdmin()

// Tenant checking
$user->hasTenant()
$user->belongsToTenant($tenantId)
$user->canAccessAdmin()
$user->canAccessApp()

// Permission checking
$user->hasPermission('manage_users')
$user->hasPermission('view_projects')
```

## 🛡️ **MIDDLEWARE**

### **AdminOnly Middleware**
- **Purpose:** Restrict access to Super Admin only
- **Usage:** Applied to `/admin/*` routes
- **Behavior:** Returns 403 for non-super-admin users

### **TenantScope Middleware**
- **Purpose:** Ensure user has tenant context
- **Usage:** Applied to `/app/*` routes
- **Behavior:** Returns 403 for users without tenant

## 🔄 **LEGACY COMPATIBILITY**

### **Legacy Redirects**
The system maintains backward compatibility through legacy redirects:

```
/dashboard              → /admin (Super Admin) or /app/dashboard (Tenant)
/dashboard/admin        → /admin
/dashboard/{role}       → /app/dashboard?role={role}
/projects               → /app/projects
/tasks                  → /app/tasks
/users                  → /admin/users (Super Admin) or /app/team (Tenant)
/tenants                → /admin/tenants
/documents              → /app/documents
/templates              → /app/templates
/settings               → /admin/settings (Super Admin) or /app/settings (Tenant)
/profile                → /app/profile
/calendar               → /calendar
/team                   → /app/team
```

## 📊 **PERFORMANCE MONITORING**

### **Performance Endpoints**

```
/performance/metrics     - System performance metrics
/performance/health      - System health status
/performance/clear-caches - Clear system caches
```

### **Metrics Tracked**
- Database connection count
- Query performance
- Cache hit rate
- Memory usage
- Route counts
- User statistics
- Load times

## 🧪 **TESTING**

### **Test Routes**

```
/test-permissions       - Permission testing page
/_debug/info           - System information
/_debug/projects-test  - Project testing
/_debug/users-debug    - User debugging
```

### **Demo Users**

```
Super Admin:
- Email: superadmin@zena.com
- Password: zena1234
- Access: /admin/*

Tenant Users:
- Email: pm@zena.com, designer@zena.com, etc.
- Password: zena1234
- Access: /app/*
```

## 🚀 **DEPLOYMENT**

### **Environment Requirements**
- PHP 8.1+
- Laravel 10+
- MySQL 8.0+
- Composer
- Node.js (for frontend assets)

### **Installation Steps**

1. **Clone Repository**
   ```bash
   git clone <repository-url>
   cd zenamanage
   ```

2. **Install Dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment Setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database Setup**
   ```bash
   php artisan migrate
   php artisan db:seed --class=DemoUsersSeeder
   ```

5. **Asset Compilation**
   ```bash
   npm run build
   ```

6. **Cache Clear**
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   ```

### **Production Considerations**
- Enable HTTPS
- Set `APP_DEBUG=false`
- Configure proper cache drivers
- Set up monitoring
- Regular backups

## 🔧 **MAINTENANCE**

### **Cleanup Commands**

```bash
# Clean up legacy files
php artisan zena:cleanup-legacy

# Dry run (see what would be cleaned)
php artisan zena:cleanup-legacy --dry-run
```

### **Cache Management**

```bash
# Clear all caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Or use the API endpoint
POST /performance/clear-caches
```

## 📈 **MONITORING**

### **Health Checks**
- Database connectivity
- Cache functionality
- Route loading
- Permission system

### **Performance Metrics**
- Memory usage
- Query performance
- Cache hit rates
- User activity

## 🆘 **TROUBLESHOOTING**

### **Common Issues**

1. **Permission Denied (403)**
   - Check user roles
   - Verify middleware configuration
   - Ensure proper authentication

2. **Route Not Found (404)**
   - Check route registration
   - Verify middleware order
   - Clear route cache

3. **Database Connection Issues**
   - Check database configuration
   - Verify database server status
   - Check user permissions

### **Debug Tools**
- `/test-permissions` - Permission debugging
- `/_debug/info` - System information
- `/performance/health` - System health
- Laravel logs in `storage/logs/`

## 📚 **API DOCUMENTATION**

### **Authentication**
```http
POST /login
Content-Type: application/x-www-form-urlencoded

email=user@example.com&password=password
```

### **Response Format**
```json
{
  "success": true,
  "message": "Login successful",
  "user": {
    "id": "user-id",
    "name": "User Name",
    "email": "user@example.com",
    "roles": ["role1", "role2"],
    "tenant_id": "tenant-id"
  }
}
```

## 🎯 **FUTURE ENHANCEMENTS**

### **Planned Features**
- React frontend integration
- Real-time notifications
- Advanced reporting
- Mobile app support
- API versioning
- Microservices architecture

### **Performance Improvements**
- Redis caching
- Database optimization
- CDN integration
- Asset optimization
- Lazy loading

---

**Last Updated:** {{ date('Y-m-d H:i:s') }}
**Version:** 1.0.0
**Maintainer:** ZenaManage Team
