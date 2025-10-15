# App UI Redesign Proposal

## Overview

This document outlines the complete redesign of `/app/*` pages to comply with Project Rules and implement a modern, data-driven interface.

## 1. HeaderShell Integration

### Current Issues
- Legacy header without HeaderShell
- No RBAC-based navigation
- No tenant switching
- No responsive design
- No dark/light mode

### Proposed HeaderShell Structure

```
┌─────────────────────────────────────────────────────────────────┐
│ HeaderShell (Sticky + Condensed on Scroll)                     │
├─────────────────────────────────────────────────────────────────┤
│ Logo | PrimaryNav (RBAC-filtered) | SecondaryActions | UserMenu │
│      |                            | Notifications    |         │
│      |                            | Theme Toggle     |         │
│      |                            | Focus Mode       |         │
└─────────────────────────────────────────────────────────────────┘
```

### HeaderShell Components

#### 1. Logo Component
```typescript
interface LogoProps {
  tenant?: Tenant;
  onClick?: () => void;
}
```

#### 2. PrimaryNav Component
```typescript
interface PrimaryNavProps {
  items: NavItem[];
  currentPath: string;
  userRole: UserRole;
  tenantId: string;
}

interface NavItem {
  id: string;
  label: string;
  href: string;
  icon: string;
  permissions: string[];
  tenantScoped: boolean;
}
```

#### 3. SecondaryActions Component
```typescript
interface SecondaryActionsProps {
  notifications: Notification[];
  unreadCount: number;
  theme: 'light' | 'dark';
  focusMode: boolean;
  onThemeToggle: () => void;
  onFocusToggle: () => void;
}
```

#### 4. UserMenu Component
```typescript
interface UserMenuProps {
  user: User;
  tenant: Tenant;
  availableTenants: Tenant[];
  onTenantSwitch: (tenantId: string) => void;
  onLogout: () => void;
}
```

## 2. Page Layouts

### 2.1 Dashboard Layout

```
┌─────────────────────────────────────────────────────────────────┐
│ HeaderShell                                                     │
├─────────────────────────────────────────────────────────────────┤
│ Breadcrumbs: Dashboard                                          │
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

### 2.2 Projects Layout

```
┌─────────────────────────────────────────────────────────────────┐
│ HeaderShell                                                     │
├─────────────────────────────────────────────────────────────────┤
│ Breadcrumbs: Dashboard > Projects                               │
├─────────────────────────────────────────────────────────────────┤
│ Page Header: "Projects" + "Manage your projects"              │
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

### 2.3 Reports Layout

```
┌─────────────────────────────────────────────────────────────────┐
│ HeaderShell                                                     │
├─────────────────────────────────────────────────────────────────┤
│ Breadcrumbs: Dashboard > Reports                               │
├─────────────────────────────────────────────────────────────────┤
│ Page Header: "Reports" + "Analytics and insights"              │
├─────────────────────────────────────────────────────────────────┤
│ Report Controls                                                 │
│ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ │
│ │Period   │ │Project  │ │Owner    │ │Type    │ │Export   │ │
│ └─────────┘ └─────────┘ └─────────┘ └─────────┘ └─────────┘ │
├─────────────────────────────────────────────────────────────────┤
│ Charts Grid (2x2)                                               │
│ ┌─────────────────────┐ ┌─────────────────────┐               │
│ │ Revenue Trend      │ │ Project Progress    │               │
│ │ (Line Chart)       │ │ (Bar Chart)         │               │
│ └─────────────────────┘ └─────────────────────┘               │
│ ┌─────────────────────┐ ┌─────────────────────┐               │
│ │ Task Distribution   │ │ Team Performance     │               │
│ │ (Pie Chart)         │ │ (Radar Chart)        │               │
│ └─────────────────────┘ └─────────────────────┘               │
├─────────────────────────────────────────────────────────────────┤
│ Data Tables                                                     │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ Project Performance Summary                                  │ │
│ │ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐           │ │
│ │ │Project  │ │Revenue  │ │Cost     │ │Profit   │           │ │
│ │ └─────────┘ └─────────┘ └─────────┘ └─────────┘           │ │
│ └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

## 3. Component Library

### 3.1 Core Components

#### KpiCard Component
```typescript
interface KpiCardProps {
  title: string;
  value: number | string;
  change?: number;
  changeType?: 'increase' | 'decrease' | 'neutral';
  icon: string;
  color: 'blue' | 'green' | 'yellow' | 'red' | 'purple';
  loading?: boolean;
  onClick?: () => void;
}
```

#### DataTable Component
```typescript
interface DataTableProps<T> {
  data: T[];
  columns: Column<T>[];
  pagination?: PaginationProps;
  sorting?: SortingProps;
  filtering?: FilteringProps;
  loading?: boolean;
  emptyState?: EmptyStateProps;
  onRowClick?: (row: T) => void;
  onSelectionChange?: (selected: T[]) => void;
}

