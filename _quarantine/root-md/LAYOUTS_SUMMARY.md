# 📋 TỔNG HỢP LAYOUTS TRONG ZENAMANAGE

**Ngày cập nhật**: 2025-01-19

---

## 📊 TỔNG QUAN

- **Main Layouts**: 7 files
- **Layout Components**: 2 files  
- **Email Layouts**: 1 file
- **Tổng cộng**: 10 layout files

---

## 📁 MAIN LAYOUTS (`resources/views/layouts/`)

### 1. **`app.blade.php`** ✅ **ACTIVE - PRIMARY APP LAYOUT**
- **Mục đích**: Layout chính cho ứng dụng tenant-scoped (`/app/*`)
- **Sử dụng**: Hầu hết các trang app (`@extends('layouts.app')`)
- **Features**:
  - Header với `x-shared.header-wrapper`
  - Primary Navigator
  - KPI Strip
  - Alert Bar
  - Main Content
  - Activity Panel
  - Alpine.js CDN (3.13.5)
  - Chart.js integration
  - Vite assets (`resources/css/app.css`, `resources/js/app.js`)

**Sử dụng bởi**: ~70+ views
- `app/dashboard/index.blade.php`
- `app/projects/*.blade.php`
- `app/tasks/*.blade.php`
- `app/clients/*.blade.php`
- `app/templates/*.blade.php`
- `app/reports/index.blade.php`
- `app/calendar/index.blade.php`
- và nhiều pages khác...

---

