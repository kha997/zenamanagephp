# 🔧 ZENAMANAGE SYSTEM RESTRUCTURING PLAN

## 📋 EXECUTIVE SUMMARY

Hệ thống ZenaManage hiện tại đang gặp nhiều vấn đề chồng chéo và bất hợp lý trong cấu trúc routing, namespace, và quyền truy cập. Tài liệu này đưa ra kế hoạch chi tiết để tái cấu trúc hệ thống một cách triệt để và có hệ thống.

---

## 🚨 CRITICAL ISSUES ANALYSIS

### 1. **Dashboard Trùng Lặp & Namespace Rối**
```
❌ HIỆN TẠI:
/dashboard/admin          (Admin Dashboard)
/admin                    (Super Admin Dashboard)
/dashboard/{role}         (Role-based dashboards)
/dashboard/projects       (Projects Dashboard - dễ nhầm với /projects)

✅ ĐỀ XUẤT:
/admin                    (Super Admin only)
/app/dashboard           (Main user dashboard)
/app/dashboard/{role}     (Role-based views, cùng layout)
```

### 2. **Web Routes vs Frontend Routes Đè Nhau**
```
❌ HIỆN TẠI:
/projects (Laravel Blade)
/projects (React Frontend)
/projects-test (Test route)
/simple-projects-test (Simple test)

✅ ĐỀ XUẤT:
/app/projects (React SPA)
/api/v1/projects (API)
/_debug/projects-test (Debug only)
```

### 3. **Admin vs Tenant Scope Chồng Chéo**
```
❌ HIỆN TẠI:
/admin/users (Super Admin)
/users (Tenant users - không rõ scope)
/admin/tenants (Super Admin)
/tenants (Tenant management - không rõ scope)

✅ ĐỀ XUẤT:
/admin/users (Super Admin only)
/admin/tenants (Super Admin only)
/app/users (Tenant users only)
```

### 4. **REST API Chưa Chuẩn**
```
❌ HIỆN TẠI:
/tasks/{task}/move (POST)
/tasks/{task}/archive (POST)
/projects/design/{project}
/projects/construction/{project}

✅ ĐỀ XUẤT:
PATCH /api/v1/tasks/{id}/move
PATCH /api/v1/tasks/{id}/archive
/api/v1/projects/{id}/design
/api/v1/projects/{id}/construction
```

### 5. **Debug Routes Rò Rỉ Production**
```
❌ HIỆN TẠI:
/tasks/{task}/edit-debug
/tasks/{task}/edit-simple-debug
/test-*
/simple-*
/frontend-test
/users-debug

✅ ĐỀ XUẤT:
/_debug/* (chỉ env local)
```

---

## 🎯 RESTRUCTURING STRATEGY

### **PHASE 1: IMMEDIATE FIXES (Week 1-2)**

#### 1.1 **Fix Critical Routing Conflicts**
```php
// routes/web.php - Immediate fixes
Route::prefix('admin')->name('admin.')->group(function () {
    // Keep only Super Admin routes
    Route::get('/', function () {
        return view('admin.super-admin-dashboard-new');
    })->name('dashboard');
    
    Route::get('/users', function () {
        return view('admin.users');
    })->name('users');
    
    Route::get('/tenants', function () {
        return view('admin.tenants');
    })->name('tenants');
    
    // Remove duplicate dashboard routes
});

// Remove conflicting routes
// Route::get('/dashboard/admin', ...); // REMOVE
// Route::get('/users', ...); // REMOVE - move to /app/users
// Route::get('/tenants', ...); // REMOVE - move to /admin/tenants
```

#### 1.2 **Separate Debug Routes**
```php
// routes/debug.php - Only for local environment
if (app()->environment('local')) {
    Route::prefix('_debug')->group(function () {
        Route::get('/projects-test', function () {
            return response()->json(['test' => 'data']);
        });
        
        Route::get('/simple-projects-test', function () {
            return view('debug.simple-projects');
        });
        
        Route::get('/users-debug', function () {
            return view('debug.users');
        });
        
        Route::get('/tasks/{task}/edit-debug', function ($task) {
            return view('debug.task-edit', compact('task'));
        });
    });
}
```

