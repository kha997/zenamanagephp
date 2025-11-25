# 🔐 Thông Tin Đăng Nhập và Links - ZenaManage

**Last Updated:** 2025-01-27  
**Status:** ✅ Active  
**Note:** React Login là hệ thống chính (SSOT). Blade Login đã bị disabled. Xem config/frontend.php để biết hệ thống active.

---

## 🌐 Links Truy Cập

### React Frontend (PRIMARY - Active System)
```
http://localhost:5173
```
- **Status:** ✅ Hệ thống chính (SSOT - Single Source of Truth)
- **Login Page:** `http://localhost:5173/login` ⭐ **SỬ DỤNG TRANG NÀY**
- **Dashboard:** `http://localhost:5173/app/dashboard`
- **Projects:** `http://localhost:5173/app/projects`
- **Tasks:** `http://localhost:5173/app/tasks`
- **Note:** React SPA với TypeScript, modern UI
- **Khởi động:** `cd frontend && npm run dev`

### Laravel Backend (API + Admin)
```
http://localhost:8000
```
- **Admin Dashboard:** `http://localhost:8000/admin/dashboard` (Blade views)
- **API Base:** `http://localhost:8000/api`
- **Login Page:** ⚠️ **DISABLED** - Blade login route đã bị comment (sử dụng React Login thay thế)
- **Root Route:** Redirect đến React Frontend login khi React active

### Vite Dev Server (Laravel Assets)
```
http://localhost:3000
```
- **Purpose:** Phục vụ assets cho Blade views (CSS, JS) - chỉ cho admin routes
- **Status:** ✅ Đang chạy (Laravel Vite plugin)
- **Note:** Không truy cập trực tiếp, Laravel tự động load assets từ đây

### Alternative URLs (127.0.0.1)
```
http://127.0.0.1:5173  (React Frontend - PRIMARY)
http://127.0.0.1:3000  (Vite Dev Server - Laravel Assets)
http://127.0.0.1:8000  (Laravel Backend - API + Admin)
```

---

## 🔑 Thông Tin Đăng Nhập

### ⭐ Recommended Test Account (Main)

```
Email:    superadmin@zena.com
Password: password
Role:     super_admin
Tenant:   01k964z50tmezcbshm5kcm8qhh
```

**Sử dụng cho:**
- ✅ Test toàn bộ hệ thống
- ✅ Test admin routes
- ✅ Test tất cả features
- ✅ Full access

---

### 📋 Danh Sách Test Accounts

#### Main Test Users (Password: `password`)

| Email | Password | Name | Role | Tenant ID |
|-------|----------|------|------|-----------|
| **`superadmin@zena.com`** | **`password`** | Super Admin | super_admin | 01k964z50tmezcbshm5kcm8qhh |
| `admin@zena.com` | `password` | Admin User | admin | 01k964z50tmezcbshm5kcm8qhh |
| `pm@zena.com` | `password` | Project Manager | project_manager | 01k964z50tmezcbshm5kcm8qhh |
| `admin@zena.local` | `password` | Admin User | N/A | 01k964z50tmezcbshm5kcm8qhh |

#### Other Test Users (Password: `zena1234`)

| Email | Password | Name | Role |
|-------|----------|------|------|
| `designer@zena.com` | `zena1234` | Designer | designer |
| `site@zena.com` | `zena1234` | Site Engineer | site_engineer |
| `qc@zena.com` | `zena1234` | QC Engineer | qc_engineer |
| `procurement@zena.com` | `zena1234` | Procurement | procurement |
| `finance@zena.com` | `zena1234` | Finance Manager | finance |
| `client@zena.com` | `zena1234` | Client User | client |

#### Alternative Test Account

```
Email:    test@example.com
Password: password
Role:     Admin
Status:   Active, Verified
```

---

## 🚀 Cách Đăng Nhập

### Option 1: React Frontend (PRIMARY - Recommended) ⭐

1. **Khởi động React Frontend:**
   ```bash
   cd frontend
   npm run dev
   ```
   React sẽ chạy trên port 5173

2. **Mở trình duyệt:**
   ```
   http://localhost:5173/login
   ```

3. **Điền thông tin:**
   - Email: `superadmin@zena.com`
   - Password: `password`
   - Remember me: (optional)

4. **Click "Sign In"**

5. **Sau khi login thành công:**
   - Redirect đến: `http://localhost:5173/app/dashboard` (hoặc trang được chỉ định)

### Option 2: Admin Dashboard (Blade Views)

1. **Truy cập trực tiếp admin dashboard:**
   ```
   http://localhost:8000/admin/dashboard
   ```
   (Sẽ redirect đến React login nếu chưa authenticated - root route redirects to React)

