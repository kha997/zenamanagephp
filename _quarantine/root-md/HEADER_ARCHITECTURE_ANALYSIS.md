# Phân Tích Kiến Trúc Header - ZenaManage

## 📊 Tổng Quan

**Ngày phân tích:** 2025-01-XX  
**Mục tiêu:** Đánh giá kiến trúc header hiện tại và thiết kế lại theo chuẩn

---

## 🔍 Hiện Trạng: Tìm Thấy 7+ Header Components

### 1. Blade Components (Backend/Laravel Views)

#### ✅ ACTIVE - Đang được sử dụng:

**1.1. `resources/views/components/shared/header-wrapper.blade.php`**
- **Mục đích:** Wrapper cho React HeaderShell component
- **Sử dụng trong:**
  - `resources/views/layouts/admin.blade.php` (line 129)
- **Cách hoạt động:**
  - Mount React HeaderShell vào `<div id="header-mount">`
  - Truyền data qua data attributes và JSON
  - Gọi `window.initHeader()` khi DOM ready
- **Props:**
  - `user`, `tenant`, `navigation`, `notifications`, `unreadCount`, `breadcrumbs`, `theme`, `variant`

**1.2. `resources/views/components/shared/header.blade.php`**
- **Mục đích:** Blade-only header (không dùng React)
- **Sử dụng trong:**
  - `resources/views/layouts/app.blade.php` (line 62)
- **Cách hoạt động:**
  - Pure Blade template với Alpine.js
  - Không có React dependency
  - Có notifications dropdown, user menu, logo
- **Props:**
  - `variant`, `user`, `tenant`, `navigation`, `notifications`, `unread-count`, `theme`, `breadcrumbs`

#### ❌ KHÔNG ACTIVE - Không được sử dụng:

**1.3. `resources/views/components/shared/header-standardized.blade.php`**
- **Trạng thái:** Không tìm thấy file (có thể đã bị xóa)
- **Lý do:** Không được sử dụng trong layouts

**1.4. `resources/views/components/shared/simple-header.blade.php`**
- **Trạng thái:** Không tìm thấy file (có thể đã bị xóa)
- **Lý do:** Không được sử dụng trong layouts

**1.5. `resources/views/components/admin/header.blade.php`**
- **Trạng thái:** Không tìm thấy file (có thể đã bị xóa)
- **Lý do:** Admin đã dùng `header-wrapper` thay thế

### 2. React Components (Frontend)

#### ✅ ACTIVE - Đang được sử dụng:

**2.1. `src/components/ui/header/HeaderShell.tsx`**
- **Mục đích:** React HeaderShell component (cho Blade wrapper)
- **Props:**
  - `theme`, `size`, `sticky`, `condensedOnScroll`, `withBorder`
  - `logo`, `primaryNav`, `secondaryActions`, `userMenu`, `notifications`, `breadcrumbs`
- **Tính năng:**
  - Sticky header
  - Condensed on scroll
  - Mobile hamburger menu
  - Mobile sheet overlay
  - Theme support

**2.2. `frontend/src/components/layout/HeaderShell.tsx`**
- **Mục đích:** React HeaderShell cho frontend React routes
- **Tính năng:**
  - RBAC integration
  - Theme toggle (light/dark/system)
  - Global search với debounce
  - Notifications với unread count
  - User profile menu
  - Mobile hamburger menu
  - Breadcrumbs
  - Full accessibility support
- **Props:**
  - `navigation`, `breadcrumbs`, `notifications`, `unreadCount`, `tenantName`, `showSearch`, `onSearch`, `onNotificationClick`, `onSettingsClick`, `onLogout`

#### ⚠️ LEGACY - Có thể không cần thiết:

**2.3. `frontend/src/components/layout/Header.tsx`**
- **Mục đích:** Legacy header component
- **Tính năng:** Đơn giản, chỉ có welcome message, notifications, user menu
- **Trạng thái:** Có thể được thay thế bởi HeaderShell

### 3. Service Layer

**3.1. `app/Services/HeaderService.php`**
- **Mục đích:** Cung cấp data cho header components
- **Methods:**
  - `getNavigation(User $user, string $context = 'app')` - Trả về navigation menu theo context
  - `getNotifications(User $user)` - Trả về notifications
  - `getUnreadCount(User $user)` - Trả về số unread notifications
  - `getBreadcrumbs(string $routeName, array $params = [])` - Trả về breadcrumbs
  - `getUserTheme(User $user)` - Trả về theme preference
  - `setUserTheme(User $user, string $theme)` - Set theme preference
  - `getAlertCount(User $user)` - Trả về số alerts (admin only)

---

## 🏗️ Kiến Trúc Hiện Tại

### Flow Diagram:

