# Dashboard Component List

## 1. HeaderShell Component

### Props
```typescript
interface HeaderShellProps {
  user: User;
  tenant: Tenant;
  notifications: Notification[];
  unreadCount: number;
  theme: 'light' | 'dark';
  onThemeToggle: () => void;
  onNotificationClick: () => void;
  onUserMenuClick: () => void;
}
```

### Features
- Logo + brand
- Primary navigation (RBAC-filtered)
- Secondary actions (notifications, theme toggle, focus mode)
- User menu with tenant switching
- Sticky + condensed on scroll
- Responsive design
- Dark/light mode toggle

## 2. KPIWidget Component

### Props
```typescript
interface KPIWidgetProps {
  title: string;
  value: number | string;
  change?: number;
  changeType?: 'increase' | 'decrease' | 'neutral';
  icon: string;
  color: 'blue' | 'green' | 'yellow' | 'red' | 'purple';
  loading?: boolean;
  error?: string;
  onClick?: () => void;
  href?: string;
}
```

### Features
- Real-time data from API
- Loading skeleton animation
- Error state handling
- Change indicator with trend
- Clickable for navigation
- Responsive sizing
- Accessibility compliant

### Usage
```blade
<x-dashboard.kpi-widget 
    title="Total Projects"
    :value="$kpis.projects.total"
    :change="$kpis.projects.change"
    change-type="increase"
    icon="fas fa-project-diagram"
    color="blue"
    :loading="$loading"
    href="{{ route('app.projects.index') }}"
/>
```

## 3. ChartWidget Component

### Props
```typescript
interface ChartWidgetProps {
  title: string;
  type: 'line' | 'bar' | 'pie' | 'doughnut';
  data: ChartData;
  options?: ChartOptions;
  loading?: boolean;
  error?: string;
  filters?: Filter[];
  onFilterChange?: (filters: Filter[]) => void;
  onExport?: () => void;
  exportable?: boolean;
  height?: number;
  responsive?: boolean;
}
```

### Features
- Chart.js integration
- Real-time data updates
- Time-based filtering (7d, 30d, custom)
- Export functionality (RBAC-controlled)
- Loading states
- Error handling
- Responsive design
- Accessibility support

### Usage
```blade
<x-dashboard.chart-widget 
    title="Project Progress"
    type="line"
    :data="$chartData"
    :filters="$timeFilters"
    :on-filter-change="handleFilterChange"
    :on-export="handleExport"
    :exportable="canExport"
    height="300"
    :responsive="true"
/>
```

## 4. ActivityList Component

### Props
```typescript
interface ActivityListProps {
  activities: Activity[];
  loading?: boolean;
  error?: string;
  maxItems?: number;
  onViewAll?: () => void;
  onExport?: () => void;
  exportable?: boolean;
}
```

### Features
- Real-time activity feed
- Clickable items for navigation
- Lazy loading for large lists
- Export functionality
- Loading states
- Error handling
- Responsive design

### Usage
```blade
<x-dashboard.activity-list 
    :activities="$recentActivities"
    :loading="$loading"
    :max-items="10"
    :on-view-all="handleViewAll"
    :on-export="handleExport"
    :exportable="canExport"
/>
```

## 5. ActivityItem Component

### Props
```typescript
interface ActivityItemProps {
  id: string;
  type: 'project' | 'task' | 'user' | 'system';
  action: string;
  description: string;
  timestamp: string;
  user: User;
  metadata?: Record<string, any>;
  onClick?: () => void;
  href?: string;
}
```

### Features
- Icon based on type
- Relative time display
- User attribution
- Clickable for navigation
- Hover effects
- Accessibility support

### Usage
```blade
<x-dashboard.activity-item 
    :id="$activity.id"
    :type="$activity.type"
    :action="$activity.action"
    :description="$activity.description"
    :timestamp="$activity.timestamp"
    :user="$activity.user"
    :href="$activity.href"
/>
```

