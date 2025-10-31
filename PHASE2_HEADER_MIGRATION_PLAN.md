# Phase 2: Header Migration Plan

## Overview

Phase 1 is complete ✅ - All header components, tests, and documentation have been created.

Phase 2 is to **migrate the existing codebase** to use the new HeaderShell component.

## Phase 2 Tasks

### Task 1: Identify All Header Usage
- [ ] Find all React components using `<Header />`
- [ ] Find all React components using `<TopBar />`
- [ ] Find all Blade views using `<x-shared.header>`
- [ ] Find all Blade views using `<x-shared.header-wrapper>`

### Task 2: Update React Components
- [ ] Update `src/app/AppShell.tsx`
- [ ] Update `src/app/layouts/MainLayout.tsx`
- [ ] Update `src/components/Layout.tsx`
- [ ] Update `src/components/ResponsiveLayout.tsx`
- [ ] Update any page-level components using headers

### Task 3: Update Blade Views
- [ ] Update `resources/views/layouts/app.blade.php`
- [ ] Update all `resources/views/app/*/index.blade.php` files
- [ ] Replace header components with `<x-shared.header-standardized>`

### Task 4: Update Props & Data Flow
- [ ] Pass navigation items with required permissions
- [ ] Pass breadcrumbs from route context
- [ ] Pass notifications data
- [ ] Pass tenant information
- [ ] Configure search callbacks

### Task 5: Test & Verify
- [ ] Run unit tests
- [ ] Run E2E tests with @header tag
- [ ] Manual testing on mobile/tablet/desktop
- [ ] Test RBAC filtering
- [ ] Test theme toggle persistence
- [ ] Test search functionality
- [ ] Test notifications
- [ ] Verify breadcrumbs work

## Migration Steps

### Step 1: Prepare Migration Branch

```bash
# Create a migration branch
git checkout -b feat/migrate-to-headershell

# Stage all new header files
git add frontend/src/components/layout/HeaderShell.tsx
git add frontend/src/components/layout/PrimaryNav.tsx
git add frontend/src/components/layout/__tests__/HeaderShell.test.tsx
git add frontend/src/components/layout/HeaderShell.stories.tsx
git add tests/E2E/header/header.spec.ts
git add resources/views/components/shared/header-standardized.blade.php
git add frontend/src/components/layout/index.ts
git add DEPRECATIONS.md IMPLEMENTATION_REPORT.md HEADER_MIGRATION_SUMMARY.md

# Commit the new components
git commit -m "feat(header): add HeaderShell with RBAC/tenancy/search

P0 Requirements:
- HeaderShell component with all features
- PrimaryNav component
- Blade header component
- Unit tests (20+ cases)
- E2E tests (13 cases with @header tag)
- Storybook stories (15 stories)
- Documentation and deprecation guide
"
```

### Step 2: Start Migration

Now update existing files to use HeaderShell. Here's the process:

#### For React Components:

**Before:**
```tsx
import { Header } from '@/components/layout';

export const Layout = () => (
  <div>
    <Header className="..." />
    {/* content */}
  </div>
);
```

**After:**
```tsx
import { HeaderShell } from '@/components/layout';
import { useAuth } from '@/hooks/useAuth';

const mockNavItems = [
  { href: '/dashboard', label: 'Dashboard', requiredPermission: 'dashboard.view' },
  { href: '/projects', label: 'Projects', requiredPermission: 'projects.view' },
];

export const Layout = () => {
  const { user } = useAuth();
  
  return (
    <div>
      <HeaderShell
        navigation={mockNavItems}
        breadcrumbs={[]}
        notifications={[]}
        unreadCount={0}
        tenantName={user?.tenant?.name}
        showSearch={true}
        searchPlaceholder="Search..."
        onSearch={(query) => console.log('Search:', query)}
        onNotificationClick={(notification) => console.log('Notification:', notification)}
        onSettingsClick={() => console.log('Settings')}
        onLogout={() => console.log('Logout')}
      />
      {/* content */}
    </div>
  );
};
```

#### For Blade Views:

**Before:**
```blade
<x-shared.header :user="$user" :variant="'app'" />
```

**After:**
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

### Step 3: Test After Migration

```bash
# Run unit tests
cd frontend && npm test

# Run E2E tests with @header tag
npx playwright test tests/E2E/header/header.spec.ts --grep @header

# Run linter
cd frontend && npm run lint

# Type check
cd frontend && npm run type-check

# Build
cd frontend && npm run build

# Start server and test manually
npm run dev
```

### Step 4: Create and Test PRs

Create 3 separate PRs:

**PR 1**: Core components
- HeaderShell, PrimaryNav, Blade header
- Basic tests

**PR 2**: Complete test suite
- Full unit tests
- E2E tests
- Storybook stories

**PR 3**: Documentation
- DEPRECATIONS.md
- IMPLEMENTATION_REPORT.md
- Migration guide

## Quick Start Commands

```bash
# 1. Create migration branch
git checkout -b feat/migrate-to-headershell

# 2. Stage new files (already created)
git add frontend/src/components/layout/HeaderShell.*
git add frontend/src/components/layout/__tests__/HeaderShell.test.tsx
git add tests/E2E/header/header.spec.ts
git add DEPRECATIONS.md IMPLEMENTATION_REPORT.md

# 3. Commit
git commit -m "feat(header): add HeaderShell implementation"

# 4. Start migration (update existing files)
# TODO: Update files as per migration steps above

# 5. Test
cd frontend && npm test && npm run lint && npm run type-check
npx playwright test tests/E2E/header/header.spec.ts

# 6. Push and create PR
git push origin feat/migrate-to-headershell
```

## Files to Update

### High Priority (Core App)
1. `frontend/src/app/AppShell.tsx` - Main app shell
2. `frontend/src/app/layouts/MainLayout.tsx` - Main layout
3. `frontend/src/components/Layout.tsx` - Layout component
4. `resources/views/layouts/app.blade.php` - Blade app layout

### Medium Priority (Feature Layouts)
5. `frontend/src/components/ResponsiveLayout.tsx` - Responsive layout
6. Individual page layouts that have headers

### Low Priority (Cleanup)
7. Remove old header files after migration complete
8. Update imports across codebase

## Success Criteria

- [ ] All tests pass (unit + E2E)
- [ ] No linting errors
- [ ] Type checking passes
- [ ] Storybook builds
- [ ] RBAC filtering works correctly
- [ ] Theme toggle persists
- [ ] Mobile menu works
- [ ] Search works with debounce
- [ ] Notifications display correctly
- [ ] Breadcrumbs reflect current route
- [ ] All legacy headers replaced
- [ ] Documentation updated

## Timeline

- **Week 1**: Complete Phase 1 (DONE ✅)
- **Week 2-3**: Migrate core app files
- **Week 4**: Test and fix issues
- **Week 5-6**: Remove legacy components
- **Week 7**: Final testing and docs

## Ready to Start Phase 2?

All the foundation is in place. Phase 2 is about **using** what we built. Let me know when you're ready to start the migration!


