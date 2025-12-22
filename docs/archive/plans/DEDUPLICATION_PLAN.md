# Deduplication Plan & CI Guard Setup

## Executive Summary

**Problem**: 12 duplicate clusters causing ~7.8k LOC duplication, with 10 priority clusters accounting for ~5.2k LOC.

**Solution**: Systematic deduplication with CI guards to prevent future duplication.

**Impact**: 40-60% LOC reduction in dashboard/projects views, unified HeaderShell across /app/*, consolidated validators & rate-limit middleware.

## Priority Matrix

| Cluster | Type | LOC Impact | Risk Level | Effort | Priority |
|---------|------|------------|------------|--------|----------|
| CL-UI-001 | Header | ~1.2k | High | Medium | P0 |
| CL-UI-002 | Layout | ~800 | High | Small | P0 |
| CL-UI-003 | Dashboard | ~1.5k | High | Large | P1 |
| CL-UI-004 | Projects | ~1.0k | Medium | Medium | P1 |
| CL-BE-005 | User Controller | ~900 | High | Medium | P1 |
| CL-BE-006 | Project Controller | ~800 | Medium | Medium | P2 |
| CL-BE-007 | Rate Limit | ~600 | High | Medium | P1 |
| CL-DATA-008 | Project Requests | ~400 | Medium | Small | P2 |
| CL-STYLE-009 | Z-index | ~200 | Low | Small | P3 |
| CL-API-010 | Login Endpoint | ~300 | High | Small | P1 |
| CL-FE-011 | Notifications | ~500 | Medium | Medium | P2 |

## Phase 1: CI Guard Setup (Week 1)

### 1.1 Install Duplication Detection Tools

```bash
# JavaScript/TypeScript duplication
npm install --save-dev jscpd

# PHP duplication  
composer require --dev phpcpd/phpcpd

# ESLint rules for code quality
npm install --save-dev eslint-plugin-sonarjs

# Tailwind class sorting
npm install --save-dev @tailwindcss/forms @tailwindcss/typography
```

### 1.2 Configure CI Pipeline

```yaml
# .github/workflows/deduplication-check.yml
name: Deduplication Check
on: [push, pull_request]

jobs:
  duplication-check:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'
          
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          
      - name: Install dependencies
        run: |
          npm install
          composer install --no-dev
          
      - name: Check JavaScript duplication
        run: npx jscpd --min-lines 10 --min-tokens 50 --threshold 5 --reporters console,html --output ./reports/jscpd resources/js src/
        
      - name: Check PHP duplication
        run: vendor/bin/phpcpd --min-lines 10 --min-tokens 50 --threshold 5 --log-pmd ./reports/phpcpd.xml app/
        
      - name: ESLint with SonarJS rules
        run: npx eslint resources/js src/ --ext .js,.ts,.jsx,.tsx --config .eslintrc.sonarjs.js
        
      - name: Upload reports
        uses: actions/upload-artifact@v3
        with:
          name: duplication-reports
          path: reports/
```

### 1.3 ESLint Configuration

```javascript
// .eslintrc.sonarjs.js
module.exports = {
  extends: [
    'eslint:recommended',
    'plugin:sonarjs/recommended'
  ],
  plugins: ['sonarjs'],
  rules: {
    'sonarjs/cognitive-complexity': 'error',
    'sonarjs/no-duplicate-string': ['error', { threshold: 3 }],
    'sonarjs/no-identical-functions': 'error',
    'sonarjs/no-redundant-boolean': 'error',
    'sonarjs/no-unused-collection': 'error',
    'sonarjs/prefer-immediate-return': 'error',
    'sonarjs/prefer-single-boolean-return': 'error'
  }
};
```

### 1.4 Pre-commit Hooks

```bash
# .husky/pre-commit
#!/bin/sh
. "$(dirname "$0")/_/husky.sh"

# Check for duplication before commit
npx jscpd --min-lines 5 --min-tokens 30 --threshold 3 --reporters console
vendor/bin/phpcpd --min-lines 5 --min-tokens 30 --threshold 3

# ESLint check
npx eslint --fix resources/js src/

# Tailwind class sorting
npx tailwindcss --input resources/css/app.css --output public/css/app.css --watch
```

## Phase 2: High Priority Deduplication (Week 2-3)

### 2.1 CL-UI-001: Header Components Consolidation

**Current State**: 5 header variants with ~1.2k LOC duplication
- `resources/views/components/shared/header.blade.php`
- `resources/views/layouts/app.blade.php` (header section)
- `resources/views/layouts/app-fixed.blade.php`
- `resources/views/components/admin/header.blade.php`
- `src/components/ui/header/HeaderShell.tsx`

**Target**: Single HeaderShell (React) + Blade wrapper

**Implementation**:

```typescript
// src/components/ui/header/HeaderShell.tsx
interface HeaderShellProps {
  user: User;
  tenant: Tenant;
  navigation: NavigationItem[];
  notifications: Notification[];
  theme: 'light' | 'dark';
  onThemeToggle: () => void;
  onNotificationClick: () => void;
  onUserMenuClick: () => void;
}

export const HeaderShell: React.FC<HeaderShellProps> = ({
  user, tenant, navigation, notifications, theme,
  onThemeToggle, onNotificationClick, onUserMenuClick
}) => {
  return (
    <header className="bg-white shadow-sm border-b border-gray-200 fixed top-0 left-0 right-0 z-header">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-full">
          {/* Logo + Brand */}
          <div className="flex items-center space-x-4">
            <div className="flex items-center space-x-2">
              <div className="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                <i className="fas fa-cube text-white text-sm"></i>
              </div>
              <span className="text-xl font-bold text-gray-900">ZenaManage</span>
            </div>
            
            <div className="hidden md:block">
              <span className="text-sm text-gray-600">
                Hello, <span className="font-medium text-gray-900">{user.first_name}</span>
              </span>
            </div>
          </div>
          
          {/* Navigation */}
          <nav className="hidden lg:flex items-center space-x-8">
            {navigation.map(item => (
              <a key={item.key} href={item.href} className="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                <i className={`${item.icon} mr-2`}></i>{item.label}
              </a>
            ))}
          </nav>
          
          {/* Actions */}
          <div className="flex items-center space-x-3">
            <NotificationDropdown notifications={notifications} />
            <ThemeToggle theme={theme} onToggle={onThemeToggle} />
            <UserMenu user={user} tenant={tenant} />
          </div>
        </div>
      </div>
    </header>
  );
};
```

```blade
{{-- resources/views/components/shared/header-shell.blade.php --}}
@props([
    'user' => null,
    'tenant' => null,
    'navigation' => [],
    'notifications' => [],
    'theme' => 'light'
])

