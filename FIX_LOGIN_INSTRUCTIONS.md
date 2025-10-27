# Fix Login 404 Error

## Vấn Đề
Frontend đang bị 404 khi login vì proxy không hoạt động đúng.

## Giải Pháp

### Bước 1: Restart Frontend Server
Vì đã sửa `frontend/vite.config.ts`, cần restart frontend dev server:

```bash
# Tìm process đang chạy trên port 5173
lsof -ti:5173 | xargs kill -9

# Hoặc nếu đang chạy trong terminal khác, dừng nó (Ctrl+C)

# Restart frontend
cd frontend
npm run dev
```

### Bước 2: Kiểm Tra Browser Console
1. Mở trình duyệt: http://localhost:5173/login
2. Mở DevTools (F12)
3. Xem tab Network
4. Thử login lại
5. Xem request thực tế đang gửi đến URL nào

### Bước 3: Test Backend Direct
Backend đang hoạt động tốt:
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"test@example.com","password":"password"}'
```

## Alternative: Use Full URL
Nếu proxy vẫn không hoạt động, có thể sửa store để dùng full URL:

```typescript
// In frontend/src/shared/auth/store.ts
const response = await apiClient.post('/auth/login', {
  email,
  password,
}, {
  baseURL: 'http://localhost:8000/api/v1'
});
```

## Debug Steps

1. **Check if frontend server is running**:
   ```bash
   curl http://localhost:5173
   ```

2. **Check if backend server is running**:
   ```bash
   curl http://localhost:8000/api/v1/auth/login -X POST \
     -H 'Content-Type: application/json' \
     -d '{"email":"test@example.com","password":"password"}'
   ```

3. **Check browser network tab**:
   - Open http://localhost:5173/login
   - F12 → Network tab
   - Try to login
   - See what URL the request goes to
   - Should be: `http://localhost:5173/api/v1/auth/login` (proxied)
   - Or: `http://localhost:8000/api/v1/auth/login` (if no proxy)

