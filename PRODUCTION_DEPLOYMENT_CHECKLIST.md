# 🚀 ZENAMANAGE PRODUCTION DEPLOYMENT CHECKLIST

## 📋 **PRE-DEPLOYMENT CHECKLIST**

### **🔧 System Configuration**
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Set `APP_URL=https://yourdomain.com`
- [ ] Configure database credentials
- [ ] Set up Redis cache (if using)
- [ ] Configure mail settings
- [ ] Set up file storage (S3/Local)

### **🛡️ Security Configuration**
- [ ] Generate new `APP_KEY`
- [ ] Set secure session configuration
- [ ] Configure CORS settings
- [ ] Set up rate limiting
- [ ] Enable HTTPS
- [ ] Configure firewall rules
- [ ] Set up SSL certificates

### **🗄️ Database Setup**
- [ ] Run migrations: `php artisan migrate`
- [ ] Seed demo data: `php artisan db:seed --class=DemoUsersSeeder`
- [ ] Create production users
- [ ] Set up database backups
- [ ] Configure database monitoring

### **📁 File Permissions**
- [ ] Set correct permissions on `storage/` directory
- [ ] Set correct permissions on `bootstrap/cache/` directory
- [ ] Ensure web server can write to logs
- [ ] Set up log rotation

---

## 🎯 **ROUTE VERIFICATION CHECKLIST**

### **✅ UI Routes (No Side Effects)**
- [ ] `/admin/*` - Super Admin only
- [ ] `/app/*` - Tenant users only
- [ ] All UI routes return views only
- [ ] No POST/PATCH/DELETE on UI routes
- [ ] Business actions moved to API

### **✅ API Routes (Business Logic)**
- [ ] `/api/v1/admin/*` - Super Admin API
- [ ] `/api/v1/app/*` - Tenant API
- [ ] `/api/v1/public/*` - Public API (rate limited)
- [ ] `/api/v1/auth/*` - Authentication API
- [ ] `/api/v1/invitations/*` - Invitation API

### **✅ Legacy Routes (301 Redirects)**
- [ ] `/dashboard` → `/app/dashboard`
- [ ] `/projects` → `/app/projects`
- [ ] `/tasks` → `/app/tasks`
- [ ] `/users` → `/app/team`
- [ ] `/tenants` → `/admin/tenants`
- [ ] All legacy routes return 301 status

### **✅ Debug Routes (Local Only)**
- [ ] `/_debug/*` - Local environment only
- [ ] IP allowlist configured
- [ ] Debug middleware active
- [ ] No debug routes in production

---

## 🔐 **PERMISSION VERIFICATION CHECKLIST**

### **✅ Middleware Configuration**
- [ ] `auth` - Authentication required
- [ ] `admin.only` - Super Admin only
- [ ] `tenant.scope` - Tenant context required
- [ ] `debug.gate` - Debug access control
- [ ] `throttle` - Rate limiting active

### **✅ Role-Based Access**
- [ ] Super Admin can access `/admin/*`
- [ ] Tenant users can access `/app/*`
- [ ] Super Admin cannot access `/app/*`
- [ ] Tenant users cannot access `/admin/*`
- [ ] Unauthenticated users redirected to login

### **✅ API Permissions**
- [ ] Admin API requires `auth:sanctum` + `ability:admin`
- [ ] App API requires `auth:sanctum` + `ability:tenant`
- [ ] Public API has rate limiting
- [ ] Auth API handles login/logout
- [ ] Invitation API handles token validation

---

## 📊 **PERFORMANCE VERIFICATION CHECKLIST**

### **✅ Caching**
- [ ] Route cache: `php artisan route:cache`
- [ ] Config cache: `php artisan config:cache`
- [ ] View cache: `php artisan view:cache`
- [ ] Application cache: `php artisan cache:cache`
- [ ] Clear all caches after deployment

### **✅ Monitoring**
- [ ] Health check: `/api/v1/public/health`
- [ ] Performance metrics: `/api/v1/admin/perf/metrics`
- [ ] System health: `/api/v1/admin/perf/health`
- [ ] Cache management: `/api/v1/admin/perf/clear-caches`

### **✅ Rate Limiting**
- [ ] Public API: 30 requests/minute
- [ ] App API: 120 requests/minute
- [ ] Admin API: 60 requests/minute
- [ ] Auth API: 10 requests/minute

