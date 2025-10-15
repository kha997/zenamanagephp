# ZenaManage Component Library Documentation

## Overview

This document provides comprehensive documentation for the ZenaManage component library, which has been standardized during Phase 1 of the UI/UX improvement initiative.

## Component Categories

### 1. Layout Components

#### Header Components
- **`x-shared.header-standardized`** - Unified header component for both app and admin interfaces
- **`x-shared.layout-wrapper`** - Consistent page layout with header, breadcrumbs, and content area
- **`x-shared.dashboard-shell`** - Dashboard-specific layout with KPIs, charts, and activity feeds

#### Layout Wrapper Props
```php
@props([
    'variant' => 'app', // 'app' or 'admin'
    'title' => null,
    'subtitle' => null,
    'breadcrumbs' => [],
    'actions' => null,
    'sidebar' => null,
    'user' => null,
    'tenant' => null,
    'notifications' => [],
    'showNotifications' => true,
    'showUserMenu' => true,
    'theme' => 'light',
    'sticky' => true,
    'condensedOnScroll' => true
])
```

### 2. Data Display Components

#### Table Components
- **`x-shared.table-standardized`** - Enhanced table with sorting, filtering, and bulk actions
- **`x-shared.table-cell`** - Individual table cell with format support

#### Table Props
```php
@props([
    'title' => null,
    'subtitle' => null,
    'columns' => [],
    'items' => [],
    'actions' => [],
    'showBulkActions' => false,
    'showActions' => true,
    'showSearch' => false,
    'showFilters' => false,
    'pagination' => null,
    'emptyState' => null,
    'loading' => false,
    'sortable' => true,
    'sticky' => false,
    'variant' => 'default', // 'default', 'compact', 'bordered'
    'theme' => 'light'
])
```

#### Card Components
- **`x-shared.card-standardized`** - Reusable card component with consistent styling

#### Card Props
```php
@props([
    'title' => null,
    'subtitle' => null,
    'header' => null,
    'footer' => null,
    'variant' => 'default', // 'default', 'bordered', 'elevated', 'flat'
    'size' => 'md', // 'sm', 'md', 'lg'
    'padding' => null, // 'none', 'sm', 'md', 'lg'
    'hover' => false,
    'clickable' => false,
    'loading' => false,
    'theme' => 'light'
])
```

### 3. Form Components

#### Input Components
- **`x-shared.form-input`** - Standardized form input with validation and icons

#### Form Input Props
```php
@props([
    'name' => '',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'autocomplete' => null,
    'size' => 'md', // 'sm', 'md', 'lg'
    'variant' => 'default', // 'default', 'filled', 'outlined'
    'error' => null,
    'help' => null,
    'icon' => null,
    'iconPosition' => 'left', // 'left', 'right'
    'theme' => 'light'
])
```

#### Button Components
- **`x-shared.button-standardized`** - Consistent button styling with variants and states

#### Button Props
```php
@props([
    'type' => 'button', // 'button', 'submit', 'reset'
    'variant' => 'primary', // 'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'ghost', 'link'
    'size' => 'md', // 'xs', 'sm', 'md', 'lg', 'xl'
    'disabled' => false,
    'loading' => false,
    'icon' => null,
    'iconPosition' => 'left', // 'left', 'right', 'only'
    'href' => null,
    'target' => null,
    'theme' => 'light'
])
```

### 4. Feedback Components

#### Alert Components
- **`x-shared.alert-standardized`** - Alert messages with different types and dismissible options

#### Alert Props
```php
@props([
    'type' => 'info', // 'success', 'warning', 'error', 'info'
    'title' => null,
    'message' => null,
    'dismissible' => true,
    'icon' => null,
    'actions' => null,
    'variant' => 'default', // 'default', 'bordered', 'filled'
    'size' => 'md', // 'sm', 'md', 'lg'
    'theme' => 'light'
])
```

#### Empty State Components
- **`x-shared.empty-state`** - Empty state displays for various scenarios

#### Empty State Props
```php
@props([
    'icon' => 'fas fa-inbox',
    'title' => 'No items found',
    'description' => 'There are no items to display at the moment.',
    'action' => null,
    'actionText' => null,
    'actionIcon' => null,
    'actionHandler' => null,
    'size' => 'md', // 'sm', 'md', 'lg'
    'variant' => 'default', // 'default', 'minimal', 'illustrated'
    'theme' => 'light'
])
```

### 5. Mobile Components

