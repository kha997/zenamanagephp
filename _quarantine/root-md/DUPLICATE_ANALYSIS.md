# PHÂN TÍCH DUPLICATE FILES TRONG FRONTEND

## Nguyên nhân có duplicate:

Sau khi làm mới frontend, có **2 hệ thống routing/layout** tồn tại song song:

### HỆ THỐNG MỚI (ĐANG ĐƯỢC SỬ DỤNG):
- **Entry Point:** `src/main.tsx` → `AppShell` → `router` từ `app/router.tsx`
- **Router:** `app/router.tsx` (React Router v6+ với `createBrowserRouter`)
- **Layouts:**
  - `app/layouts/MainLayout.tsx` (cho `/app/*`)
  - `app/layouts/AdminLayout.tsx` (cho `/admin/*`)
- **Navigator:** `components/layout/PrimaryNavigator.tsx`

### HỆ THỐNG CŨ (KHÔNG ĐƯỢC SỬ DỤNG):
- **Entry Point:** `src/App.tsx` (không được import ở đâu cả)
- **Router:** `src/routes/index.tsx` (không được dùng)
- **Layouts:**
  - `src/components/Layout.tsx` (chỉ được import trong `App.tsx`)
  - `src/components/layout/Header.tsx` (không được import)
  - `src/layouts/AppLayout.tsx` (có thể không được dùng)
  - `src/layouts/AdminLayout.tsx` (duplicate với `app/layouts/AdminLayout.tsx`)

## FILES CẦN XÓA:

### 1. Files hoàn toàn không được sử dụng:
- ✅ `src/App.tsx` - Không được import
- ✅ `src/components/Layout.tsx` - Chỉ được import trong App.tsx (không dùng)
- ✅ `src/components/layout/Header.tsx` - Không được import
- ✅ `src/layouts/AdminLayout.tsx` - Duplicate với `app/layouts/AdminLayout.tsx`

### 2. Files cần kiểm tra:
- ⚠️ `src/routes/index.tsx` - Export `AppRoutes()` nhưng không được import
- ⚠️ `src/layouts/AppLayout.tsx` - Cần kiểm tra có được import không

## GIẢI PHÁP:

1. Xóa các file không được sử dụng
2. Kiểm tra và xóa các file duplicate
3. Đảm bảo chỉ có một hệ thống routing/layout duy nhất

