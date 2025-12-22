# Deprecation Log

This document tracks deprecated components and provides migration paths.

## Header Components Migration

### Legacy Headers (Deprecated)

The following header components are deprecated and should be replaced with the standardized `HeaderShell` component.

#### React Headers

| Legacy Component | Replacement | Migration Path |
|-----------------|-------------|----------------|
| `frontend/src/components/layout/Header.tsx` | `HeaderShell` | Import from `@/components/layout/HeaderShell` |
| `frontend/src/components/layout/TopBar.tsx` | `HeaderShell` | Replace with `HeaderShell` + `PrimaryNav` |
| `frontend/src/components/ResponsiveLayout.tsx` (MobileHeader) | `HeaderShell` | Use `HeaderShell` with mobile support |
| `frontend/src/components/Layout.tsx` (top bar section) | `HeaderShell` | Replace top bar with `HeaderShell` |

#### Blade Headers

| Legacy Component | Replacement | Migration Path |
|-----------------|-------------|----------------|
| `resources/views/components/shared/header.blade.php` | `<x-shared.header-standardized>` | Use `<x-shared.header-standardized>` |
| `resources/views/components/shared/header-wrapper.blade.php` | `<x-shared.header-standardized>` | Use `<x-shared.header-standardized>` |
| Custom inline headers in views | `<x-shared.header-standardized>` | Replace with standardized component |

### Migration Timeline

- **Phase 1** (Week 1-2): Identify all instances of legacy headers
- **Phase 2** (Week 3-4): Replace with `HeaderShell` or `<x-shared.header-standardized>`
- **Phase 3** (Week 5): Remove legacy components
- **Phase 4** (Week 6): Final testing and documentation

### Breaking Changes

#### Props API Changes

**Before (Legacy Header.tsx):**
```tsx
<Header className="..." />
```

**After (HeaderShell):**
```tsx
<HeaderShell
  navigation={navItems}
  breadcrumbs={breadcrumbs}
  notifications={notifications}
  unreadCount={unreadCount}
  tenantName={tenantName}
  showSearch={true}
  searchPlaceholder="Search..."
  onSearch={(query) => handleSearch(query)}
  onNotificationClick={(notification) => handleNotificationClick(notification)}
  onSettingsClick={() => handleSettingsClick()}
  onLogout={() => handleLogout()}
  className="..."
/>
```

**Before (Legacy Blade header):**
```blade
<x-shared.header
    :user="$user"
    :variant="'app'"
/>
```

**After (HeaderShell Blade):**
```blade
<x-shared.header-standardized
    :user="$user"
    :tenant="$tenant"
    :navigation="$navigation"
    :notifications="$notifications"
    :unread-count="$unreadCount"
    :breadcrumbs="$breadcrumbs"
    :show-search="true"
    search-placeholder="Search..."
    :variant="'app'"
/>
```

### Feature Parity

The new `HeaderShell` component includes all features from legacy headers:

- ✅ RBAC navigation filtering
- ✅ Theme toggle (light/dark/system)
- ✅ Tenant context display
- ✅ Global search with debounce
- ✅ Mobile hamburger menu with focus trap
- ✅ Breadcrumbs
- ✅ Notifications with unread count
- ✅ User profile menu
- ✅ Full accessibility support (ARIA, keyboard navigation)
- ✅ Responsive design

### Deprecation Date

- **Start Date**: TBD
- **End Date**: TBD + 8 weeks
- **Status**: Pending migration

### Migration Checklist

- [ ] Identify all legacy header usage in codebase
- [ ] Create migration plan per file
- [ ] Update imports and props
- [ ] Test functionality
- [ ] Update tests
- [ ] Remove legacy components
- [ ] Update documentation

### Contact

For questions or concerns about this deprecation, please contact the development team.