---

## 🧪 **TESTING CHECKLIST**

### **✅ E2E Tests**
- [ ] Navigation `/app/*` keeps header fixed
- [ ] Content swaps without page reload
- [ ] 403 errors for unauthorized access
- [ ] Legacy 301 redirects work correctly
- [ ] API endpoints return proper responses

### **✅ Permission Tests**
- [ ] Super Admin login → `/admin` access
- [ ] Tenant user login → `/app/dashboard` access
- [ ] Unauthorized access → 403 error
- [ ] Cross-tenant access → 403 error
- [ ] API authentication → proper tokens

### **✅ Legacy Tests**
- [ ] `/dashboard` → redirects to correct dashboard
- [ ] `/projects` → redirects to `/app/projects`
- [ ] `/tasks` → redirects to `/app/tasks`
- [ ] `/users` → redirects to `/app/team`
- [ ] `/tenants` → redirects to `/admin/tenants`

---

## 📚 **DOCUMENTATION CHECKLIST**

### **✅ API Documentation**
- [ ] OpenAPI/Swagger documentation
- [ ] Example requests/responses
- [ ] Authentication examples
- [ ] Error code documentation
- [ ] Rate limiting documentation

### **✅ System Documentation**
- [ ] `SYSTEM_DOCUMENTATION.md` updated
- [ ] `ZENAMANAGE_PAGE_TREE_DIAGRAM.md` current
- [ ] `PROJECT_COMPLETION_SUMMARY.md` complete
- [ ] `legacy-map.json` accurate
- [ ] Deployment guide available

### **✅ User Documentation**
- [ ] Login instructions
- [ ] Role-based access guide
- [ ] API usage examples
- [ ] Troubleshooting guide
- [ ] Support contact information

---

## 🚀 **DEPLOYMENT STEPS**

### **1. Pre-Deployment**
```bash
# Backup database
mysqldump -u username -p database_name > backup.sql

# Backup files
tar -czf files_backup.tar.gz storage/ public/

# Test locally
php artisan test
php artisan route:list
```

### **2. Production Deployment**
```bash
# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader
npm install --production
npm run build

# Run migrations
php artisan migrate --force

# Clear and cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan cache:cache

# Set permissions
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### **3. Post-Deployment**
```bash
# Verify deployment
curl -I https://yourdomain.com/api/v1/public/health

# Test legacy redirects
curl -I https://yourdomain.com/dashboard
curl -I https://yourdomain.com/projects

# Test API endpoints
curl -H "Authorization: Bearer token" https://yourdomain.com/api/v1/app/projects

# Monitor logs
tail -f storage/logs/laravel.log
```

---

## 🔍 **MONITORING CHECKLIST**

### **✅ System Health**
- [ ] Database connectivity
- [ ] Cache functionality
- [ ] File storage access
- [ ] Mail delivery
- [ ] Queue processing

### **✅ Application Health**
- [ ] Route loading
- [ ] Middleware execution
- [ ] Permission checks
- [ ] API responses
- [ ] Error handling

### **✅ Performance Metrics**
- [ ] Response times
- [ ] Memory usage
- [ ] Database queries
- [ ] Cache hit rates
- [ ] Error rates

---

## 🆘 **ROLLBACK PLAN**

### **Emergency Rollback**
```bash
# Restore database
mysql -u username -p database_name < backup.sql

# Restore files
tar -xzf files_backup.tar.gz

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Restart services
sudo systemctl restart nginx
sudo systemctl restart php-fpm
```

### **Rollback Triggers**
- [ ] High error rate (>5%)
- [ ] Performance degradation (>2s response)
- [ ] Security incidents
- [ ] Data corruption
- [ ] Service unavailability

---

## ✅ **FINAL VERIFICATION**

### **Production Readiness**
- [ ] All checklists completed
- [ ] All tests passing
- [ ] Documentation updated
- [ ] Monitoring active
- [ ] Rollback plan ready
- [ ] Team notified
- [ ] Go-live approved

**🎉 SYSTEM READY FOR PRODUCTION!** 🚀

---

**Last Updated:** 2025-09-21  
**Version:** 1.0.0  
**Status:** Ready for Production
