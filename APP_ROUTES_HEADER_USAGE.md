# App/* Routes Header Usage

## Current Header: `<x-shared.header-wrapper>`

### Location:
`resources/views/layouts/app.blade.php` (Line 60)

### Usage:
```blade
<x-shared.header-wrapper
    variant="app"
    :user="Auth::user()"
    :tenant="Auth::user()?->tenant"
    :navigation="app(App\Services\HeaderService::class)->getNavigation(Auth::user(), 'app')"
    :notifications="app(App\Services\HeaderService::class)->getNotifications(Auth::user())"
    :unread-count="app(App\Services\HeaderService::class)->getUnreadCount(Auth::user())"
    :theme="app(App\Services\HeaderService::class)->getUserTheme(Auth::user())"
    :breadcrumbs="app(App\Services\HeaderService::class)->getBreadcrumbs(request()->route()->getName(), request()->route()->parameters())"
/>
```

## Pages Using `layouts.app`

All pages in `resources/views/app/` use:
```blade
@extends('layouts.app')
```

### Example Files:
- `resources/views/app/dashboard/index.blade.php`
- `resources/views/app/projects/index.blade.php`
- `resources/views/app/tasks/index.blade.php`
- `resources/views/app/clients/index.blade.php`
- `resources/views/app/team/users.blade.php`
- etc. (total: 34+ files)

## Header Flow

```
app/* routes
    ↓
layouts/app.blade.php
    ↓
<x-shared.header-wrapper variant="app">
    ↓
resources/views/components/shared/header-wrapper.blade.php
    ↓
#header-shell-container (React mounts here)
    ↓
React HeaderShell component
```

## Component Details

### `header-wrapper.blade.php`
- **Location:** `resources/views/components/shared/header-wrapper.blade.php`
- **Purpose:** Mount point for React HeaderShell
- **Props passed:**
  - `user`: Current authenticated user
  - `tenant`: Tenant information
  - `navigation`: Menu items from HeaderService
  - `notifications`: Notifications array
  - `unreadCount`: Count of unread notifications
  - `breadcrumbs`: Breadcrumb trail
  - `variant`: 'app' (for app/* routes)
  - `theme`: Light/dark theme preference

### React HeaderShell
- **Location:** `src/components/ui/header/HeaderShell.tsx`
- **Features:**
  - Theme toggle (light/dark)
  - User menu with logout
  - Notifications bell
  - Search functionality
  - Mobile hamburger menu
  - Breadcrumbs
  - RBAC filtering

## Summary

**Header for app/* routes:**
- Component: `header-wrapper.blade.php` ✅
- Layout: `layouts/app.blade.php` ✅
- Variant: 'app' ✅
- React Component: HeaderShell.tsx ✅

**Recent changes:**
- ✅ "Đồng bộ bố cục" button removed from MainLayout.tsx
- ✅ header-wrapper.blade.php fixed (was corrupted)
- ✅ Unused headers deleted
- ✅ All references updated

