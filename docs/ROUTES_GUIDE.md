# Routes Guide - ZenaManage

## 🚫 **KHÔNG BAO GIỜ LÀM**

### ❌ Không viết `/app/*` ở `routes/web.php`
```php
// ❌ SAI - Đừng làm thế này
Route::get('/app/projects', [ProjectController::class, 'index']);

// ✅ ĐÚNG - Viết ở routes/app.php
Route::prefix('app')->name('app.')->middleware(['web', 'auth:web'])->group(function () {
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
});
```

### ❌ Không dùng closure cho production routes
```php
// ❌ SAI - Closure cho production
Route::get('/app/dashboard', function () {
    return view('dashboard');
});

// ✅ ĐÚNG - Dùng controller
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
```

### ❌ Legacy redirect chỉ định nghĩa 1 lần ở `routes/legacy.php`
```php
// ❌ SAI - Định nghĩa trùng
Route::permanentRedirect('/projects', '/app/projects'); // Ở web.php
Route::permanentRedirect('/projects', '/app/projects'); // Ở app.php

// ✅ ĐÚNG - Chỉ ở routes/legacy.php
Route::permanentRedirect('/projects', '/app/projects');
```

## ✅ **QUY TẮC BẮT BUỘC**

### 📁 File Structure
```
routes/
├── web.php      # Auth routes + root redirect only
├── app.php      # All /app/* routes
├── admin.php    # All /admin/* routes  
├── debug.php    # All /_debug/* routes
├── legacy.php   # All 301 redirects
└── api.php      # API routes
```

### 🏷️ Prefix + Name Convention
```php
// App UI
Route::prefix('app')->name('app.')->middleware(['web', 'auth:web'])->group(function () {
    // Routes here get: /app/* and app.* names
});

// Admin UI  
Route::prefix('admin')->name('admin.')->middleware(['web', 'auth:web'])->group(function () {
    // Routes here get: /admin/* and admin.* names
});

// Debug
Route::prefix('_debug')->name('debug.')->middleware('web')->group(function () {
    // Routes here get: /_debug/* and debug.* names
});
```

### 🔒 Middleware Requirements
- **App routes**: `['web', 'auth:web']` - BẮT BUỘC
- **Admin routes**: `['web', 'auth:web']` - BẮT BUỘC  
- **Debug routes**: `['web']` - Tối thiểu
- **Legacy redirects**: `['web']` - Tối thiểu

## 🧪 **TESTING & CI**

### Chạy route tests
```bash
# Test tất cả routes
php artisan test --testsuite=Feature --filter=Routes

# CI check nhanh
./scripts/ci-routes-check.sh
```

### Thêm route mới → Update snapshot
```bash
# Nếu thay đổi public surface (thêm/xóa route)
php artisan test --testsuite=Feature --filter=RouteSnapshotTest
# Test sẽ fail → commit snapshot mới
```

## 🚀 **DEPLOYMENT CHECKLIST**

```bash
# 1. Route verification
composer route:verify

# 2. Check essential routes exist
php artisan route:list | grep -E 'app\.(projects|tasks|clients|quotes)'

# 3. Test legacy redirects
curl -I http://localhost:8000/projects  # Should return 301

# 4. Test app routes (after login)
curl -I http://localhost:8000/app/dashboard  # Should return 200

# 5. Run route tests
php artisan test --testsuite=Feature --filter=Routes
```

## 🔧 **TROUBLESHOOTING**

### Route not found (404)
1. Check file đúng chưa (`routes/app.php` cho `/app/*`)
2. Check middleware có đúng không
3. Check prefix + name convention
4. Run `php artisan route:clear && php artisan route:cache`

### Duplicate route error
1. Check không có route trùng URI + method
2. Check không có route name trùng
3. Run `php artisan test --testsuite=Feature --filter=UniqueRoutesTest`

### Middleware error
1. Check `auth:web` middleware có hoạt động không
2. Check user đã login chưa
3. Check session có valid không

---

**Remember**: Routes là foundation của app. Tuân thủ convention để tránh technical debt! 🎯
