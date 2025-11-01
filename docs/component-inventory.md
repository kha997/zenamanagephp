# Component Inventory

This document provides an overview of the components used in the ZenaManage project.

## Status labels

-   `standardized`: canonical API, covered by linting/CI, changes must follow PR checklist.
-   `legacy`: still referenced but not aligned with the design system. Refactors must add migration notes/tests.
-   `deprecate`: scheduled for removal once the replacement ships. Block new usage and provide codemods/migration plans.

## Standardized Components

### Header

-   **HeaderShell** (`standardized`): React component for the main header. (`src/components/ui/header/HeaderShell.tsx`)
-   **x-shared.header-standardized** (`standardized`): Blade facade that delegates to `header-wrapper`. (`resources/views/components/shared/header-standardized.blade.php`)

### Layout

-   **LayoutWrapper** (`standardized`): React component for wrapping the layout. (`frontend/src/components/LayoutWrapper.tsx`)
-   **x-shared.layout-wrapper** (`standardized`): Blade component for the standardized layout wrapper. (`resources/views/components/shared/layout-wrapper.blade.php`)

### Navigation

-   **PrimaryNav** (`standardized`): React component for the primary navigation. (`src/components/ui/header/PrimaryNav.tsx`)

### Table

-   **x-shared.table-standardized** (`standardized`): Blade component for the standardized table. (`resources/views/components/shared/table-standardized.blade.php`)

### Filter

-   **FilterBar** (`standardized`): React component for the filter bar. (`frontend/src/features/FilterBar.tsx`)
-   **x-shared.filter-bar** (`standardized`): Blade component for the standardized filter bar. (`resources/views/components/shared/filter-bar.blade.php`)

### Breadcrumb

-   **Breadcrumb** (`standardized`): React component for breadcrumbs. (`frontend/src/components/Breadcrumb.tsx`)

### Search

-   **SearchBar** (`standardized`): React component for the search bar. (`frontend/src/components/SearchBar.tsx`)

### Theme

-   **ThemeSwitcher** (`standardized`): React component for theme switching. (`frontend/src/components/ThemeSwitcher.tsx`)

## Legacy Components

### Legacy (still supported)

-   **LegacyHeader** (`legacy`): React component for the old header. (`frontend/src/components/LegacyHeader.jsx`) → targeted to migrate via codemod to `HeaderShell`.

### Deprecate

-   **OldTable** (`deprecate`): Blade component for the old table. (`resources/views/components/old-table.blade.php`) → replace with `<x-shared.table-standardized>`.

> ✅ Inventory is mirrored in `docs/component-inventory.csv`. Keep both files updated together.
