{{-- Header Components Documentation --}}
{{-- 
HEADER COMPONENTS STRUCTURE (CONSOLIDATED):

1. admin/header.blade.php
   - Purpose: Admin panel header
   - Features: Admin-specific navigation, admin branding, greeting, notifications
   - Usage: <x-admin.header />
   - Target: /admin/* routes
   - Includes: Greeting, notifications, admin navigation, system status

2. shared/header.blade.php  
   - Purpose: General app header for all non-admin pages
   - Features: App navigation, user menu, notifications, theme toggle, greeting
   - Usage: <x-shared.header />
   - Target: /app/* routes (all non-admin pages)
   - Includes: Greeting, notifications, theme toggle, user dropdown, navigation

CONSOLIDATION NOTES:
- universal-header.blade.php has been REMOVED
- All universal-header features have been merged into shared/header.blade.php
- Only 2 header components are allowed in the system
- Both headers must include greeting and notifications by default
- No delegation - features are integrated directly

USAGE GUIDELINES:
- Use admin/header for admin panel (/admin/* routes)
- Use shared/header for all other pages (/app/* routes)
- Both headers include greeting and notifications
- Theme toggle is available in shared/header
- Admin-specific features are in admin/header
--}}

{{-- This file serves as documentation only --}}
