# 📊 SƠ ĐỒ TỔNG QUAN TẤT CẢ CÁC TRANG CỦA HỆ THỐNG ZENAMANAGE

## 🎯 Tổng quan

Hệ thống ZenaManage được tổ chức thành **3 khu vực chính**:
1. **Frontend React (SPA)** - `/frontend/src/app/router.tsx`
2. **Backend Laravel Web Routes** - `/routes/app.php`, `/routes/web.php`, `/routes/admin.php`
3. **API Routes** - `/routes/api.php`, `/routes/api_v1.php`

---

## 🔐 1. AUTHENTICATION ROUTES (Không yêu cầu đăng nhập)

```
┌─────────────────────────────────────────────────┐
│  AUTHENTICATION PAGES                             │
├─────────────────────────────────────────────────┤
│  /login              → LoginPage.tsx             │
│  /forgot-password    → ForgotPasswordPage.tsx   │
│  /reset-password     → ResetPasswordPage.tsx    │
│  /register           → RegisterPage.tsx         │
└─────────────────────────────────────────────────┘
```

**Frontend React Routes:**
- `/login` - `pages/auth/LoginPage.tsx`
- `/forgot-password` - `pages/auth/ForgotPasswordPage.tsx`
- `/reset-password` - `pages/auth/ResetPasswordPage.tsx`

**Backend Laravel Routes:**
- `/login` - `web.php` → `LoginController@showLoginForm`
- `/register` - `web.php` → `RegisterController@showRegistrationForm`
- `/password/reset` - `web.php` → `PasswordResetController`

---

## 📱 2. APP ROUTES (/app/*) - Tenant-scoped, yêu cầu Authentication

### 2.1 Frontend React Routes (Active - SPA)

```
/app
├── /dashboard              → DashboardPage.tsx
├── /projects               → ProjectsListPage.tsx
├── /projects/:id           → ProjectDetailPage.tsx
├── /tasks                  → TasksPage.tsx
├── /documents              → DocumentsPage.tsx
├── /documents/:id          → DocumentDetailPage.tsx
├── /team                   → TeamPage.tsx
├── /calendar               → CalendarPage.tsx
├── /settings               → SettingsPage.tsx
├── /alerts                 → AlertsPage.tsx
└── /preferences            → PreferencesPage.tsx
```

### 2.2 Backend Laravel Routes (Blade Templates - Legacy/Backup)

```
/app
├── /dashboard              → DashboardController@index
│                           → view: app.dashboard.index
│
├── /tasks                  → TaskController@index
│   ├── /kanban            → TaskController@kanban
│   ├── /create            → TaskController@create
│   ├── /:id               → TaskController@show
│   └── /:id/edit          → TaskController@edit
│
├── /documents              → DocumentsController@index
│
├── /clients                → ClientController@index
│   ├── /create            → ClientController@create
│   ├── /:id               → ClientController@show
│   └── /:id/edit          → ClientController@edit
│
├── /quotes                 → QuoteController@index
│
├── /templates              → TemplateController@index
│   ├── /library           → TemplateController@library
│   ├── /builder           → TemplateController@builder
│   └── /:id               → TemplateController@show
│
├── /team                   → TeamController@index
├── /calendar               → view: app.calendar.index
└── /settings               → view: app.settings.index
```

### 2.3 Chi tiết từng trang

#### 📊 Dashboard
- **Frontend:** `pages/dashboard/DashboardPage.tsx`
- **Backend:** `routes/app.php` → `DashboardController@index`
- **Route:** `/app/dashboard`
- **Access:** Tất cả users đã đăng nhập

#### 📁 Projects
- **Frontend:** `pages/projects/ProjectsListPage.tsx`, `ProjectDetailPage.tsx`
- **Backend:** DISABLED (Đang dùng React Frontend)
- **Routes:**
  - `/app/projects` - Danh sách projects
  - `/app/projects/:id` - Chi tiết project

#### ✅ Tasks
- **Frontend:** `pages/TasksPage.tsx`
- **Backend:** `routes/app.php` → `TaskController`
- **Routes:**
  - `/app/tasks` - Danh sách tasks
  - `/app/tasks/kanban` - Kanban board
  - `/app/tasks/create` - Tạo task mới
  - `/app/tasks/:id` - Chi tiết task
  - `/app/tasks/:id/edit` - Chỉnh sửa task

