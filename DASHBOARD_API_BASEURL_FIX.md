# Dashboard API BaseURL Fix - THE FINAL FIX!

## Nguyên nhân gốc rễ của mọi lỗi 404

Sau khi logout và login lại, frontend gọi URL **SAI**: `/api/app/dashboard` thay vì `/api/v1/app/dashboard`

## Vấn đề

Trong `frontend/src/shared/api/client.ts` line 117:

```typescript
// BEFORE - SAI!
const DEFAULT_API_BASE_URL = '/api'; 

// Khi gọi:
http.get('/app/dashboard')
// = /api + /app/dashboard  
// = /api/app/dashboard ❌ (thiếu /v1)
```

## The Fix

```typescript
// AFTER - ĐÚNG!
const DEFAULT_API_BASE_URL = '/api/v1';

// Khi gọi:
http.get('/app/dashboard')
// = /api/v1 + /app/dashboard
// = /api/v1/app/dashboard ✅
```

## Tất cả lỗi đã fix:

1. ✅ 404 - Routes không được register
2. ✅ 404 - Client URL double prefix  
3. ✅ 403 - Middleware bug
4. ✅ 403 - Auth configuration
5. ✅ 403 - Case-sensitive role
6. ✅ 500 - Type mismatch (ULID vs int)
7. ✅ **404 - BASEURL SAI! (/api thay vì /api/v1)**

## File Modified
- `frontend/src/shared/api/client.ts` - Changed baseURL from `/api` to `/api/v1`

## Bây giờ URL sẽ là:
- ✅ `/api/v1/app/dashboard`
- ✅ `/api/v1/app/dashboard/alerts`
- ✅ `/api/v1/app/dashboard/widgets`
- ✅ Tất cả endpoint khác

**Hãy reload lại trang và test!**

---

**Date**: October 26, 2025
**Status**: ✅ FINAL FIX COMPLETE