#### 1.3 **Standardize API Structure**
```php
// routes/api.php
Route::prefix('api/v1')->group(function () {
    // Admin API
    Route::prefix('admin')->middleware(['auth', 'role:super_admin'])->group(function () {
        Route::get('/dashboard/stats', [AdminDashboardController::class, 'getStats']);
        Route::get('/dashboard/activities', [AdminDashboardController::class, 'getActivities']);
        Route::get('/dashboard/alerts', [AdminDashboardController::class, 'getAlerts']);
        Route::get('/dashboard/metrics', [AdminDashboardController::class, 'getMetrics']);
    });
    
    // App API
    Route::prefix('app')->middleware(['auth', 'tenant'])->group(function () {
        Route::get('/sidebar/config', [SidebarController::class, 'getConfig']);
        Route::get('/sidebar/badges', [SidebarController::class, 'getBadges']);
        Route::get('/sidebar/default/{role}', [SidebarController::class, 'getDefault']);
    });
    
    // Projects API
    Route::prefix('projects')->middleware(['auth', 'tenant'])->group(function () {
        Route::get('/', [ProjectController::class, 'index']);
        Route::post('/', [ProjectController::class, 'store']);
        Route::get('/{project}', [ProjectController::class, 'show']);
        Route::patch('/{project}', [ProjectController::class, 'update']);
        Route::delete('/{project}', [ProjectController::class, 'destroy']);
        
        // Sub-resources
        Route::get('/{project}/documents', [ProjectController::class, 'documents']);
        Route::get('/{project}/history', [ProjectController::class, 'history']);
        Route::get('/{project}/design', [ProjectController::class, 'design']);
        Route::get('/{project}/construction', [ProjectController::class, 'construction']);
    });
    
    // Tasks API
    Route::prefix('tasks')->middleware(['auth', 'tenant'])->group(function () {
        Route::get('/', [TaskController::class, 'index']);
        Route::post('/', [TaskController::class, 'store']);
        Route::get('/{task}', [TaskController::class, 'show']);
        Route::patch('/{task}', [TaskController::class, 'update']);
        Route::delete('/{task}', [TaskController::class, 'destroy']);
        
        // Actions (PATCH for state changes)
        Route::patch('/{task}/move', [TaskController::class, 'move']);
        Route::patch('/{task}/archive', [TaskController::class, 'archive']);
        
        // Sub-resources
        Route::get('/{task}/documents', [TaskController::class, 'documents']);
        Route::get('/{task}/history', [TaskController::class, 'history']);
    });
});
```

### **PHASE 2: SPA FRONTEND RESTRUCTURE (Week 3-4)**

#### 2.1 **Create App Layout Structure**
```typescript
// frontend/src/routes/AppRoutes.tsx
export function AppRoutes() {
  return (
    <Routes>
      {/* App SPA Routes - All under /app */}
      <Route path="/app" element={<AppLayout />}>
        <Route index element={<Navigate to="dashboard" replace />} />
        
        {/* Main Dashboard */}
        <Route path="dashboard" element={<Dashboard />} />
        <Route path="dashboard/:role" element={<RoleDashboard />} />
        
        {/* Projects Module */}
        <Route path="projects" element={<ProjectsList />} />
        <Route path="projects/:id" element={<ProjectDetail />} />
        <Route path="projects/:id/documents" element={<ProjectDocuments />} />
        <Route path="projects/:id/history" element={<ProjectHistory />} />
        <Route path="projects/:id/design" element={<ProjectDesign />} />
        <Route path="projects/:id/construction" element={<ProjectConstruction />} />
        
        {/* Tasks Module */}
        <Route path="tasks" element={<TasksList />} />
        <Route path="tasks/:id" element={<TaskDetail />} />
        <Route path="tasks/:id/documents" element={<TaskDocuments />} />
        <Route path="tasks/:id/history" element={<TaskHistory />} />
        
        {/* Other Modules */}
        <Route path="documents" element={<DocumentsList />} />
        <Route path="team" element={<TeamManagement />} />
        <Route path="templates" element={<TemplatesList />} />
        <Route path="settings" element={<Settings />} />
        <Route path="profile" element={<UserProfile />} />
      </Route>
      
      {/* Admin Routes - Separate layout */}
      <Route path="/admin" element={<AdminLayout />}>
        <Route index element={<AdminDashboard />} />
        <Route path="users" element={<AdminUsers />} />
        <Route path="tenants" element={<AdminTenants />} />
        <Route path="security" element={<AdminSecurity />} />
        <Route path="alerts" element={<AdminAlerts />} />
        <Route path="activities" element={<AdminActivities />} />
        <Route path="projects" element={<AdminProjects />} />
        <Route path="settings" element={<AdminSettings />} />
        <Route path="maintenance" element={<AdminMaintenance />} />
        <Route path="sidebar-builder" element={<AdminSidebarBuilder />} />
      </Route>
      
      {/* Auth Routes */}
      <Route path="/login" element={<Login />} />
      <Route path="/logout" element={<Logout />} />
      
      {/* Invitation Routes */}
      <Route path="/invitations/accept/:token" element={<AcceptInvitation />} />
      
      {/* Fallback */}
      <Route path="*" element={<Navigate to="/app/dashboard" replace />} />
    </Routes>
  );
}
```

