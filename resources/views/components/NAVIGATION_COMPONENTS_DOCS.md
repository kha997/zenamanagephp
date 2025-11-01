{{-- Navigation Components Documentation --}}
{{-- 
NAVIGATION COMPONENTS STRUCTURE:

LAYOUTS:
- layouts/navigation.blade.php
  - Purpose: Simple navigation layout
  - Usage: Basic navigation for simple pages
  - Features: Logo, basic menu items

COMPONENTS/SHARED/NAVIGATION:
- navigation.blade.php (Main Navigation Component)
  - Purpose: Comprehensive navigation component
  - Usage: <x-shared.navigation.navigation />
  - Features: Logo, main menu, user menu, responsive design

- admin-nav.blade.php
  - Purpose: Admin-specific navigation
  - Usage: <x-shared.navigation.admin-nav />
  - Features: Admin menu items, admin branding

- tenant-nav.blade.php
  - Purpose: Tenant-specific navigation
  - Usage: <x-shared.navigation.tenant-nav />
  - Features: Tenant menu items, tenant branding

- breadcrumb.blade.php
  - Purpose: Breadcrumb navigation
  - Usage: <x-shared.navigation.breadcrumb />
  - Features: Hierarchical navigation

- sidebar.blade.php
  - Purpose: Sidebar navigation
  - Usage: <x-shared.navigation.sidebar />
  - Features: Collapsible sidebar, menu items

- dynamic-sidebar.blade.php
  - Purpose: Dynamic sidebar with role-based items
  - Usage: <x-shared.navigation.dynamic-sidebar />
  - Features: Role-based menu items, dynamic content

- universal-navigation.blade.php
  - Purpose: Universal navigation component
  - Usage: <x-shared.navigation.universal-navigation />
  - Features: Universal menu, responsive design

MOBILE COMPONENTS:
- mobile-admin-nav.blade.php
- mobile-tenant-nav.blade.php

RECOMMENDATION:
- Use layouts/navigation.blade.php for simple pages
- Use components/shared/navigation/* for complex navigation needs
- Keep both as they serve different purposes
--}}

{{-- This file serves as documentation only --}}