interface Column<T> {
  key: keyof T;
  label: string;
  sortable?: boolean;
  filterable?: boolean;
  render?: (value: any, row: T) => React.ReactNode;
  width?: string;
  align?: 'left' | 'center' | 'right';
}
```

#### Chart Component
```typescript
interface ChartProps {
  type: 'line' | 'bar' | 'pie' | 'radar' | 'doughnut';
  data: ChartData;
  options?: ChartOptions;
  loading?: boolean;
  height?: number;
  responsive?: boolean;
}

interface ChartData {
  labels: string[];
  datasets: Dataset[];
}

interface Dataset {
  label: string;
  data: number[];
  backgroundColor?: string | string[];
  borderColor?: string | string[];
  borderWidth?: number;
}
```

#### FilterBar Component
```typescript
interface FilterBarProps {
  filters: Filter[];
  values: Record<string, any>;
  onChange: (values: Record<string, any>) => void;
  onReset: () => void;
  onApply: () => void;
}

interface Filter {
  key: string;
  label: string;
  type: 'text' | 'select' | 'date' | 'daterange' | 'multiselect';
  options?: Option[];
  placeholder?: string;
  required?: boolean;
}
```

#### ActionButton Component
```typescript
interface ActionButtonProps {
  action: string;
  label: string;
  icon?: string;
  variant?: 'primary' | 'secondary' | 'danger' | 'success';
  size?: 'sm' | 'md' | 'lg';
  loading?: boolean;
  disabled?: boolean;
  onClick: () => void;
  permissions?: string[];
  userRole?: UserRole;
}
```

#### Modal Component
```typescript
interface ModalProps {
  isOpen: boolean;
  onClose: () => void;
  title: string;
  children: React.ReactNode;
  size?: 'sm' | 'md' | 'lg' | 'xl';
  closable?: boolean;
  footer?: React.ReactNode;
}
```

#### TabbedInterface Component
```typescript
interface TabbedInterfaceProps {
  tabs: Tab[];
  activeTab: string;
  onTabChange: (tabId: string) => void;
  children: React.ReactNode;
}

interface Tab {
  id: string;
  label: string;
  icon?: string;
  permissions?: string[];
  userRole?: UserRole;
}
```

#### EmptyState Component
```typescript
interface EmptyStateProps {
  title: string;
  description: string;
  icon: string;
  action?: {
    label: string;
    onClick: () => void;
  };
  illustration?: string;
}
```

### 3.2 Layout Components

#### PageLayout Component
```typescript
interface PageLayoutProps {
  title: string;
  description?: string;
  breadcrumbs?: Breadcrumb[];
  headerActions?: React.ReactNode;
  children: React.ReactNode;
  loading?: boolean;
}
```

#### GridLayout Component
```typescript
interface GridLayoutProps {
  columns: number;
  gap: number;
  responsive?: boolean;
  children: React.ReactNode;
}
```

#### CardLayout Component
```typescript
interface CardLayoutProps {
  title?: string;
  subtitle?: string;
  actions?: React.ReactNode;
  children: React.ReactNode;
  loading?: boolean;
  error?: string;
}
```

## 4. Data Integration

### 4.1 API Endpoints

#### Dashboard API
```typescript
// GET /api/dashboard/kpis
interface DashboardKpis {
  projects: {
    total: number;
    active: number;
    completed: number;
    overdue: number;
  };
  tasks: {
    total: number;
    pending: number;
    inProgress: number;
    completed: number;
  };
  users: {
    total: number;
    active: number;
    inactive: number;
  };
  progress: {
    overall: number;
    thisMonth: number;
    lastMonth: number;
  };
}

// GET /api/dashboard/charts
interface DashboardCharts {
  projectProgress: ChartData;
  taskDistribution: ChartData;
  revenueTrend: ChartData;
  teamPerformance: ChartData;
}

// GET /api/dashboard/recent-activity
interface RecentActivity {
  id: string;
  type: 'project' | 'task' | 'user' | 'system';
  action: string;
  description: string;
  timestamp: string;
  user: User;
  metadata?: Record<string, any>;
}
```

#### Projects API
```typescript
// GET /api/projects
interface ProjectsResponse {
  data: Project[];
  pagination: PaginationInfo;
  filters: FilterInfo;
}

interface Project {
  id: string;
  name: string;
  description: string;
  status: 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';
  progress: number;
  owner: User;
  team: User[];
  startDate: string;
  dueDate: string;
  budget: number;
  spent: number;
  createdAt: string;
  updatedAt: string;
  tenantId: string;
}

