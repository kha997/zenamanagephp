# API v1 App Prefix Fix Summary

## 🔍 Vấn Đề

Frontend đang gọi `/api/v1/projects` nhưng backend route thực tế là `/api/v1/app/projects` (có `/app` prefix).

### Routes Backend:
```
GET|HEAD api/v1/app/projects ... projects.index
GET|HEAD api/projects ......... Unified\ProjectManagementController@getProjects
```

### Frontend Đang Gọi:
```
GET /api/v1/projects → 404 Not Found
```

### Frontend Should Call:
```
GET /api/v1/app/projects → 200 OK
```

## ✅ Fix Applied

### File: `frontend/src/entities/app/projects/api.ts`

**Changes**: Thêm `/app` prefix vào tất cả project API calls:

```typescript
// Before
apiClient.get(`/projects?${params.toString()}`);
apiClient.get(`/projects/${id}`);
apiClient.post('/projects', projectData);
apiClient.put(`/projects/${id}`, projectData);
apiClient.delete(`/projects/${id}`);
apiClient.get(`/projects/${id}/stats`);
apiClient.post(`/projects/${projectId}/team-members`, {...});
apiClient.delete(`/projects/${projectId}/team-members/${userId}`);

// After  
apiClient.get(`/app/projects?${params.toString()}`);
apiClient.get(`/app/projects/${id}`);
apiClient.post('/app/projects', projectData);
apiClient.put(`/app/projects/${id}`, projectData);
apiClient.delete(`/app/projects/${id}`);
apiClient.get(`/app/projects/${id}/stats`);
apiClient.post(`/app/projects/${projectId}/team-members`, {...});
apiClient.delete(`/app/projects/${projectId}/team-members/${userId}`);
```

## 📋 API v1 Routes Structure

API v1 sử dụng prefix `/app` cho tất cả tenant-scoped resources:

```
/api/v1/app/projects     - Projects (tenant-scoped)
/api/v1/app/tasks        - Tasks (tenant-scoped)
/api/v1/app/clients      - Clients (tenant-scoped)
/api/v1/app/quotes       - Quotes (tenant-scoped)
/api/v1/app/dashboard    - Dashboard (tenant-scoped)
```

Prefix `/app` cho biết đây là **App API** (tenant-scoped) chứ không phải Admin API (system-wide).

## 🔧 Architecture

```
apiClient (baseURL: /api/v1)
  ↓
/api/v1 + /app/projects
  ↓
/api/v1/app/projects ✅
```

## ✅ Testing

### Before Fix:
```
GET http://localhost:5173/api/v1/projects
→ Proxy: http://localhost:8000/api/v1/projects
→ 404 Not Found ❌
```

### After Fix:
```
GET http://localhost:5173/api/v1/app/projects
→ Proxy: http://localhost:8000/api/v1/app/projects  
→ 200 OK ✅
```

## 🎯 Summary

- **Root Cause**: Missing `/app` prefix in API calls
- **Solution**: Add `/app` prefix to all project endpoints
- **Files Changed**: `frontend/src/entities/app/projects/api.ts` (9 endpoints)
- **Routes Matched**: Now calling correct `/api/v1/app/projects` endpoint

---

**Status**: ✅ Fixed
**Date**: 2025-01-19

