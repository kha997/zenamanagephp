# 🌳 BẢN ĐỒ CÂY ROUTES - ZENAMANAGE SYSTEM

## 📊 TỔNG QUAN

```
ZenaManage Routes Tree
│
├─ 🔓 PUBLIC (No Auth Required)
│  ├─ /                            → React App (SPA)
│  ├─ /login                       → LoginPage.tsx
│  ├─ /register                    → RegisterPage.tsx (Backend)
│  ├─ /forgot-password             → ForgotPasswordPage.tsx
│  ├─ /reset-password              → ResetPasswordPage.tsx
│  └─ /password/reset/:token       → PasswordResetController (Backend)
│
├─ 🔐 APP ROUTES (/app/*) - Tenant-scoped, Authenticated
│  │
│  ├─ 📊 Dashboard & Main
│  │  ├─ /app                      → Redirect to /app/dashboard
│  │  ├─ /app/dashboard            → DashboardPage.tsx ✅ (React)
│  │  ├─ /app/alerts               → AlertsPage.tsx ✅ (React)
│  │  └─ /app/preferences          → PreferencesPage.tsx ✅ (React)
│  │
│  ├─ 📁 Projects Module
│  │  ├─ /app/projects             → ProjectsListPage.tsx ✅ (React)
│  │  ├─ /app/projects/:id         → ProjectDetailPage.tsx ✅ (React)
│  │  └─ /app/projects/create      → [DISABLED - Dùng React Frontend]
│  │
│  ├─ ✅ Tasks Module
│  │  ├─ /app/tasks                → TasksPage.tsx ✅ (React)
│  │  ├─ /app/tasks/kanban         → TaskController@kanban (Backend)
│  │  ├─ /app/tasks/create         → TaskController@create (Backend)
│  │  ├─ /app/tasks/:id            → TaskController@show (Backend)
│  │  └─ /app/tasks/:id/edit       → TaskController@edit (Backend)
│  │
│  ├─ 📄 Documents Module
│  │  ├─ /app/documents            → DocumentsPage.tsx ✅ (React)
│  │  ├─ /app/documents/:id        → DocumentDetailPage.tsx ✅ (React)
│  │  └─ /app/documents/create     → DocumentController@create (Backend)
│  │
│  ├─ 👥 Team & Collaboration
│  │  ├─ /app/team                 → TeamPage.tsx ✅ (React)
│  │  └─ /app/users                → UsersPage.tsx (Alt route)
│  │
│  ├─ 📅 Calendar
│  │  └─ /app/calendar             → CalendarPage.tsx ✅ (React)
│  │
│  ├─ ⚙️ Settings & Preferences
│  │  ├─ /app/settings             → SettingsPage.tsx ✅ (React)
│  │  ├─ /app/profile              → ProfilePage.tsx (Alt route)
│  │  └─ /app/preferences          → PreferencesPage.tsx ✅ (React)
│  │
│  ├─ 📋 Templates Module
│  │  ├─ /app/templates            → TemplateController@index (Backend)
│  │  ├─ /app/templates/library    → TemplateController@library (Backend)
│  │  ├─ /app/templates/builder    → TemplateController@builder (Backend)
│  │  └─ /app/templates/:id        → TemplateController@show (Backend)
│  │
│  ├─ 🔄 Change Requests
│  │  ├─ /app/change-requests      → CRListPage.tsx ✅ (React)
│  │  ├─ /app/change-requests/create → CRCreatePage.tsx ✅ (React)
│  │  └─ /app/change-requests/:id → CRDetailPage.tsx ✅ (React)
│  │
│  ├─ 📊 Reports & Analytics
│  │  ├─ /app/reports              → ReportsPage.tsx ✅ (React)
│  │  └─ /app/analytics            → AnalyticsPage.tsx ✅ (React)
│  │
│  ├─ 📈 Gantt Chart
│  │  └─ /app/gantt                → GanttChartPage.tsx ✅ (React)
│  │
│  ├─ 🔍 QC Module
│  │  └─ /app/qc                   → QCModulePage.tsx ✅ (React)
│  │
│  └─ 🧩 Components Library
│     └─ /app/components            → ComponentsListPage.tsx ✅ (React)
│
├─ 👑 ADMIN ROUTES (/admin/*) - System-wide, Admin/SuperAdmin Only
│  │
│  ├─ 📊 Admin Dashboard
│  │  ├─ /admin                    → Redirect to /admin/dashboard
│  │  ├─ /admin/dashboard          → AdminDashboardPage.tsx ✅ (React)
│  │  │                             → AdminDashboardController@index (Backend)
│  │
│  ├─ 👤 User Management
│  │  ├─ /admin/users              → AdminUsersPage.tsx ✅ (React)
│  │  │                             → AdminUsersController@index (Backend)
│  │  ├─ /admin/users/create       → view: admin.users.create (Backend)
│  │  ├─ /admin/users/debug        → AdminUsersController@debug (Backend)
│  │  └─ /admin/users/test-component → AdminUsersController@testComponent (Backend)
│  │
│  ├─ 🔐 Roles Management
│  │  └─ /admin/roles              → AdminRolesPage.tsx ✅ (React)
│  │
│  ├─ 🏢 Tenant Management
│  │  ├─ /admin/tenants            → AdminTenantsPage.tsx ✅ (React)
│  │  │                             → AdminTenantsController@index (Backend)
│  │  └─ /admin/tenants/create     → view: admin.tenants.create (Backend)
│  │
│  ├─ 📁 Admin Projects
│  │  ├─ /admin/projects           → view: admin.projects.index (Backend)
│  │  └─ /admin/projects/create    → view: admin.projects.create (Backend)
│  │
│  ├─ 🔒 Security
│  │  ├─ /admin/security           → view: admin.security.index (Backend)
│  │  └─ /admin/security/scan      → view: admin.security.scan (Backend)
│  │
│  ├─ 🔔 Alerts
│  │  └─ /admin/alerts             → view: admin.alerts.index (Backend)
│  │
│  ├─ 📊 Activities
│  │  └─ /admin/activities         → view: admin.activities.index (Backend)
│  │
│  ├─ 📈 Analytics
│  │  └─ /admin/analytics          → view: admin.analytics.index (Backend)
│  │
│  ├─ 🔧 Maintenance
│  │  ├─ /admin/maintenance        → view: admin.maintenance.index (Backend)
│  │  └─ /admin/maintenance/backup → view: admin.maintenance.backup (Backend)
│  │
│  ├─ ⚙️ Settings
│  │  ├─ /admin/settings           → view: admin.settings.index (Backend)
│  │  └─ /admin/profile            → view: admin.profile (Backend)
│  │
│  └─ 📊 Performance
│     ├─ /admin/performance        → view: admin.performance.dashboard (Backend)
│     ├─ /admin/performance/metrics → PerformanceController@getDashboard (Backend)
│     └─ /admin/performance/logs   → PerformanceController@getRealTimeMetrics (Backend)
│
├─ 🎭 ROLE-BASED DASHBOARDS (Alternative Routes)
│  │
│  ├─ /dashboard                   → Dashboard (Default)
│  ├─ /admin/dashboard             → Dashboard (Admin/SuperAdmin) - Protected
│  ├─ /pm/dashboard                → PmDashboard (PM only) - Protected
│  ├─ /designer/dashboard          → DesignerDashboard (Designer only) - Protected
│  ├─ /site-engineer/dashboard     → SiteEngineerDashboard (SiteEngineer only) - Protected
│  ├─ /qc/dashboard                → QcDashboard (QC only) - Protected
│  ├─ /procurement/dashboard       → ProcurementDashboard (Procurement only) - Protected
│  ├─ /finance/dashboard           → FinanceDashboard (Finance only) - Protected
│  └─ /client/dashboard            → ClientDashboard (Client only) - Protected
│
└─ 🧪 TEST & DEBUG ROUTES (Development Only)
   │
   ├─ Debug Routes
   │  ├─ /debug/tasks-create        → Debug task creation
   │  ├─ /debug/dropdown-test       → Debug dropdown
   │  ├─ /debug/css-conflict-check  → CSS conflict check
   │  └─ /app/debug                 → Debug session info
   │
   ├─ Test Routes (No Auth)
   │  ├─ /test-tasks                → Test tasks list
   │  ├─ /test-kanban               → Test kanban board
   │  ├─ /test-tasks/:taskId        → Test task detail
   │  ├─ /test/login                → Auto login with email
   │  ├─ /test/tasks/:id            → Test task view
   │  └─ /test-simple-task/:id      → Simple test task
   │
   ├─ Sandbox Routes
   │  ├─ /sandbox/task-view/:task   → Sandbox task view
   │  ├─ /sandbox/tasks-list        → Sandbox tasks list
   │  └─ /sandbox/kanban            → Sandbox kanban
   │
   └─ Demo Routes (Local/Testing)
      ├─ /demo/test                 → Test demo
      ├─ /demo/simple               → Simple demo
      ├─ /demo/header               → Header demo
      ├─ /demo/components           → Components demo
      ├─ /demo/dashboard            → Dashboard demo
      ├─ /demo/projects             → Projects demo
      ├─ /demo/tasks                → Tasks demo
      ├─ /demo/documents            → Documents demo
      └─ /demo/admin                → Admin demo
```