## 6. ActionButton Component

### Props
```typescript
interface ActionButtonProps {
  label: string;
  icon?: string;
  variant?: 'primary' | 'secondary' | 'success' | 'warning' | 'danger';
  size?: 'sm' | 'md' | 'lg';
  loading?: boolean;
  disabled?: boolean;
  onClick?: () => void;
  href?: string;
  permissions?: string[];
  userRole?: UserRole;
}
```

### Features
- RBAC-controlled visibility
- Loading states
- Disabled states
- Icon support
- Multiple variants
- Responsive sizing
- Accessibility compliant

### Usage
```blade
<x-dashboard.action-button 
    label="Create Project"
    icon="fas fa-plus"
    variant="primary"
    size="md"
    :loading="$creating"
    :on-click="handleCreateProject"
    permissions="['projects.create']"
    :user-role="$user.role"
/>
```

## 7. FilterBar Component

### Props
```typescript
interface FilterBarProps {
  filters: Filter[];
  values: Record<string, any>;
  onChange: (values: Record<string, any>) => void;
  onReset: () => void;
  onApply: () => void;
  loading?: boolean;
}
```

### Features
- Time-based filtering
- Custom date ranges
- Real-time updates
- Reset functionality
- Loading states
- Responsive design

### Usage
```blade
<x-dashboard.filter-bar 
    :filters="$timeFilters"
    :values="$filterValues"
    :on-change="handleFilterChange"
    :on-reset="handleFilterReset"
    :on-apply="handleFilterApply"
    :loading="$loading"
/>
```

## 8. LoadingSkeleton Component

### Props
```typescript
interface LoadingSkeletonProps {
  type: 'kpi' | 'chart' | 'activity' | 'table';
  count?: number;
  height?: number;
  width?: string;
}
```

### Features
- Multiple skeleton types
- Configurable dimensions
- Smooth animations
- Responsive design

### Usage
```blade
<x-dashboard.loading-skeleton 
    type="kpi"
    :count="4"
    height="120"
/>
```

## 9. ErrorState Component

### Props
```typescript
interface ErrorStateProps {
  title: string;
  message: string;
  icon?: string;
  onRetry?: () => void;
  retryable?: boolean;
}
```

### Features
- Clear error messaging
- Retry functionality
- Icon support
- Accessibility compliant

### Usage
```blade
<x-dashboard.error-state 
    title="Failed to load data"
    message="Unable to fetch dashboard data. Please try again."
    icon="fas fa-exclamation-triangle"
    :on-retry="handleRetry"
    :retryable="true"
/>
```

## 10. EmptyState Component

### Props
```typescript
interface EmptyStateProps {
  title: string;
  description: string;
  icon?: string;
  action?: {
    label: string;
    onClick: () => void;
  };
}
```

### Features
- Clear messaging
- Action buttons
- Icon support
- Accessibility compliant

### Usage
```blade
<x-dashboard.empty-state 
    title="No activities yet"
    description="Activity will appear here as you work on projects."
    icon="fas fa-history"
    :action="$createAction"
/>
```

## Component Integration

