# HeaderShell Components Analysis

## Overview

There are two HeaderShell components serving different contexts:

1. **`frontend/src/components/layout/HeaderShell.tsx`** - React SPA (Apple-style)
2. **`src/components/ui/header/HeaderShell.tsx`** - Blade wrapper (SSR)

## Comparison

### 1. React SPA HeaderShell (`frontend/src/components/layout/HeaderShell.tsx`)

**Purpose**: Used in React Router SPA (`MainLayout.tsx`)

**Props**:
- `logo: ReactNode`
- `primaryNav?: ReactNode`
- `centerContent?: ReactNode` (page title/breadcrumbs)
- `searchAction?: ReactNode`
- `notifications?: ReactNode`
- `helpAction?: ReactNode`
- `profileMenu?: ReactNode`
- `className?: string`

**Features**:
- Apple-style minimal design (56-64px height)
- Built-in search modal with ⌘K shortcut
- Built-in theme toggle
- Scroll backdrop blur effect
- Inline styles (no CSS classes)
- Fixed header with scroll detection

**Design**: Follows `docs/UIUX_APPLE_STYLE_SPEC.md`

**Usage**: `frontend/src/app/layouts/MainLayout.tsx`

---

### 2. Blade Wrapper HeaderShell (`src/components/ui/header/HeaderShell.tsx`)

**Purpose**: Used in Blade SSR views via `header-wrapper.blade.php`

**Props**:
- `theme?: 'light' | 'dark'`
- `size?: 'sm' | 'md' | 'lg'`
- `sticky?: boolean`
- `condensedOnScroll?: boolean`
- `withBorder?: boolean`
- `logo: ReactNode`
- `primaryNav?: ReactNode`
- `secondaryActions?: ReactNode`
- `userMenu?: ReactNode`
- `notifications?: ReactNode`
- `breadcrumbs?: ReactNode`
- `className?: string`

**Features**:
- Mobile hamburger menu with overlay
- Breadcrumbs support
- Header condensing on scroll (via `useHeaderCondense` hook)
- More configurable (size, theme, border)
- CSS classes + Tailwind-style
- Mobile sheet menu

**Usage**: `resources/views/components/shared/header-wrapper.blade.php`

---

## Strategy Decision

### Recommended: Keep Separate (Option A)

**Rationale**:
1. **Different contexts**: React SPA vs Blade SSR have different requirements
2. **Different APIs**: Props and features are optimized for their use cases
3. **Different styling approaches**: Inline styles (React) vs CSS classes (Blade)
4. **Low duplication**: Core functionality differs significantly

**Action Items**:
- Document the differences clearly (this file)
- Consider extracting shared utilities if duplication increases
- Keep both components maintained separately

### Alternative: Merge (Option B) - NOT RECOMMENDED

**Risks**:
- High breaking changes risk
- Complex prop interface to support both contexts
- Performance overhead (unused features)
- Maintenance complexity

---

## Shared Utilities (Future Consideration)

If duplication increases, consider extracting:
- Theme toggle logic
- Scroll detection
- Mobile menu state management
- Navigation link styling

---

## Documentation Updates

- ✅ `docs/CURSOR_CONSISTENCY_FIXES.md` - Updated with analysis
- ✅ `docs/RFC-UI-Standardization.md` - Updated to reflect actual implementation
- ✅ `docs/header-inventory.csv` - Updated status
- ✅ This file - Analysis and strategy

---

**Last Updated**: 2025-01-19
**Status**: Analysis complete, strategy decided (Keep Separate)

