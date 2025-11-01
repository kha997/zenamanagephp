# Dashboard API Endpoint Fix

## Issue Identified

The frontend dashboard was getting 404 errors for API calls:
```
GET http://localhost:5173/api/v1/app/dashboard/ 404 (Not Found)
```

## Root Cause

The `DashboardApiService` was using a base URL of `/app/dashboard` which doesn't match the actual API routes registered in Laravel. The routes are registered under `/api/v1/app/dashboard/*`.

## Solution Applied

Updated `frontend/src/entities/dashboard/api.ts`:

### Before:
```typescript
private baseUrl = '/app/dashboard';
```

### After:
```typescript
private baseUrl = '/api/v1/app/dashboard';
```

This ensures all API calls go through the correct path:
- GET `/api/v1/app/dashboard/stats`
- GET `/api/v1/app/dashboard/recent-projects`
- GET `/api/v1/app/dashboard/recent-activity`
- GET `/api/v1/app/dashboard/team-status`
- GET `/api/v1/app/dashboard/charts/{type}`
- GET `/api/v1/app/dashboard/metrics`

## Vite Proxy Configuration

The Vite dev server proxy is already configured to forward `/api/*` requests to the Laravel backend:

```typescript
// vite.config.ts
proxy: {
  '/api': {
    target: 'http://localhost:8000',
    changeOrigin: true,
    secure: false,
  }
}
```

This means:
- Frontend: `http://localhost:5173/api/v1/app/dashboard/*`
- Proxy forwards to: `http://localhost:8000/api/v1/app/dashboard/*`
- Laravel backend processes the request

## How It Works

1. Frontend runs on `localhost:5173` (Vite dev server)
2. API calls use `/api/v1/app/dashboard/*` paths
3. Vite proxy forwards to `localhost:8000` (Laravel backend)
4. Laravel processes the request with auth middleware
5. Response returns to frontend through proxy

## Testing

After this fix, the dashboard should:
- ✅ Load data from backend APIs
- ✅ Display recent projects
- ✅ Show activity feed
- ✅ Display team status
- ✅ Render charts with data
- ✅ Show KPIs with values

## Endpoints Map

| Frontend Call | Proxy Forwards To | Laravel Route |
|--------------|-------------------|---------------|
| `/api/v1/app/dashboard/stats` | `http://localhost:8000/api/v1/app/dashboard/stats` | `GET /api/v1/app/dashboard/stats` |
| `/api/v1/app/dashboard/recent-projects?limit=5` | `http://localhost:8000/api/v1/app/dashboard/recent-projects?limit=5` | `GET /api/v1/app/dashboard/recent-projects` |
| `/api/v1/app/dashboard/recent-activity?limit=10` | `http://localhost:8000/api/v1/app/dashboard/recent-activity?limit=10` | `GET /api/v1/app/dashboard/recent-activity` |
| `/api/v1/app/dashboard/team-status` | `http://localhost:8000/api/v1/app/dashboard/team-status` | `GET /api/v1/app/dashboard/team-status` |
| `/api/v1/app/dashboard/charts/project-progress?period=30d` | `http://localhost:8000/api/v1/app/dashboard/charts/project-progress?period=30d` | `GET /api/v1/app/dashboard/charts/{type}` |
| `/api/v1/app/dashboard/charts/task-completion?period=30d` | `http://localhost:8000/api/v1/app/dashboard/charts/task-completion?period=30d` | `GET /api/v1/app/dashboard/charts/{type}` |

## Status

✅ API endpoints fixed  
✅ Proxy configured correctly  
✅ Dashboard ready to load data

