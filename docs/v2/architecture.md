# ZENAMANAGE v2.0 - ARCHITECTURE GUIDE
## System Architecture and Design Patterns

**Version**: 2.0  
**Last Updated**: October 5, 2025  
**Status**: Production Ready ✅

---

## 🏗️ **SYSTEM ARCHITECTURE OVERVIEW**

### **Layered Architecture**
```
┌─────────────────────────────────────────┐
│                Frontend Layer            │
│         (Alpine.js + Tailwind CSS)      │
├─────────────────────────────────────────┤
│                Web Routes               │
│         (Session-based Auth)            │
├─────────────────────────────────────────┤
│                API Layer                │
│         (Token-based Auth)              │
├─────────────────────────────────────────┤
│              Business Logic             │
│         (Services + Repositories)       │
├─────────────────────────────────────────┤
│              Data Layer                 │
│         (Eloquent ORM + Database)       │
└─────────────────────────────────────────┘
```

### **Route Architecture**
| Route Pattern | Purpose | Middleware | Auth Type |
|---------------|---------|------------|-----------|
| `/admin/*` | System-wide administration | `web`, `auth`, `rbac:admin` | Session |
| `/app/*` | Tenant-scoped application | `web`, `auth`, `tenant.isolation` | Session |
| `/api/v1/*` | REST API | `auth:sanctum`, `ability:admin\|tenant` | Token |
| `/_debug/*` | Debug routes | `DebugGate` | Environment |

---

## 🔗 **CROSS-REFERENCES**

- **[📄 Complete System Documentation](../COMPLETE_SYSTEM_DOCUMENTATION.md)** - Main documentation
- **[📁 Architecture Decisions](../adr/)** - All ADRs
- **[📋 ADR Collection](../adr/ADR-001-to-006.md)** - Complete ADR list
- **[🔒 Security Guide](security-guide.md)** - Security implementation
- **[📊 Performance Guide](performance-guide.md)** - Performance monitoring
- **[🚀 Deployment Guide](deployment-guide.md)** - Production deployment

---

## 🎯 **CORE PRINCIPLES**

### **1. Multi-Tenant Isolation**
- **Mandatory**: Every query must filter by `tenant_id`
- **Enforcement**: At repository/service layer
- **Testing**: Explicit tests to prove tenant A cannot read B
- **Indexes**: Composite indexes on `(tenant_id, foreign_key)`

### **2. Single Source of Truth**
- **UI renders only** — business logic lives in the API
- **Clear separation**: `/admin/*` (system-wide) ≠ `/app/*` (tenant-scoped)
- **No side-effects** in UI routes - all writes via API

### **3. Modular Design**
- **Blade Components**: Reusable UI components
- **Domain Organization**: Clear separation of concerns
- **Feature Flags**: Dynamic feature control
- **Service Providers**: Shared data and functionality

---

## 🧩 **COMPONENT ARCHITECTURE**

### **Blade Components Structure**
```
📁 resources/views/components/
├── 📁 kpi/
│   └── strip.blade.php              # KPI Strip Component
├── 📁 projects/
│   ├── filters.blade.php            # Smart Filters Component
│   ├── table.blade.php              # Table View Component
│   └── card-grid.blade.php          # Card View Component
└── 📁 shared/
    ├── empty-state.blade.php        # Empty State Component
    ├── alert.blade.php              # Alert Component
    ├── pagination.blade.php         # Pagination Component
    └── toolbar.blade.php            # Toolbar Component
```

### **Component Registration**
```php
// ViewServiceProvider.php
Blade::component('components.kpi.strip', 'kpi-strip');
Blade::component('components.projects.filters', 'projects-filters');
Blade::component('components.projects.table', 'projects-table');
Blade::component('components.projects.card-grid', 'projects-card-grid');
Blade::component('components.shared.empty-state', 'empty-state');
Blade::component('components.shared.alert', 'alert');
Blade::component('components.shared.pagination', 'pagination');
Blade::component('components.shared.toolbar', 'toolbar');
```

---

## 🔧 **TECHNICAL IMPLEMENTATION**

### **ViewServiceProvider Architecture**
```php
// app/Providers/ViewServiceProvider.php
View::composer('*', function ($view) {
    $user = Auth::user();
    $tenantId = $user ? $user->tenant_id : '01k5kzpfwd618xmwdwq3rej3jz';
    
    $view->with([
        'currentTenant' => $tenantId,
        'currentUser' => $user,
        'navCounters' => $navCounters,
        'featureFlags' => $featureFlags,
    ]);
});
```

