# App UI Guide

## Overview

This guide provides comprehensive documentation for the redesigned `/app/*` pages in ZenaManage, following Project Rules compliance and modern UI/UX best practices.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Component Library](#component-library)
3. [Page Layouts](#page-layouts)
4. [Data Integration](#data-integration)
5. [RBAC Implementation](#rbac-implementation)
6. [Responsive Design](#responsive-design)
7. [Accessibility](#accessibility)
8. [Performance Optimization](#performance-optimization)
9. [Testing Strategy](#testing-strategy)
10. [Development Guidelines](#development-guidelines)

## Architecture Overview

### Design System Principles

The App UI follows these core principles:

- **Component-Based**: Reusable, composable UI components
- **Data-Driven**: All UI elements bound to real data sources
- **RBAC-Aware**: Role-based access control throughout
- **Tenant-Scoped**: Multi-tenant isolation at UI level
- **Performance-First**: Optimized for speed and efficiency
- **Accessibility-First**: WCAG 2.1 AA compliance

### Technology Stack

- **Frontend**: Blade templates + Alpine.js + Tailwind CSS
- **Charts**: Chart.js for data visualization
- **Icons**: Font Awesome 6
- **State Management**: Alpine.js reactive data
- **API Communication**: Fetch API with structured error handling

### File Structure

```
resources/views/app/
├── dashboard-new.blade.php      # Dashboard with KPIs and charts
├── projects-new.blade.php       # Projects list with filtering
├── tasks-new.blade.php          # Tasks management
├── reports-new.blade.php         # Reports and analytics
├── users-new.blade.php          # User management
├── settings-new.blade.php       # Settings and configuration
└── profile-new.blade.php        # User profile

resources/views/components/shared/
├── header.blade.php             # HeaderShell component
├── kpi-card.blade.php           # KPI display component
├── data-table.blade.php         # Table with pagination
├── filter-bar.blade.php         # Search and filtering
├── chart.blade.php              # Chart visualization
├── modal.blade.php              # Modal dialogs
└── empty-state.blade.php        # Empty state display
```

## Component Library

### Core Components

#### 1. KpiCard Component

**Purpose**: Display key performance indicators with trend data

**Usage**:
```blade
<x-shared.kpi-card 
    title="Total Projects"
    value="12"
    change="15"
    change-type="increase"
    icon="fas fa-project-diagram"
    color="blue"
    :loading="false"
/>
```

**Props**:
- `title` (string): KPI title
- `value` (number|string): Current value
- `change` (number): Percentage change
- `change-type` (string): 'increase' | 'decrease' | 'neutral'
- `icon` (string): Font Awesome icon class
- `color` (string): Color theme
- `loading` (boolean): Loading state

#### 2. DataTable Component

**Purpose**: Display tabular data with sorting, filtering, and pagination

**Usage**:
```blade
<x-shared.data-table 
    :data="$projects"
    :columns="$columns"
    :pagination="$pagination"
    :sorting="$sorting"
    :filtering="$filtering"
    :loading="false"
    empty-state-title="No projects found"
    empty-state-description="Get started by creating your first project."
/>
```

**Props**:
- `data` (array): Table data
- `columns` (array): Column definitions
- `pagination` (object): Pagination info
- `sorting` (object): Sorting configuration
- `filtering` (object): Filtering options
- `loading` (boolean): Loading state
- `empty-state-title` (string): Empty state title
- `empty-state-description` (string): Empty state description

#### 3. Chart Component

**Purpose**: Display data visualizations

**Usage**:
```blade
<x-shared.chart 
    type="line"
    :data="$chartData"
    :options="$chartOptions"
    height="300"
    :loading="false"
/>
```

**Props**:
- `type` (string): Chart type ('line' | 'bar' | 'pie' | 'doughnut')
- `data` (object): Chart data
- `options` (object): Chart options
- `height` (number): Chart height
- `loading` (boolean): Loading state

#### 4. FilterBar Component

**Purpose**: Provide search and filtering capabilities

**Usage**:
```blade
<x-shared.filter-bar 
    :filters="$filters"
    :values="$filterValues"
    @change="handleFilterChange"
    @reset="handleFilterReset"
    @apply="handleFilterApply"
/>
```

**Props**:
- `filters` (array): Filter definitions
- `values` (object): Current filter values
- `@change`: Filter change event
- `@reset`: Filter reset event
- `@apply`: Filter apply event

#### 5. Modal Component

**Purpose**: Display modal dialogs

**Usage**:
```blade
<x-shared.modal 
    :is-open="$showModal"
    @close="$showModal = false"
    title="Create Project"
    size="lg"
    :closable="true"
>
    <!-- Modal content -->
</x-shared.modal>
```

**Props**:
- `is-open` (boolean): Modal visibility
- `@close`: Close event handler
- `title` (string): Modal title
- `size` (string): Modal size ('sm' | 'md' | 'lg' | 'xl')
- `closable` (boolean): Show close button

### Layout Components

#### 1. PageLayout Component

**Purpose**: Standard page layout with header and content

**Usage**:
```blade
<x-shared.page-layout 
    title="Projects"
    description="Manage your projects and track progress"
    :breadcrumbs="$breadcrumbs"
    :header-actions="$headerActions"
    :loading="false"
>
    <!-- Page content -->
</x-shared.page-layout>
```

#### 2. GridLayout Component

**Purpose**: Responsive grid layout

**Usage**:
```blade
<x-shared.grid-layout 
    :columns="3"
    :gap="6"
    :responsive="true"
>
    <!-- Grid items -->
</x-shared.grid-layout>
```

#### 3. CardLayout Component

**Purpose**: Card-based content layout

**Usage**:
```blade
<x-shared.card-layout 
    title="Recent Projects"
    subtitle="Latest project updates"
    :actions="$cardActions"
    :loading="false"
    :error="$error"
>
    <!-- Card content -->
</x-shared.card-layout>
```

## Page Layouts

### Dashboard Layout

The dashboard provides an overview of key metrics and recent activity.

**Structure**:
```
┌─────────────────────────────────────────────────────────────────┐
│ HeaderShell                                                     │
├─────────────────────────────────────────────────────────────────┤
│ Page Header: "Dashboard" + "Welcome back, {user.name}"          │
├─────────────────────────────────────────────────────────────────┤
│ KPI Strip (4 cards)                                             │
│ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐               │
│ │Projects │ │Tasks    │ │Users    │ │Progress │               │
│ │  12     │ │  45     │ │   8     │ │  78%    │               │
│ └─────────┘ └─────────┘ └─────────┘ └─────────┘               │
├─────────────────────────────────────────────────────────────────┤
│ Main Content Grid (2 columns)                                    │
│ ┌─────────────────────┐ ┌─────────────────────┐               │
│ │ Recent Projects     │ │ Recent Activity     │               │
│ │ (Data Table)        │ │ (Activity Feed)     │               │
│ └─────────────────────┘ └─────────────────────┘               │
│ ┌─────────────────────┐ ┌─────────────────────┐               │
│ │ Project Progress    │ │ Quick Actions       │               │
│ │ (Chart)             │ │ (Action Buttons)    │               │
│ └─────────────────────┘ └─────────────────────┘               │
└─────────────────────────────────────────────────────────────────┘
```

**Key Features**:
- Real-time KPI updates
- Interactive charts
- Recent activity feed
- Quick action buttons
- Responsive grid layout

### Projects Layout

The projects page provides comprehensive project management capabilities.

**Structure**:
```
┌─────────────────────────────────────────────────────────────────┐
│ HeaderShell                                                     │
├─────────────────────────────────────────────────────────────────┤
│ Page Header: "Projects" + "Manage your projects"               │
├─────────────────────────────────────────────────────────────────┤
│ Filter Bar                                                      │
│ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ │
│ │Search   │ │Status   │ │Owner    │ │Date    │ │Actions  │ │
│ └─────────┘ └─────────┘ └─────────┘ └─────────┘ └─────────┘ │
├─────────────────────────────────────────────────────────────────┤
│ Data Table                                                      │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ Name        │ Status │ Owner │ Progress │ Due Date │ Actions │ │
│ ├─────────────────────────────────────────────────────────────┤ │
│ │ Project A   │ Active │ John  │ 75%      │ 2024-01-15│ [Edit] │ │
│ │ Project B   │ On Hold│ Jane  │ 45%      │ 2024-02-01│ [Edit] │ │
│ └─────────────────────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────────────────────┤
│ Pagination                                                      │
│ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐               │
│ │Previous │ │   1     │ │   2     │ │ Next    │               │
│ └─────────┘ └─────────┘ └─────────┘ └─────────┘               │
└─────────────────────────────────────────────────────────────────┘
```

**Key Features**:
- Advanced filtering and search
- Sortable columns
- Bulk operations
- RBAC-based action buttons
- Export functionality
- Responsive table/grid views

## Data Integration

### API Endpoints

#### Dashboard API

**GET /api/dashboard/kpis**
```json
{
  "success": true,
  "data": {
    "projects": {
      "total": 12,
      "active": 8,
      "completed": 4,
      "change": 15
    },
    "tasks": {
      "total": 45,
      "pending": 20,
      "in_progress": 15,
      "completed": 10,
      "change": -5
    },
    "users": {
      "total": 8,
      "active": 7,
      "inactive": 1,
      "change": 12
    },
    "progress": {
      "overall": 78,
      "this_month": 85,
      "last_month": 72,
      "change": 13
    }
  }
}
```

**GET /api/dashboard/charts**
```json
{
  "success": true,
  "data": {
    "project_progress": {
      "labels": ["Planning", "Active", "Completed"],
      "datasets": [{
        "label": "Projects",
        "data": [2, 8, 4],
        "backgroundColor": ["#F59E0B", "#10B981", "#3B82F6"]
      }]
    },
    "task_distribution": {
      "labels": ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
      "datasets": [{
        "label": "Tasks Completed",
        "data": [12, 19, 3, 5, 2, 3],
        "borderColor": "#3B82F6"
      }]
    }
  }
}
```

**GET /api/dashboard/recent-activity**
```json
{
  "success": true,
  "data": [
    {
      "id": "1",
      "type": "project",
      "action": "created",
      "description": "Project 'Website Redesign' was created",
      "timestamp": "2024-01-15T10:30:00Z",
      "user": {
        "id": "1",
        "name": "John Doe"
      }
    }
  ]
}
```

#### Projects API

**GET /api/projects**
```json
{
  "success": true,
  "data": [
    {
      "id": "1",
      "name": "Website Redesign",
      "description": "Complete website redesign project",
      "status": "active",
      "progress": 75,
      "owner": {
        "id": "1",
        "name": "John Doe"
      },
      "team": [
        {
          "id": "2",
          "name": "Jane Smith"
        }
      ],
      "start_date": "2024-01-01",
      "due_date": "2024-03-31",
      "budget": 50000,
      "spent": 37500,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-15T10:30:00Z",
      "tenant_id": "tenant-1"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 10,
    "total": 25
  }
}
```

**POST /api/projects**
```json
{
  "name": "New Project",
  "description": "Project description",
  "owner_id": "1",
  "team_ids": ["2", "3"],
  "start_date": "2024-01-01",
  "due_date": "2024-03-31",
  "budget": 50000
}
```

**PUT /api/projects/{id}**
```json
{
  "name": "Updated Project Name",
  "status": "completed",
  "progress": 100
}
```

**DELETE /api/projects/{id}**
```json
{
  "success": true,
  "message": "Project deleted successfully"
}
```

### State Management

#### Alpine.js Data Structure

```javascript
// Dashboard data
Alpine.data('dashboard', () => ({
  kpis: null,
  charts: null,
  recentActivity: [],
  loading: false,
  error: null,
  
  async init() {
    await this.loadData();
  },
  
  async loadData() {
    this.loading = true;
    try {
      const [kpis, charts, activity] = await Promise.all([
        this.fetchKPIs(),
        this.fetchCharts(),
        this.fetchRecentActivity()
      ]);
      
      this.kpis = kpis;
      this.charts = charts;
      this.recentActivity = activity;
    } catch (error) {
      this.error = error.message;
    } finally {
      this.loading = false;
    }
  },
  
  async fetchKPIs() {
    const response = await fetch('/api/dashboard/kpis');
    return await response.json();
  }
}));

// Projects data
Alpine.data('projects', () => ({
  projects: [],
  pagination: {},
  filters: {},
  selectedProjects: [],
  loading: false,
  error: null,
  
  async init() {
    await this.loadProjects();
  },
  
  async loadProjects() {
    this.loading = true;
    try {
      const response = await fetch('/api/projects?' + new URLSearchParams(this.filters));
      const data = await response.json();
      
      this.projects = data.data;
      this.pagination = data.meta;
    } catch (error) {
      this.error = error.message;
    } finally {
      this.loading = false;
    }
  }
}));
```

## RBAC Implementation

### Permission System

#### Permission Definitions

```php
// config/permissions.php
return [
    'projects' => [
        'view' => 'View projects',
        'create' => 'Create projects',
        'edit' => 'Edit projects',
        'delete' => 'Delete projects',
        'export' => 'Export projects'
    ],
    'tasks' => [
        'view' => 'View tasks',
        'create' => 'Create tasks',
        'edit' => 'Edit tasks',
        'delete' => 'Delete tasks',
        'assign' => 'Assign tasks'
    ],
    'users' => [
        'view' => 'View users',
        'create' => 'Create users',
        'edit' => 'Edit users',
        'delete' => 'Delete users',
        'manage_roles' => 'Manage user roles'
    ],
    'reports' => [
        'view' => 'View reports',
        'export' => 'Export reports',
        'create' => 'Create custom reports'
    ],
    'settings' => [
        'view' => 'View settings',
        'edit' => 'Edit settings',
        'manage_system' => 'Manage system settings'
    ]
];
```

#### Role Definitions

```php
// config/roles.php
return [
    'super_admin' => [
        'permissions' => ['*'], // All permissions
        'tenant_scoped' => false
    ],
    'project_manager' => [
        'permissions' => [
            'projects.*',
            'tasks.*',
            'users.view',
            'reports.view',
            'settings.view'
        ],
        'tenant_scoped' => true
    ],
    'team_member' => [
        'permissions' => [
            'projects.view',
            'tasks.view',
            'tasks.create',
            'tasks.edit'
        ],
        'tenant_scoped' => true
    ],
    'client' => [
        'permissions' => [
            'projects.view',
            'tasks.view'
        ],
        'tenant_scoped' => true
    ]
];
```

### RBAC Components

#### PermissionGate Component

```blade
<x-shared.permission-gate 
    permission="projects.create"
    resource="project"
    :user="$user"
    :tenant="$tenant"
>
    <button class="btn btn-primary">Create Project</button>
</x-shared.permission-gate>
```

#### RoleBasedMenu Component

```blade
<x-shared.role-based-menu 
    :items="$menuItems"
    :user-role="$user->role"
    :user-permissions="$user->permissions"
    :tenant-id="$tenant->id"
/>
```

### RBAC Implementation in Controllers

```php
// app/Http/Controllers/Api/ProjectsController.php
class ProjectsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('projects.view');
        
        $projects = Project::where('tenant_id', $request->user()->tenant_id)
            ->with(['owner', 'team'])
            ->paginate(10);
            
        return ApiResponse::success($projects);
    }
    
    public function store(Request $request)
    {
        $this->authorize('projects.create');
        
        $project = Project::create([
            'name' => $request->name,
            'description' => $request->description,
            'owner_id' => $request->owner_id,
            'tenant_id' => $request->user()->tenant_id,
            // ... other fields
        ]);
        
        return ApiResponse::created($project);
    }
    
    public function update(Request $request, Project $project)
    {
        $this->authorize('projects.edit', $project);
        
        $project->update($request->validated());
        
        return ApiResponse::success($project);
    }
    
    public function destroy(Project $project)
    {
        $this->authorize('projects.delete', $project);
        
        $project->delete();
        
        return ApiResponse::deleted();
    }
}
```

## Responsive Design

### Breakpoint System

```css
/* Tailwind CSS breakpoints */
/* sm: 640px */
/* md: 768px */
/* lg: 1024px */
/* xl: 1280px */
/* 2xl: 1536px */
```

### Mobile-First Approach

#### Dashboard Mobile Layout

```blade
<!-- Mobile: Single column -->
<div class="grid grid-cols-1 gap-6">
    <!-- KPI Cards -->
    <div class="grid grid-cols-2 gap-4">
        <x-shared.kpi-card title="Projects" value="12" />
        <x-shared.kpi-card title="Tasks" value="45" />
    </div>
    
    <!-- Charts -->
    <div class="space-y-6">
        <x-shared.chart type="line" :data="$chartData" height="200" />
        <x-shared.chart type="doughnut" :data="$pieData" height="200" />
    </div>
</div>

<!-- Tablet: Two columns -->
<div class="md:grid md:grid-cols-2 md:gap-6">
    <!-- Content -->
</div>

<!-- Desktop: Three columns -->
<div class="lg:grid lg:grid-cols-3 lg:gap-8">
    <!-- Content -->
</div>
```

#### Projects Mobile Layout

```blade
<!-- Mobile: Card layout -->
<div class="md:hidden space-y-4">
    @foreach($projects as $project)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-start justify-between mb-3">
                <h3 class="text-lg font-semibold text-gray-900">{{ $project->name }}</h3>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $project->status_color }}">
                    {{ $project->status }}
                </span>
            </div>
            
            <p class="text-sm text-gray-600 mb-3">{{ $project->description }}</p>
            
            <div class="flex items-center justify-between text-sm text-gray-500 mb-3">
                <span>Owner: {{ $project->owner->name }}</span>
                <span>Progress: {{ $project->progress }}%</span>
            </div>
            
            <div class="flex items-center justify-end space-x-2">
                <button onclick="viewProject('{{ $project->id }}')" class="text-blue-600 hover:text-blue-900 text-sm">
                    <i class="fas fa-eye mr-1"></i> View
                </button>
                @can('projects.edit')
                    <button onclick="editProject('{{ $project->id }}')" class="text-gray-600 hover:text-gray-900 text-sm">
                        <i class="fas fa-edit mr-1"></i> Edit
                    </button>
                @endcan
            </div>
        </div>
    @endforeach
</div>

<!-- Desktop: Table layout -->
<div class="hidden md:block">
    <x-shared.data-table :data="$projects" :columns="$columns" />
</div>
```

### Touch-Friendly Design

#### Button Sizing

```css
/* Minimum touch target size: 44px */
.btn-mobile {
    min-height: 44px;
    min-width: 44px;
    padding: 12px 16px;
}

/* Larger touch targets for important actions */
.btn-primary-mobile {
    min-height: 48px;
    padding: 16px 24px;
    font-size: 16px;
}
```

#### Swipe Gestures

```javascript
// Swipe detection for mobile
class SwipeDetector {
    constructor(element, onSwipeLeft, onSwipeRight) {
        this.element = element;
        this.onSwipeLeft = onSwipeLeft;
        this.onSwipeRight = onSwipeRight;
        
        this.startX = 0;
        this.startY = 0;
        
        this.element.addEventListener('touchstart', this.handleTouchStart.bind(this));
        this.element.addEventListener('touchmove', this.handleTouchMove.bind(this));
        this.element.addEventListener('touchend', this.handleTouchEnd.bind(this));
    }
    
    handleTouchStart(e) {
        this.startX = e.touches[0].clientX;
        this.startY = e.touches[0].clientY;
    }
    
    handleTouchMove(e) {
        e.preventDefault();
    }
    
    handleTouchEnd(e) {
        const endX = e.changedTouches[0].clientX;
        const endY = e.changedTouches[0].clientY;
        
        const diffX = this.startX - endX;
        const diffY = this.startY - endY;
        
        // Only trigger if horizontal swipe is greater than vertical
        if (Math.abs(diffX) > Math.abs(diffY)) {
            if (diffX > 50) {
                this.onSwipeLeft();
            } else if (diffX < -50) {
                this.onSwipeRight();
            }
        }
    }
}
```

## Accessibility

### WCAG 2.1 AA Compliance

#### Keyboard Navigation

```blade
<!-- Focus management -->
<div class="focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
    <!-- Content -->
</div>

<!-- Skip links -->
<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-0 focus:left-0 bg-blue-600 text-white p-2 rounded-br-md">
    Skip to main content
</a>

<!-- Tab order -->
<button tabindex="1" class="btn">First Button</button>
<button tabindex="2" class="btn">Second Button</button>
<button tabindex="3" class="btn">Third Button</button>
```

#### Screen Reader Support

```blade
<!-- ARIA labels -->
<button aria-label="Close modal" onclick="closeModal()">
    <i class="fas fa-times" aria-hidden="true"></i>
</button>

<!-- ARIA descriptions -->
<div aria-describedby="help-text">
    <input type="text" aria-describedby="help-text" />
    <div id="help-text" class="text-sm text-gray-500">
        Enter your project name
    </div>
</div>

<!-- Live regions for dynamic content -->
<div aria-live="polite" aria-atomic="true" class="sr-only">
    <span id="status-message"></span>
</div>

<!-- Semantic HTML -->
<main id="main-content" role="main">
    <section aria-labelledby="dashboard-heading">
        <h1 id="dashboard-heading">Dashboard</h1>
        <!-- Content -->
    </section>
</main>
```

#### Color and Contrast

```css
/* High contrast mode support */
@media (prefers-contrast: high) {
    .btn-primary {
        background-color: #000000;
        color: #ffffff;
        border: 2px solid #000000;
    }
    
    .btn-secondary {
        background-color: #ffffff;
        color: #000000;
        border: 2px solid #000000;
    }
}

/* Color not the only indicator */
.status-active {
    background-color: #10B981;
    color: #ffffff;
}

.status-active::before {
    content: "●";
    margin-right: 4px;
}

/* Focus indicators */
.focus-visible:focus {
    outline: 2px solid #3B82F6;
    outline-offset: 2px;
}
```

### Accessibility Components

#### AccessibleButton Component

```blade
<x-shared.accessible-button 
    :on-click="handleClick"
    :disabled="false"
    :loading="false"
    aria-label="Create new project"
    aria-described-by="create-help"
    keyboard-shortcut="Ctrl+N"
>
    <i class="fas fa-plus mr-2"></i>
    Create Project
</x-shared.accessible-button>
```

#### AccessibleTable Component

```blade
<x-shared.accessible-table 
    :data="$projects"
    :columns="$columns"
    caption="Projects list with sorting and filtering"
    summary="Table showing project name, status, owner, progress, and due date"
    :on-row-click="handleRowClick"
    :on-selection-change="handleSelectionChange"
/>
```

## Performance Optimization

### Lazy Loading

#### Component Lazy Loading

```javascript
// Lazy load heavy components
const LazyChart = {
    async init() {
        // Load Chart.js only when needed
        if (typeof Chart === 'undefined') {
            await this.loadChartJS();
        }
        
        this.renderChart();
    },
    
    async loadChartJS() {
        return new Promise((resolve) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            script.onload = resolve;
            document.head.appendChild(script);
        });
    }
};
```

#### Data Lazy Loading

```javascript
// Infinite scroll for large datasets
class InfiniteScroll {
    constructor(container, loadMore) {
        this.container = container;
        this.loadMore = loadMore;
        this.loading = false;
        this.hasMore = true;
        
        this.setupIntersectionObserver();
    }
    
    setupIntersectionObserver() {
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && this.hasMore && !this.loading) {
                this.loadMoreData();
            }
        });
        
        // Observe the last item
        const lastItem = this.container.lastElementChild;
        if (lastItem) {
            observer.observe(lastItem);
        }
    }
    
    async loadMoreData() {
        this.loading = true;
        try {
            const newData = await this.loadMore();
            if (newData.length === 0) {
                this.hasMore = false;
            }
        } finally {
            this.loading = false;
        }
    }
}
```

### Virtualization

#### VirtualizedTable Component

```javascript
class VirtualizedTable {
    constructor(container, data, rowHeight = 50) {
        this.container = container;
        this.data = data;
        this.rowHeight = rowHeight;
        this.containerHeight = container.clientHeight;
        this.visibleRows = Math.ceil(this.containerHeight / this.rowHeight);
        this.scrollTop = 0;
        
        this.setupVirtualization();
    }
    
    setupVirtualization() {
        this.container.addEventListener('scroll', this.handleScroll.bind(this));
        this.render();
    }
    
    handleScroll(e) {
        this.scrollTop = e.target.scrollTop;
        this.render();
    }
    
    render() {
        const startIndex = Math.floor(this.scrollTop / this.rowHeight);
        const endIndex = Math.min(startIndex + this.visibleRows, this.data.length);
        
        const visibleData = this.data.slice(startIndex, endIndex);
        
        // Render only visible rows
        this.container.innerHTML = visibleData.map((item, index) => 
            this.renderRow(item, startIndex + index)
        ).join('');
        
        // Set total height for scrollbar
        this.container.style.height = `${this.data.length * this.rowHeight}px`;
    }
}
```

### Caching

#### API Response Caching

```javascript
class ApiCache {
    constructor(ttl = 300000) { // 5 minutes default
        this.cache = new Map();
        this.ttl = ttl;
    }
    
    async get(key, fetcher) {
        const cached = this.cache.get(key);
        
        if (cached && Date.now() - cached.timestamp < this.ttl) {
            return cached.data;
        }
        
        const data = await fetcher();
        this.cache.set(key, {
            data,
            timestamp: Date.now()
        });
        
        return data;
    }
    
    invalidate(key) {
        this.cache.delete(key);
    }
    
    clear() {
        this.cache.clear();
    }
}

// Usage
const cache = new ApiCache();

async function fetchProjects() {
    return cache.get('projects', async () => {
        const response = await fetch('/api/projects');
        return await response.json();
    });
}
```

## Testing Strategy

### Unit Tests

#### Component Tests

```javascript
// tests/components/KpiCard.test.js
describe('KpiCard', () => {
    it('renders with correct title and value', () => {
        const wrapper = mount(KpiCard, {
            props: {
                title: 'Total Projects',
                value: 12,
                change: 15,
                changeType: 'increase',
                icon: 'fas fa-project-diagram',
                color: 'blue'
            }
        });
        
        expect(wrapper.find('.kpi-title').text()).toBe('Total Projects');
        expect(wrapper.find('.kpi-value').text()).toBe('12');
        expect(wrapper.find('.kpi-change').text()).toContain('15%');
    });
    
    it('shows loading state', () => {
        const wrapper = mount(KpiCard, {
            props: {
                title: 'Total Projects',
                value: 12,
                loading: true
            }
        });
        
        expect(wrapper.find('.loading-skeleton').exists()).toBe(true);
    });
    
    it('handles click events', async () => {
        const onClick = jest.fn();
        const wrapper = mount(KpiCard, {
            props: {
                title: 'Total Projects',
                value: 12,
                onClick
            }
        });
        
        await wrapper.trigger('click');
        expect(onClick).toHaveBeenCalled();
    });
});
```

#### Hook Tests

```javascript
// tests/hooks/useDashboardData.test.js
describe('useDashboardData', () => {
    it('fetches dashboard data', async () => {
        const mockData = {
            kpis: { projects: { total: 12 } },
            charts: { projectProgress: {} },
            recentActivity: []
        };
        
        fetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockData
        });
        
        const { result } = renderHook(() => useDashboardData());
        
        await waitFor(() => {
            expect(result.current.data).toEqual(mockData);
        });
    });
    
    it('handles errors gracefully', async () => {
        fetch.mockRejectedValueOnce(new Error('Network error'));
        
        const { result } = renderHook(() => useDashboardData());
        
        await waitFor(() => {
            expect(result.current.error).toBe('Network error');
        });
    });
});
```

### Integration Tests

#### API Integration Tests

```javascript
// tests/integration/dashboard.test.js
describe('Dashboard API Integration', () => {
    it('returns KPIs data', async () => {
        const response = await request(app)
            .get('/api/dashboard/kpis')
            .expect(200);
        
        expect(response.body.success).toBe(true);
        expect(response.body.data).toHaveProperty('projects');
        expect(response.body.data).toHaveProperty('tasks');
        expect(response.body.data).toHaveProperty('users');
        expect(response.body.data).toHaveProperty('progress');
    });
    
    it('handles authentication', async () => {
        await request(app)
            .get('/api/dashboard/kpis')
            .expect(401);
    });
    
    it('respects tenant scoping', async () => {
        const user = await createUser({ tenant_id: 'tenant-1' });
        const token = await createToken(user);
        
        const response = await request(app)
            .get('/api/dashboard/kpis')
            .set('Authorization', `Bearer ${token}`)
            .expect(200);
        
        // Verify data is scoped to tenant-1
        expect(response.body.data.tenant_id).toBe('tenant-1');
    });
});
```

### E2E Tests

#### User Flow Tests

```javascript
// tests/e2e/dashboard.spec.js
describe('Dashboard User Flow', () => {
    it('displays dashboard with real data', async () => {
        await page.goto('/app/dashboard');
        
        // Wait for KPI cards to load
        await page.waitForSelector('.kpi-card');
        
        // Verify KPI values are displayed
        const projectCount = await page.textContent('[data-testid="projects-count"]');
        expect(projectCount).toBe('12');
        
        // Verify charts are rendered
        const chart = await page.$('.chart-container');
        expect(chart).toBeTruthy();
    });
    
    it('navigates to projects page', async () => {
        await page.goto('/app/dashboard');
        
        // Click on projects link
        await page.click('[href="/app/projects"]');
        
        // Verify navigation
        await page.waitForURL('/app/projects');
        expect(page.url()).toContain('/app/projects');
    });
    
    it('creates new project', async () => {
        await page.goto('/app/projects');
        
        // Click create button
        await page.click('[data-testid="create-project-btn"]');
        
        // Fill form
        await page.fill('[name="name"]', 'New Project');
        await page.fill('[name="description"]', 'Project description');
        
        // Submit form
        await page.click('[type="submit"]');
        
        // Verify success message
        await page.waitForSelector('.success-message');
        expect(await page.textContent('.success-message')).toContain('Project created successfully');
    });
});
```

## Development Guidelines

### Code Standards

#### Component Structure

```blade
{{-- Component template structure --}}
<div class="component-wrapper">
    {{-- Header section --}}
    <div class="component-header">
        <h2 class="component-title">{{ $title }}</h2>
        @if($actions)
            <div class="component-actions">
                {{ $actions }}
            </div>
        @endif
    </div>
    
    {{-- Content section --}}
    <div class="component-content">
        @if($loading)
            <div class="loading-state">
                {{-- Loading skeleton --}}
            </div>
        @elseif($error)
            <div class="error-state">
                {{-- Error message --}}
            </div>
        @elseif(empty($data))
            <div class="empty-state">
                {{-- Empty state --}}
            </div>
        @else
            {{-- Main content --}}
            {{ $slot }}
        @endif
    </div>
    
    {{-- Footer section --}}
    @if($footer)
        <div class="component-footer">
            {{ $footer }}
        </div>
    @endif
</div>
```

#### JavaScript Structure

```javascript
// Component class structure
class ComponentName {
    constructor(options = {}) {
        this.options = { ...this.defaultOptions, ...options };
        this.state = this.initialState;
        this.elements = {};
        this.eventListeners = [];
        
        this.init();
    }
    
    get defaultOptions() {
        return {
            // Default options
        };
    }
    
    get initialState() {
        return {
            // Initial state
        };
    }
    
    async init() {
        this.setupElements();
        this.setupEventListeners();
        await this.loadData();
    }
    
    setupElements() {
        // Cache DOM elements
    }
    
    setupEventListeners() {
        // Setup event listeners
    }
    
    async loadData() {
        // Load component data
    }
    
    render() {
        // Render component
    }
    
    destroy() {
        // Cleanup
        this.eventListeners.forEach(remove => remove());
    }
}
```

### Error Handling

#### API Error Handling

```javascript
class ApiError extends Error {
    constructor(message, status, code, details = null) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.code = code;
        this.details = details;
    }
}

async function apiRequest(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                ...options.headers
            },
            ...options
        });
        
        if (!response.ok) {
            const errorData = await response.json();
            throw new ApiError(
                errorData.message || 'Request failed',
                response.status,
                errorData.code || 'UNKNOWN_ERROR',
                errorData.details
            );
        }
        
        return await response.json();
    } catch (error) {
        if (error instanceof ApiError) {
            throw error;
        }
        
        throw new ApiError(
            'Network error',
            0,
            'NETWORK_ERROR',
            { originalError: error.message }
        );
    }
}
```

#### UI Error Handling

```javascript
class ErrorHandler {
    static handle(error, context = '') {
        console.error(`Error in ${context}:`, error);
        
        if (error instanceof ApiError) {
            this.handleApiError(error, context);
        } else {
            this.handleGenericError(error, context);
        }
    }
    
    static handleApiError(error, context) {
        switch (error.status) {
            case 401:
                this.showError('Please log in to continue');
                window.location.href = '/login';
                break;
            case 403:
                this.showError('You do not have permission to perform this action');
                break;
            case 404:
                this.showError('The requested resource was not found');
                break;
            case 422:
                this.showValidationErrors(error.details);
                break;
            case 500:
                this.showError('A server error occurred. Please try again later.');
                break;
            default:
                this.showError(error.message || 'An unexpected error occurred');
        }
    }
    
    static handleGenericError(error, context) {
        this.showError('An unexpected error occurred. Please try again.');
    }
    
    static showError(message) {
        // Show error notification
        const notification = document.createElement('div');
        notification.className = 'error-notification';
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
    
    static showValidationErrors(errors) {
        // Show validation errors
        Object.entries(errors).forEach(([field, messages]) => {
            const fieldElement = document.querySelector(`[name="${field}"]`);
            if (fieldElement) {
                const errorElement = document.createElement('div');
                errorElement.className = 'field-error';
                errorElement.textContent = messages.join(', ');
                fieldElement.parentNode.appendChild(errorElement);
            }
        });
    }
}
```

### Performance Monitoring

#### Performance Metrics

```javascript
class PerformanceMonitor {
    static measurePageLoad() {
        window.addEventListener('load', () => {
            const navigation = performance.getEntriesByType('navigation')[0];
            const metrics = {
                pageLoadTime: navigation.loadEventEnd - navigation.loadEventStart,
                domContentLoaded: navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart,
                firstPaint: performance.getEntriesByType('paint').find(entry => entry.name === 'first-paint')?.startTime,
                firstContentfulPaint: performance.getEntriesByType('paint').find(entry => entry.name === 'first-contentful-paint')?.startTime
            };
            
            this.sendMetrics('page_load', metrics);
        });
    }
    
    static measureApiCall(url, startTime, endTime) {
        const duration = endTime - startTime;
        this.sendMetrics('api_call', {
            url,
            duration,
            timestamp: Date.now()
        });
    }
    
    static measureComponentRender(componentName, startTime, endTime) {
        const duration = endTime - startTime;
        this.sendMetrics('component_render', {
            component: componentName,
            duration,
            timestamp: Date.now()
        });
    }
    
    static sendMetrics(type, data) {
        // Send metrics to monitoring service
        if (window.gtag) {
            window.gtag('event', 'performance', {
                event_category: type,
                event_label: JSON.stringify(data),
                value: data.duration || 0
            });
        }
    }
}

// Usage
PerformanceMonitor.measurePageLoad();

// Measure API calls
const startTime = performance.now();
const data = await apiRequest('/api/projects');
const endTime = performance.now();
PerformanceMonitor.measureApiCall('/api/projects', startTime, endTime);
```

## Conclusion

This App UI Guide provides comprehensive documentation for building modern, accessible, and performant user interfaces in ZenaManage. By following these guidelines, developers can create consistent, maintainable, and user-friendly applications that comply with Project Rules and industry best practices.

### Key Takeaways

1. **Component-Based Architecture**: Use reusable components for consistency
2. **Data-Driven Design**: Bind all UI elements to real data sources
3. **RBAC Implementation**: Implement role-based access control throughout
4. **Responsive Design**: Design mobile-first with progressive enhancement
5. **Accessibility**: Ensure WCAG 2.1 AA compliance
6. **Performance**: Optimize for speed and efficiency
7. **Testing**: Implement comprehensive testing strategy
8. **Error Handling**: Provide graceful error handling and recovery
9. **Monitoring**: Track performance and user experience metrics
10. **Documentation**: Maintain comprehensive documentation

### Next Steps

1. Review and implement the component library
2. Set up the development environment
3. Begin with the dashboard implementation
4. Gradually implement other pages
5. Add comprehensive testing
6. Monitor performance and user feedback
7. Iterate and improve based on metrics

For questions or clarifications, please refer to the Project Rules documentation or contact the development team.