```
┌─────────────────────────────────────────────────────────────┐
│                    Laravel Blade Views                      │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  admin.blade.php                                            │
│  └─> <x-shared.header-wrapper variant="admin" ...>         │
│      └─> Mount React HeaderShell (src/components/ui/...)   │
│                                                             │
│  app.blade.php                                              │
│  └─> <x-shared.header variant="app" ...>                   │
│      └─> Pure Blade template (Alpine.js)                   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    HeaderService                            │
│  - getNavigation(user, context)                             │
│  - getNotifications(user)                                   │
│  - getUnreadCount(user)                                     │
│  - getBreadcrumbs(route)                                    │
│  - getUserTheme(user)                                       │
└─────────────────────────────────────────────────────────────┘
```

### Vấn Đề Hiện Tại:

1. **❌ Vi phạm Rules:** 
   - Theo rules, chỉ nên có 1 header component: `header-wrapper.blade.php` với variant prop
   - Nhưng hiện tại có 2 header components đang active:
     - `header-wrapper.blade.php` (admin)
     - `header.blade.php` (app)

2. **❌ Inconsistency:**
   - Admin dùng React HeaderShell
   - App dùng Blade-only header
   - Không nhất quán giữa admin và app

3. **❌ Duplicate Code:**
   - Có 2 React HeaderShell components khác nhau:
     - `src/components/ui/header/HeaderShell.tsx`
     - `frontend/src/components/layout/HeaderShell.tsx`
   - Cả 2 đều có tính năng tương tự nhưng khác implementation

4. **❌ Complexity:**
   - `header-wrapper.blade.php` mount React component phức tạp
   - `header.blade.php` là pure Blade nhưng không có đầy đủ tính năng như React version

---

## ✅ Thiết Kế Lại Header Chuẩn

### Nguyên Tắc:

1. **Single Source of Truth:** Chỉ 1 header component duy nhất
2. **Variant-based:** Dùng `variant` prop để phân biệt admin/app
3. **Consistent:** Cùng một component cho cả admin và app
4. **Service-driven:** Navigation và data từ HeaderService

### Kiến Trúc Mới:

```
┌─────────────────────────────────────────────────────────────┐
│              header-wrapper.blade.php                       │
│              (DUY NHẤT - Single Source of Truth)            │
│                                                             │
│  Props:                                                      │
│  - variant: "admin" | "app"                                 │
│  - user, tenant, navigation, notifications, etc.            │
│                                                             │
│  Logic:                                                      │
│  - Nếu variant="admin" → HeaderService.getNavigation(admin) │
│  - Nếu variant="app" → HeaderService.getNavigation(app)     │
│  - Render React HeaderShell hoặc Blade template             │
│                                                             │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│              HeaderService                                  │
│  - getNavigation(user, context) → [admin|app]              │
│  - getNotifications(user)                                    │
│  - getUnreadCount(user)                                     │
│  - getBreadcrumbs(route)                                    │
│  - getUserTheme(user)                                       │
└─────────────────────────────────────────────────────────────┘
```

### Implementation Plan:

#### Phase 1: Thống nhất header-wrapper
- [ ] Cập nhật `header-wrapper.blade.php` để support cả admin và app
- [ ] Cập nhật `app.blade.php` để dùng `header-wrapper` thay vì `header.blade.php`
- [ ] Đảm bảo `header-wrapper` có đầy đủ tính năng như `header.blade.php` hiện tại

#### Phase 2: Tối ưu React HeaderShell
- [ ] Chọn 1 React HeaderShell component (merge hoặc chọn best one)
- [ ] Đảm bảo React HeaderShell support đầy đủ tính năng:
  - Theme toggle
  - Notifications
  - User menu
  - Mobile menu
  - Breadcrumbs
  - Search (optional)

#### Phase 3: Cleanup
- [ ] Xóa `header.blade.php` (nếu không cần thiết)
- [ ] Xóa các header components không sử dụng
- [ ] Update documentation

#### Phase 4: Testing
- [ ] Test header trên admin routes
- [ ] Test header trên app routes
- [ ] Test responsive (mobile/desktop)
- [ ] Test theme toggle
- [ ] Test notifications
- [ ] Test user menu

---

## 📋 Checklist Trước Khi Thiết Kế Lại

- [ ] Xác nhận tất cả layouts đang dùng header nào
- [ ] Liệt kê tất cả tính năng của `header.blade.php` hiện tại
- [ ] Liệt kê tất cả tính năng của `header-wrapper.blade.php` hiện tại
- [ ] So sánh 2 React HeaderShell components
- [ ] Xác định tính năng nào cần giữ lại
- [ ] Xác định tính năng nào có thể bỏ
- [ ] Plan migration path (không break existing functionality)

---

## 🎯 Kết Luận

**Hiện tại:**
- 2 header components đang active (vi phạm rules)
- Inconsistency giữa admin và app
- Duplicate code

**Mục tiêu:**
- 1 header component duy nhất (`header-wrapper.blade.php`)
- Variant-based approach
- Consistent experience
- Full feature parity

**Next Steps:**
1. Thiết kế lại `header-wrapper.blade.php` để support cả admin và app
2. Migrate `app.blade.php` sang dùng `header-wrapper`
3. Cleanup các header components không cần thiết
4. Test thoroughly

