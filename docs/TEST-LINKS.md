# Test Links - Frontend Rebuild

## 🚀 Server Status

- ✅ Laravel Server: `http://localhost:8000` (running)
- ✅ Vite Dev Server: `http://localhost:5173` (running)

## 🔗 Test Links

### 1. Login Page
```
http://localhost:8000/login
```
**Mô tả**: Trang đăng nhập React mới
**Kiểm tra**:
- Form login hiển thị đúng
- Có thể nhập email/password
- Remember me checkbox hoạt động
- Submit form gọi API `/api/v1/auth/login`

### 2. App Routes (Sau khi login)

#### Projects List
```
http://localhost:8000/app/projects
```
**Mô tả**: Danh sách projects (React SPA)
**Kiểm tra**:
- Header với navigation hiển thị
- Projects list load từ API
- KPI strip (nếu có)
- Filters và pagination

#### Projects Detail
```
http://localhost:8000/app/projects/{id}
```
**Mô tả**: Chi tiết project
**Kiểm tra**:
- Project details load đúng
- API call `/api/v1/app/projects/{id}`

#### Create Project
```
http://localhost:8000/app/projects/create
```
**Mô tả**: Tạo project mới (skeleton - Phase 5)

#### Tasks List
```
http://localhost:8000/app/tasks
```
**Mô tả**: Danh sách tasks (React SPA)
**Kiểm tra**:
- Tasks list load từ API
- Filters hoạt động

#### Tasks Detail
```
http://localhost:8000/app/tasks/{id}
```
**Mô tả**: Chi tiết task

#### Create Task
```
http://localhost:8000/app/tasks/create
```
**Mô tả**: Tạo task mới (skeleton - Phase 6)

### 3. Admin Routes

#### Admin Dashboard
```
http://localhost:8000/admin
```
**Mô tả**: Admin dashboard (React SPA với AdminLayout)
**Kiểm tra**:
- AdminLayout với AdminNavigator
- Dashboard content

### 4. Auth Routes

#### Forgot Password
```
http://localhost:8000/forgot-password
```
**Mô tả**: Quên mật khẩu (skeleton - Phase 3)

#### Reset Password
```
http://localhost:8000/reset-password
```
**Mô tả**: Đặt lại mật khẩu (skeleton - Phase 3)

## 🧪 Test Flow

### Flow 1: Login → Projects
1. Truy cập: `http://localhost:8000/login`
2. Nhập credentials và login
3. Redirect đến: `http://localhost:8000/app/projects`
4. Kiểm tra projects list load

### Flow 2: Login → Tasks
1. Login tại: `http://localhost:8000/login`
2. Navigate đến: `http://localhost:8000/app/tasks`
3. Kiểm tra tasks list load

### Flow 3: Unauthenticated Access
1. Truy cập: `http://localhost:8000/app/projects` (chưa login)
2. Kiểm tra redirect đến: `http://localhost:8000/login`
3. Sau khi login, redirect về `/app/projects`

## 🔍 Kiểm Tra Console

Mở Browser DevTools (F12) và kiểm tra:

1. **Network Tab**:
   - API calls đến `/api/v1/auth/login`
   - API calls đến `/api/v1/app/projects`
   - API calls đến `/api/v1/app/tasks`
   - Headers có `X-CSRF-TOKEN` và `withCredentials: true`

2. **Console Tab**:
   - Không có lỗi JavaScript
   - React app mount thành công
   - Auth state được lưu vào localStorage

3. **Application Tab**:
   - LocalStorage có `auth-storage` key
   - Cookies có session cookie (nếu dùng session auth)

## ⚠️ Lưu Ý

1. **Build Required**: Nếu chạy production, cần build trước:
   ```bash
   cd frontend && npm run build
   ```

2. **Dev Server**: Nếu chạy development, Vite dev server phải chạy:
   ```bash
   cd frontend && npm run dev
   ```

3. **CORS/CSRF**: Đảm bảo Laravel backend cho phép requests từ frontend

4. **Session**: Login sử dụng session-based auth với `X-Web-Login: true` header

## 📝 Test Credentials

Sử dụng credentials từ database hoặc seed data để test login.

## 🐛 Troubleshooting

### SPA không mount
- Kiểm tra `resources/views/app/spa.blade.php` có `<div id="app"></div>`
- Kiểm tra manifest file: `public/build/.vite/manifest.json`
- Kiểm tra console errors

### API calls fail
- Kiểm tra `withCredentials: true` trong API client
- Kiểm tra CSRF token trong headers
- Kiểm tra Laravel routes: `routes/api.php`

### Redirect loop
- Kiểm tra AuthGuard logic
- Kiểm tra auth store state
- Kiểm tra localStorage `auth-storage`