<div id="header-shell" 
     data-user="{{ json_encode($user) }}"
     data-tenant="{{ json_encode($tenant) }}"
     data-navigation="{{ json_encode($navigation) }}"
     data-notifications="{{ json_encode($notifications) }}"
     data-theme="{{ $theme }}">
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const headerElement = document.getElementById('header-shell');
    if (headerElement) {
        ReactDOM.render(
            React.createElement(HeaderShell, {
                user: JSON.parse(headerElement.dataset.user),
                tenant: JSON.parse(headerElement.dataset.tenant),
                navigation: JSON.parse(headerElement.dataset.navigation),
                notifications: JSON.parse(headerElement.dataset.notifications),
                theme: headerElement.dataset.theme,
                onThemeToggle: () => window.toggleTheme(),
                onNotificationClick: () => window.showNotifications(),
                onUserMenuClick: () => window.toggleUserMenu()
            }),
            headerElement
        );
    }
});
</script>
@endpush
```

**Migration Steps**:
1. Create HeaderShell React component
2. Create Blade wrapper component
3. Update all layouts to use `<x-shared.header-shell>`
4. Remove old header components
5. Test across all pages

### 2.2 CL-UI-002: Layout Consolidation

**Current State**: 4 layout variants with ~800 LOC duplication
- `resources/views/layouts/app-layout.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/app-fixed.blade.php`
- `resources/views/layouts/app-backup.blade.php`

**Target**: Single `layouts/app.blade.php` with slots

**Implementation**:

```blade
{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" 
      x-data="appLayout()" 
      :class="{ 'dark': theme === 'dark' }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', 'Dashboard') - ZenaManage</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @yield('head')
</head>

<body class="bg-gray-50 dark:bg-gray-900 transition-colors duration-200">
    <!-- Header -->
    <x-shared.header-shell 
        :user="Auth::user()" 
        :tenant="Auth::user()->tenant ?? null"
        :navigation="$navigation ?? []"
        :notifications="$notifications ?? []"
        :theme="$theme ?? 'light'"
    />
    
    <!-- Breadcrumbs -->
    @hasSection('breadcrumbs')
        <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
                @yield('breadcrumbs')
            </div>
        </nav>
    @endif
    
    <!-- Main Content -->
    <main class="pt-20">
        @hasSection('kpi-strip')
            <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    @yield('kpi-strip')
                </div>
            </div>
        @endif
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            @yield('content')
        </div>
    </main>
    
    <!-- Footer -->
    @hasSection('footer')
        <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                @yield('footer')
            </div>
        </footer>
    @endif
    
    <!-- Scripts -->
    @stack('scripts')
</body>
</html>
```

**Migration Steps**:
1. Consolidate common parts into single layout
2. Use slots for optional sections
3. Remove backup layouts
4. Update all views to use new layout
5. Test responsive design

### 2.3 CL-UI-003: Dashboard Consolidation

**Current State**: 3 dashboard variants with ~1.5k LOC duplication
- `resources/views/app/dashboard/index.blade.php`
- `resources/views/app/dashboard-new.blade.php`
- `resources/views/app/dashboard.blade.php`

**Target**: Single React component + Blade shell

**Implementation**:

```typescript
// src/pages/app/dashboard.tsx
import React, { useState, useEffect } from 'react';
import { KPIWidget } from '../components/dashboard/KPIWidget';
import { ChartWidget } from '../components/dashboard/ChartWidget';
import { ActivityList } from '../components/dashboard/ActivityList';

interface DashboardData {
  kpis: {
    projects: { total: number; change: number };
    users: { active: number; change: number };
    progress: { overall: number; change: number };
    budget: { utilization: number; change: number };
  };
  charts: {
    projectProgress: any;
    taskDistribution: any;
  };
  activities: Activity[];
}

export const DashboardPage: React.FC = () => {
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadDashboardData();
  }, []);

  const loadDashboardData = async () => {
    try {
      setLoading(true);
      const [kpis, charts, activities] = await Promise.all([
        fetch('/api/dashboard/kpis').then(r => r.json()),
        fetch('/api/dashboard/charts').then(r => r.json()),
        fetch('/api/dashboard/recent-activity').then(r => r.json())
      ]);
      
      setData({ kpis: kpis.data, charts: charts.data, activities: activities.data });
    } catch (err) {
      setError('Failed to load dashboard data');
    } finally {
      setLoading(false);
    }
  };

  if (loading) return <DashboardSkeleton />;
  if (error) return <DashboardError error={error} onRetry={loadDashboardData} />;

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Page Header */}
      <div className="bg-white shadow-sm border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
              <p className="mt-1 text-sm text-gray-600">
                Welcome back, <span className="font-medium text-gray-900">User</span>
              </p>
            </div>
            <div className="flex items-center space-x-3">
              <button onClick={loadDashboardData} className="btn btn-secondary">
                <i className="fas fa-sync-alt mr-2"></i>Refresh
              </button>
              <button className="btn btn-primary">
                <i className="fas fa-plus mr-2"></i>New Project
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* KPI Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <KPIWidget
            title="Total Projects"
            value={data.kpis.projects.total}
            change={data.kpis.projects.change}
            icon="fas fa-project-diagram"
            color="blue"
          />
          <KPIWidget
            title="Active Users"
            value={data.kpis.users.active}
            change={data.kpis.users.change}
            icon="fas fa-users"
            color="green"
          />
          <KPIWidget
            title="Average Progress"
            value={`${data.kpis.progress.overall}%`}
            change={data.kpis.progress.change}
            icon="fas fa-chart-line"
            color="purple"
          />
          <KPIWidget
            title="Budget Utilization"
            value={`${data.kpis.budget.utilization}%`}
            change={data.kpis.budget.change}
            icon="fas fa-dollar-sign"
            color="yellow"
          />
        </div>

        {/* Charts */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
          <ChartWidget
            title="Project Status Distribution"
            type="doughnut"
            data={data.charts.projectProgress}
            height={300}
          />
          <ChartWidget
            title="Project Progress Over Time"
            type="line"
            data={data.charts.taskDistribution}
            height={300}
          />
        </div>

        {/* Recent Activity */}
        <div className="bg-white shadow-sm rounded-lg border border-gray-200">
          <div className="px-6 py-4 border-b border-gray-200">
            <h2 className="text-lg font-semibold text-gray-900">Recent Activity</h2>
          </div>
          <div className="p-6">
            <ActivityList activities={data.activities} />
          </div>
        </div>
      </main>
    </div>
  );
};
```

**Migration Steps**:
1. Create React DashboardPage component
2. Create shared KPIWidget, ChartWidget, ActivityList components
3. Create Blade shell for dashboard
4. Remove old dashboard views
5. Test data binding and responsiveness

## Phase 3: Medium Priority Deduplication (Week 4-5)

### 3.1 CL-BE-005: User Controller Consolidation

**Current State**: 4 user controller variants with ~900 LOC duplication
- `app/Http/Controllers/UserController.php`
- `app/Http/Controllers/UserControllerV2.php`
- `app/Http/Controllers/Api/App/UserController.php`
- `app/Http/Controllers/Api/Admin/UserController.php`

**Target**: Single UserController with policy-based access control

**Implementation**:

```php
<?php
// app/Http/Controllers/Api/UserController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Services\UserManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UserManagementService $userService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('ability:tenant');
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        
        $filters = $request->only(['search', 'status', 'role', 'sort_by', 'sort_direction']);
        $perPage = min($request->get('per_page', 15), 100);
        
        $users = $this->userService->listUsers($filters, $perPage);
        
        return ApiResponse::paginated($users, 'Users retrieved successfully');
    }

    public function store(UserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);
        
        $userData = $request->validated();
        $user = $this->userService->createUser($userData);
        
        return ApiResponse::created($user, 'User created successfully');
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);
        
        return ApiResponse::success($user, 'User retrieved successfully');
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);
        
        $userData = $request->validated();
        $updatedUser = $this->userService->updateUser($user, $userData);
        
        return ApiResponse::success($updatedUser, 'User updated successfully');
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);
        
        $this->userService->deleteUser($user);
        
        return ApiResponse::success(null, 'User deleted successfully');
    }
}
```

### 3.2 CL-BE-007: Rate Limit Middleware Consolidation

**Current State**: 2 rate limit middleware variants with ~600 LOC duplication
- `app/Http/Middleware/AdvancedRateLimitMiddleware.php`
- `app/Http/Middleware/EnhancedRateLimitMiddleware.php`

**Target**: Single configurable RateLimitMiddleware

**Implementation**:

```php
<?php
// app/Http/Middleware/RateLimitMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;