#### Navigation Components
- **`x-shared.hamburger-menu`** - Responsive hamburger menu for mobile navigation

#### Hamburger Menu Props
```php
@props([
    'open' => false,
    'variant' => 'default', // 'default', 'minimal', 'overlay'
    'size' => 'md', // 'sm', 'md', 'lg'
    'theme' => 'light'
])
```

#### Action Components
- **`x-shared.fab`** - Floating Action Button for mobile-first design

#### FAB Props
```php
@props([
    'icon' => 'fas fa-plus',
    'label' => null,
    'position' => 'bottom-right', // 'bottom-right', 'bottom-left', 'top-right', 'top-left'
    'size' => 'md', // 'sm', 'md', 'lg'
    'variant' => 'primary', // 'primary', 'secondary', 'success', 'danger'
    'href' => null,
    'target' => null,
    'theme' => 'light'
])
```

#### Sheet Components
- **`x-shared.mobile-sheet`** - Bottom sheet for mobile interactions

#### Mobile Sheet Props
```php
@props([
    'open' => false,
    'position' => 'bottom', // 'bottom', 'top', 'left', 'right'
    'size' => 'md', // 'sm', 'md', 'lg', 'full'
    'backdrop' => true,
    'dismissible' => true,
    'title' => null,
    'actions' => null,
    'theme' => 'light'
])
```

## Design Tokens

### CSS Variables
The component library uses CSS custom properties for consistent theming:

```css
:root {
  /* Header Colors */
  --hdr-bg: #ffffff;
  --hdr-fg: #111827;
  --hdr-border: #e5e7eb;
  --hdr-bg-hover: #f9fafb;
  --hdr-fg-muted: #6b7280;
  
  /* Navigation Colors */
  --nav-active: #3b82f6;
  --nav-hover: #1d4ed8;
  --nav-active-bg: #eff6ff;
  
  /* Table Colors */
  --table-bg: #ffffff;
  --table-border: #e5e7eb;
  --table-header-bg: #f9fafb;
  --table-header-fg: #374151;
  --table-row-hover: #f9fafb;
  --table-row-selected: #eff6ff;
  
  /* Card Colors */
  --card-bg: #ffffff;
  --card-border: #e5e7eb;
  --card-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
  --card-shadow-hover: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
  
  /* Form Colors */
  --form-input-bg: #ffffff;
  --form-input-border: #d1d5db;
  --form-input-border-focus: #3b82f6;
  --form-input-border-error: #ef4444;
  --form-label-fg: #374151;
  --form-help-fg: #6b7280;
  --form-error-fg: #ef4444;
  
  /* Button Colors */
  --btn-primary-bg: #3b82f6;
  --btn-primary-hover: #2563eb;
  --btn-secondary-bg: #6b7280;
  --btn-secondary-hover: #4b5563;
  --btn-success-bg: #10b981;
  --btn-success-hover: #059669;
  --btn-danger-bg: #ef4444;
  --btn-danger-hover: #dc2626;
}
```

### Dark Theme Support
All components support dark theme through the `[data-theme="dark"]` selector:

```css
[data-theme="dark"] {
  --hdr-bg: #0b0f14;
  --hdr-fg: #e6e7e8;
  --hdr-border: #1f2937;
  --hdr-bg-hover: #1f2937;
  --hdr-fg-muted: #9ca3af;
  
  /* ... other dark theme variables ... */
}
```

## Usage Examples

### Basic Layout
```blade
<x-shared.layout-wrapper 
    variant="app"
    title="Dashboard"
    subtitle="Welcome back, John"
    :breadcrumbs="[
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'Dashboard', 'url' => null]
    ]">
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <x-shared.card-standardized title="Total Projects" variant="elevated">
            <div class="text-3xl font-bold text-blue-600">24</div>
        </x-shared.card-standardized>
    </div>
</x-shared.layout-wrapper>
```

### Data Table
```blade
<x-shared.table-standardized 
    title="Users"
    :columns="[
        ['key' => 'name', 'label' => 'Name', 'sortable' => true],
        ['key' => 'email', 'label' => 'Email', 'sortable' => true],
        ['key' => 'status', 'label' => 'Status', 'format' => 'status']
    ]"
    :items="$users"
    :actions="[
        ['type' => 'link', 'icon' => 'fas fa-eye', 'label' => 'View', 'url' => fn($item) => route('users.show', $item['id'])],
        ['type' => 'button', 'icon' => 'fas fa-edit', 'label' => 'Edit', 'handler' => 'editUser']
    ]"
    show-bulk-actions="true"
    show-search="true" />
```

