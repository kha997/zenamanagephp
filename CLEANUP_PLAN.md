# 🧹 Dashboard Routes Cleanup Plan

## 📊 Tình trạng hiện tại

### routes/api.php có QUÁ NHIỀU dashboard routes:

1. **Simple dashboard endpoint** (line 89) ✅ GIỮ
   ```php
   Route::get('/dashboard', function() { ... });
   ```

2. **Dashboard endpoints** (line 217) ❌ XÓA - TRÙNG LẶP
   ```php
   Route::prefix('dashboard')->group(function () {
       Route::get('/data', [DashboardController::class, 'getDashboardData']);
       Route::get('/csrf-token', [DashboardController::class, 'getCsrfToken']);
   });
   ```

3. **DASHBOARDS API ENDPOINTS** (line 280) ❌ XÓA - CRUD không cần
   ```php
   Route::prefix('dashboards')->group(function () {
       Route::get('/', [DashboardController::class, 'index']);
       Route::post('/', [DashboardController::class, 'store']);
       // ...
   });
   ```

4. **DASHBOARD API ENDPOINTS** (line 291) ✅ GIỮ (Đang được dùng)
   ```php
   Route::prefix('dashboard')->group(function () {
       Route::get('/kpis', ...);
       Route::get('/charts', ...);
       Route::get('/recent-activity', ...);
   });
   ```

5. **ADMIN DASHBOARD API ENDPOINTS** (line 646) ✅ GIỮ
   ```php
   Route::prefix('admin/dashboard')->middleware(['ability:admin'])->group(function () {
       // Admin dashboard routes
   });
   ```

6. **Dashboard Analytics API** (line 790) ⚠️ REVIEW
   ```php
   Route::prefix('dashboard-analytics')->group(function () {
       Route::get('/analytics', ...);
       Route::get('/metrics', ...);
   });
   ```

7. **Dashboard API v1** (line 796) ❌ XÓA - TRÙNG LẶP với api_v1.php
   ```php
   Route::prefix('v1/dashboard')->group(function () {
       // Trùng lặp với routes/api_v1.php
   });
   ```

8. **Customizable Dashboard** (line 813) ❌ XÓA - Quá phức tạp
   ```php
   Route::prefix('v1/dashboard/customizable')->group(function () {
       // Rất nhiều routes
   });
   ```

9. **Role-Based Dashboard** (line 832) ❌ XÓA - Quá phức tạp
   ```php
   Route::prefix('v1/dashboard/role-based')->group(function () {
       // Rất nhiều routes
   });
   ```

---

## ✅ Routes cần GIỮ LẠI

### 1. Core Dashboard Endpoints (ĐANG DÙNG)
```php
// Line ~291
Route::prefix('dashboard')->group(function () {
    Route::get('/kpis', ...);
    Route::get('/charts', ...);
    Route::get('/recent-activity', ...);
});

// Line 89
Route::get('/dashboard', function() { ... });
```

### 2. Admin Dashboard (CHỨC NĂNG QUAN TRỌNG)
```php
// Line ~646
Route::prefix('admin/dashboard')->middleware(['ability:admin'])->group(function () {
    Route::get('/summary', ...);
    // ... admin routes
});
```

---

## ❌ Routes cần XÓA

1. **Dashboard endpoints** (line 217) - Trùng lặp
2. **DASHBOARDS API ENDPOINTS** (line 280) - CRUD không dùng
3. **Dashboard API v1** (line 796) - Trùng với api_v1.php
4. **Customizable Dashboard** (line 813) - Quá phức tạp, không dùng
5. **Role-Based Dashboard** (line 832) - Quá phức tạp, không dùng

---

## 🎯 Kết quả mong đợi

**Trước:** ~56 dashboard routes
**Sau:** ~10-15 routes cần thiết

**Files cần modify:**
- ✅ routes/api.php (chính)
- ✅ Archive legacy files

**Files backup trước khi modify:**
- ✅ routes/api.php.backup.20241027

