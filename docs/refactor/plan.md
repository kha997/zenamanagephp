# ZENAMANAGE REFACTOR PLAN

**Version:** 1.0  
**Created:** 2024-12-19  
**Status:** Ready for Execution  

## 🎯 **OVERVIEW**

This plan outlines the systematic refactoring of ZenaManage to comply with Non-Negotiable Principles. The refactoring is broken down into 7 sequential PRs, each focusing on specific aspects of the codebase.

## 📋 **EXECUTION STRATEGY**

### **Wave-Based Approach**
- **Wave 1:** Routes & Middleware (PRs #1-2)
- **Wave 2:** Code Structure (PRs #3-4)  
- **Wave 3:** Quality & Performance (PRs #5-7)

### **PR Size Limits**
- Each PR: 300-500 lines maximum
- Focused scope per PR
- Clear rollback strategy
- Comprehensive testing

---

## 🚀 **PR EXECUTION PLAN**

### **PR #1: Route Normalization**
**Scope:** Routes, middleware, debug gates  
**Estimated Lines:** 400-500  
**Duration:** 2-3 days  

#### **Tasks:**
1. **Fix Authentication Middleware**
   - Add `auth` middleware to all protected routes
   - Add `rbac:admin` to admin routes
   - Add `tenant.isolation` to app routes

2. **Move UI Side-Effects to API**
   - Move POST operations from web routes to API routes
   - Update form actions to use API endpoints
   - Remove business logic from web controllers

3. **Implement Debug Gates**
   - Add `debug.gate` middleware to `/_debug/*` routes
   - Ensure debug routes only work in non-production
   - Add IP whitelisting for debug access

4. **Standardize Route Prefixes**
   - Ensure `/admin/*` for system-wide operations
   - Ensure `/app/*` for tenant-scoped operations
   - Ensure `/_debug/*` for development tools
   - Ensure `/api/v1/*` for REST endpoints

#### **Files Modified:**
- `routes/web.php`
- `routes/api.php`
- `app/Http/Kernel.php`
- `app/Http/Middleware/DebugGateMiddleware.php`

#### **Testing:**
- Verify all routes have proper middleware
- Test debug gate functionality
- Verify API endpoints work correctly

---

### **PR #2: Naming Standardization**
**Scope:** File names, class names, route names  
**Estimated Lines:** 300-400  
**Duration:** 2-3 days  

#### **Tasks:**
1. **Apply Rename Map**
   - Execute all renames from `rename-map.json`
   - Update all imports and references
   - Update route names to kebab-case

2. **Fix Controller Namespaces**
   - Move controllers to proper namespaces
   - Update all controller references
   - Ensure consistent naming patterns

3. **Update View Names**
   - Standardize Blade view naming
   - Update all view references
   - Ensure consistent directory structure

#### **Files Modified:**
- All controller files (moved/renamed)
- All view files (renamed)
- All route definitions
- All import statements

#### **Testing:**
- Verify all imports work correctly
- Test all renamed routes
- Verify view rendering works

---

### **PR #3: Remove Duplicates**
**Scope:** Duplicate components, services, views  
**Estimated Lines:** 400-500  
**Duration:** 3-4 days  

#### **Tasks:**
1. **Consolidate Dashboard Views**
   - Keep only `dashboard-clean.blade.php`
   - Remove duplicate dashboard implementations
   - Update all dashboard references

2. **Merge Duplicate Controllers**
   - Consolidate `ProjectController` variants
   - Merge `UserController` implementations
   - Remove unused controller methods

3. **Eliminate Duplicate Services**
   - Identify and merge duplicate services
   - Update all service references
   - Ensure single source of truth

4. **Clean Up Alpine.js Components**
   - Resolve function name conflicts
   - Consolidate duplicate components
   - Ensure clean component structure

#### **Files Modified:**
- Multiple dashboard view files
- Duplicate controller files
- Service files
- JavaScript/Alpine.js files

#### **Testing:**
- Verify dashboard functionality
- Test all consolidated features
- Ensure no broken references

---

### **PR #4: Error Envelope & OpenAPI Sync**
**Scope:** API responses, error handling, documentation  
**Estimated Lines:** 300-400  
**Duration:** 2-3 days  

#### **Tasks:**
1. **Implement Error Envelope**
   - Standardize error response format
   - Add error IDs and codes
   - Implement i18n for error messages

2. **Update API Responses**
   - Ensure consistent response format
   - Add proper HTTP status codes
   - Implement retry-after headers

3. **Regenerate OpenAPI Documentation**
   - Update API documentation
   - Ensure accuracy of endpoints
   - Add proper examples

#### **Files Modified:**
- All API controllers
- Error handling middleware
- OpenAPI/Swagger files
- Language files

#### **Testing:**
- Verify error envelope format
- Test API documentation
- Ensure proper error handling

---

### **PR #5: Tests & A11y/Perf Gates**
**Scope:** Testing, accessibility, performance  
**Estimated Lines:** 400-500  
**Duration:** 3-4 days  

#### **Tasks:**
1. **Add Comprehensive Tests**
   - Unit tests for all services
   - Integration tests for API endpoints
   - E2E tests for critical flows

2. **Implement A11y Gates**
   - Add Lighthouse CI integration
   - Implement axe-core testing
   - Ensure WCAG 2.1 AA compliance

3. **Add Performance Monitoring**
   - Implement performance budgets
   - Add monitoring for p95 latency
   - Ensure page load times < 500ms

#### **Files Modified:**
- Test files (new/updated)
- CI/CD configuration
- Performance monitoring setup
- Accessibility testing

#### **Testing:**
- Run full test suite
- Verify accessibility compliance
- Check performance metrics

---

### **PR #6: Legacy Plan Implementation**
**Scope:** Legacy route management, redirects  
**Estimated Lines:** 300-400  
**Duration:** 2-3 days  

#### **Tasks:**
1. **Implement Legacy Map**
   - Add deprecation headers to legacy routes
   - Implement 301 redirects
   - Set up monitoring

2. **Create Migration Guide**
   - Document all breaking changes
   - Provide migration instructions
   - Create rollback procedures

3. **Set Up Monitoring**
   - Track legacy route usage
   - Monitor redirect performance
   - Set up alerts

#### **Files Modified:**
- Legacy route definitions
- Migration documentation
- Monitoring configuration
- Alert setup

#### **Testing:**
- Test all redirects
- Verify monitoring works
- Test rollback procedures

---

### **PR #7: Final Cleanups**
**Scope:** Documentation, diagrams, scripts  
**Estimated Lines:** 200-300  
**Duration:** 1-2 days  

#### **Tasks:**
1. **Update Documentation**
   - Complete API documentation
   - Update architecture diagrams
   - Create user guides

2. **Create Mermaid Diagrams**
   - Update page tree diagrams
   - Create architecture diagrams
   - Document data flow

3. **Finalize Scripts**
   - Complete command guide
   - Add deployment scripts
   - Create maintenance procedures

#### **Files Modified:**
- Documentation files
- Diagram files
- Script files
- Configuration files

#### **Testing:**
- Verify all documentation
- Test all scripts
- Ensure diagrams are accurate

---

## 🔧 **COMMANDS GUIDE**

### **Development Commands**
```bash
# Code Quality
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/larastan analyse

# Testing
php artisan test
php artisan test --coverage
./vendor/bin/phpunit

# Performance
php artisan optimize:clear
php artisan route:cache
php artisan config:cache
php artisan view:cache

# Security
php artisan route:list
php artisan middleware:list
php artisan auth:clear-resets
```

### **Refactoring Commands**
```bash
# Route Analysis
php artisan route:list --columns=method,uri,name,middleware
php artisan route:list --path=admin
php artisan route:list --path=app

# Cache Management
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Database
php artisan migrate:status
php artisan db:seed
php artisan tinker
```

---

## ✅ **SUCCESS CRITERIA**

### **Route Normalization**
- [ ] All routes have proper middleware
- [ ] No UI side-effects in web routes
- [ ] Debug gates working correctly
- [ ] Route prefixes standardized

### **Naming Standards**
- [ ] All files follow naming conventions
- [ ] All classes follow PascalCase
- [ ] All routes follow kebab-case
- [ ] All imports updated

### **Duplicate Removal**
- [ ] No duplicate dashboard views
- [ ] No duplicate controllers
- [ ] No duplicate services
- [ ] No Alpine.js conflicts

### **Error Handling**
- [ ] Error envelope implemented
- [ ] i18n error messages
- [ ] Proper HTTP status codes
- [ ] OpenAPI documentation updated

### **Testing & Quality**
- [ ] Comprehensive test coverage
- [ ] A11y compliance verified
- [ ] Performance budgets met
- [ ] CI/CD gates working

### **Legacy Management**
- [ ] Legacy routes properly managed
- [ ] Migration guide complete
- [ ] Monitoring in place
- [ ] Rollback procedures tested

---

## 🚨 **RISK MITIGATION**

### **High-Risk Changes**
1. **Route Changes:** Could break existing functionality
2. **Controller Moves:** Could break imports
3. **Dashboard Consolidation:** Could break UI

### **Mitigation Strategies**
1. **Comprehensive Testing:** Test all changes thoroughly
2. **Gradual Rollout:** Deploy changes incrementally
3. **Rollback Plans:** Have rollback procedures ready
4. **Monitoring:** Monitor for issues post-deployment

### **Rollback Procedures**
1. **Immediate:** Revert problematic changes
2. **Short-term:** Restore previous functionality
3. **Long-term:** Address root causes

---

## 📊 **PROGRESS TRACKING**

### **Phase 1: Foundation (PRs #1-2)**
- [ ] PR #1: Route Normalization
- [ ] PR #2: Naming Standardization

### **Phase 2: Structure (PRs #3-4)**
- [ ] PR #3: Remove Duplicates
- [ ] PR #4: Error Envelope & OpenAPI

### **Phase 3: Quality (PRs #5-7)**
- [ ] PR #5: Tests & A11y/Perf Gates
- [ ] PR #6: Legacy Plan Implementation
- [ ] PR #7: Final Cleanups

---

**Status:** ✅ Plan Complete  
**Next Action:** Begin PR #1 - Route Normalization