#### 2.2 **Update Navigation Structure**
```typescript
// frontend/src/components/Navigation.tsx
export function Navigation() {
  const { user } = useAuth();
  const location = useLocation();
  
  const isAdminRoute = location.pathname.startsWith('/admin');
  const isAppRoute = location.pathname.startsWith('/app');
  
  if (isAdminRoute) {
    return <AdminNavigation />;
  }
  
  if (isAppRoute) {
    return <AppNavigation />;
  }
  
  return <AuthNavigation />;
}

// App Navigation (for /app/* routes)
function AppNavigation() {
  return (
    <nav className="app-navigation">
      <div className="nav-brand">
        <Link to="/app/dashboard">
          <div className="logo">
            <span>Z</span>
          </div>
          <span className="brand-text">ZenaManage</span>
        </Link>
      </div>
      
      <div className="nav-menu">
        <NavLink to="/app/dashboard">Dashboard</NavLink>
        <NavLink to="/app/projects">Projects</NavLink>
        <NavLink to="/app/tasks">Tasks</NavLink>
        <NavLink to="/app/documents">Documents</NavLink>
        <NavLink to="/app/team">Team</NavLink>
        <NavLink to="/app/templates">Templates</NavLink>
        <NavLink to="/app/settings">Settings</NavLink>
      </div>
    </nav>
  );
}

// Admin Navigation (for /admin/* routes)
function AdminNavigation() {
  return (
    <nav className="admin-navigation">
      <div className="nav-brand">
        <Link to="/admin">
          <div className="logo">
            <span>Z</span>
          </div>
          <span className="brand-text">ZenaManage Admin</span>
        </Link>
      </div>
      
      <div className="nav-menu">
        <NavLink to="/admin">Dashboard</NavLink>
        <NavLink to="/admin/users">Users</NavLink>
        <NavLink to="/admin/tenants">Tenants</NavLink>
        <NavLink to="/admin/security">Security</NavLink>
        <NavLink to="/admin/alerts">Alerts</NavLink>
        <NavLink to="/admin/activities">Activities</NavLink>
        <NavLink to="/admin/projects">Projects</NavLink>
        <NavLink to="/admin/settings">Settings</NavLink>
      </div>
    </nav>
  );
}
```

### **PHASE 3: PERMISSION & SCOPE CLARIFICATION (Week 5-6)**

#### 3.1 **Implement Clear Permission Structure**
```php
// app/Http/Middleware/AdminOnly.php
class AdminOnly
{
    public function handle($request, Closure $next)
    {
        if (!auth()->check() || !auth()->user()->hasRole('super_admin')) {
            abort(403, 'Access denied. Super Admin access required.');
        }
        
        return $next($request);
    }
}

// app/Http/Middleware/TenantScope.php
class TenantScope
{
    public function handle($request, Closure $next)
    {
        if (!auth()->check() || !auth()->user()->tenant_id) {
            abort(403, 'Access denied. Tenant access required.');
        }
        
        // Set tenant context
        app()->instance('tenant', auth()->user()->tenant);
        
        return $next($request);
    }
}
```

#### 3.2 **Update Route Groups with Proper Middleware**
```php
// routes/web.php
// Admin routes - Super Admin only
Route::prefix('admin')->middleware(['auth', AdminOnly::class])->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/tenants', [AdminController::class, 'tenants'])->name('admin.tenants');
    Route::get('/security', [AdminController::class, 'security'])->name('admin.security');
    Route::get('/alerts', [AdminController::class, 'alerts'])->name('admin.alerts');
    Route::get('/activities', [AdminController::class, 'activities'])->name('admin.activities');
    Route::get('/projects', [AdminController::class, 'projects'])->name('admin.projects');
    Route::get('/settings', [AdminController::class, 'settings'])->name('admin.settings');
    Route::get('/maintenance', [AdminController::class, 'maintenance'])->name('admin.maintenance');
    Route::get('/sidebar-builder', [AdminController::class, 'sidebarBuilder'])->name('admin.sidebar-builder');
});

// App routes - Tenant users only
Route::prefix('app')->middleware(['auth', TenantScope::class])->group(function () {
    Route::get('/dashboard', [AppController::class, 'dashboard'])->name('app.dashboard');
    Route::get('/projects', [AppController::class, 'projects'])->name('app.projects');
    Route::get('/tasks', [AppController::class, 'tasks'])->name('app.tasks');
    Route::get('/documents', [AppController::class, 'documents'])->name('app.documents');
    Route::get('/team', [AppController::class, 'team'])->name('app.team');
    Route::get('/templates', [AppController::class, 'templates'])->name('app.templates');
    Route::get('/settings', [AppController::class, 'settings'])->name('app.settings');
    Route::get('/profile', [AppController::class, 'profile'])->name('app.profile');
});
```

### **PHASE 4: MIGRATION & CLEANUP (Week 7-8)**

