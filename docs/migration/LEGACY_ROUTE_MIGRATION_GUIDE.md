# 🔄 **ZENAMANAGE LEGACY ROUTE MIGRATION GUIDE**

## 📋 **OVERVIEW**

This guide provides comprehensive instructions for migrating from legacy routes to the new standardized route structure in ZenaManage.

## 🎯 **MIGRATION SCOPE**

### **Legacy Routes Being Migrated**
- `/dashboard` → `/app/dashboard`
- `/projects` → `/app/projects`
- `/tasks` → `/app/tasks`

### **Migration Timeline**
- **Announce Phase:** December 20, 2024 - December 26, 2024
- **Redirect Phase:** December 27, 2024 - January 9, 2025
- **Remove Phase:** January 10, 2025 onwards

## 📅 **3-PHASE MIGRATION PLAN**

### **Phase 1: Announce (Dec 20-26, 2024)**

#### **What Happens:**
- Deprecation headers added to legacy routes
- Usage monitoring begins
- Users notified of upcoming changes

#### **Headers Added:**
```
Deprecation: true
Sunset: 2025-01-10
Link: </app/dashboard>; rel="successor-version"
X-Legacy-Route: true
X-New-Route: /app/dashboard
X-Migration-Reason: Standardize app routes
```

#### **Actions Required:**
1. **Update Frontend Code:**
   ```javascript
   // OLD
   window.location.href = '/dashboard';
   
   // NEW
   window.location.href = '/app/dashboard';
   ```

2. **Update API Calls:**
   ```javascript
   // OLD
   fetch('/dashboard-data')
   
   // NEW
   fetch('/app/dashboard-data')
   ```

3. **Update Links:**
   ```html
   <!-- OLD -->
   <a href="/dashboard">Dashboard</a>
   
   <!-- NEW -->
   <a href="/app/dashboard">Dashboard</a>
   ```

### **Phase 2: Redirect (Dec 27, 2024 - Jan 9, 2025)**

#### **What Happens:**
- 301 permanent redirects implemented
- Legacy routes automatically redirect to new routes
- Query parameters preserved

#### **Redirect Behavior:**
```
GET /dashboard → 301 → /app/dashboard
GET /projects → 301 → /app/projects
GET /tasks → 301 → /tasks
```

#### **Actions Required:**
1. **Test Redirects:**
   ```bash
   curl -I http://localhost:8000/dashboard
   # Should return: HTTP/1.1 301 Moved Permanently
   # Location: /app/dashboard
   ```

2. **Update Bookmarks:**
   - Update browser bookmarks
   - Update documentation links
   - Update external integrations

3. **Monitor Performance:**
   - Check redirect response times
   - Monitor error rates
   - Track user adoption

### **Phase 3: Remove (Jan 10, 2025 onwards)**

#### **What Happens:**
- Legacy routes return 410 Gone
- Routes completely removed from codebase
- Monitoring data archived

#### **Response Format:**
```json
{
  "error": {
    "id": "req_12345678",
    "code": "E410.GONE",
    "message": "This route has been permanently removed",
    "details": {
      "legacy_path": "/dashboard",
      "new_path": "/app/dashboard",
      "removal_date": "2025-01-10",
      "reason": "Standardize app routes",
      "migration_guide": "/docs/migration/legacy-routes"
    }
  }
}
```

## 🔧 **IMPLEMENTATION DETAILS**

### **Middleware Stack**
```php
Route::middleware(['legacy.gone', 'legacy.redirect', 'legacy.route'])->group(function () {
    Route::get('/dashboard', function () {
        return redirect('/app/dashboard');
    });
});
```

### **Middleware Order**
1. **LegacyGoneMiddleware** - Returns 410 if past removal date
2. **LegacyRedirectMiddleware** - Returns 301 if in redirect phase
3. **LegacyRouteMiddleware** - Adds deprecation headers

