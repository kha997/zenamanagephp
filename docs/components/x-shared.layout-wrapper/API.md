# `<x-shared.layout-wrapper>` API

Standard Blade frame that wraps every authenticated page with header, sidebar, page chrome, flash messaging, and slots. Source: `resources/views/components/shared/layout-wrapper.blade.php`.

## Props

| Name | Type | Default | Description |
| --- | --- | --- | --- |
| `variant` | `'app' \| 'admin'` | `'app'` | Switches copy, default titles, and theming. |
| `title` | `string|null` | Auto-derived from route name | Override the H1 heading. |
| `subtitle` | `string|null` | `'Welcome back, {user}'` | Secondary text below the title. |
| `breadcrumbs` | `array` | `[]` | Ordered breadcrumb items `[ ['label' => 'Projects', 'url' => '/app/projects'] ]`. |
| `actions` | `Illuminate\View\View|null` | `null` | Slot for right-aligned action buttons. |
| `sidebar` | `Illuminate\View\View|null` | `null` | Optional Blade chunk injected into the fixed sidebar. |
| `user` | `App\Models\User|null` | `Auth::user()` | Passed down to the header wrapper. |
| `tenant` | `App\Models\Tenant|null` | `$user?->tenant` | Used for header + feature flag context. |
| `notifications` | `array` | `[]` | Forwarded to the header wrapper. |
| `showNotifications` | `bool` | `true` | Toggle notifications region (future-ready). |
| `showUserMenu` | `bool` | `true` | Toggle user menu in header (future-ready). |
| `theme` | `'light' \| 'dark'` | `'light'` | Applied to `<html data-theme="">`. |
| `sticky` | `bool` | `true` | Keeps the page-header bar sticky. |
| `condensedOnScroll` | `bool` | `true` | Propagated to the header for condensing behavior. |

## Slots

- Default slot: page content.
- Named slot `actions`: `<x-slot name="actions">...</x-slot>` renders next to the page title.
- Named slot `sidebar`: pass arbitrary markup to show inside the persistent sidebar (desktop) and mobile drawer (Alpine).

## Events

Alpine component emits DOM events for:

- `filter-search`, `filter-sort`, etc., bubbled from nested filter/table components.
- `mobileSidebarOpen` is exposed as Alpine state for hooks (listen with `x-on:toggle-mobile-sidebar` if needed).

## Usage

```blade
<x-shared.layout-wrapper
    variant="app"
    :breadcrumbs="$breadcrumbs"
    :notifications="$notifications"
>
    <x-slot name="actions">
        <x-shared.button-standardized icon="plus">New Project</x-shared.button-standardized>
    </x-slot>

    {{-- Page content --}}
    <livewire:projects.table />
</x-shared.layout-wrapper>
```

## Accessibility

- Header inherits ARIA hooks from `HeaderShell`.
- Breadcrumbs render semantic `<nav>` lists and rely on screen-reader-friendly separators.
- Flash messages use icon/text pairs for better parsing.