### Dashboard Layout
```blade
@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- HeaderShell is already included in layout -->
    
    <!-- Breadcrumbs -->
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <ol class="flex items-center space-x-2 text-sm text-gray-500">
                <li><a href="{{ route('app.dashboard') }}" class="hover:text-gray-700">Dashboard</a></li>
            </ol>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Welcome back, <span class="font-medium text-gray-900">{{ Auth::user()->first_name }}</span>
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <x-dashboard.action-button 
                        label="Refresh"
                        icon="fas fa-sync-alt"
                        variant="secondary"
                        :on-click="refreshDashboard"
                    />
                    <x-dashboard.action-button 
                        label="New Project"
                        icon="fas fa-plus"
                        variant="primary"
                        href="{{ route('app.projects.create') }}"
                        permissions="['projects.create']"
                        :user-role="Auth::user()->role"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- KPI Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <x-dashboard.kpi-widget 
                title="Total Projects"
                :value="$kpis.projects.total"
                :change="$kpis.projects.change"
                change-type="increase"
                icon="fas fa-project-diagram"
                color="blue"
                :loading="$loading"
                href="{{ route('app.projects.index') }}"
            />
            <x-dashboard.kpi-widget 
                title="Active Users"
                :value="$kpis.users.active"
                :change="$kpis.users.change"
                change-type="increase"
                icon="fas fa-users"
                color="green"
                :loading="$loading"
            />
            <x-dashboard.kpi-widget 
                title="Average Progress"
                :value="$kpis.progress.overall"
                :change="$kpis.progress.change"
                change-type="increase"
                icon="fas fa-chart-line"
                color="purple"
                :loading="$loading"
            />
            <x-dashboard.kpi-widget 
                title="Total Revenue"
                :value="$kpis.revenue.total"
                :change="$kpis.revenue.change"
                change-type="increase"
                icon="fas fa-dollar-sign"
                color="yellow"
                :loading="$loading"
            />
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <x-dashboard.chart-widget 
                title="Project Progress"
                type="line"
                :data="$progressChartData"
                :filters="$timeFilters"
                :on-filter-change="handleProgressFilterChange"
                :on-export="handleProgressExport"
                :exportable="canExport"
                height="300"
            />
            <x-dashboard.chart-widget 
                title="Project Status Distribution"
                type="doughnut"
                :data="$statusChartData"
                :on-export="handleStatusExport"
                :exportable="canExport"
                height="300"
            />
        </div>

        <!-- Activity Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Recent Activity</h2>
                    <div class="flex items-center space-x-2">
                        <x-dashboard.action-button 
                            label="View All"
                            variant="secondary"
                            size="sm"
                            href="{{ route('app.activities.index') }}"
                        />
                        <x-dashboard.action-button 
                            label="Export"
                            variant="secondary"
                            size="sm"
                            icon="fas fa-download"
                            :on-click="handleActivityExport"
                            :exportable="canExport"
                        />
                    </div>
                </div>
            </div>
            <div class="p-6">
                <x-dashboard.activity-list 
                    :activities="$recentActivities"
                    :loading="$loading"
                    :max-items="10"
                />
            </div>
        </div>
    </main>
</div>
@endsection
```

## Data Management

### Alpine.js Data Structure
```javascript
Alpine.data('dashboard', () => ({
  // State
  loading: false,
  error: null,
  kpis: null,
  charts: null,
  activities: [],
  filters: {},
  
  // Methods
  async init() {
    await this.loadData();
  },
  
  async loadData() {
    this.loading = true;
    try {
      const [kpis, charts, activities] = await Promise.all([
        this.fetchKPIs(),
        this.fetchCharts(),
        this.fetchActivities()
      ]);
      
      this.kpis = kpis;
      this.charts = charts;
      this.activities = activities;
    } catch (error) {
      this.error = error.message;
    } finally {
      this.loading = false;
    }
  },
  
  async fetchKPIs() {
    const response = await fetch('/api/dashboard/kpis');
    return await response.json();
  },
  
  async fetchCharts() {
    const response = await fetch('/api/dashboard/charts');
    return await response.json();
  },
  
  async fetchActivities() {
    const response = await fetch('/api/dashboard/recent-activity');
    return await response.json();
  },
  
  handleFilterChange(filters) {
    this.filters = filters;
    this.loadData();
  },
  
  refreshDashboard() {
    this.loadData();
  }
}));
```

## Testing Strategy

### Unit Tests
- KPIWidget data rendering
- ChartWidget data visualization
- ActivityList data display
- FilterBar functionality
- ActionButton permissions

### Integration Tests
- API data fetching
- Component interactions
- Error handling
- Loading states

### E2E Tests
- Dashboard load
- KPI display
- Chart rendering
- Activity feed
- Action buttons
- Responsive design
