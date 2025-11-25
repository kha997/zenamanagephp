# 🔐 THÔNG TIN ĐĂNG NHẬP - TEST ACCOUNTS

## 🚀 URL Đăng Nhập

```
http://127.0.0.1:8000/login
```

---

## 👥 TÀI KHOẢN TEST (Từ TestUsersSeeder)

**Tất cả tài khoản sau đều dùng password: `zena1234`**

### 1. Super Admin (Quyền cao nhất)
```
Email: superadmin@zena.com
Password: zena1234
Role: super_admin
```
**Sử dụng cho**: Test admin routes, system-wide settings

### 2. Admin User (Quản trị viên)
```
# AGENT_HANDOFF.md

## Done

*   Phân tích layouts và đưa ra kế hoạch dọn dẹp.

## Next for Cursor

*   Phân tích `auth.blade.php` để tìm các chức năng trùng lặp với `auth-layout.blade.php`.
*   Tìm kiếm các view sử dụng `simple-layout.blade.php` và `no-nav-layout.blade.php`.
*   Di chuyển `navigation.blade.php` vào `resources/views/components/shared/navigation/` nếu nó là một component.

## Next for Reviewer

*   Đánh giá kết quả phân tích của Cursor.
*   Quyết định giữ lại hoặc loại bỏ các layout.
*   Tạo các patch cần thiết để hợp nhất hoặc loại bỏ các layout.
*   Viết test cho các thay đổi.
*   Cập nhật `CHANGES.md`.
Password: zena1234
Role: admin
```
**Sử dụng cho**: Test admin functions, user management

### 3. Project Manager (Quản lý dự án)
```
Email: pm@zena.com
Password: zena1234
Role: project_manager
```
**Sử dụng cho**: Test project management, task assignment

### 4. Designer (Thiết kế)
```
Email: designer@zena.com
Password: zena1234
Role: designer
```
**Sử dụng cho**: Test design-related features

### 5. Site Engineer (Kỹ sư công trường)
```
Email: site@zena.com
Password: zena1234
Role: site_engineer
```
**Sử dụng cho**: Test site-related features

### 6. QC Engineer (Kỹ sư kiểm tra chất lượng)
```
Email: qc@zena.com
Password: zena1234
Role: qc_engineer
```
**Sử dụng cho**: Test quality control features

### 7. Procurement (Mua hàng)
```
Email: procurement@zena.com
Password: zena1234
Role: procurement
```
**Sử dụng cho**: Test procurement features

### 8. Finance Manager (Quản lý tài chính)
```
Email: finance@zena.com
Password: zena1234
Role: finance
```
**Sử dụng cho**: Test financial features

### 9. Client User (Khách hàng)
```
Email: client@zena.com
Password: zena1234
Role: client
```
**Sử dụng cho**: Test client-facing features

---

## 🎯 TÀI KHOẢN KHUYẾN NGHỊ CHO TESTING

### Để test Dashboard và App routes:
```
Email: admin@zena.com
Password: zena1234
```
→ Sau khi login, truy cập: `http://127.0.0.1:8000/app/dashboard`

### Để test Admin routes:
```
Email: superadmin@zena.com
Password: zena1234
```
→ Sau khi login, truy cập: `http://127.0.0.1:8000/admin/dashboard`

### Để test với quyền hạn hạn chế:
```
Email: client@zena.com
Password: zena1234
```

---

## 🔄 TẠO LẠI TÀI KHOẢN TEST (Nếu cần)

```bash
# Chạy seeder để tạo/cập nhật tất cả test users
php artisan db:seed --class=TestUsersSeeder

# Chạy RoleSeeder để tạo các roles (nếu thiếu)
php artisan db:seed --class=RoleSeeder

# Chạy UserRoleSeeder để assign roles cho users
php artisan db:seed --class=UserRoleSeeder

# Hoặc chạy tất cả seeders
php artisan migrate:fresh --seed
```

## ⚠️ NẾU LOGIN KHÔNG THÀNH CÔNG

### Kiểm tra User trong Database:
```bash
php artisan tinker --execute="
\$user = App\Models\User::where('email', 'superadmin@zena.com')->first();
if (\$user) {
    echo '✅ User exists' . PHP_EOL;
    echo 'Is Active: ' . (\$user->is_active ? 'Yes' : 'No') . PHP_EOL;
    echo 'Tenant ID: ' . (\$user->tenant_id ?: 'NULL') . PHP_EOL;
    echo 'Password check: ' . (Hash::check('zena1234', \$user->password) ? 'Correct' : 'Incorrect') . PHP_EOL;
} else {
    echo '❌ User NOT found! Run: php artisan db:seed --class=TestUsersSeeder' . PHP_EOL;
}
"
```

