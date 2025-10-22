# ZenaManage Production Deployment Checklist

## 🚀 **Pre-Deployment Checklist**

### **Environment Configuration**
- [ ] Update `.env` file for production:
  - [ ] `APP_ENV=production`
  - [ ] `APP_DEBUG=false`
  - [ ] `APP_URL=https://your-domain.com`
  - [ ] Generate new `APP_KEY` for production
  - [ ] Update database credentials
  - [ ] Configure Redis for production
  - [ ] Set secure session configuration

### **Security Configuration**
- [ ] Enable all security middleware:
  - [x] Security headers middleware
  - [x] CSRF protection
  - [x] Rate limiting
  - [x] Input validation & sanitization
- [ ] Configure HTTPS redirects
- [ ] Set secure cookie settings
- [ ] Enable CORS for production domains

### **Database & Performance**
- [ ] Run migrations: `php artisan migrate --force`
- [ ] Seed production data: `php artisan db:seed --class=ProductionSeeder`
- [ ] Optimize database indexes
- [ ] Configure query caching
- [ ] Set up Redis for sessions and cache

### **File Permissions**
- [ ] Set correct permissions:
  - [ ] `storage/` - writable by web server
  - [ ] `bootstrap/cache/` - writable by web server
  - [ ] `public/` - readable by web server
- [ ] Ensure `.env` is not publicly accessible

### **Asset Compilation**
- [ ] Build production assets: `npm run build`
- [ ] Verify Vite assets are compiled
- [ ] Test asset loading in production

### **Monitoring & Logging**
- [ ] Configure production logging
- [ ] Set up error tracking (Sentry, Bugsnag, etc.)
- [ ] Configure performance monitoring
- [ ] Set up health checks

### **Testing**
- [ ] Run backend test suite: `php artisan test`
- [ ] Run Playwright smoke suite: `npx playwright test --project=chromium --grep @smoke`
- [ ] Run Playwright core suite: `npx playwright test --project=chromium --grep @core`
- [ ] Run Playwright regression suite: `npx playwright test --project=chromium --grep @regression`
- [ ] Test authentication flow
- [ ] Test all main routes
- [ ] Verify tenant isolation

## 🔧 **Deployment Commands**

### **1. Environment Setup**
```bash
# Copy production environment
cp .env.production .env

# Generate application key
php artisan key:generate

# Clear all caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

### **2. Database Setup**
```bash
# Run migrations
php artisan migrate --force

# Seed production data
php artisan db:seed --class=ProductionSeeder

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### **3. Asset Compilation**
```bash
# Install dependencies
npm ci

# Build production assets
npm run build

# Verify build
ls -la public/build/
```

### **4. Final Verification**
```bash
# Test health endpoint
curl -I https://your-domain.com/_debug/health

# Test authentication
curl -I https://your-domain.com/login

# Test main routes
curl -I https://your-domain.com/app/dashboard
```

## 📊 **Post-Deployment Verification**

### **Health Checks**
- [ ] Health endpoint returns 200
- [ ] Database connectivity
- [ ] Redis connectivity
- [ ] File system permissions
- [ ] Asset loading

### **Security Verification**
- [ ] HTTPS redirects working
- [ ] Security headers present
- [ ] CSRF protection active
- [ ] Rate limiting functional
- [ ] Authentication working

### **Performance Verification**
- [ ] Page load times < 500ms
- [ ] API response times < 300ms
- [ ] Database queries optimized
- [ ] Caching working
- [ ] Asset compression active

### **Functional Testing**
- [ ] Login/logout flow
- [ ] All main routes accessible
- [ ] Tenant isolation working
- [ ] Data persistence
- [ ] Error handling

## 🚨 **Rollback Plan**

### **If Issues Occur**
1. **Immediate**: Revert to previous deployment
2. **Database**: Restore from backup
3. **Assets**: Revert to previous build
4. **Configuration**: Restore previous .env
5. **Investigation**: Check logs and monitoring

### **Emergency Contacts**
- [ ] System Administrator
- [ ] Database Administrator
- [ ] Security Team
- [ ] Monitoring Team

## 📈 **Success Metrics**

### **Performance Targets**
- Page load time: < 500ms (p95)
- API response time: < 300ms (p95)
- Database query time: < 100ms (p95)
- Error rate: < 0.1%

### **Security Targets**
- All security headers present
- Zero authentication bypasses
- Zero SQL injection attempts
- Zero XSS attempts

### **Reliability Targets**
- Uptime: > 99.9%
- Zero data loss
- Zero security incidents
- All health checks passing

---

**Deployment Date**: _______________
**Deployed By**: _______________
**Verified By**: _______________
**Status**: _______________
