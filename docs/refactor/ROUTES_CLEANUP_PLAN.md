# Routes Cleanup Plan

## Status: 🟡 IN PROGRESS

## Summary

Plan to cleanup duplicate routes and remove legacy route files that are not being loaded.

## Analysis

### Files Loaded by RouteServiceProvider
- ✅ `routes/web.php` - Active
- ✅ `routes/app.php` - Active
- ✅ `routes/admin.php` - Active
- ✅ `routes/api.php` - Active
- ✅ `routes/api_v1.php` - Active
- ✅ `routes/debug.php` - Active
- ✅ `routes/security.php` - Active
- ✅ `routes/legacy.php` - Active
- ✅ `routes/test.php` - Active (testing/local/development only)

### Legacy Files NOT Loaded (Can be archived/deleted)
- ❌ `routes/api_v1_minimal.php` - NOT loaded
- ❌ `routes/api-simple.php` - NOT loaded
- ❌ `routes/admin_simple.php` - NOT loaded
- ❌ `routes/web.php.backup.20251108_062128` - Backup file

### Dashboard Routes Analysis

#### routes/api.php
- Line 313-400: `/api/dashboard/*` - Inline closures (should use controller)
- Line 502: `/api/v1/app/projects/{id}/dashboard` - Uses ProjectManagementController ✅
- Line 702: `/api/v1/admin/dashboard/*` - Admin dashboard ✅
- Line 786: `/api/v1/app/dashboard` - Inline closure (duplicate?)
- Line 803: `/api/v1/app/dashboard` - PUT method (duplicate?)
- Line 846: `/api/dashboard-analytics/*` - Uses DashboardAnalyticsController ✅

#### routes/api_v1.php
- Line 112-129: `/api/v1/app/dashboard/*` - Uses DashboardController ✅ (Proper implementation)

## Issues Found

1. **Duplicate Dashboard Routes**:
   - `routes/api.php` line 313-400: Inline closures for dashboard KPIs
   - `routes/api_v1.php` line 112-129: Proper controller-based routes
   - **Recommendation**: Remove inline closures, use controller from api_v1.php

2. **Legacy Files**:
   - `api_v1_minimal.php` - Not loaded, can archive
   - `api-simple.php` - Not loaded, can archive
   - `admin_simple.php` - Not loaded, can archive

## Cleanup Actions

### Phase 1: Archive Legacy Files ✅
- [x] Move `api_v1_minimal.php` to archived (if exists)
- [x] Move `api-simple.php` to archived (if exists)
- [x] Move `admin_simple.php` to archived (if exists)

### Phase 2: Consolidate Dashboard Routes
- [ ] Remove inline dashboard closures from `routes/api.php` (lines 313-400)
- [ ] Verify `routes/api_v1.php` dashboard routes are sufficient
- [ ] Update any frontend code that uses old dashboard endpoints
- [ ] Test dashboard functionality

### Phase 3: Remove Duplicate Routes
- [ ] Remove duplicate `/api/v1/app/dashboard` routes (lines 786, 803) if covered by api_v1.php
- [ ] Verify all dashboard functionality works

## Recommendations

1. **Use Controller Pattern**: All dashboard routes should use controllers, not inline closures
2. **Single Source**: Dashboard routes should be in `routes/api_v1.php` using `DashboardController`
3. **Remove Inline Closures**: Replace with controller methods for better maintainability

## Testing

After cleanup:
- [ ] Test dashboard KPIs endpoint
- [ ] Test dashboard stats endpoint
- [ ] Test dashboard widgets endpoint
- [ ] Test admin dashboard
- [ ] Verify no broken routes

