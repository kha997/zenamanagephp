# Header Test Information

## 🚀 Laravel Server Status
✅ **Server đang chạy**: `http://127.0.0.1:8000`

## 🔐 Test Users

### Super Admin (để test admin routes)
- **Email**: `superadmin@zena.com` hoặc `admin@zenamanage.com`
- **Password**: `zena1234` hoặc `password`
- **Role**: `super_admin`

### Admin User
- **Email**: `admin@zena.com`
- **Password**: `zena1234`
- **Role**: `admin`

## 📍 Test URLs

### Login Page
- **URL**: `http://127.0.0.1:8000/login`
- **Hoặc**: `http://127.0.0.1:8000/auth/login`

### Admin Dashboard
- **URL**: `http://127.0.0.1:8000/admin/dashboard`
- **Required**: Login với super_admin user

### Admin Routes để Test Header
1. `/admin/dashboard` - Dashboard
2. `/admin/users` - Users management
3. `/admin/tenants` - Tenants management
4. `/admin/projects` - Projects
5. `/admin/security` - Security
6. `/admin/alerts` - Alerts
7. `/admin/activities` - Activities
8. `/admin/analytics` - Analytics
9. `/admin/maintenance` - Maintenance
10. `/admin/settings` - Settings

## 🧪 Test Steps

1. **Login:**
   - Go to `http://127.0.0.1:8000/login`
   - Login với `superadmin@zena.com` / `zena1234`

2. **Access Admin Dashboard:**
   - Go to `http://127.0.0.1:8000/admin/dashboard`

3. **Test Header:**
   - Check header hiển thị đúng
   - Check navigation items
   - Check notifications dropdown
   - Check user menu
   - Check mobile menu (resize browser)
   - Check active states khi navigate

## ✅ Expected Results

- Header hiển thị với logo "ZenaManage"
- Navigation menu có 10 items (Dashboard, Users, Tenants, Projects, Security, Alerts, Activities, Analytics, Maintenance, Settings)
- Active state highlight đúng item
- Notifications bell icon hiển thị
- User menu hiển thị user name và email
- Mobile menu hoạt động trên mobile viewport
- Tất cả links navigate đúng routes