#### 4.1 **Create Migration Scripts**
```php
// database/migrations/2024_01_01_000000_cleanup_routes.php
class CleanupRoutes extends Migration
{
    public function up()
    {
        // Remove old route entries from database if any
        DB::table('route_cache')->truncate();
        
        // Clear route cache
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
    }
    
    public function down()
    {
        // Rollback if needed
    }
}
```

#### 4.2 **Create Redirect Routes for Backward Compatibility**
```php
// routes/legacy.php - Temporary redirects
Route::get('/dashboard/admin', function () {
    return redirect('/admin');
});

Route::get('/dashboard/{role}', function ($role) {
    return redirect("/app/dashboard/{$role}");
});

Route::get('/users', function () {
    return redirect('/app/users');
});

Route::get('/tenants', function () {
    return redirect('/admin/tenants');
});

// Debug routes redirects
Route::get('/test-*', function () {
    if (app()->environment('local')) {
        return redirect('/_debug' . request()->path());
    }
    abort(404);
});
```

#### 4.3 **Update Breadcrumb System**
```php
// app/Services/BreadcrumbService.php
class BreadcrumbService
{
    public function generateBreadcrumbs($path)
    {
        $breadcrumbs = [];
        
        if (str_starts_with($path, '/admin')) {
            $breadcrumbs[] = ['name' => 'Admin', 'url' => '/admin'];
            
            if ($path === '/admin') {
                return $breadcrumbs;
            }
            
            $segments = explode('/', trim($path, '/'));
            array_shift($segments); // Remove 'admin'
            
            foreach ($segments as $segment) {
                $breadcrumbs[] = [
                    'name' => ucfirst($segment),
                    'url' => '/admin/' . implode('/', array_slice($segments, 0, array_search($segment, $segments) + 1))
                ];
            }
        }
        
        if (str_starts_with($path, '/app')) {
            $breadcrumbs[] = ['name' => 'Dashboard', 'url' => '/app/dashboard'];
            
            if ($path === '/app/dashboard') {
                return $breadcrumbs;
            }
            
            $segments = explode('/', trim($path, '/'));
            array_shift($segments); // Remove 'app'
            
            foreach ($segments as $segment) {
                $breadcrumbs[] = [
                    'name' => ucfirst($segment),
                    'url' => '/app/' . implode('/', array_slice($segments, 0, array_search($segment, $segments) + 1))
                ];
            }
        }
        
        return $breadcrumbs;
    }
}
```

---

## 📋 IMPLEMENTATION CHECKLIST

### **Week 1-2: Critical Fixes**
- [ ] Remove duplicate dashboard routes
- [ ] Separate debug routes to /_debug/*
- [ ] Standardize API structure to /api/v1/*
- [ ] Fix REST API endpoints (PATCH for actions)
- [ ] Update middleware for proper scoping

### **Week 3-4: Frontend Restructure**
- [ ] Create AppLayout for /app/* routes
- [ ] Create AdminLayout for /admin/* routes
- [ ] Update React routing structure
- [ ] Implement proper navigation components
- [ ] Update all frontend route references

### **Week 5-6: Permission & Scope**
- [ ] Implement AdminOnly middleware
- [ ] Implement TenantScope middleware
- [ ] Update all route groups with proper middleware
- [ ] Test permission boundaries
- [ ] Update user role management

### **Week 7-8: Migration & Cleanup**
- [ ] Create migration scripts
- [ ] Implement legacy redirects
- [ ] Update breadcrumb system
- [ ] Test all routes and permissions
- [ ] Update documentation
- [ ] Performance testing

---

## 🎯 SUCCESS METRICS

### **Technical Metrics**
- [ ] Zero route conflicts
- [ ] Clear namespace separation
- [ ] Proper permission boundaries
- [ ] Consistent API structure
- [ ] No debug routes in production

### **User Experience Metrics**
- [ ] Intuitive navigation
- [ ] Consistent breadcrumbs
- [ ] Clear role-based access
- [ ] Fast page loads
- [ ] No broken links

### **Maintenance Metrics**
- [ ] Easy to add new routes
- [ ] Clear code organization
- [ ] Proper documentation
- [ ] Testable components
- [ ] Scalable architecture

---

## 🚀 NEXT STEPS

1. **Immediate Action**: Start with Phase 1 critical fixes
2. **Team Coordination**: Assign specific tasks to team members
3. **Testing Strategy**: Implement comprehensive testing for each phase
4. **Documentation**: Update all documentation to reflect new structure
5. **Training**: Train team on new routing and permission structure

---

## 📞 SUPPORT & QUESTIONS

For any questions or clarifications about this restructuring plan, please refer to:
- Technical Lead: [Contact Info]
- Project Manager: [Contact Info]
- Documentation: `/docs/RESTRUCTURING_GUIDE.md`

---

*This document will be updated as the restructuring progresses.*
