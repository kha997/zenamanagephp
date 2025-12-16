# Dashboard Feature Implementation

## Overview

Complete dashboard feature implementation for tenant and admin users, including KPIs, alerts, recent projects/tasks, and activity feed.

## API Endpoints

### Tenant Dashboard (`/api/v1/app/dashboard/*`)

All tenant dashboard endpoints require `ability:tenant` middleware and are tenant-scoped.

- `GET /api/v1/app/dashboard` - Main dashboard data (combined view)
- `GET /api/v1/app/dashboard/stats` - Dashboard stats/KPIs
- `GET /api/v1/app/dashboard/recent-projects?limit=5` - Recent projects
- `GET /api/v1/app/dashboard/recent-tasks?limit=5` - Recent tasks
- `GET /api/v1/app/dashboard/recent-activity?limit=10` - Recent activity
- `GET /api/v1/app/dashboard/alerts` - Dashboard alerts
- `GET /api/v1/app/dashboard/metrics` - Dashboard metrics
- `GET /api/v1/app/dashboard/team-status` - Team status
- `PUT /api/v1/app/dashboard/alerts/{id}/read` - Mark alert as read
- `PUT /api/v1/app/dashboard/alerts/read-all` - Mark all alerts as read

### Admin Dashboard (`/api/admin/dashboard/*`)

Admin dashboard endpoints require `ability:admin` middleware.

- `GET /api/admin/dashboard/summary` - Admin dashboard summary (system-wide statistics)

**Response Format**:

```typescript
{
  success: true,
  data: {
    stats: {
      total_users: number;        // Total users across all tenants
      total_projects: number;     // Total projects system-wide
      total_tasks: number;        // Total tasks system-wide
      active_sessions: number;    // Active user sessions
    },
    recent_activities: ActivityItem[];  // System-wide activities
    system_health: 'good' | 'warning' | 'critical';  // System health status
  }
}
```

**Example Response**:

```json
{
  "success": true,
  "data": {
    "stats": {
      "total_users": 150,
      "total_projects": 45,
      "total_tasks": 320,
      "active_sessions": 25
    },
    "recent_activities": [
      {
        "id": "user_123",
        "type": "user",
        "action": "registered",
        "description": "User John Doe (john@example.com) registered",
        "timestamp": "2025-01-15T10:30:00Z",
        "user": {
          "id": "123",
          "name": "John Doe"
        }
      }
    ],
    "system_health": "good"
  }
}
```

**Notes**:
- All statistics are system-wide (across all tenants)
- Data is cached for 60 seconds to improve performance
- System health is calculated based on database connectivity, cache status, and error rates
- Recent activities include user registrations, tenant creations, and project creations

## Response Formats

### Dashboard Stats

```typescript
{
  success: true,
  data: {
    projects: {
      total: number;
      active: number;
      completed: number;
    };
    tasks: {
      total: number;
      completed: number;
      in_progress: number;
      overdue: number;
    };
    users: {
      total: number;
      active: number;
    };
  }
}
```

### Recent Projects

```typescript
{
  success: true,
  data: [
    {
      id: string | number;
      name: string;
      status: 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';
      progress: number;
      updated_at: string;
      owner?: {
        id: string | number;
        name: string;
      };
    }
  ]
}
```

### Dashboard Alerts

```typescript
{
  success: true,
  data: [
    {
      id: string | number;
      type: 'warning' | 'error' | 'info' | 'success';
      message: string;
      created_at: string;
    }
  ]
}
```

## Frontend Components

### Data Test IDs

For E2E testing, the following `data-testid` attributes are available:

- `dashboard-page` - Main dashboard page container
- `kpi-strip` - KPI strip component
- `kpi-total-projects` - Total projects KPI
- `kpi-active-projects` - Active projects KPI
- `kpi-total-tasks` - Total tasks KPI
- `kpi-in-progress-tasks` - In progress tasks KPI
- `kpi-overdue-tasks` - Overdue tasks KPI
- `alert-banner` - Alert banner component
- `recent-projects` - Recent projects card
- `recent-tasks` - Recent tasks card
- `recent-activity` - Recent activity card
- `activity-list` - Activity list container
- `activity-item-{id}` - Individual activity item

### React Query Cache Strategy

- **KPIs/Stats**: `staleTime: 60s`, `gcTime: 5m` (cached longer)
- **Recent Projects/Tasks**: `staleTime: 30s`, `gcTime: 5m`
- **Recent Activity**: `staleTime: 15s`, `gcTime: 5m` (updates more frequently)
- **Alerts**: `staleTime: 30s`, `gcTime: 5m`

## Access Control

### Tenant Dashboard

- Route: `/app/dashboard`
- Guard: `AuthGuard` (requires authentication)
- Middleware: `ability:tenant` (backend)

### Admin Dashboard

- Route: `/admin/dashboard`
- Guards: `AuthGuard` + `AdminGuard` (requires authentication + admin role)
- Middleware: `ability:admin` (backend)
- Non-admin users are redirected to `/app/dashboard`

## Testing

### Unit Tests

Location: `frontend/src/features/dashboard/__tests__/hooks.test.ts`

Tests cover:
- Success states for all hooks
- Error handling
- Empty states
- Mutations (mark as read)

### E2E Tests

Location: `tests/e2e/dashboard/Dashboard.spec.ts`

Tests cover:
- Dashboard page load
- KPI display
- Alert banner
- Recent projects/tasks
- Activity feed
- Navigation
- Responsive design

### MSW Handlers

Location: `tests/msw/handlers/dashboard.ts`

Mock handlers for all dashboard endpoints with realistic data.

## Files Structure

```
frontend/src/features/dashboard/
├── api.ts                    # API client
├── types.ts                  # TypeScript types
├── hooks.ts                  # React Query hooks
├── components/
│   ├── DashboardKpiStrip.tsx
│   ├── AlertBanner.tsx
│   └── RecentActivityList.tsx
├── pages/
│   ├── DashboardPage.tsx     # Tenant dashboard
│   └── AdminDashboardPage.tsx # Admin dashboard
└── __tests__/
    └── hooks.test.ts         # Unit tests
```

## Routes

- `/app` → redirects to `/app/dashboard`
- `/app/dashboard` → `DashboardPage` (tenant)
- `/admin` → redirects to `/admin/dashboard`
- `/admin/dashboard` → `AdminDashboardPage` (admin only)

## Navigation

- **AppNavigator**: Includes "Dashboard" link for all authenticated users
- **AdminNavigator**: Includes "Dashboard" link for admin users only

## Error Handling

All components handle:
- Loading states (skeleton/spinner)
- Error states (error message with retry)
- Empty states (friendly empty messages)

## Performance Considerations

- Components are memoized to prevent unnecessary re-renders
- React Query caching reduces API calls
- Query keys include tenant/user context for proper isolation
- Admin and tenant queries use separate query keys

## Future Enhancements

- Real-time updates via WebSocket
- Customizable dashboard widgets
- Export dashboard data
- Advanced filtering and date ranges
- Chart visualizations

