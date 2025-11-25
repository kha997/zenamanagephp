# API Base URL Fix

## 🔍 Vấn Đề

Frontend đang gọi `/api/v1/projects` nhưng backend Laravel có route là `/api/projects` (không có v1).

### Routes Backend Hiện Tại:
```
GET|HEAD        api/projects .................................................................... Unified\ProjectManagementController@getProjects
GET|HEAD        api/v1/app/projects ... projects.index › Unified\ProjectManagementController@getProjects
```

### Frontend Đang Gọi:
```
GET /api/v1/projects → 404 Not Found
```

## ✅ Fix Applied

### File: `frontend/src/shared/api/client.ts`
**Change:**
```typescript
// Before
const DEFAULT_API_BASE_URL = '/api/v1';

// After
const DEFAULT_API_BASE_URL = '/api';
```

**Kết quả**: URL bây giờ sẽ là `/api/projects` (match với backend route)

## 📋 Testing

### Before Fix:
```
GET http://localhost:5173/api/v1/projects?page=1&per_page=12
→ Proxy to: http://localhost:8000/api/v1/projects
→ 404 Not Found
```

### After Fix:
```
GET http://localhost:5173/api/projects?page=1&per_page=12
→ Proxy to: http://localhost:8000/api/projects
→ 200 OK (hopefully)
```

## 🔄 Next Steps

1. **Restart Vite dev server** để apply changes:
   ```bash
   npm run dev
   ```

2. **Verify** routes match:
   - Frontend calls: `/api/projects`
   - Backend route: `api/projects` ✅

3. **Test** endpoint với curl:
   ```bash
   curl http://localhost:8000/api/projects \
     -H "Authorization: Bearer {token}"
   ```

## ⚠️ Note

Nếu sau này muốn migrate sang v1 API:
- Backend: Add routes with v1 prefix
- Frontend: Change baseURL back to `/api/v1`

---

**Status**: ✅ Fixed
**Date**: 2025-01-19

