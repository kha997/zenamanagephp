{{-- Header Standardized Component - Alias for header-wrapper --}}
{{-- This component provides backward compatibility with RFC-UI-Standardization.md --}}
{{-- It delegates to header-wrapper which is the actual implementation --}}

@props([
    'user' => null,
    'tenant' => null,
    'navigation' => [],
    'notifications' => [],
    'unreadCount' => 0,
    'theme' => 'light',
    'variant' => 'app',
    'alertCount' => 0,
])

{{-- Delegate to header-wrapper with all props passed through --}}
<x-shared.header-wrapper
    :user="$user"
    :tenant="$tenant"
    :navigation="$navigation"
    :notifications="$notifications"
    :unreadCount="$unreadCount"
    :theme="$theme"
    :variant="$variant"
    :alertCount="$alertCount"
/>

