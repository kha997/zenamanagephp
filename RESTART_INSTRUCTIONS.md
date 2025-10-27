# 🔄 Hướng Dẫn Restart và Test Login

## ✅ Đã Sửa:
1. ✅ **frontend/vite.config.ts** - Sửa proxy config
2. ✅ **frontend/src/shared/auth/store.ts** - Thêm baseURL full để tránh lỗi

## 🚀 Các Bước Thực Hiện:

### Bước 1: Restart Frontend Server
```bash
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage/frontend
npm run dev
```

### Bước 2: Verify Backend đang chạy
Backend (Laravel) phải đang chạy trên port 8000:
```bash
# Kiểm tra backend
curl http://localhost:8000/api/v1/auth/login -X POST \
  -H 'Content-Type: application/json' \
  -d '{"email":"test@example.com","password":"password"}'

# Nếu không chạy, start backend:
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage
php artisan serve
```

### Bước 3: Test Login
1. Mở trình duyệt: **http://localhost:5173/login**
2. Nhập thông tin:
   - Email: `test@example.com`
   - Password: `password`
3. Click "Sign In"

### Bước 4: Kiểm Tra DevTools
Nếu vẫn lỗi, mở DevTools (F12):
- Tab **Console**: Xem error message
- Tab **Network**: Xem request gửi đi đến đâu
  - Should be: `http://localhost:8000/api/v1/auth/login`
  - Nếu là `http://localhost:5173/api/v1/auth/login` → proxy hoạt động
  - Nếu là `http://localhost:8000/api/v1/auth/login` → direct call

## 🔧 Troubleshooting

### Nếu vẫn 404:
1. Kiểm tra cả 2 servers đang chạy:
   ```bash
   lsof -ti:5173  # Frontend
   lsof -ti:8000  # Backend
   ```

2. Test backend directly:
   ```bash
   curl -X POST http://localhost:8000/api/v1/auth/login \
     -H 'Content-Type: application/json' \
     -d '{"email":"test@example.com","password":"password"}'
   ```

3. Check browser console có CORS error không?

### Nếu vẫn 404 sau khi restart:
Có thể cần hard refresh browser:
- **Chrome/Edge**: Ctrl+Shift+R (Cmd+Shift+R trên Mac)
- Hoặc clear cache và reload

## 📝 Summary of Changes:
1. `frontend/vite.config.ts`: Simplified proxy config
2. `frontend/src/shared/auth/store.ts`: 
   - Added explicit `baseURL: 'http://localhost:8000/api/v1'`
   - Added `withCredentials: false` (no cookies needed for token auth)
   - Fixed response parsing to handle API response structure