class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next, string $ability = 'default')
    {
        $key = $this->resolveRequestSignature($request, $ability);
        $maxAttempts = $this->getMaxAttempts($ability);
        $decayMinutes = $this->getDecayMinutes($ability);
        
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);
            
            Log::warning('Rate limit exceeded', [
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
                'ability' => $ability,
                'retry_after' => $retryAfter
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Too many requests',
                'retry_after' => $retryAfter
            ], 429)->header('Retry-After', $retryAfter);
        }
        
        RateLimiter::hit($key, $decayMinutes * 60);
        
        $response = $next($request);
        
        return $response->header('X-RateLimit-Limit', $maxAttempts)
                       ->header('X-RateLimit-Remaining', RateLimiter::remaining($key, $maxAttempts));
    }
    
    private function resolveRequestSignature(Request $request, string $ability): string
    {
        $user = $request->user();
        $ip = $request->ip();
        
        return $ability . '|' . ($user ? $user->id : $ip);
    }
    
    private function getMaxAttempts(string $ability): int
    {
        return config("rate_limit.{$ability}.max_attempts", 60);
    }
    
    private function getDecayMinutes(string $ability): int
    {
        return config("rate_limit.{$ability}.decay_minutes", 1);
    }
}
```

## Phase 4: Low Priority Deduplication (Week 6)

### 4.1 CL-DATA-008: Project Requests Consolidation

**Implementation**:

```php
<?php
// app/Http/Requests/ProjectRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\ProjectStatus;
use App\Enums\ProjectPriority;