// POST /api/projects
interface CreateProjectRequest {
  name: string;
  description: string;
  ownerId: string;
  teamIds: string[];
  startDate: string;
  dueDate: string;
  budget: number;
}

// PUT /api/projects/{id}
interface UpdateProjectRequest {
  name?: string;
  description?: string;
  status?: string;
  ownerId?: string;
  teamIds?: string[];
  startDate?: string;
  dueDate?: string;
  budget?: number;
}
```

#### Reports API
```typescript
// GET /api/reports/analytics
interface ReportsAnalytics {
  period: {
    start: string;
    end: string;
  };
  revenue: {
    total: number;
    trend: number;
    breakdown: Record<string, number>;
  };
  costs: {
    total: number;
    trend: number;
    breakdown: Record<string, number>;
  };
  profit: {
    total: number;
    margin: number;
    trend: number;
  };
  projects: {
    total: number;
    completed: number;
    overdue: number;
    averageProgress: number;
  };
}

// GET /api/reports/export
interface ExportReportRequest {
  type: 'pdf' | 'excel' | 'csv';
  format: 'summary' | 'detailed' | 'custom';
  filters: Record<string, any>;
  period: {
    start: string;
    end: string;
  };
}
```

### 4.2 State Management

#### Redux Store Structure
```typescript
interface AppState {
  auth: AuthState;
  dashboard: DashboardState;
  projects: ProjectsState;
  reports: ReportsState;
  users: UsersState;
  settings: SettingsState;
  ui: UIState;
}

interface DashboardState {
  kpis: DashboardKpis | null;
  charts: DashboardCharts | null;
  recentActivity: RecentActivity[];
  loading: boolean;
  error: string | null;
}

interface ProjectsState {
  list: Project[];
  pagination: PaginationInfo;
  filters: FilterInfo;
  selected: Project[];
  loading: boolean;
  error: string | null;
}
```

## 5. RBAC Implementation

### 5.1 Permission System

```typescript
interface Permission {
  id: string;
  name: string;
  description: string;
  resource: string;
  action: string;
  conditions?: PermissionCondition[];
}

interface PermissionCondition {
  field: string;
  operator: 'eq' | 'ne' | 'gt' | 'lt' | 'in' | 'nin';
  value: any;
}

interface Role {
  id: string;
  name: string;
  description: string;
  permissions: string[];
  tenantScoped: boolean;
}
```

### 5.2 RBAC Components

#### PermissionGate Component
```typescript
interface PermissionGateProps {
  permission: string;
  resource?: string;
  user?: User;
  tenant?: Tenant;
  children: React.ReactNode;
  fallback?: React.ReactNode;
}
```

#### RoleBasedMenu Component
```typescript
interface RoleBasedMenuProps {
  items: MenuItem[];
  userRole: UserRole;
  userPermissions: string[];
  tenantId: string;
}

