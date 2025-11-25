# Frontend Structure Audit

## Overview

This document audits the frontend structure to identify duplication and inconsistencies between `src/` and `frontend/` directories.

**Date**: 2025-01-19  
**Status**: Audit Complete

---

## Directory Structure

### 1. `frontend/` (Modern React App)

**Purpose**: Modern React SPA with Vite, Vitest, Playwright

**Structure**:
```
frontend/
├── src/
│   ├── components/
│   │   ├── layout/
│   │   │   └── HeaderShell.tsx (Apple-style, for React routes)
│   │   ├── navigation/
│   │   │   ├── AdminNavigator.tsx
│   │   │   └── AppNavigator.tsx
│   │   ├── shared/
│   │   │   ├── SmartSearch.tsx
│   │   │   ├── KpiStrip.tsx
│   │   │   └── ... (other shared components)
│   │   └── ui/
│   │       ├── layout/
│   │       │   └── Container.tsx
│   │       └── primitives/
│   │           ├── Button.tsx
│   │           ├── Card.tsx
│   │           └── ...
│   ├── app/
│   │   └── layouts/
│   │       └── MainLayout.tsx (uses HeaderShell from layout/)
│   └── ...
├── vite.config.ts (path aliases: @ → ./src)
├── vitest.config.ts
└── playwright.config.ts
```

**Vite Config Path Aliases**:
- `@` → `frontend/src`
- `@/components` → `frontend/src/components`
- All aliases point to `frontend/src/`

**Usage**: React Router SPA (localhost:5173)

---

### 2. `src/` (Mixed Structure)

**Purpose**: Contains both PHP modules and React components for Blade SSR

**Structure**:
```
src/
├── components/
│   └── ui/
│       └── header/
│           ├── HeaderShell.tsx (Blade wrapper version)
│           ├── Hamburger.tsx
│           ├── MobileSheet.tsx
│           ├── NotificationsBell.tsx
│           ├── NotificationsOverlay.tsx
│           ├── PrimaryNav.tsx
│           ├── SearchOverlay.tsx
│           ├── SearchToggle.tsx
│           └── UserMenu.tsx
├── hooks/
│   └── useHeaderCondense.ts
├── lib/
│   └── menu/
│       └── filterMenu.ts
├── Auth/ (PHP module)
├── ChangeRequest/ (PHP module)
├── Compensation/ (PHP module)
└── ... (other PHP modules)
```

**Usage**: 
- React components: Used by Blade views (via build process)
- PHP modules: Domain modules

**Note**: The `header-wrapper.blade.php` currently uses pure Blade/Alpine.js, not React mounting. The React components in `src/components/ui/header/` may be legacy or for future use.

---

## Key Findings

### 1. HeaderShell Duplication

**Two different HeaderShell components**:

1. **`frontend/src/components/layout/HeaderShell.tsx`**
   - Apple-style design
   - Used in React SPA (`MainLayout.tsx`)
   - Props: logo, primaryNav, centerContent, searchAction, notifications, helpAction, profileMenu
   - Inline styles

2. **`src/components/ui/header/HeaderShell.tsx`**
   - Blade wrapper version
   - Props: theme, size, sticky, condensedOnScroll, withBorder, logo, primaryNav, secondaryActions, userMenu, notifications, breadcrumbs
   - CSS classes + Tailwind-style
   - Mobile menu support

**Status**: ✅ **Keep Separate** (documented in `docs/HEADER_SHELL_ANALYSIS.md`)

---

### 2. Frontend Root Confusion

**Issue**: Two different frontend roots:
- `frontend/` - Modern React app (Vite config, path aliases)
- `src/` - Mixed (PHP modules + React components)

**Impact**:
- Confusion about where to place new components
- Path aliases only work for `frontend/`
- `src/components/ui/header/` not accessible via Vite aliases

---

### 3. Component Location Inconsistency

**Components in `src/components/ui/header/`**:
- Not accessible via `@/components` alias (points to `frontend/src/components`)
- May require custom build configuration
- Currently not used by `header-wrapper.blade.php` (uses pure Blade)

**Components in `frontend/src/components/`**:
- Accessible via Vite aliases
- Part of modern React app structure
- Used by React Router SPA

---

## Recommendations

### Option A: Migrate `src/components/ui/header/` → `frontend/src/components/ui/header/` (Recommended)

**Pros**:
- Single canonical frontend root
- Components accessible via Vite aliases
- Consistent with modern React app structure
- Easier to maintain

**Cons**:
- Requires updating imports
- May break if components are used elsewhere
- Need to verify build process

**Action Items**:
1. Verify if `src/components/ui/header/` components are actually used
2. Check build configuration for `src/` components
3. Move components to `frontend/src/components/ui/header/`
4. Update imports in `header-wrapper.blade.php` (if it uses React)
5. Update path aliases if needed

### Option B: Keep Separate (Current State)

**Pros**:
- No breaking changes
- Clear separation: React SPA vs Blade SSR

**Cons**:
- Confusion about where to place components
- Duplication risk
- Maintenance overhead

**Action Items**:
1. Document the separation clearly
2. Create guidelines for when to use which location
3. Consider creating shared utilities if duplication increases

---

## Migration Plan (If Option A)

### Phase 1: Verification
- [ ] Check if `src/components/ui/header/` components are used
- [ ] Verify build process for `src/` components
- [ ] Check imports/references to `src/components/ui/header/`

### Phase 2: Migration
- [ ] Move `src/components/ui/header/*` → `frontend/src/components/ui/header/`
- [ ] Move `src/hooks/useHeaderCondense.ts` → `frontend/src/hooks/useHeaderCondense.ts`
- [ ] Update imports in moved files
- [ ] Update `header-wrapper.blade.php` if it uses React
- [ ] Update any build configurations

### Phase 3: Cleanup
- [ ] Remove empty `src/components/` directory
- [ ] Update documentation
- [ ] Update path aliases if needed

---

## Current State Summary

| Location | Purpose | Status | Recommendation |
|----------|---------|--------|----------------|
| `frontend/src/components/` | React SPA components | ✅ Active | Keep as canonical |
| `src/components/ui/header/` | Blade SSR components | ⚠️ Unclear usage | Verify and migrate or document |
| `src/hooks/` | React hooks | ⚠️ Unclear usage | Verify and migrate or document |
| `src/lib/` | Shared libraries | ⚠️ Unclear usage | Verify and migrate or document |

---

**Next Steps**: 
1. Verify actual usage of `src/components/ui/header/` components
2. Decide on migration strategy (Option A or B)
3. Execute chosen strategy

