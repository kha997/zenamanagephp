# Component Library Guide

## Philosophy

The component library aims to provide a set of reusable, consistent, and accessible UI components for the ZenaManage project. It promotes a unified user experience and reduces development time by providing pre-built solutions for common UI patterns.

## Directory Structure

```
shared/ui/
├── layout/
│   ├── LayoutWrapper.tsx          # Main layout wrapper component
│   ├── index.ts                     # Exports all layout components
├── header/
│   ├── HeaderShell.tsx              # Standardized header component
│   ├── index.ts
├── table/
│   ├── TableStandardized.tsx        # Standardized table component
│   ├── index.ts
└── ...
```

## Naming Conventions

-   Component names should be descriptive and PascalCase (e.g., `HeaderShell`, `TableStandardized`).
-   Props should be camelCase (e.g., `title`, `items`).
-   Files should use the `.tsx` extension for React components and `.blade.php` for Blade components.

## Props and Slots/Children

-   Use descriptive prop names with clear types.
-   For React components, use `children` prop for content projection.
-   For Blade components, use slots for content projection.

## Accessibility (a11y)

-   All components must be accessible to users with disabilities.
-   Use semantic HTML elements.
-   Provide ARIA attributes where necessary.

## Theming

-   Components should support theming using CSS/Tailwind tokens.
-   Use consistent naming conventions for theme variables.

## Usage Examples

### HeaderShell (React)

```tsx
<HeaderShell title="ZenaManage" />
```

### x-shared.header-standardized (Blade)

```blade
<x-shared.header-standardized title="ZenaManage" />
```

### LayoutWrapper (React)

```tsx
<LayoutWrapper>
  {/* Content goes here */}
</LayoutWrapper>
```

### x-shared.layout-wrapper (Blade)

```blade
<x-shared.layout-wrapper>
  @slot('content')
    Content goes here
  @endslot
</x-shared.layout-wrapper>
```

### TableStandardized (React)

```tsx
<TableStandardized data={data} />
```

### x-shared.table-standardized (Blade)

```blade
<x-shared.table-standardized :data="$data" />
```

## Storybook

-   Stories should be organized by component type.
-   Use tags to categorize stories for easy searching.

```javascript
export default {
  title: 'Shared/UI/Header/HeaderShell',
  component: HeaderShell,
  tags: ['header', 'shell', 'standardized'],
};
```

## Deprecation Policy

-   When a component is deprecated, provide a clear migration path.
-   Announce the deprecation in the component library guide.
-   Provide a timeline for the removal of the deprecated component.

## Refactor Plan

-   See [RFC-UI-Standardization.md(RFC-UI-Standardization.md) for the refactor plan.