#### 📄 Documents
- **Frontend:** `pages/documents/DocumentsPage.tsx`, `DocumentDetailPage.tsx`
- **Backend:** `routes/app.php` → `DocumentsController@index`
- **Routes:**
  - `/app/documents` - Danh sách documents
  - `/app/documents/:id` - Chi tiết document

#### 👥 Team
- **Frontend:** `pages/TeamPage.tsx`
- **Backend:** `routes/app.php` → `TeamController@index`
- **Route:** `/app/team`

#### 📅 Calendar
- **Frontend:** `pages/CalendarPage.tsx`
- **Backend:** `routes/app.php` → view `app.calendar.index`
- **Route:** `/app/calendar`

#### ⚙️ Settings
- **Frontend:** `pages/SettingsPage.tsx`
- **Backend:** `routes/app.php` → view `app.settings.index`
- **Route:** `/app/settings`

#### 🔔 Alerts
- **Frontend:** `pages/alerts/AlertsPage.tsx`
- **Route:** `/app/alerts`

#### ⚙️ Preferences
- **Frontend:** `pages/preferences/PreferencesPage.tsx`
- **Route:** `/app/preferences`

---

## 👑 3. ADMIN ROUTES (/admin/*) - System-wide, yêu cầu Admin Role

### 3.1 Frontend React Routes (Active - SPA)

```
/admin
├── /dashboard              → AdminDashboardPage.tsx
├── /users                  → AdminUsersPage.tsx
├── /roles                  → AdminRolesPage.tsx
└── /tenants                → AdminTenantsPage.tsx
```

### 3.2 Backend Laravel Routes (Blade Templates)

```
/admin
├── /dashboard              → AdminDashboardController@index
├── /users                  → AdminUsersController@index
│   ├── /create            → view: admin.users.create
│   ├── /debug             → AdminUsersController@debug
│   └── /test-component    → AdminUsersController@testComponent
│
├── /tenants                → AdminTenantsController@index
│   └── /create            → view: admin.tenants.create
│
├── /projects               → view: admin.projects.index
│   └── /create             → view: admin.projects.create
│
├── /security               → view: admin.security.index
│   └── /scan              → view: admin.security.scan
│
├── /alerts                 → view: admin.alerts.index
├── /activities             → view: admin.activities.index
├── /analytics              → view: admin.analytics.index
├── /maintenance            → view: admin.maintenance.index
│   └── /backup            → view: admin.maintenance.backup
│
├── /settings               → view: admin.settings.index
├── /profile                → view: admin.profile
└── /performance            → PerformanceController
    ├── /metrics           → PerformanceController@getDashboard
    └── /logs              → PerformanceController@getRealTimeMetrics
```

### 3.3 Chi tiết từng trang Admin

#### 📊 Admin Dashboard
- **Frontend:** `pages/admin/DashboardPage.tsx`
- **Backend:** `routes/web.php` → `AdminDashboardController@index`
- **Route:** `/admin/dashboard`
- **Access:** Admin, SuperAdmin only

#### 👤 Admin Users
- **Frontend:** `pages/admin/UsersPage.tsx`
- **Backend:** `routes/web.php` → `AdminUsersController@index`
- **Routes:**
  - `/admin/users` - Danh sách users
  - `/admin/users/create` - Tạo user mới
  - `/admin/users/debug` - Debug endpoint
  - `/admin/users/test-component` - Test component endpoint

#### 🔐 Admin Roles
- **Frontend:** `pages/admin/RolesPage.tsx`
- **Route:** `/admin/roles`
- **Access:** Admin, SuperAdmin only

#### 🏢 Admin Tenants
- **Frontend:** `pages/admin/TenantsPage.tsx`
- **Backend:** `routes/web.php` → `AdminTenantsController@index`
- **Routes:**
  - `/admin/tenants` - Danh sách tenants
  - `/admin/tenants/create` - Tạo tenant mới

---

## 🎭 4. ROLE-BASED DASHBOARDS (Frontend React - Alternate Routes)

Các dashboard theo role được định nghĩa trong `routes/index.tsx`:

```
/
├── /dashboard              → Dashboard (default)
├── /admin/dashboard        → Dashboard (Admin/SuperAdmin only)
├── /pm/dashboard           → PmDashboard (PM only)
├── /designer/dashboard     → DesignerDashboard (Designer only)
├── /site-engineer/dashboard → SiteEngineerDashboard (SiteEngineer only)
├── /qc/dashboard           → QcDashboard (QC only)
├── /procurement/dashboard   → ProcurementDashboard (Procurement only)
├── /finance/dashboard      → FinanceDashboard (Finance only)
└── /client/dashboard       → ClientDashboard (Client only)
```

**Component Files:**
- `pages/dashboard/DashboardPage.tsx`
- `pages/dashboard/PmDashboard.tsx`
- `pages/dashboard/DesignerDashboard.tsx`
- `pages/dashboard/SiteEngineerDashboard.tsx`
- `pages/dashboard/QcDashboard.tsx`
- `pages/dashboard/ProcurementDashboard.tsx`
- `pages/dashboard/FinanceDashboard.tsx`
- `pages/dashboard/ClientDashboard.tsx`

---

## 🔧 5. OTHER FEATURES (Frontend React)

### Change Requests
- `/app/change-requests` → `pages/ChangeRequests/CRListPage.tsx`
- `/app/change-requests/create` → `pages/ChangeRequests/CRCreatePage.tsx`
- `/app/change-requests/:id` → `pages/ChangeRequests/CRDetailPage.tsx`

### Reports & Analytics
- `/app/reports` → `pages/reports/ReportsPage.tsx`
- `/app/analytics` → `pages/AnalyticsPage.tsx`

### Gantt Chart
- `/app/gantt` → `pages/GanttChartPage.tsx`

### QC Module
- `/app/qc` → `pages/QCModulePage.tsx`

### Components Library
- `/app/components` → `pages/components/ComponentsListPage.tsx`

### Profile & Users
- `/app/profile` → `pages/ProfilePage.tsx`
- `/app/users` → `pages/UsersPage.tsx`
- `/app/users/:id` → `pages/UserDetailPage.tsx`
- `/app/users/new` → `pages/CreateUserPage.tsx`

---

## 🧪 6. TEST & DEBUG ROUTES (Development Only)

```
/_debug/*              → routes/debug.php
/test/*                → Test routes
/sandbox/*             → Sandbox routes for E2E testing
/demo/*                → Component demos (local/testing only)
```

**Test Routes trong web.php:**
- `/test/login` - Auto login với email query param
- `/test/tasks/{id}` - Test task view page
- `/test-simple-task/{id}` - Simple test task page
- `/sandbox/task-view/{task}` - Sandbox task view
- `/sandbox/tasks-list` - Sandbox tasks list
- `/sandbox/kanban` - Sandbox kanban board
- `/debug/tasks-create` - Debug task creation
- `/debug/dropdown-test` - Debug dropdown
- `/test-kanban` - Test kanban (no auth)
- `/test-tasks` - Test tasks list (no auth)

---

## 📋 7. SUMMARY TABLE

### Frontend React Pages (Active SPA)

| Route | Component | Access | Status |
|-------|-----------|--------|--------|
| `/login` | `LoginPage.tsx` | Public | ✅ Active |
| `/app/dashboard` | `DashboardPage.tsx` | Authenticated | ✅ Active |
| `/app/projects` | `ProjectsListPage.tsx` | Authenticated | ✅ Active |
| `/app/projects/:id` | `ProjectDetailPage.tsx` | Authenticated | ✅ Active |
| `/app/tasks` | `TasksPage.tsx` | Authenticated | ✅ Active |
| `/app/documents` | `DocumentsPage.tsx` | Authenticated | ✅ Active |
| `/app/documents/:id` | `DocumentDetailPage.tsx` | Authenticated | ✅ Active |
| `/app/team` | `TeamPage.tsx` | Authenticated | ✅ Active |
| `/app/calendar` | `CalendarPage.tsx` | Authenticated | ✅ Active |
| `/app/settings` | `SettingsPage.tsx` | Authenticated | ✅ Active |
| `/app/alerts` | `AlertsPage.tsx` | Authenticated | ✅ Active |
| `/app/preferences` | `PreferencesPage.tsx` | Authenticated | ✅ Active |
| `/admin/dashboard` | `AdminDashboardPage.tsx` | Admin/SuperAdmin | ✅ Active |
| `/admin/users` | `AdminUsersPage.tsx` | Admin/SuperAdmin | ✅ Active |
| `/admin/roles` | `AdminRolesPage.tsx` | Admin/SuperAdmin | ✅ Active |
| `/admin/tenants` | `AdminTenantsPage.tsx` | Admin/SuperAdmin | ✅ Active |

