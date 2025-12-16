# Routes Cleanup - Completed Actions

## Status: ✅ PARTIALLY COMPLETED

## Summary

Cleaned up duplicate routes and archived legacy route files that are not being loaded.

## Actions Completed

### ✅ 1. Archived Legacy Files
Moved legacy route files that are NOT loaded by RouteServiceProvider to `routes/archived/`:
- ✅ `routes/api_v1_minimal.php` → `routes/archived/`
- ✅ `routes/api-simple.php` → `routes/archived/`
- ✅ `routes/admin_simple.php` → `routes/archived/`

**Note**: These files were not being loaded, so moving them to archived is safe.

### ✅ 2. Commented Duplicate Dashboard Routes

**In `routes/api.php`**:

1. **Lines 313-494**: Commented out inline dashboard closures
   - `/api/dashboard/kpis` - Inline closure
   - `/api/dashboard/charts` - Inline closure
   - `/api/dashboard/recent-activity` - Inline closure
   - **Reason**: These are duplicates of proper controller-based routes in `routes/api_v1.php`
   - **Replacement**: Use `/api/v1/app/dashboard/*` routes from `routes/api_v1.php`

2. **Lines 786-816**: Commented out duplicate dashboard preference routes
   - `/api/v1/app/dashboard` (GET) - Inline closure
   - `/api/v1/app/dashboard` (PUT) - Inline closure
   - **Reason**: Duplicates of routes in `routes/api_v1.php`
   - **Replacement**: Use `/api/v1/app/dashboard/*` routes from `routes/api_v1.php`

### ✅ 3. Documentation

Created cleanup plan document: `docs/refactor/ROUTES_CLEANUP_PLAN.md`

## Active Dashboard Routes

### ✅ Proper Implementation (Keep These)

**`routes/api_v1.php`** - Lines 112-129:
- `/api/v1/app/dashboard/*` - Uses `DashboardController` ✅
- Proper middleware: `ability:tenant`
- Controller-based implementation
- **This is the single source of truth for dashboard API**

**`routes/api.php`** - Line 502:
- `/api/v1/app/projects/{id}/dashboard` - Uses `ProjectManagementController` ✅
- Project-specific dashboard data

**`routes/api.php`** - Line 702:
- `/api/v1/admin/dashboard/*` - Admin dashboard ✅
- Proper middleware: `auth:sanctum`, `ability:admin`

**`routes/api.php`** - Line 846:
- `/api/dashboard-analytics/*` - Uses `DashboardAnalyticsController` ✅

## Next Steps

### ⚠️ Verification Required

Before permanently removing commented routes:

1. **Check Frontend Code**:
   - Search frontend codebase for `/api/dashboard/kpis`
   - Search frontend codebase for `/api/dashboard/charts`
   - Search frontend codebase for `/api/dashboard/recent-activity`
   - Search frontend codebase for `/api/v1/app/dashboard` (GET/PUT without sub-paths)

2. **Test Dashboard Functionality**:
   - Verify dashboard KPIs load correctly
   - Verify dashboard charts load correctly
   - Verify recent activity loads correctly
   - Verify dashboard preferences work

3. **If No Frontend Usage Found**:
   - Remove commented code sections permanently
   - Update documentation

### 🔄 Future Improvements

1. **Migrate Inline Closures to Controllers**:
   - If any dashboard routes need to stay, move logic to `DashboardController`
   - Remove all inline closures from routes

2. **Consolidate Dashboard Routes**:
   - Ensure all dashboard routes use controllers
   - Single source of truth: `routes/api_v1.php` for app dashboard
   - Single source of truth: `routes/api.php` for admin dashboard

## Files Modified

- ✅ `routes/api.php` - Commented duplicate dashboard routes
- ✅ `routes/archived/` - Added legacy files

## Testing Checklist

- [ ] Test dashboard KPIs endpoint (`/api/v1/app/dashboard/stats`)
- [ ] Test dashboard charts endpoint (`/api/v1/app/dashboard/charts/{type}`)
- [ ] Test recent activity endpoint (`/api/v1/app/dashboard/recent-activity`)
- [ ] Test dashboard preferences (`/api/v1/app/dashboard/layout`)
- [ ] Verify no broken routes
- [ ] Check frontend doesn't use old endpoints

## Notes

- Commented routes are safe to remove after verification
- All active dashboard routes use proper controllers
- Legacy files archived, not deleted (can be restored if needed)

