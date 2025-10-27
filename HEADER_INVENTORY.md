# Header Components Inventory

## Current Header Files Found

### Blade Components (Backend/Laravel Views):
1. **`resources/views/components/shared/header.blade.php`** ✅ ACTIVE
   - Main app header
   - Bridge between Blade and React
   - Used in `layouts/app.blade.php`

2. **`resources/views/components/shared/header-wrapper.blade.php`** ✅ ACTIVE  
   - Wrapper for React HeaderShell
   - Used in layouts (app.blade.php, admin.blade.php, etc.)

3. **`resources/views/components/shared/header-standardized.blade.php`** ❓
   - Standardized header (Alpine.js based)
   - Not actively used in current layouts

4. **`resources/views/components/shared/simple-header.blade.php`** ❓
   - Simple header
   - Not actively used

5. **`resources/views/components/admin/header.blade.php`** ❓
   - Admin header
   - Not actively used (admin uses header-wrapper)

6. **`resources/views/_demos/header-demo.blade.php`** ❌ DEMO
   - Demo file only

### React Components (Frontend):
1. **`frontend/src/components/layout/Header.tsx`** ✅
   - React header component

2. **`src/components/ui/header/HeaderShell.tsx`** ✅ ACTIVE
   - Main HeaderShell component
   - Used by header-wrapper

3. **`frontend/src/app/layouts/MainLayout.tsx`** ✅ ACTIVE
   - React router layout header
   - Recently had "Đồng bộ bố cục" button removed

## Current Active Headers

### For Laravel Blade Views:
**Component:** `<x-shared.header-wrapper>` (resources/views/components/shared/header-wrapper.blade.php)

**Used in:**
- `resources/views/layouts/app.blade.php` (app routes)
- `resources/views/layouts/admin.blade.php` (admin routes)
- `resources/views/layouts/app-layout.blade.php`
- `resources/views/layouts/admin-layout.blade.php`

**Implementation:**
- Wraps React HeaderShell component
- Passes data via data attributes
- Renders in `<div id="header-shell-root">`

### For React Frontend:
**Component:** `MainLayout` (frontend/src/app/layouts/MainLayout.tsx)

**Used for:**
- React routes (localhost:5173)
- Frontend v1 routes

**Implementation:**
- Direct JSX implementation
- Theme toggle, Logout button
- ✅ Recently removed "Đồng bộ bố cục" button

## Summary

### Total Headers Found: 6 Blade + 3 React = 9 files

### Currently ACTIVE:
1. **Blade:** `<x-shared.header-wrapper>` (for /app routes)
2. **Blade:** `<x-shared.header-wrapper variant="admin">` (for /admin routes)
3. **React:** `MainLayout` (for React frontend)

### Currently INACTIVE/UNUSED:
- `resources/views/components/shared/header-standardized.blade.php`
- `resources/views/components/shared/simple-header.blade.php`
- `resources/views/components/admin/header.blade.php`
- `resources/views/_demos/header-demo.blade.php`

## Recommendation

Based on architecture rules:
- ✅ KEEP: header-wrapper.blade.php (active, used)
- ✅ KEEP: MainLayout.tsx (active, React routes)
- ⚠️ CONSIDER: Remove unused headers to avoid confusion
- 📝 Document which header to use for each route type