### Form with Validation
```blade
<form method="POST" action="{{ route('users.store') }}">
    @csrf
    
    <x-shared.form-input 
        name="name"
        label="Full Name"
        placeholder="Enter your name"
        required="true"
        icon="fas fa-user"
        :error="$errors->first('name')" />
    
    <x-shared.form-input 
        name="email"
        label="Email Address"
        type="email"
        placeholder="Enter your email"
        required="true"
        icon="fas fa-envelope"
        :error="$errors->first('email')" />
    
    <x-shared.button-standardized 
        type="submit" 
        variant="primary" 
        icon="fas fa-save">
        Save User
    </x-shared.button-standardized>
</form>
```

### Alert Messages
```blade
@if(session('success'))
    <x-shared.alert-standardized 
        type="success"
        title="Success!"
        message="{{ session('success') }}"
        dismissible="true" />
@endif

@if($errors->any())
    <x-shared.alert-standardized 
        type="error"
        title="Validation Error"
        message="Please correct the errors below."
        dismissible="true" />
@endif
```

### Mobile Components
```blade
{{-- Hamburger Menu --}}
<x-shared.hamburger-menu 
    size="md"
    x-model:open="mobileMenuOpen" />

{{-- Floating Action Button --}}
<x-shared.fab 
    icon="fas fa-plus"
    label="Add Item"
    position="bottom-right"
    size="md"
    variant="primary"
    href="{{ route('items.create') }}" />

{{-- Mobile Sheet --}}
<x-shared.mobile-sheet 
    title="Options"
    x-model:open="showSheet">
    
    <div class="space-y-4">
        <button class="w-full text-left p-4 bg-gray-100 rounded-lg">
            Option 1
        </button>
        <button class="w-full text-left p-4 bg-gray-100 rounded-lg">
            Option 2
        </button>
    </div>
</x-shared.mobile-sheet>
```

## Accessibility Features

All components include accessibility features:

- **ARIA Labels**: Proper labeling for screen readers
- **Keyboard Navigation**: Full keyboard support
- **Focus Management**: Visible focus indicators
- **Color Contrast**: WCAG AA compliant color combinations
- **Semantic HTML**: Proper HTML structure and roles

## Browser Support

- **Modern Browsers**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **Mobile Browsers**: iOS Safari 14+, Chrome Mobile 90+
- **Progressive Enhancement**: Graceful degradation for older browsers

## Performance Considerations

- **CSS-in-JS**: Minimal runtime CSS generation
- **Tree Shaking**: Unused styles are automatically removed
- **Lazy Loading**: Components load only when needed
- **Optimized Assets**: Minified CSS and JavaScript

## Migration Guide

### From Legacy Components

1. **Replace old header components**:
   ```blade
   <!-- Old -->
   <x-shared.header />
   
   <!-- New -->
   <x-shared.header-standardized variant="app" />
   ```

2. **Update table components**:
   ```blade
   <!-- Old -->
   <x-shared.table :items="$items" />
   
   <!-- New -->
   <x-shared.table-standardized :items="$items" :columns="$columns" />
   ```

3. **Standardize form inputs**:
   ```blade
   <!-- Old -->
   <input type="text" name="name" class="form-control" />
   
   <!-- New -->
   <x-shared.form-input name="name" label="Name" />
   ```

## Demo Pages

- **Header Demo**: `/demo/header` - Header component variations
- **Components Demo**: `/demo/components` - Comprehensive component showcase

## Contributing

When adding new components:

1. Follow the established naming convention: `x-shared.component-name`
2. Include comprehensive props documentation
3. Add accessibility features
4. Support both light and dark themes
5. Include responsive design considerations
6. Add to the demo pages
7. Update this documentation

## Changelog

### Phase 1 (Current)
- ✅ Standardized header components
- ✅ Created layout wrapper system
- ✅ Enhanced table components
- ✅ Standardized form inputs and buttons
- ✅ Added feedback components (alerts, empty states)
- ✅ Created mobile components (hamburger, FAB, mobile sheet)
- ✅ Implemented design token system
- ✅ Added comprehensive documentation
- ✅ Created demo pages

### Future Phans
- **Phase 2**: Priority page implementations
- **Phase 3**: Admin interface enhancements
- **Phase 4**: Advanced features and animations
- **Phase 5**: Performance optimizations and testing
