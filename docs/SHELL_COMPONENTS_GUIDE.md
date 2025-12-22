# ZenaManage Shell Components Documentation

## Overview

ZenaManage Shell Components là hệ thống các component thống nhất được thiết kế để loại bỏ duplicate code và cung cấp single source of truth cho các chức năng chính của hệ thống.

## Architecture

### Shell Component Pattern

Mỗi Shell component được thiết kế theo pattern:
- **Context-Aware**: Tự động detect context (admin/app/web/api)
- **Permission-Based**: Role-based access control tích hợp
- **Unified Interface**: Consistent API across all contexts
- **Extensible**: Dễ dàng mở rộng cho các use cases mới

## Components

### 1. HeaderShell Component

**File**: `resources/views/components/shared/header-shell.blade.php`

**Purpose**: Thống nhất header UI cho cả app và admin interfaces

**Props**:
```php
@props([
    'variant' => 'app', // 'app' or 'admin'
    'user' => null,
    'tenant' => null,
    'navigation' => [],
    'notifications' => [],
    'unreadCount' => 0,
    'alertCount' => 0,
    'theme' => 'light'
])
```

**Usage**:
```blade
{{-- App variant --}}
<x-shared.header-shell 
    variant="app"
    :user="Auth::user()"
    :notifications="$notifications"
    :unread-count="$unreadCount"
/>

{{-- Admin variant --}}
<x-shared.header-shell 
    variant="admin"
    :user="Auth::user()"
    :alert-count="$alertCount"
/>
```

**Features**:
- Dynamic navigation menu
- User dropdown với context-aware options
- Theme toggle (app only)
- Notification system (app only)
- Quick actions (admin only)
- Responsive design
- Accessibility support

### 2. DashboardShell Component

**File**: `resources/views/components/shared/dashboard-shell.blade.php`

**Purpose**: Thống nhất dashboard UI cho cả app và admin

**Props**:
```php
@props([
    'variant' => 'app', // 'app' or 'admin'
    'user' => null,
    'tenant' => null,
    'kpis' => [],
    'charts' => [],
    'recentActivity' => [],
    'recentProjects' => [],
    'alerts' => [],
    'notifications' => [],
    'theme' => 'light'
])
```

**Usage**:
```blade
{{-- App Dashboard --}}
<x-shared.dashboard-shell 
    variant="app"
    :user="Auth::user()"
    :kpis="$kpis"
    :charts="$charts"
/>

{{-- Admin Dashboard --}}
<x-shared.dashboard-shell 
    variant="admin"
    :user="Auth::user()"
    :kpis="$adminKpis"
    :charts="$adminCharts"
/>
```

**Features**:
- Dynamic KPIs based on context
- Real-time charts integration
- Recent activity feed
- Recent projects/tenants
- Loading states và error handling
- Responsive grid layout
- Auto-refresh functionality

### 3. UserShellController

**File**: `app/Http/Controllers/UserShellController.php`

**Purpose**: Thống nhất user management cho tất cả contexts

**Methods**:
```php
public function index(Request $request): View|JsonResponse
public function create(Request $request): View
public function store(StoreUserRequest $request): JsonResponse|Response
public function show(Request $request, User $user): View|JsonResponse
public function edit(Request $request, User $user): View
public function update(UpdateUserRequest $request, User $user): JsonResponse|Response
public function destroy(Request $request, User $user): JsonResponse|Response
public function bulkAction(Request $request): JsonResponse|Response
public function statistics(Request $request): JsonResponse
```

**Features**:
- Context-aware permissions
- Bulk operations
- Statistics và analytics
- Unified API responses
- Error handling
- Tenant isolation

**Consolidated Controllers**:
- `Web/UserController.php`
- `Api/Admin/UserController.php`
- `Api/App/UserController.php`
- `Admin/UsersApiController.php`
- `App/TeamUsersController.php`

### 4. ProjectShellController

**File**: `app/Http/Controllers/ProjectShellController.php`

**Purpose**: Thống nhất project management cho tất cả contexts

**Methods**:
```php
public function index(Request $request): View|JsonResponse
public function create(Request $request): View
public function store(StoreProjectRequest $request): JsonResponse|Response
public function show(Request $request, Project $project): View|JsonResponse
public function edit(Request $request, Project $project): View
public function update(UpdateProjectRequest $request, Project $project): JsonResponse|Response
public function destroy(Request $request, Project $project): JsonResponse|Response
public function bulkAction(Request $request): JsonResponse|Response
public function analytics(Request $request, ?Project $project = null): JsonResponse
public function templates(Request $request): JsonResponse|View
public function createFromTemplate(Request $request, ProjectTemplate $template): JsonResponse|Response
```

**Features**:
- Context-aware project management
- Template-based project creation
- Bulk operations
- Analytics integration
- Team management
- Budget tracking
- Progress monitoring

