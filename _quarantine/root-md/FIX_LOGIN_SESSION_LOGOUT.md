# 🔧 FIX LOGIN SESSION LOGOUT ISSUE

## 📋 Checklist đã hoàn thành

### ✅ 1. Backend: AuthenticationController.php
- [x] `Auth::guard('web')->login($user, $remember)` - Dùng web guard
- [x] `session()->regenerate()` - Regenerate session ID sau login
- [x] Set `session_start_time`, `last_activity`, `user_agent`, `ip_address`
- [x] `session()->save()` - Force save session
- [x] Thêm verification logging để debug

### ✅ 2. Routes: web.php
- [x] Route `/api/auth/login` có middleware `['web', 'throttle:5,1']`
- [x] Route `/app/dashboard` có middleware `['web', 'auth:web']`

### ✅ 3. Middleware: SessionManagementMiddleware.php
- [x] Skip kiểm tra cho `api/auth/login`, `api/auth/logout`, `login`, `logout`
- [x] Grace period 30 giây sau login (skip expiration check)
- [x] `isSessionExpired()` return `false` nếu không có `last_activity` (new session)

### ✅ 4. Frontend: login.blade.php
- [x] Header `X-Web-Login: '1'` (đã sửa từ 'true' thành '1')
- [x] `credentials: 'include'` - Include cookies
- [x] Header `Accept: application/json`

### ⚠️ 5. .env Configuration (CẦN KIỂM TRA)
Cần đảm bảo trong `.env`:
```env
SESSION_DRIVER=file
SESSION_DOMAIN=           # Để trống cho localhost
SESSION_SECURE_COOKIE=false  # false cho http://localhost
APP_URL=http://localhost:8000  # Hoặc http://127.0.0.1:8000
```

## 🔍 Debugging Steps

### Step 1: Kiểm tra session config
```bash
php artisan config:clear
php artisan cache:clear
php artisan tinker --execute="
echo 'SESSION_DRIVER: ' . config('session.driver') . PHP_EOL;
echo 'SESSION_DOMAIN: ' . (config('session.domain') ?: 'NULL') . PHP_EOL;
echo 'SESSION_SECURE_COOKIE: ' . (config('session.secure') ? 'true' : 'false') . PHP_EOL;
echo 'SESSION_SAME_SITE: ' . config('session.same_site') . PHP_EOL;
"
```

### Step 2: Test login API
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Web-Login: 1" \
  -H "X-CSRF-TOKEN: <token>" \
  -c cookies.txt \
  -b cookies.txt \
  -d '{"email":"admin@zena.com","password":"zena1234","remember":true}'
```

### Step 3: Kiểm tra session files
```bash
ls -la storage/framework/sessions/ | tail -10
```

### Step 4: Kiểm tra logs
```bash
tail -f storage/logs/laravel.log | grep -i "login\|session\|logout"
```

## 🐛 Common Issues & Solutions

### Issue 1: Session không được lưu
**Nguyên nhân**: Cookie không được set do domain/secure config
**Giải pháp**: 
```env
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=false
```

### Issue 2: Session bị invalidate ngay sau login
**Nguyên nhân**: Middleware check session quá sớm
**Giải pháp**: Đã fix với grace period 30s

### Issue 3: Auth check failed sau login
**Nguyên nhân**: Session không được persist giữa requests
**Giải pháp**: Đảm bảo `credentials: 'include'` trong fetch

## ✅ Verification Checklist

Sau khi login thành công:
1. [ ] Cookie `laravel_session` được set trong browser
2. [ ] Log có `"auth_check": true` và `"auth_id": <user_id>`
3. [ ] Redirect đến `/app/dashboard` thành công
4. [ ] Không bị redirect về `/login`
5. [ ] Session file được tạo trong `storage/framework/sessions/`

## 📝 Next Steps

1. Test login với tài khoản: `admin@zena.com` / `zena1234`
2. Kiểm tra browser DevTools → Application → Cookies
3. Kiểm tra Laravel logs để xem session có được lưu đúng không
4. Nếu vẫn logout, check middleware order và session config

