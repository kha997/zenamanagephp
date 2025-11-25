# 🔍 DEBUG SESSION LOGOUT ISSUE

## 📋 Thay đổi đã thực hiện

### 1. AuthenticationController.php
- ✅ Set session data TRƯỚC regenerate
- ✅ Verify user sau regenerate và re-login nếu cần
- ✅ Force save session

### 2. DashboardController.php
- ✅ Thêm debug logging để track auth state
- ✅ Fallback check với `Auth::guard('web')->user()`

## 🧪 Bước tiếp theo để debug

### 1. Test login và check logs
```bash
# Login và ngay sau đó check logs
tail -f storage/logs/laravel.log | grep -iE "login|dashboard|auth|session"
```

**Kỳ vọng thấy:**
- `User logged in for web session` với `auth_check: true`
- `DashboardController: Checking auth` với `auth_check: true`
- Nếu `auth_check: false` → vấn đề là session không persist

### 2. Check browser cookies
- DevTools → Application → Cookies → `http://localhost:8000`
- Kiểm tra có cookie `laravel_session` không
- Nếu không có → cookie không được set

### 3. Check Network tab
- Network → `/api/auth/login` → Headers → Response Headers
- Tìm `Set-Cookie: laravel_session=...`
- Nếu không có → cookie không được set trong response

### 4. Check session files
```bash
ls -la storage/framework/sessions/ | tail -5
cat storage/framework/sessions/<session_file> | head -20
```

## 🐛 Nguyên nhân có thể

### 1. Cookie không được set
**Nguyên nhân**: Response không có Set-Cookie header
**Giải pháp**: Đảm bảo middleware `web` được apply và session được start

### 2. Cookie không được gửi lại
**Nguyên nhân**: Browser không gửi cookie trong request tiếp theo
**Giải pháp**: Đảm bảo `credentials: 'include'` trong fetch

### 3. Session regenerate làm mất auth
**Nguyên nhân**: `regenerate()` không preserve auth state
**Giải pháp**: Đã fix với re-login sau regenerate

### 4. Middleware logout user
**Nguyên nhân**: Có middleware đang logout user
**Giải pháp**: Check logs để xem middleware nào logout

## ✅ Checklist debug

Sau khi login:
1. [ ] Cookie `laravel_session` có trong browser?
2. [ ] Log có `auth_check: true` sau login?
3. [ ] Log có `DashboardController: Checking auth` với `auth_check: true`?
4. [ ] Session file được tạo trong `storage/framework/sessions/`?
5. [ ] Cookie được gửi trong request đến `/app/dashboard`?

## 📝 Next: Test và báo cáo

1. Login với: `admin@zena.com` / `zena1234`
2. Check browser DevTools → Application → Cookies
3. Check browser DevTools → Network → `/app/dashboard` → Headers → Request Headers → Cookie
4. Check Laravel logs: `tail -f storage/logs/laravel.log`
5. Báo cáo kết quả