### Backend Laravel Pages (Blade Templates)

| Route | Controller | View | Access | Status |
|-------|-----------|------|--------|--------|
| `/app/dashboard` | `DashboardController@index` | `app.dashboard.index` | Authenticated | ✅ Active |
| `/app/tasks` | `TaskController@index` | `app.tasks.index` | Authenticated | ✅ Active |
| `/app/tasks/:id` | `TaskController@show` | `app.tasks.show` | Authenticated | ✅ Active |
| `/app/team` | `TeamController@index` | `app.team.index` | Authenticated | ✅ Active |
| `/app/calendar` | Closure | `app.calendar.index` | Authenticated | ✅ Active |
| `/app/settings` | Closure | `app.settings.index` | Authenticated | ✅ Active |
| `/admin/dashboard` | `AdminDashboardController@index` | `admin.dashboard.index` | Admin Only | ✅ Active |
| `/admin/users` | `AdminUsersController@index` | `admin.users.index` | Admin Only | ✅ Active |
| `/admin/tenants` | `AdminTenantsController@index` | `admin.tenants.index` | Admin Only | ✅ Active |

---

## 🗺️ 8. ROUTE ARCHITECTURE DIAGRAM

```
                    ┌─────────────────┐
                    │   ROOT (/)      │
                    │   React App     │
                    └────────┬────────┘
                             │
        ┌────────────────────┼────────────────────┐
        │                    │                    │
   ┌────▼────┐         ┌────▼────┐         ┌────▼────┐
   │  PUBLIC │         │   APP   │         │  ADMIN  │
   │ ROUTES  │         │ ROUTES  │         │ ROUTES  │
   └─────────┘         └─────────┘         └─────────┘
        │                    │                    │
        │                    │                    │
   /login            /app/dashboard        /admin/dashboard
   /register        /app/projects         /admin/users
   /forgot-password  /app/tasks            /admin/roles
   /reset-password   /app/documents        /admin/tenants
                     /app/team
                     /app/calendar
                     /app/settings
```

---

## 🔍 9. PHÂN TÍCH VÀ ĐÁNH GIÁ

### ✅ Điểm mạnh
1. **Tách biệt rõ ràng:** Frontend React (SPA) và Backend Laravel (Blade)
2. **RBAC được implement:** Admin routes có role guard
3. **Consistent naming:** Routes follow kebab-case convention
4. **Comprehensive coverage:** Đầy đủ các tính năng cần thiết

### ⚠️ Điểm cần cải thiện
1. **Duplication:** Một số routes có cả Frontend React và Backend Blade (ví dụ: `/app/dashboard`)
2. **Test routes:** Nhiều test routes trong production code (nên move vào development only)
3. **Navigation:** Navbar component cần sync với actual routes
4. **Documentation:** Cần cập nhật docs khi thêm routes mới

### 📝 Gợi ý cải thiện
1. **Consolidate routes:** Quyết định route nào dùng React, route nào dùng Blade
2. **Move test routes:** Chuyển test routes vào routes/test.php với environment guard
3. **Create route registry:** Tạo file registry để track tất cả active routes
4. **Update navigation:** Đảm bảo Navbar và PrimaryNavigator reflect đúng routes

---

## 📚 10. FILES REFERENCE

### Frontend React Routes
- `frontend/src/app/router.tsx` - Main React Router config
- `frontend/src/routes/index.tsx` - Alternate routes file
- `frontend/src/pages/` - All page components

### Backend Laravel Routes
- `routes/web.php` - Web routes (auth, admin)
- `routes/app.php` - App routes (/app/*)
- `routes/admin.php` - Admin routes (/admin/*)
- `routes/api.php` - API routes
- `routes/api_v1.php` - API v1 routes

### Documentation
- `docs/ROUTES_GUIDE.md` - Routes conventions
- `BUILD_ROADMAP.md` - Page creation plan
- `SYSTEM_ARCHITECTURE_DIAGRAM.md` - System architecture

---

**Last Updated:** 2025-01-XX  
**Version:** 1.0  
**Maintained by:** Development Team