### 2. **`admin.blade.php`** ✅ **ACTIVE - ADMIN LAYOUT**
- **Mục đích**: Layout cho admin dashboard (`/admin/*`)
- **Sử dụng**: Tất cả admin pages (`@extends('layouts.admin')`)
- **Features**:
  - Tailwind CDN (production)
  - Alpine.js CDN (3.13.3)
  - Chart.js integration
  - Custom CSS files (page-refresh, loading-states, ui-loading, dashboard-enhanced, tenants-enhanced)
  - Custom JS files (tenants/* performance scripts)

**Sử dụng bởi**: ~30+ admin views
- `admin/dashboard/index.blade.php`
- `admin/users/*.blade.php`
- `admin/tenants/*.blade.php`
- `admin/security/*.blade.php`
- `admin/analytics/index.blade.php`
- `admin/billing/*.blade.php`
- và nhiều admin pages khác...

---

### 3. **`auth-layout.blade.php`** ✅ **ACTIVE - AUTH LAYOUT**
- **Mục đích**: Layout cho authentication pages (login, register, password reset)
- **Sử dụng**: Auth pages (`@extends('layouts.auth-layout')`)
- **Features**:
  - Vite assets (`resources/css/app.css`, `resources/js/app.js`)
  - Minimal design (chỉ content area)
  - Fonts (Bunny.net Figtree)

**Sử dụng bởi**: ~5 views
- `auth/login.blade.php`
- `auth/register.blade.php`
- `auth/verify-email.blade.php`
- `auth/passwords/reset.blade.php`
- `auth/passwords/email.blade.php`

---

### 4. **`auth.blade.php`** ⚠️ **LEGACY/DEPRECATED**
- **Mục đích**: Legacy auth layout (có thể duplicate với `auth-layout.blade.php`)
- **Sử dụng**: Không rõ (có thể không còn được sử dụng)
- **Features**:
  - Tailwind CDN
  - Alpine.js CDN (unpkg)
  - Font Awesome
  - Custom button/form styles

**Cần kiểm tra**: Có thể merge vào `auth-layout.blade.php` hoặc remove nếu không dùng

---

### 5. **`navigation.blade.php`** ⚠️ **PARTIAL/COMPONENT**
- **Mục đích**: Không phải full layout, chỉ là navigation component
- **Sử dụng**: Có thể được include trong các layout khác
- **Features**:
  - Enhanced Navigation bar
  - Logo
  - Navigation links (Dashboard, Projects, Tasks, Calendar, Team, Documents, Templates, Settings)
  - Search box
  - User menu

**Ghi chú**: File này là partial, không phải full layout template

---

### 6. **`simple-layout.blade.php`** ⚠️ **MINIMAL/UTILITY**
- **Mục đích**: Minimal layout cho testing hoặc simple pages
- **Sử dụng**: Có thể cho test pages hoặc utility pages
- **Features**:
  - Tailwind CDN
  - Alpine.js CDN
  - Minimal structure

**Cần kiểm tra**: Có thể remove nếu không còn được sử dụng

---

### 7. **`no-nav-layout.blade.php`** ⚠️ **UTILITY**
- **Mục đích**: Layout không có navigation (cho embedded pages hoặc modals)
- **Sử dụng**: Có thể cho embedded content hoặc standalone pages
- **Features**:
  - Tailwind CDN
  - Alpine.js CDN
  - No navigation bar

**Cần kiểm tra**: Có thể remove nếu không còn được sử dụng

---

## 📦 LAYOUT COMPONENTS (`resources/views/components/shared/`)

### 1. **`layout-wrapper.blade.php`**
- **Mục đích**: Wrapper component cho universal page frame
- **Features**:
  - Alpine.js component (`layoutWrapperComponent`)
  - Universal frame structure
  - Header, Navigation, Content, Activity sections

---

### 2. **`mobile-page-layout.blade.php`**
- **Mục đích**: Mobile-optimized page layout component
- **Features**:
  - Mobile-first design
  - Responsive layout
  - Mobile navigation patterns

---

## 📧 EMAIL LAYOUTS (`resources/views/emails/`)

### 1. **`layout.blade.php`**
- **Mục đích**: Base layout cho email templates
- **Sử dụng**: Tất cả email templates (`@extends('emails.layout')`)
- **Features**:
  - Email-safe HTML structure
  - Responsive email design
  - Consistent branding

**Sử dụng bởi**: ~5 email templates
- `emails/welcome.blade.php`
- `emails/invitation.blade.php`
- `emails/client-created.blade.php`
- `emails/quote-sent.blade.php`
- `emails/task-completed.blade.php`

---

## 🎯 RECOMMENDATIONS

### ✅ **KEEP (Active Usage)**

1. **`app.blade.php`** - Primary app layout (70+ views)
2. **`admin.blade.php`** - Admin layout (30+ views)
3. **`auth-layout.blade.php`** - Auth layout (5+ views)
4. **`emails/layout.blade.php`** - Email layout (5+ templates)

### ⚠️ **REVIEW/CLEANUP**

1. **`auth.blade.php`** - Kiểm tra có duplicate với `auth-layout.blade.php` không
2. **`simple-layout.blade.php`** - Kiểm tra có còn được sử dụng không
3. **`no-nav-layout.blade.php`** - Kiểm tra có còn được sử dụng không
4. **`navigation.blade.php`** - Không phải layout, chỉ là partial component

### 🔄 **CONSOLIDATION OPPORTUNITIES**

1. **Merge `auth.blade.php` vào `auth-layout.blade.php`** nếu không còn dùng
2. **Remove unused layouts** (`simple-layout`, `no-nav-layout`) nếu không còn references
3. **Move `navigation.blade.php`** vào `resources/views/components/shared/navigation/` nếu là partial

---

## 📊 STATISTICS

| Layout Type | Count | Active Usage |
|------------|-------|--------------|
| Main Layouts | 7 | 3-4 active |
| Layout Components | 2 | 2 active |
| Email Layouts | 1 | 1 active |
| **Total** | **10** | **6-7 active** |

---

## 🔍 NEXT STEPS

1. ✅ **Audit unused layouts**: Check grep results for `@extends('layouts.*')` references
2. ✅ **Consolidate auth layouts**: Merge `auth.blade.php` nếu duplicate
3. ✅ **Remove unused layouts**: Clean up `simple-layout`, `no-nav-layout` nếu không dùng
4. ✅ **Document layout purposes**: Update PROJECT_RULES.md với layout architecture

---

**Status**: ✅ **DOCUMENTED**

**Last Updated**: 2025-01-19