abstract class ProjectRequest extends FormRequest
{
    protected const RULES = [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string|max:1000',
        'status' => 'required|in:' . ProjectStatus::values(),
        'priority' => 'required|in:' . ProjectPriority::values(),
        'budget_total' => 'nullable|numeric|min:0',
        'start_date' => 'nullable|date',
        'end_date' => 'nullable|date|after:start_date',
        'tags' => 'nullable|array',
        'tags.*' => 'string|max:50'
    ];
    
    public function authorize(): bool
    {
        return $this->user()->can('manage', Project::class);
    }
    
    abstract public function rules(): array;
}

// app/Http/Requests/ProjectCreateRequest.php
class ProjectCreateRequest extends ProjectRequest
{
    public function rules(): array
    {
        return array_merge(self::RULES, [
            'name' => 'required|string|max:255|unique:projects,name',
            'budget_total' => 'required|numeric|min:1000'
        ]);
    }
}

// app/Http/Requests/ProjectUpdateRequest.php
class ProjectUpdateRequest extends ProjectRequest
{
    public function rules(): array
    {
        return array_merge(self::RULES, [
            'name' => 'required|string|max:255|unique:projects,name,' . $this->route('project')->id
        ]);
    }
}
```

### 4.2 CL-STYLE-009: Z-index System Consolidation

**Implementation**:

```typescript
// tailwind.config.ts
export default {
  theme: {
    extend: {
      zIndex: {
        'nav': 10,
        'dropdown': 20,
        'sticky': 30,
        'fixed': 40,
        'modal': 50,
        'popover': 60,
        'tooltip': 70,
        'toast': 80,
        'max': 9999
      }
    }
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
    // Auto-generate CSS utilities from config
    function({ addUtilities, theme }) {
      const zIndex = theme('zIndex');
      const utilities = {};
      
      Object.entries(zIndex).forEach(([key, value]) => {
        utilities[`.z-${key}`] = { 'z-index': value };
      });
      
      addUtilities(utilities);
    }
  ]
};
```

## CI Guard Configuration

### Package.json Scripts

```json
{
  "scripts": {
    "dedupe:check": "jscpd --min-lines 10 --min-tokens 50 --threshold 5 --reporters console,html --output ./reports/jscpd resources/js src/",
    "dedupe:php": "vendor/bin/phpcpd --min-lines 10 --min-tokens 50 --threshold 5 --log-pmd ./reports/phpcpd.xml app/",
    "lint:sonar": "eslint resources/js src/ --ext .js,.ts,.jsx,.tsx --config .eslintrc.sonarjs.js",
    "lint:fix": "eslint resources/js src/ --ext .js,.ts,.jsx,.tsx --fix",
    "css:sort": "tailwindcss --input resources/css/app.css --output public/css/app.css --watch",
    "ci:check": "npm run dedupe:check && npm run dedupe:php && npm run lint:sonar"
  }
}
```

### Pre-commit Configuration

```bash
#!/bin/sh
# .husky/pre-commit

