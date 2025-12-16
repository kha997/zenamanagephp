# Navigation API Documentation

## Overview

The Navigation API provides a single source of truth for navigation menu items, filtered by user permissions. Both React and Blade components consume this API to ensure consistent navigation across the application.

## Endpoint

### GET `/api/v1/me/nav`

Returns navigation items filtered by user permissions.

**Authentication:** Required (Bearer token)

**Middleware:** `auth:sanctum`, `ability:tenant`

**Response Format:**

```json
{
  "navigation": [
    {
      "path": "/app/dashboard",
      "label": "Dashboard",
      "icon": "Gauge",
      "perm": "view_dashboard"
    },
    {
      "path": "/app/projects",
      "label": "Projects",
      "icon": "Folder",
      "perm": "view_projects"
    },
    {
      "path": "/admin/templates",
      "label": "WBS Templates",
      "icon": "FileText",
      "admin": true,
      "perm": "manage_templates"
    }
  ],
  "role": "member",
  "permissions": ["view_dashboard", "view_projects", "view_tasks"]
}
```

## Navigation Item Structure

- `path` (string, required): Route path for the navigation item
- `label` (string, required): Display label
- `icon` (string, optional): Icon identifier
- `perm` (string, optional): Permission required to see this item
- `admin` (boolean, optional): True if this is an admin-only item

## Permission Filtering

Navigation items are automatically filtered based on:
1. User permissions (`perm` field)
2. User role (admin items only shown to admin users)

## Usage

### React

```tsx
import { useNavigation } from '../app/hooks/useNavigation';

function MyComponent() {
  const { data: navItems, isLoading } = useNavigation();
  
  if (isLoading) return <div>Loading...</div>;
  
  return (
    <nav>
      {navItems?.map(item => (
        <a key={item.path} href={item.path}>
          {item.label}
        </a>
      ))}
    </nav>
  );
}
```

### Blade

```blade
@php
    $navigation = \App\Services\NavigationService::getNavigationForBlade();
@endphp

@foreach($navigation as $item)
    <a href="{{ $item['path'] }}">{{ $item['label'] }}</a>
@endforeach
```

## Implementation Details

### Backend

- **Controller:** `App\Http\Controllers\Api\NavigationController`
- **Service:** `App\Services\NavigationService` (for Blade components)
- **Route:** `routes/api_v1.php` line 35

### Frontend

- **Hook:** `frontend/src/app/hooks/useNavigation.ts`
- **Component:** `frontend/src/app/layouts/MainLayout.tsx` (React)
- **Component:** `resources/views/components/shared/navigation/primary-navigator.blade.php` (Blade)

## Notes

- Templates navigation item (`/app/templates`) has been removed - Templates are now admin-only at `/admin/templates/*`
- Navigation is cached for 60 seconds to improve performance
- Fallback navigation is provided if API call fails