### Fix User nếu thiếu thông tin:
```bash
php artisan tinker --execute="
\$user = App\Models\User::where('email', 'superadmin@zena.com')->first();
if (\$user) {
    if (!\$user->is_active) {
        \$user->is_active = true;
        echo '✅ Activated user' . PHP_EOL;
    }
    if (!\$user->tenant_id) {
        \$tenant = DB::table('tenants')->first();
        if (\$tenant) {
            \$user->tenant_id = \$tenant->id;
            echo '✅ Assigned tenant: ' . \$tenant->id . PHP_EOL;
        }
    }
    \$user->save();
    echo '✅ User updated!' . PHP_EOL;
}
"
```

---

## 📋 CHECKLIST TRƯỚC KHI TEST

- [ ] Laravel server đang chạy: `php artisan serve`
- [ ] Database đã được migrate và seed
- [ ] Đã có ít nhất 1 tenant trong database
- [ ] Đã có roles trong database (RoleSeeder đã chạy)

---

## 🧪 TEST NHANH ĐĂNG NHẬP

### Bước 1: Mở trình duyệt
```
http://127.0.0.1:8000/login
```

### Bước 2: Điền thông tin
- **Email**: `admin@zena.com`
- **Password**: `zena1234`

### Bước 3: Click "Login" hoặc "Sign In"

### Bước 4: Verify
- Nếu thành công → Redirect đến `/app/dashboard`
- Nếu lỗi → Kiểm tra console và network tab

---

## 🔍 KIỂM TRA BẰNG COMMAND LINE

### Test login bằng curl:
```bash
curl -X POST http://127.0.0.1:8000/login \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "email=admin@zena.com&password=zena1234&_token=$(php artisan tinker --execute='echo csrf_token();')"
```

### Kiểm tra user tồn tại trong database:
```bash
php artisan tinker --execute="echo App\Models\User::where('email', 'admin@zena.com')->first();"
```

---

## ⚠️ LƯU Ý

1. **Password**: Tất cả test users đều dùng password `zena1234`
2. **Email domain**: Sử dụng `@zena.com` cho test users
3. **Tenant**: Tất cả users được gán vào tenant đầu tiên (tenant_id = 1)
4. **Roles**: Mỗi user có 1 role được assign tự động

---

## 📊 TÓM TẮT NHANH

**Tài khoản khuyến nghị cho testing Dashboard:**
```
Email: admin@zena.com
Password: zena1234
URL: http://127.0.0.1:8000/app/dashboard
```

**Tài khoản khuyến nghị cho testing Admin:**
```
Email: superadmin@zena.com
Password: zena1234
URL: http://127.0.0.1:8000/admin/dashboard
```

---

## ✅ VERIFICATION STATUS

**Last Check**: Users đã được tạo thành công
- ✅ `superadmin@zena.com` - Active, có Tenant ID, Password đúng
- ✅ `admin@zena.com` - Active, có Tenant ID, Password đúng

**Nếu login vẫn không thành công**, kiểm tra:
1. Server đang chạy: `php artisan serve`
2. Browser console có errors không
3. Network tab xem API call có được gửi không
4. Laravel logs: `tail -f storage/logs/laravel.log`

---

## 🔍 DEBUG LOGIN ISSUES

### ⚠️ NẾU GẶP LỖI "IP address temporarily blocked":

**Nguyên nhân**: Brute force protection đã block IP sau nhiều lần login failed.

**Giải pháp nhanh**:
```bash
# Option 1: Clear tất cả cache (NHANH NHẤT)
php artisan cache:clear

# Option 2: Clear chỉ brute force protection cache
php artisan tinker --execute="
\$ip = request()->ip() ?: '127.0.0.1';
Cache::forget('brute_force:ip:' . \$ip);
Cache::forget('brute_force:account:superadmin@zena.com');
Cache::forget('brute_force:account:admin@zena.com');
Cache::forget('auth_attempts:' . \$ip);
Cache::forget('user_attempts:superadmin@zena.com');
Cache::forget('user_attempts:admin@zena.com');
Cache::forget('auth_lockout:superadmin@zena.com');
Cache::forget('auth_lockout:admin@zena.com');
echo '✅ Lockout cleared!';
"

# Option 3: Sử dụng artisan command (nếu đã tạo)
php artisan auth:clear-lockout --ip=127.0.0.1
```

**Sau khi clear cache, thử login lại ngay!**

### Kiểm tra Laravel Logs:
```bash
tail -f storage/logs/laravel.log
```

### Test API Login trực tiếp:
```bash
curl -X POST http://127.0.0.1:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Requested-With: XMLHttpRequest" \
  -d '{"email":"superadmin@zena.com","password":"zena1234"}'
```

### Expected Response:
```json
{
  "success": true,
  "data": {
    "token": "...",
    "user": {...}
  }
}
```

---

**Status**: ✅ USERS READY FOR LOGIN

**Next**: Sau khi login, follow Step 1 trong `verify-browser-fixes.md` để verify browser fixes