# Check for duplication
npm run dedupe:check
vendor/bin/phpcpd --min-lines 5 --min-tokens 30 --threshold 3

# Lint and fix
npm run lint:fix

# Sort Tailwind classes
npm run css:sort
```

## Success Metrics

### Quantitative Goals
- **LOC Reduction**: 40-60% reduction in dashboard/projects views
- **Duplicate Detection**: <5% similarity threshold in CI
- **Code Quality**: 0 SonarJS violations
- **Performance**: <500ms page load time

### Qualitative Goals
- **Consistency**: Unified HeaderShell across all /app/* pages
- **Maintainability**: Single source of truth for components
- **Developer Experience**: Faster development with shared components
- **Code Quality**: Reduced cognitive complexity

## Risk Mitigation

### Technical Risks
- **Breaking Changes**: Gradual migration with feature flags
- **Performance Impact**: Lazy loading for heavy components
- **Browser Compatibility**: Polyfills for older browsers

### Process Risks
- **Team Adoption**: Training sessions and documentation
- **Timeline Delays**: Phased approach with rollback plans
- **Quality Regression**: Comprehensive testing strategy

## Timeline

| Week | Phase | Deliverables |
|------|-------|-------------|
| 1 | CI Guard Setup | jscpd, phpcpd, ESLint config, pre-commit hooks |
| 2 | Header Consolidation | HeaderShell React component, Blade wrapper |
| 3 | Layout Consolidation | Single app.blade.php layout |
| 4 | Dashboard Consolidation | React DashboardPage component |
| 5 | Controller Consolidation | Unified UserController, RateLimitMiddleware |
| 6 | Final Cleanup | Project requests, Z-index system |

## Conclusion

This deduplication plan will significantly reduce code duplication while establishing CI guards to prevent future duplication. The phased approach ensures minimal disruption while delivering maximum impact on code quality and maintainability.