### **Feature Flags System**
```php
// config/features.php
return [
    'projects' => [
        'view_mode' => env('PROJECTS_VIEW_MODE', 'table'),
        'enable_filters' => env('PROJECTS_ENABLE_FILTERS', true),
        'enable_export' => env('PROJECTS_ENABLE_EXPORT', true),
        'items_per_page' => env('PROJECTS_ITEMS_PER_PAGE', 15),
    ],
    'dashboard' => [
        'enable_kpi_cache' => env('DASHBOARD_ENABLE_KPI_CACHE', true),
        'kpi_cache_ttl' => env('DASHBOARD_KPI_CACHE_TTL', 60),
    ],
];
```

---

## 📊 **PERFORMANCE ARCHITECTURE**

### **Caching Strategy**
| Cache Type | TTL | Scope | Purpose |
|------------|-----|-------|---------|
| **KPI Cache** | 60s | Per tenant | Reduce database queries for KPIs |
| **Navigation Counters** | 60s | Per tenant | Cached navigation badges |
| **Feature Flags** | 300s | Per tenant | Tenant-specific settings |
| **View Cache** | Enabled | Global | Compiled Blade views |
| **Query Cache** | Enabled | Global | Database query optimization |

### **Database Optimization**
```php
// Eager Loading
$projects = Project::with(['owner:id,name,email'])
    ->where('tenant_id', $tenantId)
    ->paginate(15);

// Select Specific Columns
$users = \App\Models\User::where('tenant_id', $tenantId)
    ->select('id', 'name', 'email')
    ->get();

// Server-side Pagination
$projects = $query->paginate(15);
```

---

## 🔒 **SECURITY ARCHITECTURE**

### **RBAC Implementation**
| Role | Level | Permissions |
|------|-------|-------------|
| **super_admin** | 100 | All permissions (*) |
| **pm** | 80 | projects.*, tasks.*, team.read, documents.*, templates.*, calendar.*, reports.read |
| **member** | 60 | projects.read, projects.update, tasks.*, team.read, documents.read, documents.create, calendar.read, calendar.create |
| **client** | 40 | projects.read, tasks.read, documents.read, calendar.read, reports.read |

### **Security Headers**
- **Content Security Policy (CSP)**
- **HTTP Strict Transport Security (HSTS)**
- **X-Content-Type-Options**
- **X-Frame-Options**
- **X-XSS-Protection**
- **Referrer Policy**
- **Permissions Policy**

---

## 📈 **MONITORING ARCHITECTURE**

### **Health Check Endpoints**
- `/api/v1/health` - Basic health check
- `/api/v1/health/detailed` - Detailed system metrics
- `/api/v1/health/performance` - Performance metrics
- `/api/v1/health/database` - Database health
- `/api/v1/health/cache` - Cache health

### **Structured Logging**
```php
// Log structure
{
  "timestamp": "2025-10-05T10:30:00Z",
  "level": "INFO",
  "message": "User login successful",
  "context": {...},
  "extra": {
    "request_id": "req_7f1a2b3c",
    "tenant_id": "01k5kzpfwd618xmwdwq3rej3jz",
    "user_id": "01k5kzpfwd618xmwdwq3rej3jz",
    "route": "auth.login",
    "method": "POST",
    "url": "https://app.zenamanage.com/login",
    "ip": "192.168.1.1",
    "latency": 150,
    "memory_usage": 25600000,
    "environment": "production"
  }
}
```

---

## 🎯 **ARCHITECTURAL BENEFITS**

### **Scalability**
- **Modular Components**: Easy to reuse and maintain
- **Domain Organization**: Clear separation of concerns
- **Feature Flags**: Easy to enable/disable features
- **Caching Strategy**: Performance optimization

### **Maintainability**
- **Organized Structure**: Clear folder hierarchy
- **Domain-specific i18n**: Easy to manage translations
- **Component Reuse**: Reduced code duplication
- **Comprehensive Testing**: Smoke tests prevent regressions

### **Security**
- **Multi-tenant Isolation**: Complete data separation
- **RBAC Implementation**: Clear permission boundaries
- **Security Headers**: Protection against common attacks
- **Audit Logging**: Complete activity tracking

---

## 🚀 **NEXT STEPS**

1. **[🔒 Security Guide](security-guide.md)** - Implement security features
2. **[📊 Performance Guide](performance-guide.md)** - Set up monitoring
3. **[🚀 Deployment Guide](deployment-guide.md)** - Deploy to production
4. **[📁 Architecture Decisions](../adr/)** - Review all ADRs

---

*This architecture guide provides the foundation for understanding and implementing the ZenaManage system.*
