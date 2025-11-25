# API Fixes Summary

## 🔍 Vấn Đề Đã Phát Hiện

### 1. Duplicate "api" trong URL
**Lỗi**: `/api/v1/api/projects` (duplicate "api")
**Nguyên nhân**: 
- API client có `baseURL: '/api/v1'`
- Code gọi `/api/projects` 
- Kết quả: `/api/v1` + `/api/projects` = `/api/v1/api/projects`

### 2. API Endpoint 500 Error
**Lỗi**: `/api/v1/dashboard/alerts` trả về 500
**Nguyên nhân**: Có thể do:
- Model `DashboardAlert` không tồn tại
- Database table chưa có
- Logic trong controller có vấn đề

## ✅ Fixes Đã Thực Hiện

### 1. Sửa Duplicate "api" trong Projects API

**File**: `frontend/src/entities/app/projects/api.ts`

**Changes**:
```typescript
// Before
apiClient.get(`/api/projects?${params.toString()}`);
apiClient.get(`/api/projects/${id}`);
apiClient.post('/api/projects', projectData);
apiClient.put(`/api/projects/${id}`, projectData);
apiClient.delete(`/api/projects/${id}`);

// After  
apiClient.get(`/projects?${params.toString()}`);
apiClient.get(`/projects/${id}`);
apiClient.post('/projects', projectData);
apiClient.put(`/projects/${id}`, projectData);
apiClient.delete(`/projects/${id}`);
```

**Kết quả**: URL bây giờ là `/api/v1/projects` (đúng format)

### 2. Cần Kiểm Tra Dashboard Alerts Endpoint

**Route**: `GET api/v1/dashboard/alerts`
**Controller**: `DashboardController@getUserAlerts`
**Status**: Route tồn tại nhưng có thể có vấn đề với:
- Database table `dashboard_alerts`
- Logic authentication
- Model relationships

## 📋 Next Steps

1. **Kiểm tra database table `dashboard_alerts`**
2. **Test lại API endpoints**:
   - `/api/v1/projects` - Should work now
   - `/api/v1/dashboard/alerts` - Cần debug
3. **Xem logs** để biết chi tiết lỗi 500

## 🧪 Testing

### Test Projects API
```bash
# Should return 200 OK
curl -X GET "http://localhost:8000/api/v1/projects" \
  -H "Authorization: Bearer {token}"

# Should NOT return 404 anymore
curl -X GET "http://localhost:8000/api/v1/api/projects"
```

### Test Dashboard Alerts API
```bash
# Check logs for error details
tail -f storage/logs/laravel.log

# Test endpoint
curl -X GET "http://localhost:8000/api/v1/dashboard/alerts" \
  -H "Authorization: Bearer {token}"
```

## 📝 Notes

- Duplicate "api" fix áp dụng cho tất cả projects API calls
- Dashboard alerts cần investigate thêm
- Frontend React app ở port 5173 cần restart để apply changes

---

**Status**: ✅ Duplicate "api" fixed, ⚠️ Dashboard alerts cần debug
**Date**: 2025-01-19

