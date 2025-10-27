# ✅ ĐỊA CHỈ TRANG LOGIN ĐÚNG

## ❌ SAI - Không dùng:
```
http://localhost:8000/api/v1/auth/login
```
Đây là **backend API endpoint**, không phải trang web!

## ✅ ĐÚNG - Dùng địa chỉ này:
```
http://localhost:5173/login
```

## 🎯 Hướng Dẫn Chi Tiết:

### Bước 1: Mở Trang Login
1. Đóng tab hiện tại (có lỗi)
2. Mở tab mới
3. Truy cập: **http://localhost:5173/login**

### Bước 2: Đăng Nhập
- Email: `test@example.com`
- Password: `password`
- Click **"Sign In"**

### Bước 3: Xác Nhận
Sau khi login thành công, bạn sẽ được redirect đến:
**http://localhost:5173/app/dashboard**

## 🔍 Hiểu Sự Khác Biệt:

| URL | Mục Đích | Cho Ai |
|-----|----------|--------|
| `localhost:8000/*` | Backend API (Laravel) | Backend server |
| `localhost:5173/*` | Frontend UI (React) | **Bạn - người dùng** |

## 📝 Quan Trọng:
- **Frontend** (localhost:5173) = Giao diện web để bạn tương tác
- **Backend** (localhost:8000) = API server xử lý logic

Khi bạn đăng nhập ở frontend, nó sẽ tự động gọi backend API để xác thực!

