# Guard Lint Tools

## 📋 **TỔNG QUAN**

Bộ công cụ Guard Lint được thiết kế để phát hiện và sửa các vấn đề với `auth()` helper trong Laravel, giúp đảm bảo sử dụng đúng `Auth` facade.

## 🛠️ **CÔNG CỤ**

### 1. Guard Lint (`guard-lint.php`)
**Mục đích**: Phát hiện các lỗi sử dụng `auth()` helper

**Cách sử dụng**:
```bash
# Kiểm tra toàn bộ app directory
php guard-lint.php app/

# Kiểm tra specific directory
php guard-lint.php app/Http/Controllers/

# Kiểm tra specific file
php guard-lint.php app/Http/Controllers/AppController.php
```

**Output**:
- ❌ **ERRORS**: Các lỗi cần sửa ngay
- ⚠️ **WARNINGS**: Các cảnh báo cần xem xét
- ✅ **GOOD EXAMPLES**: Các ví dụ sử dụng đúng

### 2. Auth Auto-Fix (`auth-auto-fix.php`)
**Mục đích**: Tự động sửa các lỗi `auth()` helper phổ biến

**Cách sử dụng**:
```bash
# Sửa toàn bộ app directory
php auth-auto-fix.php app/

# Sửa specific directory
php auth-auto-fix.php app/Http/Controllers/

# Sửa specific file
php auth-auto-fix.php app/Http/Controllers/AppController.php
```

**Các thay đổi tự động**:
- `auth()->check()` → `Auth::check()`
- `auth()->user()` → `Auth::user()`
- `auth()->id()` → `Auth::id()`
- `auth()->login()` → `Auth::login()`
- `auth()->logout()` → `Auth::logout()`
- `auth()->guest()` → `Auth::guest()`

### 3. GitHub Actions Workflow (`.github/workflows/auth-lint.yml`)
**Mục đích**: Tự động kiểm tra trong CI/CD pipeline

**Trigger**:
- Push to `main` or `develop` branches
- Pull requests to `main` or `develop` branches

**Actions**:
- Setup PHP 8.2
- Install dependencies
- Run Guard Lint
- Check for remaining `auth()` usage

## 🔍 **CÁC LỖI PHỔ BIẾN**

### 1. Direct auth() calls
```php
// ❌ Wrong
if (auth()) {
    // ...
}

// ✅ Correct
if (Auth::check()) {
    // ...
}
```

### 2. auth() with parameters
```php
// ❌ Wrong
$user = auth('api')->user();

// ✅ Correct
$user = Auth::guard('api')->user();
```

### 3. Method chaining
```php
// ❌ Wrong
$userId = auth()->user()->id;

// ✅ Correct
$userId = Auth::user()->id;
```

## 📊 **KẾT QUẢ MẪU**

### Guard Lint Output
```
🔍 Guard Lint - Checking for incorrect auth() usage...

❌ ERRORS:
================================================================================
File: app/Http/Controllers/AppController.php:25
Code: if (auth()->check()) {
Fix:  Use Auth::check() or Auth::user() instead
--------------------------------------------------------------------------------

✅ GOOD EXAMPLES:
================================================================================
File: app/Http/Controllers/AppController.php:30
Code: if (Auth::check()) {
Note: Good: Using Auth facade
--------------------------------------------------------------------------------
```

### Auto-Fix Output
```
🔧 Auth Auto-Fix - Fixing common auth() usage issues...

✅ FIXED FILES:
================================================================================
Fixed: app/Http/Controllers/AppController.php
Fixed: app/Http/Controllers/DashboardController.php
--------------------------------------------------------------------------------

⚠️  SKIPPED FILES:
================================================================================
Skipped: app/Http/Controllers/ExampleController.php
--------------------------------------------------------------------------------
```

## 🚀 **WORKFLOW KHUYẾN NGHỊ**

### 1. Development Phase
```bash
# Trước khi commit
php guard-lint.php app/

# Nếu có lỗi, chạy auto-fix
php auth-auto-fix.php app/

# Kiểm tra lại
php guard-lint.php app/
```

### 2. CI/CD Phase
- GitHub Actions tự động chạy Guard Lint
- PR sẽ fail nếu có lỗi `auth()` usage
- Developer phải sửa lỗi trước khi merge

### 3. Maintenance Phase
```bash
# Chạy định kỳ để kiểm tra
php guard-lint.php app/

# Sửa lỗi mới phát sinh
php auth-auto-fix.php app/
```

## ⚙️ **CẤU HÌNH**

### Custom Patterns
Bạn có thể thêm các pattern tùy chỉnh trong `guard-lint.php`:

```php
$patterns = [
    // Thêm pattern mới
    '/your_custom_pattern/' => 'Your custom suggestion',
];
```

### Exclude Files
Để loại trừ một số file khỏi kiểm tra:

```php
// Trong scanFile method
if (strpos($filePath, 'vendor/') !== false) {
    return; // Skip vendor files
}
```

## 📝 **NOTES**

- **Performance**: Guard Lint chỉ kiểm tra file `.php`
- **Safety**: Auto-fix chỉ thay đổi các pattern đã được xác định
- **Backup**: Luôn backup code trước khi chạy auto-fix
- **Review**: Luôn review các thay đổi tự động trước khi commit

## 🐛 **TROUBLESHOOTING**

### Common Issues

#### 1. "Permission denied"
```bash
chmod +x guard-lint.php
chmod +x auth-auto-fix.php
```

#### 2. "PHP not found"
```bash
# Sử dụng full path
/usr/bin/php guard-lint.php app/
```

#### 3. "No changes made"
- Kiểm tra xem file có chứa `auth()` usage không
- Kiểm tra pattern matching trong script

---

*Last Updated: September 24, 2025*
*Version: 1.0*