### **Monitoring Integration**
```php
// Record usage
$monitoringService->recordUsage('/dashboard', '/app/dashboard', [
    'user_agent' => $request->userAgent(),
    'ip' => $request->ip()
]);

// Get statistics
$stats = $monitoringService->getUsageStats('/dashboard');
```

## 📊 **MONITORING & ANALYTICS**

### **Available Endpoints**
- `GET /api/v1/legacy-routes/usage` - Usage statistics
- `GET /api/v1/legacy-routes/migration-phase` - Phase information
- `GET /api/v1/legacy-routes/report` - Comprehensive report
- `POST /api/v1/legacy-routes/record-usage` - Record usage
- `POST /api/v1/legacy-routes/cleanup` - Clean old data

### **Usage Statistics**
```json
{
  "success": true,
  "data": {
    "routes": {
      "/dashboard": {
        "legacy_path": "/dashboard",
        "total_usage": 150,
        "last_7_days_total": 25,
        "average_daily": 3.57,
        "trend": "decreasing"
      }
    },
    "summary": {
      "total_legacy_usage": 450,
      "last_7_days_total": 75,
      "average_daily_total": 10.71,
      "highest_usage_route": "/dashboard",
      "highest_usage_count": 150,
      "active_routes": 3
    }
  }
}
```

### **Migration Phase Statistics**
```json
{
  "success": true,
  "data": {
    "current_date": "2024-12-19",
    "phase_distribution": {
      "announce": 0,
      "redirect": 3,
      "remove": 0
    },
    "total_routes": 3,
    "migration_progress": {
      "completed_announce": 3,
      "completed_redirect": 0,
      "completion_percentage": 0.0
    }
  }
}
```

## 🚨 **ROLLBACK PROCEDURES**

### **Emergency Rollback**
If critical issues arise, follow these steps:

1. **Disable Legacy Middleware:**
   ```php
   // Comment out middleware in routes/web.php
   // Route::middleware(['legacy.gone', 'legacy.redirect', 'legacy.route'])->group(function () {
   ```

2. **Restore Original Routes:**
   ```php
   Route::get('/dashboard', function () {
       return view('app.dashboard');
   });
   ```

3. **Clear Monitoring Data:**
   ```bash
   php artisan tinker
   >>> app(LegacyRouteMonitoringService::class)->clearOldData(0);
   ```

4. **Notify Users:**
   - Send email notification
   - Update status page
   - Post in support channels

**Estimated Time:** 5 minutes

### **Phased Rollback**
For gradual rollback:

1. **Revert to Previous Phase:**
   - Update phase dates in legacy-map.json
   - Adjust middleware behavior

2. **Update Timeline:**
   - Extend migration timeline
   - Communicate new dates

3. **Monitor Impact:**
   - Track usage patterns
   - Monitor error rates
   - Gather user feedback

**Estimated Time:** 30 minutes

## 🔍 **TESTING PROCEDURES**

### **Pre-Migration Testing**
```bash
# Test legacy routes
curl -I http://localhost:8000/dashboard
curl -I http://localhost:8000/projects
curl -I http://localhost:8000/tasks

# Test new routes
curl -I http://localhost:8000/app/dashboard
curl -I http://localhost:8000/app/projects
curl -I http://localhost:8000/app/tasks
```

### **Phase Testing**
```bash
# Test deprecation headers
curl -I http://localhost:8000/dashboard | grep -i deprecation

# Test redirects
curl -L http://localhost:8000/dashboard

# Test 410 responses (after removal date)
curl -i http://localhost:8000/dashboard
```

### **Monitoring Testing**
```bash
# Test monitoring endpoints
curl -H "Authorization: Bearer <token>" \
     http://localhost:8000/api/v1/legacy-routes/usage

curl -H "Authorization: Bearer <token>" \
     http://localhost:8000/api/v1/legacy-routes/migration-phase
```

## 📚 **DEVELOPER GUIDELINES**

### **Code Updates Required**

