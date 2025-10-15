# Security Checklist CI/CD

## 🚨 Critical Security Checks

### 1. Authentication Vulnerabilities
- [ ] **NO unprotected login endpoints** - All login must use AuthenticationController@login
- [ ] **NO direct Auth::attempt()** without middleware protection
- [ ] **NO bypass routes** - All auth routes must have proper middleware
- [ ] **Rate limiting** - All auth endpoints must have throttle middleware

### 2. Dangerous Test Routes
- [ ] **NO test-login routes** in production
- [ ] **NO auto-login routes** in production  
- [ ] **NO debug-auth routes** in production
- [ ] **NO direct login routes** in production
- [ ] **Environment protection** - Debug routes only in APP_DEBUG=true

### 3. RBAC Security
- [ ] **NO hasPermission() returning true** - Must use real permission checks
- [ ] **NO bypass authorization** - All controllers must use proper policies
- [ ] **NO hardcoded permissions** - Use dynamic permission system
- [ ] **Policy enforcement** - All CRUD operations must check permissions

### 4. Tenancy Security
- [ ] **NO session-based tenant checks** in API routes
- [ ] **NO cross-tenant data access** - All queries must filter by tenant_id
- [ ] **Middleware enforcement** - Tenant scope middleware on all API routes
- [ ] **Auth::user() usage** - Use Auth::user() not session('user')

### 5. Mock Data Security
- [ ] **NO mock data in production** - All data must be real
- [ ] **NO hardcoded notifications** - Use real API data
- [ ] **NO fake user data** - Use real database queries
- [ ] **NO placeholder content** - All content must be dynamic

### 6. Rate Limiting Consistency
- [ ] **Single rate limit middleware** - No duplicate middleware
- [ ] **Consistent throttling** - Same rules across all endpoints
- [ ] **Proper configuration** - Rate limits must be configured
- [ ] **No bypass routes** - All routes must have rate limiting

### 7. Module Duplication
- [ ] **NO duplicate modules** - Single source of truth
- [ ] **NO conflicting routes** - Routes must be unique
- [ ] **NO duplicate controllers** - Consolidate similar functionality
- [ ] **Clean autoload** - Remove unused modules

### 8. API Response Consistency
- [ ] **Single response format** - Use ApiResponse consistently
- [ ] **NO mixed formats** - No JSendResponse mixed với ApiResponse
- [ ] **Consistent error handling** - Standard error format
- [ ] **Proper status codes** - Use correct HTTP status codes

### 9. FormRequest Security
- [ ] **NO abort(403) in prepareForValidation()** - Use proper authorization
- [ ] **Consistent validation** - No overlapping rules
- [ ] **Auth::user() usage** - Use Auth::user() not session
- [ ] **Tenant injection** - Proper tenant scope validation

### 10. CI/CD Security Gates
- [ ] **Fail on debug routes** - CI must fail if debug routes exist in production
- [ ] **Duplicate detection** - Run jscpd + phpcpd on every commit
- [ ] **Route validation** - Check for duplicate route names
- [ ] **Middleware validation** - Ensure proper middleware usage

## 🔧 Implementation Commands

### Check for Dangerous Routes
```bash
# Check for test routes
grep -r "test-login\|auto-login\|debug-auth" routes/

# Check for unprotected login
grep -r "Auth::attempt" routes/ --exclude-dir=vendor

# Check for bypass routes
grep -r "Auth::login" routes/ --exclude-dir=vendor
```

### Check for Mock Data
```bash
# Check for hardcoded notifications
grep -r "New Project Created\|Task Completed" resources/views/

# Check for fake data
grep -r "Project Owner\|Sample User" app/Http/Controllers/

# Check for placeholder content
grep -r "Welcome to ZenaManage" resources/views/
```

### Check for Duplicate Modules
```bash
# Check for duplicate controllers
find . -name "*Controller.php" | grep -E "(Project|User)" | sort

# Check for duplicate middleware
find . -name "*Middleware.php" | grep -i rate | sort

# Check for duplicate requests
find . -name "*Request.php" | grep -E "(Project|User)" | sort
```

### Check for Security Issues
```bash
# Check for hasPermission returning true
grep -r "return true.*permission" app/Http/Controllers/

# Check for session-based tenant checks
grep -r "session.*user" app/Http/Requests/

# Check for mixed response formats
grep -r "JSendResponse\|ApiResponse" app/Http/Controllers/
```

## 🚨 Emergency Actions

### If Critical Issues Found:
1. **Immediately disable** dangerous routes
2. **Remove mock data** from production
3. **Fix RBAC bypass** in controllers
4. **Consolidate middleware** to single implementation
5. **Remove duplicate modules** from autoload
6. **Standardize API responses** to single format
7. **Update CI/CD** to prevent future issues

### Rollback Plan:
1. **Revert to last known good state**
2. **Apply security fixes incrementally**
3. **Test each fix thoroughly**
4. **Deploy with monitoring**
5. **Verify security improvements**

## 📋 Pre-Deployment Checklist

- [ ] All critical security checks pass
- [ ] No dangerous routes in production
- [ ] No mock data in production
- [ ] RBAC properly implemented
- [ ] Tenancy properly enforced
- [ ] Rate limiting consistent
- [ ] No duplicate modules
- [ ] API responses standardized
- [ ] FormRequests properly secured
- [ ] CI/CD security gates active

**This checklist must be completed before any deployment to production.**