interface MenuItem {
  id: string;
  label: string;
  href: string;
  icon: string;
  permissions: string[];
  tenantScoped: boolean;
  children?: MenuItem[];
}
```

## 6. Responsive Design

### 6.1 Breakpoints

```typescript
const breakpoints = {
  xs: '0px',
  sm: '640px',
  md: '768px',
  lg: '1024px',
  xl: '1280px',
  '2xl': '1536px',
} as const;
```

### 6.2 Mobile Optimizations

#### Mobile Navigation
- Hamburger menu for primary navigation
- Bottom navigation for quick actions
- Swipe gestures for table rows
- Pull-to-refresh for data updates

#### Mobile Tables
- Horizontal scroll for wide tables
- Card layout for complex data
- Collapsible rows for details
- Sticky headers for long lists

#### Mobile Forms
- Full-screen modals
- Step-by-step wizards
- Auto-focus on inputs
- Keyboard-friendly navigation

## 7. Accessibility (A11y)

### 7.1 WCAG 2.1 AA Compliance

#### Keyboard Navigation
- Tab order follows logical flow
- Focus indicators visible
- Skip links for main content
- Keyboard shortcuts for common actions

#### Screen Reader Support
- Semantic HTML elements
- ARIA labels and descriptions
- Live regions for dynamic content
- Alt text for images and icons

#### Color and Contrast
- Minimum 4.5:1 contrast ratio
- Color not the only indicator
- High contrast mode support
- Dark/light theme compatibility

### 7.2 Accessibility Components

#### AccessibleButton Component
```typescript
interface AccessibleButtonProps {
  children: React.ReactNode;
  onClick: () => void;
  disabled?: boolean;
  loading?: boolean;
  ariaLabel?: string;
  ariaDescribedBy?: string;
  keyboardShortcut?: string;
}
```

#### AccessibleTable Component
```typescript
interface AccessibleTableProps<T> {
  data: T[];
  columns: Column<T>[];
  caption: string;
  summary?: string;
  onRowClick?: (row: T) => void;
  onSelectionChange?: (selected: T[]) => void;
}
```

## 8. Performance Optimization

### 8.1 Lazy Loading

#### Component Lazy Loading
```typescript
const LazyChart = lazy(() => import('./Chart'));
const LazyDataTable = lazy(() => import('./DataTable'));
const LazyModal = lazy(() => import('./Modal'));
```

#### Data Lazy Loading
```typescript
interface LazyDataProps {
  endpoint: string;
  params: Record<string, any>;
  pageSize: number;
  onLoad: (data: any[]) => void;
  onError: (error: Error) => void;
}
```

### 8.2 Virtualization

#### VirtualizedTable Component
```typescript
interface VirtualizedTableProps<T> {
  data: T[];
  height: number;
  rowHeight: number;
  columns: Column<T>[];
  onRowClick?: (row: T) => void;
}
```

### 8.3 Caching

#### API Response Caching
```typescript
interface CacheConfig {
  ttl: number; // Time to live in seconds
  maxSize: number; // Maximum cache size
  strategy: 'lru' | 'fifo' | 'lfu';
}
```

## 9. Testing Strategy

### 9.1 Unit Tests

#### Component Tests
```typescript
describe('KpiCard', () => {
  it('renders with correct title and value', () => {
    // Test implementation
  });
  
  it('shows loading state', () => {
    // Test implementation
  });
  
  it('handles click events', () => {
    // Test implementation
  });
});
```

#### Hook Tests
```typescript
describe('useDashboardData', () => {
  it('fetches dashboard data', () => {
    // Test implementation
  });
  
  it('handles errors gracefully', () => {
    // Test implementation
  });
  
  it('refetches on dependency change', () => {
    // Test implementation
  });
});
```

### 9.2 Integration Tests

#### API Integration Tests
```typescript
describe('Dashboard API', () => {
  it('returns KPIs data', async () => {
    // Test implementation
  });
  
  it('handles authentication', async () => {
    // Test implementation
  });
  
  it('respects tenant scoping', async () => {
    // Test implementation
  });
});
```

### 9.3 E2E Tests

#### User Flow Tests
```typescript
describe('Dashboard User Flow', () => {
  it('displays dashboard with real data', () => {
    // Test implementation
  });
  
  it('navigates to projects page', () => {
    // Test implementation
  });
  
  it('creates new project', () => {
    // Test implementation
  });
});
```

## 10. Implementation Timeline

### Phase 1: Foundation (Week 1)
- [ ] HeaderShell implementation
- [ ] Design system setup
- [ ] Core components development
- [ ] API contracts definition
- [ ] RBAC middleware setup

### Phase 2: Core Pages (Week 2)
- [ ] Dashboard implementation
- [ ] Projects page implementation
- [ ] Users page implementation
- [ ] Basic data binding
- [ ] Responsive design

### Phase 3: Advanced Features (Week 3)
- [ ] Reports page implementation
- [ ] Settings page implementation
- [ ] Advanced filtering
- [ ] Export functionality
- [ ] Performance optimization

### Phase 4: Polish & Testing (Week 4)
- [ ] Accessibility improvements
- [ ] Dark/light mode
- [ ] E2E testing
- [ ] Performance monitoring
- [ ] Documentation

## 11. Success Metrics

### Performance Metrics
- [ ] Page load time < 2 seconds
- [ ] Time to interactive < 3 seconds
- [ ] Core Web Vitals score > 90
- [ ] Bundle size < 500KB
- [ ] API response time < 300ms

### Accessibility Metrics
- [ ] WCAG 2.1 AA compliance
- [ ] Keyboard navigation coverage 100%
- [ ] Screen reader compatibility
- [ ] Color contrast ratio > 4.5:1
- [ ] Focus management score > 95%

### User Experience Metrics
- [ ] Task completion rate > 95%
- [ ] User satisfaction score > 4.5/5
- [ ] Error rate < 1%
- [ ] Support ticket reduction > 50%
- [ ] User adoption rate > 90%

## Conclusion

This redesign proposal provides a comprehensive framework for rebuilding the `/app/*` pages with modern, accessible, and performant components that comply with all Project Rules. The implementation should follow a phased approach with continuous testing and validation.

**Next Steps**:
1. Review and approve this proposal
2. Set up development environment
3. Begin Phase 1 implementation
4. Establish testing and monitoring
5. Plan user training and migration
