# `<x-shared.header-standardized>` API

Blade-only façade that delegates to `components.shared.header-wrapper`. Use it when a Blade view needs the React-powered `HeaderShell`.

Source: `resources/views/components/shared/header-standardized.blade.php`.

## Props

| Name | Type | Default | Description |
| --- | --- | --- | --- |
| `user` | `App\Models\User|null` | `Auth::user()` | Used to derive tenant + personalization. |
| `tenant` | `App\Models\Tenant|null` | `$user?->tenant` | Explicit tenant override. |
| `navigation` | `array` | `[]` | Menu tree passed to the React layer. |
| `notifications` | `array` | `[]` | Notification payload. |
| `unreadCount` | `int` | `0` | Badge count for notifications. |
| `breadcrumbs` | `array` | `[]` | `[ ['label' => 'Projects', 'url' => '/app/projects'] ]`. |
| `theme` | `'light' \| 'dark'` | `'light'` | Matches the React prop. |
| `variant` | `'app' \| 'admin'` | `'app'` | Allows admin-specific styling downstream. |

## Slots

None. All rendering happens through the React mount.

## Usage

```blade
@php
    $headerNavigation = app(\App\Services\HeaderService::class)->getNavigation(Auth::user(), 'app');
@endphp

<x-shared.header-standardized
    :user="$currentUser"
    :tenant="$currentTenant"
    :navigation="$headerNavigation"
    :breadcrumbs="$breadcrumbs"
    theme="{{ session('theme', 'light') }}"
/>
```

## Accessibility & telemetry

- The component itself is a passthrough; accessibility is handled inside `HeaderShell`.
- Telemetry hooks (`data-debug="header-shell"`) remain intact for QA to assert header presence.
