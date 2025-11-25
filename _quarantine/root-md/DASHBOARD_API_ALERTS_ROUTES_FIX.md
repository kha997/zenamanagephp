# Dashboard API Alerts Routes - Missing Routes Fix

## Issue
Lỗi 404 khi frontend gọi `/api/v1/app/dashboard/alerts`

## Root Cause
Dashboard routes chỉ có 8 routes ban đầu, thiếu các routes cho alerts, widgets, layout, preferences, etc.

## Routes Added
Added **11 missing routes** to `routes/api_v1.php`:

```php
Route::get('/alerts', [DashboardController::class, 'getAlerts']);
Route::put('/alerts/{id}/read', [DashboardController::class, 'markAlertAsRead']);
Route::put('/alerts/read-all', [DashboardController::class, 'markAllAlertsAsRead']);
Route::get('/widgets', [DashboardController::class, 'getAvailableWidgets']);
Route::get('/widgets/{id}/data', [DashboardController::class, 'getWidgetData']);
Route::post('/widgets', [DashboardController::class, 'addWidget']);
Route::delete('/widgets/{id}', [DashboardController::class, 'removeWidget']);
Route::put('/widgets/{id}', [DashboardController::class, 'updateWidgetConfig']);
Route::put('/layout', [DashboardController::class, 'updateLayout']);
Route::post('/preferences', [DashboardController::class, 'saveUserPreferences']);
Route::post('/reset', [DashboardController::class, 'resetToDefault']);
```

## Controller Methods Added
Added **10 new methods** to `DashboardController`:

1. ✅ `getAlerts()` - Get user alerts
2. ✅ `markAlertAsRead(string $id)` - Mark single alert as read
3. ✅ `markAllAlertsAsRead()` - Mark all alerts as read
4. ✅ `getAvailableWidgets()` - Get available widgets
5. ✅ `getWidgetData(string $id)` - Get widget data
6. ✅ `addWidget(Request $request)` - Add widget to dashboard
7. ✅ `removeWidget(string $id)` - Remove widget
8. ✅ `updateWidgetConfig(Request $request, string $id)` - Update widget config
9. ✅ `updateLayout(Request $request)` - Update dashboard layout
10. ✅ `saveUserPreferences(Request $request)` - Save user preferences
11. ✅ `resetToDefault()` - Reset dashboard to default

## Total Routes Now
**19 routes** registered for dashboard API:
- Main dashboard data (1)
- Stats and metrics (2)
- Recent data (3)
- Charts (1)
- Alerts (3)
- Widgets (5)
- Layout (1)
- Preferences (1)
- Reset (1)
- Team status (1)
- Charts (1)

## Verification
```bash
php artisan route:list --path=api/v1/app/dashboard
```
Shows 19 routes ✅

## Status
✅ All missing routes added
✅ All controller methods implemented
✅ No 404 errors for dashboard/alerts anymore

---

**Date**: October 26, 2025
**Status**: ✅ COMPLETE