**Consolidated Controllers**:
- `Api/ProjectsController.php`
- `Web/ProjectController.php`
- `Web/OptimizedProjectController.php`
- `Api_backup/App/ProjectController.php`
- `Api_backup/App/ProjectsController.php`
- `Api_backup/App/ProjectsAnalyticsController.php`
- `Api_backup/App/ProjectsRealtimeController.php`
- `Api_backup/App/ProjectsIntegrationsController.php`
- `Api_backup/App/ProjectsAutomationController.php`
- `Api_backup/App/ProjectsSeriesController.php`
- `Api_backup/App/ProjectsOverviewController.php`
- `Api_backup/ProjectManagerController.php`
- `Api_backup/ProjectController.php`
- `Api_backup/ProjectTemplateController.php`
- `Api_backup/ProjectAnalyticsController.php`
- `Api_backup/ProjectMilestoneController.php`
- `ProjectTaskController.php`
- `Web/ProjectBulkController.php`
- `ProjectTemplateController.php`

### 5. ProjectShellRequest

**File**: `app/Http/Requests/ProjectShellRequest.php`

**Purpose**: Thống nhất validation rules cho project operations

**Validation Rules**:
- Create rules với context-specific validation
- Update rules với conditional validation
- Index rules cho filtering và pagination
- Bulk action rules
- Template creation rules
- Baseline creation rules

**Features**:
- Context-aware validation
- Auto-generation of fields (code, tenant_id, owner_id)
- Custom error messages
- Attribute mapping
- Data preparation

**Consolidated Requests**:
- `ProjectUpdateRequest.php`
- `ProjectStoreRequest.php`
- `ProjectCreateRequest.php`
- `StoreProjectRequest.php`
- `ProjectBulkCreateRequest.php`
- `ProjectFormRequest.php`
- `IndexProjectRequest.php`
- `CreateBaselineFromProjectRequest.php`
- `UpdateProjectRequest.php`

## Context Detection

### Automatic Context Detection

Tất cả Shell components tự động detect context dựa trên route name:

```php
private function determineContext(Request $request): string
{
    $route = $request->route();
    $routeName = $route ? $route->getName() : '';
    
    if (str_contains($routeName, 'admin')) {
        return 'admin';
    } elseif (str_contains($routeName, 'app')) {
        return 'app';
    } elseif (str_contains($routeName, 'api')) {
        return 'api';
    }
    
    return 'web';
}
```

### Context-Specific Behavior

**Admin Context**:
- Full system access
- Cross-tenant operations
- System-wide analytics
- Super admin permissions

**App Context**:
- Tenant-scoped operations
- User role-based permissions
- Tenant-specific analytics
- Standard user permissions

**API Context**:
- JSON responses
- API-specific error handling
- Token-based authentication
- RESTful conventions

**Web Context**:
- View responses
- Session-based authentication
- Web-specific error handling
- Form-based interactions

## Permission System

### Role-Based Access Control

```php
private function getPermissions(User $user, string $context): array
{
    $isSuperAdmin = $user->hasRole('super_admin');
    $isAdmin = $user->hasRole('admin');
    $isPM = $user->hasRole('project_manager');
    
    switch ($context) {
        case 'admin':
            return [
                'can_view' => $isSuperAdmin,
                'can_create' => $isSuperAdmin,
                'can_edit' => $isSuperAdmin,
                'can_delete' => $isSuperAdmin,
                'can_bulk_action' => $isSuperAdmin
            ];
        case 'app':
            return [
                'can_view' => $isAdmin || $isPM || $isSuperAdmin,
                'can_create' => $isAdmin || $isPM || $isSuperAdmin,
                'can_edit' => $isAdmin || $isPM || $isSuperAdmin,
                'can_delete' => $isAdmin || $isSuperAdmin,
                'can_bulk_action' => $isAdmin || $isPM || $isSuperAdmin
            ];
        default:
            return [
                'can_view' => true,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'can_bulk_action' => false
            ];
    }
}
```

### Permission Checks

Tất cả operations đều được kiểm tra permissions trước khi thực hiện:

```php
if (!$permissions['can_view']) {
    if ($request->expectsJson()) {
        return ApiResponse::error('Insufficient permissions', 403);
    }
    abort(403, 'Insufficient permissions');
}
```

## Error Handling

### Unified Error Responses

Tất cả Shell components sử dụng `ApiResponse` helper để đảm bảo consistent error handling:

```php
// Success responses
ApiResponse::success($data, 'Operation completed successfully');
ApiResponse::created($data, 'Resource created successfully');

// Error responses
ApiResponse::error('Operation failed', 500, null, 'OPERATION_ERROR');
ApiResponse::validationError($validator->errors());
```

