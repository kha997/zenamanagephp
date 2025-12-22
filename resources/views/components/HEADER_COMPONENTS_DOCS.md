{{-- Header Components Documentation --}}
{{-- 
HEADER COMPONENTS STRUCTURE (SIMPLIFIED):

Only 1 header component is used: shared/header-wrapper.blade.php

This single header handles ALL contexts (admin and app) via the variant prop:
- variant="admin" for /admin/* routes
- variant="app" for /app/* routes

Features:
- Context-aware navigation via HeaderService (different menu items for admin vs app)
- Always includes: greeting, notifications, user menu, breadcrumbs
- Theme support (light/dark)
- Responsive design (desktop + mobile)
- Accessibility compliant

Architecture:
- Blade Component: resources/views/components/shared/header-wrapper.blade.php
- React Component: src/components/ui/header/HeaderShell.tsx
- Service Layer: app/Services/HeaderService.php (handles navigation differences)

Usage:
```blade
<!-- For admin routes -->
<x-shared.header-wrapper
    variant="admin"
    :user="Auth::user()"
    :navigation="app(App\Services\HeaderService::class)->getNavigation(Auth::user(), 'admin')"
    ...
/>

<!-- For app routes -->
<x-shared.header-wrapper
    variant="app"
    :user="Auth::user()"
    :navigation="app(App\Services\HeaderService::class)->getNavigation(Auth::user(), 'app')"
    ...
/>
```

How it works:
1. HeaderService.getNavigation() returns different navigation arrays based on context
2. Admin context: Returns admin navigation (Users, Tenants, Security, Alerts, etc.)
3. App context: Returns app navigation (Dashboard, Projects, Tasks, Team, Reports)
4. React HeaderShell renders the navigation but styling is identical
5. No need for separate header components

Benefits:
- Single source of truth
- Easier maintenance
- Consistent styling across contexts
- Centralized changes in one component
--}}

{{-- This file serves as documentation only --}}