### Option 3: Blade Login (DISABLED)

⚠️ **Lưu ý:** Blade Login route đã bị disabled theo SSOT. 
- Route `GET /login` đã bị comment trong `routes/web.php`
- Sử dụng React Login thay thế (Option 1)
- Để enable lại Blade Login, cần:
  1. Thay đổi `config/frontend.php`: `'active' => 'blade'`
  2. Uncomment route trong `routes/web.php`
  3. Chạy: `php artisan frontend:validate`

---

## 📍 Các Trang Quan Trọng

### React Frontend Routes (Port 5173) - ✅ PRIMARY - Active System

- **Login:** `http://localhost:5173/login` ⭐ **SỬ DỤNG TRANG NÀY**
- **Dashboard:** `http://localhost:5173/app/dashboard`
- **Projects List:** `http://localhost:5173/app/projects`
- **Projects Create:** `http://localhost:5173/app/projects/create`
- **Projects Detail:** `http://localhost:5173/app/projects/{id}`
- **Tasks List:** `http://localhost:5173/app/tasks`
- **Tasks Create:** `http://localhost:5173/app/tasks/create`
- **Tasks Detail:** `http://localhost:5173/app/tasks/{id}`
- **Forgot Password:** `http://localhost:5173/forgot-password`
- **Reset Password:** `http://localhost:5173/reset-password`
- **Register:** `http://localhost:5173/register`

### Blade App Routes (Port 8000) - ⚠️ DISABLED

⚠️ **Lưu ý:** Blade app routes đã bị disabled theo SSOT. Sử dụng React Frontend thay thế.

- **Login:** ⚠️ DISABLED - Route đã bị comment
- **Dashboard:** ⚠️ DISABLED - Sử dụng React Frontend
- **Projects:** ⚠️ DISABLED - Sử dụng React Frontend
- **Tasks:** ⚠️ DISABLED - Sử dụng React Frontend

### Admin Routes (Blade - Port 8000)

- **Admin Dashboard:** `http://localhost:8000/admin/dashboard`
- **Admin Users:** `http://localhost:8000/admin/users`
- **Admin Tenants:** `http://localhost:8000/admin/tenants`
- **Admin Projects:** `http://localhost:8000/admin/projects`
- **Admin Security:** `http://localhost:8000/admin/security`
- **Admin Alerts:** `http://localhost:8000/admin/alerts`
- **Admin Activities:** `http://localhost:8000/admin/activities`
- **Admin Analytics:** `http://localhost:8000/admin/analytics`
- **Admin Maintenance:** `http://localhost:8000/admin/maintenance`
- **Admin Settings:** `http://localhost:8000/admin/settings`

### API Endpoints (Port 8000)

- **Login API:** `POST http://localhost:8000/api/auth/login`
- **User Info:** `GET http://localhost:8000/api/auth/me`
- **Permissions:** `GET http://localhost:8000/api/auth/permissions`

---

## 🧪 Test Flow

### Flow 1: React Login → Dashboard → Projects (PRIMARY) ⭐

1. Khởi động React Frontend: `cd frontend && npm run dev`
2. Truy cập: `http://localhost:5173/login`
3. Login với: `superadmin@zena.com` / `password`
4. Redirect đến: `http://localhost:5173/app/dashboard`
5. Navigate đến: `http://localhost:5173/app/projects`
6. Kiểm tra projects list load từ API

### Flow 2: React Login → Tasks

1. Khởi động React Frontend: `cd frontend && npm run dev`
2. Login tại: `http://localhost:5173/login`
3. Navigate đến: `http://localhost:5173/app/tasks`
4. Kiểm tra tasks list load từ API

### Flow 3: Admin Access

1. Login với React Frontend: `http://localhost:5173/login`
2. Truy cập: `http://localhost:8000/admin/dashboard`
3. Kiểm tra admin dashboard hiển thị (Blade view)

### Flow 4: Unauthenticated Access (React)

1. Khởi động React Frontend: `cd frontend && npm run dev`
2. Truy cập: `http://localhost:5173/app/projects` (chưa login)
3. Kiểm tra redirect đến: `http://localhost:5173/login`
4. Sau khi login, redirect về `/app/projects`

### Flow 5: Root Route Redirect

1. Truy cập: `http://localhost:8000/` (root route)
2. Kiểm tra redirect đến: `http://localhost:5173/login` (React Frontend)
3. Verify redirect hoạt động đúng khi React active

---

## 🔧 Kiểm Tra Services

### Check if Services are Running