#### **Frontend JavaScript**
```javascript
// Update all route references
const routes = {
  dashboard: '/app/dashboard',
  projects: '/app/projects',
  tasks: '/app/tasks'
};

// Update navigation
function navigateToDashboard() {
  window.location.href = routes.dashboard;
}
```

#### **API Integration**
```javascript
// Update API endpoints
const apiEndpoints = {
  dashboard: '/api/v1/app/dashboard/stats',
  projects: '/api/v1/app/projects',
  tasks: '/api/v1/app/tasks'
};
```

#### **Laravel Routes**
```php
// Update route references
Route::get('/app/dashboard', [AppController::class, 'dashboard']);
Route::get('/app/projects', [AppController::class, 'projects']);
Route::get('/app/tasks', [AppController::class, 'tasks']);
```

### **Database Updates**
```sql
-- Update any stored URLs
UPDATE user_preferences SET dashboard_url = '/app/dashboard' WHERE dashboard_url = '/dashboard';
UPDATE bookmarks SET url = '/app/projects' WHERE url = '/projects';
UPDATE bookmarks SET url = '/app/tasks' WHERE url = '/tasks';
```

## 📈 **SUCCESS METRICS**

### **Migration Success Criteria**
- **Usage Reduction:** < 10% of original usage after 30 days
- **Error Rate:** < 1% during migration
- **Performance:** No degradation in response times
- **User Adoption:** > 90% using new routes within 14 days

### **Monitoring Thresholds**
- **High Usage Alert:** > 100 requests per day
- **Increasing Trend Alert:** > 10% increase week-over-week
- **Slow Migration Alert:** < 50% completion after 7 days

## 🆘 **SUPPORT & TROUBLESHOOTING**

### **Common Issues**

#### **404 Errors After Migration**
```bash
# Check if routes exist
php artisan route:list | grep dashboard

# Verify middleware is applied
php artisan route:list | grep legacy
```

#### **Redirect Loops**
```bash
# Check redirect configuration
curl -I http://localhost:8000/dashboard

# Verify new routes work
curl -I http://localhost:8000/app/dashboard
```

#### **Monitoring Data Issues**
```bash
# Clear cache
php artisan cache:clear

# Check monitoring service
php artisan tinker
>>> app(LegacyRouteMonitoringService::class)->getAllUsageStats();
```

### **Support Channels**
- **Email:** support@zenamanage.com
- **Slack:** #legacy-migration
- **Documentation:** /docs/migration/legacy-routes
- **Status Page:** /status

## 📋 **CHECKLIST**

### **Pre-Migration**
- [ ] Update all frontend route references
- [ ] Update API endpoint references
- [ ] Update documentation links
- [ ] Test new routes functionality
- [ ] Prepare rollback procedures
- [ ] Notify users of upcoming changes

### **During Migration**
- [ ] Monitor usage statistics
- [ ] Check error rates
- [ ] Verify redirect functionality
- [ ] Track user adoption
- [ ] Respond to user feedback
- [ ] Update monitoring dashboards

### **Post-Migration**
- [ ] Archive monitoring data
- [ ] Clean up unused code
- [ ] Update tests
- [ ] Document lessons learned
- [ ] Plan future migrations

## 🔗 **RESOURCES**

### **Documentation**
- [Legacy Route Map](../../public/legacy-map.json)
- [API Documentation](../../docs/api/README.md)
- [Migration Timeline](../../docs/migration/timeline.md)

### **Tools**
- **Monitoring Dashboard:** `/admin/legacy-routes`
- **API Endpoints:** `/api/v1/legacy-routes/*`
- **Status Page:** `/status`

### **Commands**
```bash
# Generate usage report
curl -H "Authorization: Bearer <token>" \
     http://localhost:8000/api/v1/legacy-routes/report

# Clean up old data
curl -X POST -H "Authorization: Bearer <token>" \
     http://localhost:8000/api/v1/legacy-routes/cleanup \
     -d '{"days_to_keep": 30}'
```

---

**Last Updated:** December 19, 2024  
**Version:** 1.0  
**Maintainer:** ZenaManage Development Team