### Error Codes

Standardized error codes cho debugging và monitoring:
- `USERS_FETCH_ERROR`
- `USER_CREATE_ERROR`
- `USER_UPDATE_ERROR`
- `USER_DELETE_ERROR`
- `PROJECTS_FETCH_ERROR`
- `PROJECT_CREATE_ERROR`
- `PROJECT_UPDATE_ERROR`
- `PROJECT_DELETE_ERROR`
- `BULK_ACTION_ERROR`

## API Integration

### Real-time Data

DashboardShell component tích hợp với API endpoints để fetch real-time data:

```javascript
async fetchKPIs() {
    const endpoint = this.variant === 'admin' ? '/api/admin/dashboard/kpis' : '/api/dashboard/kpis';
    const response = await fetch(endpoint, {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    });
    return await response.json();
}
```

### Chart Integration

DashboardShell hỗ trợ Chart.js integration với real-time data:

```javascript
renderCharts(chartData = null) {
    const charts = this.variant === 'admin' ? [
        { key: 'tenant-growth', type: 'line' },
        { key: 'user-distribution', type: 'doughnut' }
    ] : [
        { key: 'project-progress', type: 'doughnut' },
        { key: 'task-distribution', type: 'line' }
    ];
    
    charts.forEach(chart => {
        const ctx = document.getElementById(`${chart.key}-chart`);
        if (!ctx) return;
        
        const data = chartData?.[chart.key] || this.getMockChartData(chart.key, chart.type);
        this.createChart(ctx, chart.type, data);
    });
}
```

## Performance Optimizations

### Lazy Loading

Components sử dụng lazy loading cho heavy operations:

```javascript
// Load charts after data is ready
setTimeout(() => {
    this.loadCharts();
}, 500);
```

### Caching

API responses được cache để giảm server load:

```php
// Cache KPI data for 60 seconds
$kpis = Cache::remember("dashboard_kpis_{$user->tenant_id}", 60, function() use ($user) {
    return $this->calculateKPIs($user);
});
```

### Pagination

Tất cả list operations sử dụng pagination để optimize performance:

```php
$perPage = $request->input('per_page', 15);
return $query->paginate($perPage);
```

## Testing

### Unit Tests

Mỗi Shell component có unit tests để đảm bảo functionality:

```php
class UserShellControllerTest extends TestCase
{
    public function test_index_returns_users_for_app_context()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        
        $response = $this->get('/app/users');
        
        $response->assertStatus(200);
        $response->assertViewIs('app.users.index');
    }
}
```

### Integration Tests

Integration tests để verify context switching và permissions:

```php
public function test_admin_can_view_all_users_across_tenants()
{
    $admin = User::factory()->create(['role' => 'super_admin']);
    $this->actingAs($admin);
    
    $response = $this->get('/admin/users');
    
    $response->assertStatus(200);
    $response->assertViewIs('admin.users.index');
}
```

## Migration Guide

### Updating Existing Code

1. **Replace old controllers**:
```php
// Old
Route::get('/users', [UserController::class, 'index']);

// New
Route::get('/users', [UserShellController::class, 'index']);
```

2. **Update views**:
```blade
{{-- Old --}}
@include('components.header')

{{-- New --}}
<x-shared.header-shell variant="app" :user="Auth::user()" />
```

3. **Update requests**:
```php
// Old
class StoreUserRequest extends FormRequest

// New
class StoreUserRequest extends ProjectShellRequest
```

### Backward Compatibility

Shell components được thiết kế để maintain backward compatibility:
- Old routes vẫn hoạt động
- Old views vẫn render correctly
- Gradual migration approach

## Best Practices

### Development

1. **Always use context-aware methods**
2. **Implement proper permission checks**
3. **Use unified error handling**
4. **Follow naming conventions**
5. **Write comprehensive tests**

### Maintenance

1. **Regular code reviews**
2. **Performance monitoring**
3. **Security audits**
4. **Documentation updates**
5. **User feedback integration**

## Future Enhancements

### Planned Features

1. **Real-time notifications**
2. **Advanced analytics**
3. **Mobile app integration**
4. **Third-party integrations**
5. **AI-powered insights**

### Extensibility

Shell components được thiết kế để dễ dàng mở rộng:
- Plugin system
- Custom validators
- Additional contexts
- New permission types
- Enhanced UI components

## Conclusion

ZenaManage Shell Components cung cấp một foundation mạnh mẽ và scalable cho việc phát triển ứng dụng. Với architecture thống nhất, permission system robust, và error handling comprehensive, các components này giúp giảm duplicate code, tăng maintainability, và cải thiện developer experience.

Việc sử dụng Shell components không chỉ giúp code cleaner và more maintainable, mà còn đảm bảo consistency across toàn bộ application và provide better user experience.