---

## 📈 STATISTICS

### Frontend React Pages (Active SPA)
- **Total:** 18 pages
- **App Routes:** 13 pages
- **Admin Routes:** 4 pages
- **Auth Routes:** 3 pages

### Backend Laravel Pages (Blade Templates)
- **Total:** ~25 pages
- **App Routes:** ~15 pages
- **Admin Routes:** ~10 pages

### Role-based Dashboards
- **Total:** 8 dashboard variants

### Test & Debug Routes
- **Total:** ~20 routes (Development only)

---

## 🔍 QUICK REFERENCE

### Most Used Routes
1. `/app/dashboard` - Main dashboard
2. `/app/projects` - Projects list
3. `/app/tasks` - Tasks list
4. `/app/team` - Team management
5. `/admin/dashboard` - Admin dashboard

### Recently Added
- ✅ `/app/alerts` - Alerts page (React)
- ✅ `/app/preferences` - Preferences page (React)
- ✅ `/app/change-requests` - Change requests module (React)

### Needs Migration
- ⚠️ `/app/tasks/*` - Mixed React/Backend (should consolidate)
- ⚠️ `/app/documents/*` - Mixed React/Backend (should consolidate)
- ⚠️ `/app/templates/*` - Backend only (consider migrating to React)

---

**Generated:** 2025-01-XX  
**Last Updated:** 2025-01-XX  
**Main Route Files:**
- `frontend/src/app/router.tsx`
- `routes/web.php`
- `routes/app.php`
- `routes/admin.php`

