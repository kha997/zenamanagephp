# 🏗️ ZENAMANAGE SYSTEM ARCHITECTURE DIAGRAM

## 📋 TỔNG QUAN HỆ THỐNG

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              ZENAMANAGE SYSTEM                                 │
│                           Multi-Tenant Project Management                      │
└─────────────────────────────────────────────────────────────────────────────────┘
```

## 🔐 AUTHENTICATION & AUTHORIZATION

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              AUTHENTICATION LAYER                               │
├─────────────────────────────────────────────────────────────────────────────────┤
│  🔑 Login (/login)                    🔓 Logout (/logout)                      │
│                                                                                 │
│  Purpose: Standard Laravel authentication for web routes                       │
│  Middleware: Standard Laravel auth middleware                                  │
│  Scope: Web application only (not for API or debug)                            │
└─────────────────────────────────────────────────────────────────────────────────┘
```

## 🎯 ADMIN SECTION (System-wide Management)

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              ADMIN DASHBOARD                                    │
│                         System-wide Management                                  │
├─────────────────────────────────────────────────────────────────────────────────┤
│  📊 Dashboard (/admin)              👥 Users (/admin/users)                     │
│  🏢 Tenants (/admin/tenants)        🔒 Security (/admin/security)               │
│  📈 Projects (/admin/projects)      ⚠️  Alerts (/admin/alerts)                  │
│  📋 Tasks (/admin/tasks)            📊 Analytics (/admin/analytics)             │
│  🔧 Maintenance (/admin/maintenance) ⚙️  Settings (/admin/settings)              │
│                                                                                 │
│  Scope: System-wide (All Tenants)                                              │
│  Middleware: auth + admin.only                                                 │
│  Layout: layouts.admin-layout                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

## 🏠 APP SECTION (Tenant-scoped Operations)

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              APP DASHBOARD                                     │
│                         Tenant-scoped Operations                               │
├─────────────────────────────────────────────────────────────────────────────────┤
│  📊 Dashboard (/app/dashboard)      📋 Tasks (/app/tasks)                      │
│  📈 Projects (/app/projects)        📅 Calendar (/app/calendar)               │
│  📄 Documents (/app/documents)       📝 Templates (/app/templates)             │
│  👥 Team (/app/team)                ⚙️  Settings (/app/settings)               │
│                                                                                 │
│  Scope: Tenant-internal (Single Tenant)                                        │
│  Middleware: auth + tenant.scope                                               │
│  Layout: layouts.app-layout                                                    │
└─────────────────────────────────────────────────────────────────────────────────┘
```

## 🔌 API ARCHITECTURE

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              API ENDPOINTS                                     │
├─────────────────────────────────────────────────────────────────────────────────┤
│  🌐 PUBLIC API (/api/v1/public)                                                │
│  ├── /health - System liveness check                                           │
│  └── Middleware: throttle:public (no session)                                  │
│                                                                                 │
│  🔐 ADMIN API (/api/v1/admin)                                                  │
│  ├── /perf/metrics - Performance metrics                                       │
│  ├── /perf/health - Detailed health check                                      │
│  ├── /perf/clear-caches - Cache management                                     │
│  └── Middleware: auth:sanctum + ability:admin                                   │
│                                                                                 │
│  🏠 APP API (/api/v1/app)                                                      │
│  ├── /tasks - Task management                                                  │
│  │   ├── GET /tasks - List tasks with filters                                 │
│  │   ├── POST /tasks - Create new task                                         │
│  │   ├── GET /tasks/{id} - Get task details                                    │
│  │   ├── PUT /tasks/{id} - Update task                                         │
│  │   ├── DELETE /tasks/{id} - Delete task                                      │
│  │   ├── PATCH /tasks/{id}/move - Move task to different status                │
│  │   └── PATCH /tasks/{id}/archive - Archive task                             │
│  ├── /projects - Project management                                            │
│  │   ├── GET /projects/metrics - KPI 4 thẻ portfolio                           │
│  │   ├── GET /projects - List projects with filters, pagination, sort         │
│  │   ├── POST /projects - Create new project                                   │
│  │   ├── GET /projects/{id} - Get project details                             │
│  │   ├── PATCH /projects/{id} - Status/health/budget updates                  │
│  │   ├── DELETE /projects/{id} - Delete project                               │
│  │   ├── GET /projects/alerts - Project alerts                                 │
│  │   ├── GET /projects/now-panel - Current project status                     │
│  │   ├── GET /projects/filters - Available filters                            │
│  │   ├── GET /projects/insights - Project insights                            │
│  │   ├── GET /projects/activity - Project activity feed                       │
│  │   ├── GET /projects/{id}/documents - Project documents                      │
│  │   ├── GET /projects/{id}/history - Project history (audit trail)           │
│  │   ├── GET /projects/{id}/design - Design phase details                     │
│  │   └── GET /projects/{id}/construction - Construction phase details         │
│  ├── /calendar - Calendar events management                                    │
│  │   ├── GET /calendar - Get events by date range                             │
│  │   ├── POST /calendar - Create new event                                    │
│  │   ├── PUT /calendar/{id} - Update event                                    │
│  │   ├── DELETE /calendar/{id} - Delete event                                 │
│  │   └── GET /calendar/upcoming - Get upcoming events                         │
│  └── Middleware: auth:sanctum + ability:tenant                                │
│                                                                                 │
│  📧 INVITATION API (/api/v1/invitations)                                       │
│  ├── /accept/{token} - Accept invitation                                        │
│  ├── /decline/{token} - Decline invitation                                      │
│  └── Middleware: throttle:public                                               │
└─────────────────────────────────────────────────────────────────────────────────┘
```

