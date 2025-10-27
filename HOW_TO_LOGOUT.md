# 🔓 Cách Logout

## 📍 Có 2 Nơi Có Thể Logout:

### Option 1: Từ Console (Nhanh Nhất)
1. Mở **F12** → **Console** tab
2. Gõ lệnh sau:

```javascript
// Method 1: Xóa token
localStorage.clear();

// Method 2: Hoặc chỉ xóa token
localStorage.removeItem('auth_token');

// Sau đó reload trang
location.reload();
```

### Option 2: Tìm Nút Logout trong UI

**Nút logout có thể ở:**
- 🎯 **Góc trên bên phải** → Avatar/User icon → Click → Logout
- 🎯 **Sidebar cuối trang** → Button "Logout" hoặc icon ⌂
- 🎯 **Header bar** → Logout icon (⏻)

### Option 3: Direct URL
Truy cập: http://localhost:5173/logout

### Option 4: Backend API
```bash
curl -X POST http://localhost:8000/api/v1/auth/logout \
  -H "Authorization: Bearer <token>"
```

## 🎯 Recommended
**Cách nhanh nhất:** 
1. F12 → Console
2. Gõ: `localStorage.clear()` + Enter
3. Reload: `location.reload()` + Enter

## ✨ Những Gì Sẽ Xảy Ra:
- Token bị xóa
- User session bị clear
- Redirect về trang login
- Data cached được xóa

