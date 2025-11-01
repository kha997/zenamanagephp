# 🔐 Thông Tin Đăng Nhập Kiểm Tra

## 📍 Links Truy Cập

### Trang Chính / Main App
```
http://localhost:5173
```

### Trang Đăng Nhập Trực Tiếp
```
http://localhost:5173/login
```

### Backend API
```
http://localhost:8000
```

---

## 🔑 Thông Tin Đăng Nhập

### User Test (Đã tạo sẵn)
- **Email**: `test@example.com`
- **Password**: `password`
- **Tenant**: Test Tenant
- **Role**: Admin
- **Status**: Active, Verified

---

## 🚀 Cách Đăng Nhập

### Bước 1: Mở trình duyệt
Truy cập: `http://localhost:5173/login`

### Bước 2: Điền thông tin
- Email: `test@example.com`
- Password: `password`

### Bước 3: Click "Sign In" hoặc "Login"

### Bước 4: Kiểm tra
- Nếu thành công, sẽ redirect đến Dashboard
- Hoặc có thể truy cập trực tiếp: `http://localhost:5173/app/dashboard`

---

## 🔄 Tạo Lại User Test (Nếu Cần)

```bash
# Chạy seeder để tạo/cập nhật user test
php artisan db:seed --class=TestLoginUserSeeder
```

---

## 📱 Kiểm Tra Nhanh

### Test API Login bằng curl
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"test@example.com","password":"password"}'
```

### Test bằng Script
```bash
./test-login-simple.sh
```

---

## 🌐 Các Trang Quan Trọng

- **Login**: http://localhost:5173/login
- **Dashboard**: http://localhost:5173/app/dashboard  
- **Documents**: http://localhost:5173/app/documents
- **Projects**: http://localhost:5173/app/projects
- **Tasks**: http://localhost:5173/app/tasks

---

## ⚠️ Lưu Ý

1. **Frontend phải chạy** trên port 5173 (Vite dev server)
2. **Backend phải chạy** trên port 8000 (Laravel)
3. User test đã được tạo với đầy đủ quyền truy cập

## 🛠️ Kiểm Tra Services

```bash
# Xem services đang chạy
./check_ports.php

# Hoặc xem trong terminal đang chạy
# Frontend: npm run dev (port 5173)
# Backend: php artisan serve (port 8000)
```

