{{-- Header Wrapper Component --}}
{{-- Wrapper for React HeaderShell that mounts dynamically --}}

@props([
    'user' => null,
    'tenant' => null,
    'navigation' => [],
    'notifications' => [],
    'unreadCount' => 0,
    'breadcrumbs' => [],
    'theme' => 'light',
    'variant' => 'app'
])

@php
    $user = $user ?? Auth::user();
    
    // User data for React
    $userData = $user ? [
        'id' => $user->id,
        'name' => $user->name ?? 'User',
        'email' => $user->email ?? '',
        'avatar' => $user->avatar ?? null,
        'tenant_id' => $user->tenant_id ?? null,
    ] : null;
    
    // Tenant data
    $tenantData = $tenant ? [
        'id' => $tenant->id,
        'name' => $tenant->name,
    ] : null;
@endphp

{{-- Mount point for React HeaderShell --}}
<div id="header-mount" 
     data-testid="header-wrapper"
     data-source="blade"
     data-user='@json($userData)'
     data-tenant='@json($tenantData)'
     data-navigation='@json($navigation)'
     data-notifications='@json($notifications)'
     data-unread-count="{{ $unreadCount }}"
     data-breadcrumbs='@json($breadcrumbs)'
     data-variant="{{ $variant }}"
     data-theme="{{ $theme }}">
    {{-- HeaderShell will mount here via React --}}
</div>

{{-- Initialize Header --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('header-mount');
    if (container && window.initHeader) {
        window.initHeader({
            user: @json($userData),
            tenant: @json($tenantData),
            menuItems: @json($navigation),
            notifications: @json($notifications),
            unreadCount: {{ $unreadCount }},
            breadcrumbs: @json($breadcrumbs),
            logoutUrl: '{{ route('logout') }}',
            csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        });
    }
});
</script>
