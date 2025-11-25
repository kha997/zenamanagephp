# ✅ Dashboard 401 Unauthorized - FIXED

## 🐛 Vấn Đề

**Error trong Console:**
```
GET http://localhost:5173/api/v1/dashboard/ 401 (Unauthorized)
```

**Nguyên nhân:**
- `authToken` chỉ load từ localStorage khi module load lần đầu
- Khi user login, token mới được lưu vào localStorage NHƯNG biến `authToken` không được update
- API call không có Authorization header → 401

## ✅ Giải Pháp

**File: `frontend/src/shared/api/client.ts`**

```typescript
// Before
if (authToken) {
  config.headers.Authorization = `Bearer ${authToken}`;
}

// After
// Always check localStorage for latest token
if (typeof window !== 'undefined') {
  const token = window.localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
}
```

**Reasoning:**
- Mỗi request đều đọc token mới nhất từ localStorage
- Đảm bảo token luôn được send đúng
- Fix race condition khi login rồi immediately load dashboard

## 🧪 Test

1. **Clear browser data** (optional):
   - F12 → Application → Clear storage → Clear site data
   
2. **Login lại**:
   - http://localhost:5173/login
   - test@example.com / password
   
3. **Dashboard sẽ load ngay**:
   - http://localhost:5173/app/dashboard
   - Không còn 401 error!

## 📋 Summary

**Root cause:** Token not refreshed in interceptor  
**Fix:** Read token from localStorage on every request  
**Result:** 401 → 200 ✅