## 🐛 DEBUG & TESTING

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              DEBUG NAMESPACE                                   │
│                         /_debug/* (Protected Routes)                           │
├─────────────────────────────────────────────────────────────────────────────────┤
│  📊 /dashboard-data - Mock dashboard data                                      │
│  📚 /api-docs - API documentation                                             │
│  🧪 /test-api-admin-dashboard - Admin API testing                             │
│  🔍 /test-permissions - Permission testing                                     │
│  🔐 /test-login-simple - Simple login testing                                  │
│  📝 /test-session-auth - Session auth testing                                  │
│  🔑 /test-login/{email} - Debug login with email                               │
│                                                                                 │
│  Middleware: DebugGate (env check + IP allowlist)                             │
│  Access: Local/Testing only OR IP allowlist                                    │
│  Purpose: Development and debugging tools (NOT production)                     │
└─────────────────────────────────────────────────────────────────────────────────┘
```

## 🔄 LEGACY ROUTE MANAGEMENT

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              LEGACY REDIRECTS                                  │
│                         3-Phase Removal Strategy                               │
├─────────────────────────────────────────────────────────────────────────────────┤
│  ✅ PHASE 1: Essential Routes (Active) - 3 routes                            │
│  ├── /dashboard → /app/dashboard                                               │
│  ├── /projects → /app/projects                                                 │
│  └── /tasks → /app/tasks                                                      │
│                                                                                 │
│  ✅ PHASE 2: Performance Routes (Moved to API) - 7 routes                     │
│  ├── /health → /api/v1/public/health                                           │
│  ├── /metrics → /api/v1/admin/perf/metrics                                     │
│  ├── /health-check → /api/v1/admin/perf/health                                 │
│  ├── /clear-cache → /api/v1/admin/perf/clear-caches                           │
│  ├── /performance/metrics → /api/v1/admin/perf/metrics                        │
│  ├── /performance/health → /api/v1/admin/perf/health                          │
│  └── /performance/clear-caches → /api/v1/admin/perf/clear-caches             │
│                                                                                 │
│  📅 PHASE 3: Invitation Routes (2025-03-21 to 2025-04-21) - 2 routes          │
│  ├── /invite/accept/{token} → /invitations/accept/{token}                      │
│  └── /invite/decline/{token} → /invitations/decline/{token}                    │
│  └── 410 Removal Date: 2025-05-21                                              │
│                                                                                 │
│  ❌ REMOVED: Non-essential routes (14 → 3 routes)                              │
│  ├── /users, /tenants, /admin-dashboard, /role-dashboard                       │
│  └── /documents, /templates, /settings, /profile, /team                        │
│                                                                                 │
│  📊 TOTAL LEGACY REDIRECTS: 12 routes (3 + 7 + 2)                             │
└─────────────────────────────────────────────────────────────────────────────────┘
```

## 🎨 UI COMPONENTS ARCHITECTURE

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              UI COMPONENTS                                     │
├─────────────────────────────────────────────────────────────────────────────────┤
│  📱 LAYOUTS                                                                     │
│  ├── layouts.admin-layout - Admin section layout (extends admin-base)          │
│  └── layouts.app-layout - App section layout                                   │
│                                                                                 │
│  🧩 COMPONENTS                                                                  │
│  ├── components.header - Regular app header                                    │
│  ├── components.admin-header - Admin section header                            │
│  └── components.breadcrumb - Dynamic breadcrumbs                               │
│                                                                                 │
│  📄 CONTENT VIEWS                                                              │
│  ├── admin/*-content.blade.php - Admin section content                        │
│  ├── app/*-content.blade.php - App section content                             │
│  └── Dynamic content loading based on currentView                             │
└─────────────────────────────────────────────────────────────────────────────────┘
```

## 🔧 MIDDLEWARE STACK

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              MIDDLEWARE LAYER                                  │
├─────────────────────────────────────────────────────────────────────────────────┤
│  🔐 auth (Laravel Standard)                                                    │
│  ├── Purpose: Standard Laravel session authentication                          │
│  ├── Used by: Web routes (/app/*, /admin/*)                                   │
│  └── Features: Session-based, redirect to login if not authenticated          │
│                                                                                 │
│  👑 admin.only (AdminOnlyMiddleware)                                           │
│  ├── Purpose: Restrict access to admin users only                              │
│  ├── Used by: Admin web routes (/admin/*)                                     │
│  └── Features: Checks Auth::check() + Auth::user()->isSuperAdmin()            │
│                                                                                 │
│  🏠 tenant.scope (TenantScopeMiddleware)                                       │
│  ├── Purpose: Tenant-scoped access for web routes                             │
│  ├── Used by: App web routes (/app/*)                                         │
│  └── Features: Standard auth + tenant isolation                               │
│                                                                                 │
│  🛡️ DebugGate                                                                  │
│  ├── Purpose: Protect debug routes                                             │
│  ├── Used by: /_debug/* routes                                                 │
│  └── Features: Environment check + IP allowlist                               │
│                                                                                 │
│  🚦 throttle:public                                                            │
│  ├── Purpose: Rate limiting for public routes                                  │
│  ├── Used by: Public API routes                                                │
│  └── Features: No session required                                             │
│                                                                                 │
│  🔑 auth:sanctum + ability:admin                                               │
│  ├── Purpose: Token-based auth with admin ability                              │
│  ├── Used by: Admin API routes (/api/v1/admin/*)                             │
│  └── Features: Sanctum token + admin permission check                         │
│                                                                                 │
│  🔑 auth:sanctum + ability:tenant                                              │
│  ├── Purpose: Token-based auth with tenant ability                             │
│  ├── Used by: App API routes (/api/v1/app/*)                                  │
│  └── Features: Sanctum token + tenant permission check                        │
└─────────────────────────────────────────────────────────────────────────────────┘
```

## 📊 DATA FLOW ARCHITECTURE

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              DATA FLOW                                         │
├─────────────────────────────────────────────────────────────────────────────────┤
│  🌐 CLIENT REQUEST                                                              │
│  ├── Web Routes (Blade Views)                                                   │
│  ├── API Routes (JSON Responses)                                               │
│  └── Debug Routes (Protected Access)                                           │
│                                                                                 │
│  🔄 MIDDLEWARE PROCESSING                                                       │
│  ├── Authentication (auth, auth:sanctum)                                       │
│  ├── Authorization (admin.only, tenant.scope, ability:*)                       │
│  ├── Rate Limiting (throttle:public)                                           │
│  └── Debug Protection (DebugGate)                                              │
│                                                                                 │
│  🎯 CONTROLLER LAYER                                                            │
│  ├── AdminController - Admin section views                                     │
│  ├── Api\Admin\* - Admin API endpoints                                        │
│  ├── Api\App\* - App API endpoints                                             │
│  ├── Api\Public\* - Public API endpoints                                      │
│  └── InvitationController - Invitation handling                                │
│                                                                                 │
│  🏢 APPLICATION/DOMAIN SERVICES                                                │
│  ├── TaskService - Task business logic & operations                            │
│  ├── ProjectService - Project management & workflows                           │
│  ├── MetricsService - Analytics & reporting calculations                        │
│  ├── NotificationService - Alert & notification management                     │
│  ├── AuditService - Audit trail & logging                                     │
│  ├── SecretsRotationService - Secrets management & rotation                    │
│  └── TenantService - Tenant isolation & scoping                               │
│                                                                                 │
│  📦 REPOSITORY/DATA LAYER                                                      │
│  ├── TaskRepository - Task data access                                         │
│  ├── ProjectRepository - Project data access                                   │
│  ├── UserRepository - User data access                                         │
│  ├── TenantRepository - Tenant data access                                     │
│  └── AuditRepository - Audit log data access                                   │
│                                                                                 │
│  🗄️ DATABASE LAYER                                                             │
│  ├── MySQL/PostgreSQL - Primary database                                       │
│  ├── Redis - Caching & sessions                                                │
│  └── File Storage - Document & file storage                                   │
│                                                                                 │
│  🚌 EVENT BUS/QUEUE SYSTEM                                                     │
│  ├── Laravel Queue - Background job processing                                 │
│  ├── Event Broadcasting - Real-time updates                                    │
│  ├── Audit Events - Audit trail events                                         │
│  ├── Notification Events - Alert & notification events                         │
│  └── Side Effects - Async operations & integrations                            │
│                                                                                 │
│  📱 VIEW RENDERING                                                             │
│  ├── Alpine.js SPA Navigation                                                  │
│  ├── Dynamic Content Loading                                                    │
│  ├── Real-time Updates                                                         │
│  └── Responsive Design                                                          │
└─────────────────────────────────────────────────────────────────────────────────┘
```

## 🎯 KEY FEATURES BY SECTION

### 🔐 ADMIN SECTION FEATURES
- **System-wide Monitoring**: All tenants, projects, tasks
- **User Management**: Create, edit, delete users across tenants
- **Tenant Management**: Manage tenant organizations
- **Security Management**: Monitor threats, manage policies
- **Analytics**: Advanced reporting and system metrics
- **Maintenance**: System administration tools
- **Alerts**: System-wide alert management

### 🏠 APP SECTION FEATURES
- **Personal Dashboard**: Individual user overview
- **Project Management**: Tenant-scoped project management
- **Task Management**: Personal and team task management
- **Calendar**: Project and task scheduling
- **Document Management**: File and document handling
- **Team Collaboration**: Team member management
- **Templates**: Project and task templates

## 📈 SYSTEM STATISTICS

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              SYSTEM STATS                                      │
├─────────────────────────────────────────────────────────────────────────────────┤
│  📊 TOTAL ROUTES: 151 routes                                                    │
│  ├── Admin Routes: 12 routes                                                    │
│  ├── App Routes: 25 routes                                                      │
│  ├── API Routes: 19 routes                                                      │
│  ├── Debug Routes: 16 routes                                                    │
│  ├── Legacy Redirects: 30 routes                                                 │
│  ├── Authentication: 3 routes (login, logout, api-demo)                          │
│  └── Other Routes: 49 routes (projects, tasks, documents, etc.)                  │
│                                                                                 │
│  🎯 MIDDLEWARE: 6 middleware types                                             │
│  ├── auth (Laravel Standard)                                                    │
│  ├── admin.only (AdminOnlyMiddleware)                                           │
│  ├── tenant.scope (TenantScopeMiddleware)                                      │
│  ├── auth:sanctum + ability:admin                                              │
│  ├── auth:sanctum + ability:tenant                                             │
│  └── throttle:public                                                           │
│                                                                                 │
│  🛡️ SECURITY LAYERS: Multi-layer protection                                    │
│  ├── Authentication & Authorization                                             │
│  │   ├── Session-based auth (web routes)                                        │
│  │   ├── Token-based auth (API routes)                                          │
│  │   ├── Role-based access control (RBAC)                                       │
│  │   └── Tenant isolation & scoping                                            │
│  ├── Content Security Policy (CSP)                                             │
│  │   ├── frame-src: 'self' (prevent clickjacking)                              │
│  │   ├── img-src: 'self' data: https: (image sources)                          │
│  │   ├── connect-src: 'self' (API endpoints)                                   │
│  │   └── script-src: 'self' 'unsafe-inline' (Alpine.js)                        │
│  ├── CORS Policy                                                                │
│  │   ├── Allowed origins: configured domains                                    │
│  │   ├── Allowed methods: GET, POST, PUT, PATCH, DELETE                         │
│  │   ├── Allowed headers: Content-Type, Authorization, X-CSRF-TOKEN             │
│  │   └── Credentials: true (for authenticated requests)                        │
│  ├── Secrets Management & Rotation                                              │
│  │   ├── Environment variables (.env)                                          │
│  │   ├── API keys rotation schedule                                             │
│  │   ├── Database credentials management                                        │
│  │   └── JWT secret rotation                                                    │
│  ├── HTTPS Security (Production)                                                │
│  │   ├── HSTS (HTTP Strict Transport Security)                                  │
│  │   ├── SSL/TLS certificates                                                   │
│  │   ├── Secure cookies (HttpOnly, Secure, SameSite)                           │
│  │   └── Security headers (X-Frame-Options, X-Content-Type-Options)           │
│  └── Rate Limiting & DDoS Protection                                           │
│      ├── throttle:public (public endpoints)                                     │
│      ├── throttle:api (API endpoints)                                           │
│      ├── IP-based rate limiting                                                 │
│      └── Request size limits                                                    │
│                                                                                 │
│  📱 UI COMPONENTS: 10+ components                                              │
│  ├── 3 Layout files                                                             │
│  ├── 3 Component files                                                         │
│  ├── 8 Admin content views                                                     │
│  └── 7 App content views                                                       │
│                                                                                 │
│  🏢 APPLICATION SERVICES: 7+ domain services                                   │
│  ├── TaskService - Task business logic & operations                            │
│  ├── ProjectService - Project management & workflows                           │
│  ├── MetricsService - Analytics & reporting calculations                        │
│  ├── NotificationService - Alert & notification management                     │
│  ├── AuditService - Audit trail & logging                                     │
│  ├── SecretsRotationService - Secrets management & rotation                    │
│  └── TenantService - Tenant isolation & scoping                               │
│                                                                                 │
│  🚌 EVENT SYSTEM: Async processing                                             │
│  ├── Laravel Queue - Background job processing                                 │
│  ├── Event Broadcasting - Real-time updates                                    │
│  ├── Audit Events - Audit trail events                                         │
│  ├── Notification Events - Alert & notification events                         │
│  └── Side Effects - Async operations & integrations                            │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 📊 DATA FLOW ARCHITECTURE

### **Complete Data Flow with Service Layer:**

```
🌐 CLIENT REQUEST
├── Web Routes (Blade Views)
├── API Routes (JSON Responses)
└── Debug Routes (Protected Access)

🔄 MIDDLEWARE PROCESSING
├── Authentication (auth, auth:sanctum)
├── Authorization (admin.only, tenant.scope, ability:*)
├── Rate Limiting (throttle:public)
└── Debug Protection (DebugGate)

🎯 CONTROLLER LAYER
├── AdminController - Admin section views
├── Api\Admin\* - Admin API endpoints
├── Api\App\* - App API endpoints
├── Api\Public\* - Public API endpoints
└── InvitationController - Invitation handling

🏢 APPLICATION/DOMAIN SERVICES
├── TaskService - Task business logic & operations
├── ProjectService - Project management & workflows
├── MetricsService - Analytics & reporting calculations
├── NotificationService - Alert & notification management
├── AuditService - Audit trail & logging
├── SecretsRotationService - Secrets management & rotation
└── TenantService - Tenant isolation & scoping

📦 REPOSITORY/DATA LAYER
├── TaskRepository - Task data access
├── ProjectRepository - Project data access
├── UserRepository - User data access
├── TenantRepository - Tenant data access
└── AuditRepository - Audit log data access

🗄️ DATABASE LAYER
├── MySQL/PostgreSQL - Primary database
├── Redis - Caching & sessions
└── File Storage - Document & file storage

🚌 EVENT BUS/QUEUE SYSTEM
├── Laravel Queue - Background job processing
├── Event Broadcasting - Real-time updates
├── Audit Events - Audit trail events
├── Notification Events - Alert & notification events
└── Side Effects - Async operations & integrations

📱 VIEW RENDERING
├── Alpine.js SPA Navigation
├── Dynamic Content Loading
├── Real-time Updates
└── Responsive Design
```

### **Service Layer Benefits:**
- ✅ **Separation of Concerns**: Business logic separated from controllers
- ✅ **Reusability**: Services can be used by multiple controllers
- ✅ **Testability**: Business logic can be unit tested independently
- ✅ **Maintainability**: Changes to business rules centralized
- ✅ **Event-Driven**: Side effects handled through event system
- ✅ **Async Processing**: Background jobs for heavy operations

---

## 🚀 DEPLOYMENT ARCHITECTURE

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              DEPLOYMENT                                        │
├─────────────────────────────────────────────────────────────────────────────────┤
│  🐳 DOCKER CONTAINERS                                                          │
│  ├── Web Server (Nginx/Apache)                                                │
│  ├── PHP-FPM (Laravel Application)                                            │
│  ├── Database (MySQL/PostgreSQL)                                               │
│  ├── Cache (Redis)                                                             │
│  └── Queue Worker (Laravel Queue)                                              │
│                                                                                 │
│  🔒 SECURITY LAYERS                                                            │
│  ├── HTTPS/TLS Encryption                                                     │
│  ├── Rate Limiting                                                             │
│  ├── CSRF Protection                                                           │
│  ├── XSS Protection                                                            │
│  └── SQL Injection Prevention                                                 │
│                                                                                 │
│  📊 MONITORING & LOGGING                                                       │
│  ├── Application Logs                                                          │
│  ├── Access Logs                                                               │
│  ├── Error Tracking                                                            │
│  ├── Performance Metrics                                                       │
│  └── Security Monitoring                                                       │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 🎯 SUMMARY

**ZenaManage** là một hệ thống quản lý dự án đa tenant với kiến trúc rõ ràng:

- **🔐 Admin Section**: Quản lý hệ thống toàn cục
- **🏠 App Section**: Vận hành nội bộ tenant  
- **🔌 API Architecture**: RESTful APIs với middleware phù hợp
- **🐛 Debug Tools**: Công cụ debug được bảo vệ
- **🔄 Legacy Management**: Quản lý legacy routes có hệ thống
- **🎨 Modern UI**: Alpine.js SPA với responsive design
- **🛡️ Security**: Multi-layer security với CORS, CSP, Secrets management, HSTS

Hệ thống được thiết kế để dễ bảo trì, mở rộng và bảo mật cao! 🚀✨
