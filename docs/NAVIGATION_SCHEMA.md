# Navigation Schema - Single Source of Truth

**Last Updated**: 2025-01-20  
**Purpose**: Unified navigation schema for both Blade and React components

---

## Overview

Navigation menu is generated from a single source of truth: `NavigationService::getNavigation()`. Both Blade and React components read from this service to ensure consistency.

- **Backend**: `App\Services\NavigationService`
- **API Endpoint**: `/api/v1/me/nav` (for React)
- **Blade Direct Call**: `NavigationService::getNavigationForBlade()` (for Blade)

---

## Navigation Schema

### Base Navigation Item Structure

```typescript
interface NavItem {
  path: string;        // Route path (e.g., '/app/dashboard')
  label: string;       // Display label (e.g., 'Dashboard')
  icon?: string;       // Icon name (e.g., 'Gauge', 'Folder')
  perm?: string;       // Required permission (e.g., 'dashboard.view')
  admin?: boolean;     // Is admin-only item?
  tenant_scoped?: boolean;  // Is tenant-scoped admin item?
  system_only?: boolean;   // Is system-only item (super admin)?
}
```

### Example Response

```json
{
  "navigation": [
    {
      "path": "/app/dashboard",
      "label": "Dashboard",
      "icon": "Gauge",
      "perm": "dashboard.view"
    },
    {
      "path": "/app/projects",
      "label": "Projects",
      "icon": "Folder",
      "perm": "projects.view"
    },
    {
      "path": "/admin/dashboard",
      "label": "Admin Dashboard",
      "icon": "Shield",
      "admin": true,
      "perm": "admin.access"
    }
  ],
  "user": {
    "id": "...",
    "name": "...",
    "email": "...",
    "tenant_id": "...",
    "role": "pm"
  },
  "permissions": ["projects.view", "tasks.view"],
  "abilities": ["tenant"],
  "admin_access": {
    "is_super_admin": false,
    "is_org_admin": true
  }
}
```

---

## Permission-Based Filtering

Navigation items are automatically filtered based on user permissions:

1. **Permission Check**: Each item has a `perm` field that is checked against user permissions
2. **Super Admin**: Users with `admin.access` permission see all items
3. **Org Admin**: Users with `admin.access.tenant` see tenant-scoped admin items
4. **Regular Users**: Only see items they have permissions for

### Permission Mapping

| Permission | Required For |
|------------|--------------|
| `dashboard.view` | Dashboard |
| `projects.view` | Projects |
| `tasks.view` | Tasks |
| `clients.view` | Clients |
| `quotes.view` | Quotes |
| `documents.view` | Documents |
| `settings.view` | Settings |
| `admin.access` | All admin items |
| `admin.access.tenant` | Tenant-scoped admin items |
| `admin.templates.manage` | WBS Templates |
| `admin.projects.read` | Projects Portfolio |
| `admin.analytics.tenant` | Analytics |
| `admin.activities.tenant` | Activity Log |
| `admin.settings.tenant` | Settings |
| `admin.members.manage` | Members (Tenant) |

---

## Usage

### React Component

```typescript
import { useNavigation } from '@/app/hooks/useNavigation';

function MainLayout() {
  const { data: navItems = [], isLoading } = useNavigation();
  
  return (
    <nav>
      {navItems
        .filter(item => !item.admin) // Filter admin items if needed
        .map(item => (
          <NavLink key={item.path} to={item.path}>
            {item.label}
          </NavLink>
        ))}
    </nav>
  );
}
```

### Blade Component

```blade
@php
  $navigation = \App\Services\NavigationService::getNavigationForBlade();
@endphp

<nav>
  @foreach($navigation as $item)
    <a href="{{ $item['path'] }}" 
       class="{{ request()->is($item['path'] . '*') ? 'active' : '' }}">
      {{ $item['label'] }}
    </a>
  @endforeach
</nav>
```

---

## Adding New Navigation Items

To add a new navigation item:

1. **Update NavigationService**: Add item to `$navItems` array in `NavigationService::getNavigation()`
2. **Set Permission**: Add `perm` field with required permission
3. **Set Icon**: Add `icon` field (optional)
4. **Test**: Verify item appears for users with correct permissions

### Example

```php
// In NavigationService::getNavigation()
$navItems = [
    // ... existing items
    [
        'path' => '/app/reports',
        'label' => 'Reports',
        'icon' => 'FileText',
        'perm' => 'reports.view',
    ],
];
```

---

## Testing

### Unit Tests

- Test `NavigationService::getNavigation()` returns correct items for different user roles
- Test permission filtering works correctly
- Test admin items only appear for admin users

### Integration Tests

- Test API endpoint `/api/v1/me/nav` returns correct format
- Test Blade component renders correct navigation
- Test React component renders correct navigation

### E2E Tests

- Test navigation consistency between Blade and React
- Test navigation filtering based on permissions
- Test active state highlighting

---

## Migration Notes

### From Hardcoded Navigation

If you have hardcoded navigation in Blade or React:

1. **Identify hardcoded items**: Find all navigation arrays/objects
2. **Map to NavigationService**: Ensure all items exist in `NavigationService`
3. **Replace hardcoded**: Replace with `NavigationService` call or API call
4. **Test**: Verify navigation still works correctly

### Breaking Changes

- Navigation items are now permission-based
- Items without `perm` field are shown to all users
- Admin items require `admin.access` or `admin.access.tenant`

---

## Future Enhancements

1. **Nested Navigation**: Support for sub-menus
2. **Badges**: Support for notification badges on nav items
3. **Custom Ordering**: Allow users to customize nav item order
4. **Feature Flags**: Support for feature-flagged nav items

---

## Related Files

- `app/Services/NavigationService.php` - Service implementation
- `app/Http/Controllers/Api/NavigationController.php` - API controller
- `frontend/src/app/hooks/useNavigation.ts` - React hook
- `resources/views/components/shared/navigation/primary-navigator.blade.php` - Blade component
- `frontend/src/components/layout/HeaderShell.tsx` - React header component

---

**Status**: ✅ Active - Single source of truth established