```bash
# Check Laravel Backend (Port 8000)
curl -I http://localhost:8000

# Check Vite Dev Server (Port 3000 - Laravel Assets)
curl -I http://localhost:3000

# Check React Frontend (Port 5173 - Optional)
curl -I http://localhost:5173

# Check if processes are running
ps aux | grep "artisan serve"
ps aux | grep "vite"
```

### Start Services (if not running)

```bash
# Start Laravel Backend (Required)
php artisan serve --host=127.0.0.1 --port=8000

# Start Vite Dev Server (Laravel Assets) - từ root directory (Required for admin routes)
npm run dev

# Start React Frontend (REQUIRED - PRIMARY system) - trong frontend directory
cd frontend
npm run dev
```

**Lưu ý:** Cả 3 services cần chạy đồng thời:
- Laravel Backend (8000) - API + Admin routes
- Vite Dev Server (3000) - Assets cho admin routes
- React Frontend (5173) - PRIMARY login và app routes

---

## 🧪 Test API Login

### Using curl

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{
    "email": "superadmin@zena.com",
    "password": "password"
  }'
```

### Using Browser Console

```javascript
fetch('http://localhost:8000/api/auth/login', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  credentials: 'include',
  body: JSON.stringify({
    email: 'superadmin@zena.com',
    password: 'password'
  })
})
.then(r => r.json())
.then(console.log);
```

---

## ⚠️ Lưu Ý Quan Trọng

### Current Setup (Active)

- ✅ **React Frontend** (Port 5173) handles: `/login`, `/forgot-password`, `/reset-password`, `/app/*` - **PRIMARY SYSTEM**
- ✅ **Laravel Backend** (Port 8000) handles: `/admin/*` (Blade views), API endpoints
- ✅ **Vite Dev Server** (Port 3000) serves Laravel assets (CSS, JS) cho admin Blade views
- ⚠️ **Blade Login** đã bị disabled - route `GET /login` đã bị comment trong `routes/web.php`
- ✅ See `config/frontend.php` for active frontend system (React is active)

### Authentication

- React Login uses **API-based auth** với `X-Web-Login: true` header
- API uses **token-based auth** (Sanctum) + session support
- Session cookies are required for web routes
- `withCredentials: true` must be set for API calls from React

### Development Setup

1. **Laravel Backend** phải chạy trên port 8000 (Required)
2. **Vite Dev Server** (Laravel assets) phải chạy trên port 3000 (Required for admin routes)
3. **React Frontend** phải chạy trên port 5173 (Required - PRIMARY system)
4. Cả 3 services phải chạy đồng thời để hệ thống hoạt động đầy đủ
5. CORS is configured to allow requests from React Frontend
6. Root route (`/`) redirects to React Frontend login when React is active

---

## 🐛 Troubleshooting

### Cannot Access Login Page

1. **Check if services are running:**
   ```bash
   ps aux | grep "artisan serve"
   ps aux | grep "vite"
   ```

2. **Check ports:**
   ```bash
   lsof -i :3000  # Vite Dev Server (Laravel Assets)
   lsof -i :5173  # React Frontend (Optional)
   lsof -i :8000  # Laravel Backend
   ```

3. **Check Laravel logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Login Fails

1. **Check browser console** for JavaScript errors
2. **Check Network tab** for API call status
3. **Verify credentials** are correct
4. **Check Laravel logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Redirect Loop

1. Check AuthGuard logic in React
2. Check auth store state
3. Check localStorage `auth-storage`
4. Clear browser cache and cookies

### API Calls Fail

1. Verify `withCredentials: true` in API client
2. Check CSRF token in headers
3. Verify CORS configuration
4. Check Laravel routes: `routes/api.php`

---

## 📝 Quick Reference

### Most Used Credentials

```
Email:    superadmin@zena.com
Password: password
```

### Most Used URLs

```
React Frontend: http://localhost:5173 (PRIMARY)
Login:          http://localhost:5173/login ⭐
Dashboard:      http://localhost:5173/app/dashboard
Backend API:    http://localhost:8000/api
Admin:          http://localhost:8000/admin/dashboard
Vite Assets:    http://localhost:3000 (Laravel Assets)
```

---

## 📚 Related Documentation

- **`docs/USER-CREDENTIALS.md`** - Detailed user credentials
- **`DEVELOPMENT_SERVER_SETUP.md`** - Server setup guide
- **`docs/TEST-LINKS.md`** - Test links and flows
- **`SINGLE_SOURCE_OF_TRUTH_REPORT.md`** - Frontend architecture guide

---

**For questions or issues, check the troubleshooting section or refer to the related documentation.**

