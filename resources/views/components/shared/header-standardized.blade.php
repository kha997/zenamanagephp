{{--
HeaderShell Blade Component - Standardized Header with RBAC, Tenancy, Search, and Mobile Support

Features:
- Role-based navigation filtering
- Theme toggle (light/dark/system)
- Tenant context display
- Global search with debounce
- Mobile hamburger menu
- Breadcrumbs
- Notifications with unread count
- User profile menu
- Full accessibility support

Usage:
<x-shared.header-standardized
    :user="$user"
    :tenant="$tenant"
    :navigation="$navigation"
    :notifications="$notifications"
    :unread-count="$unreadCount"
    :breadcrumbs="$breadcrumbs"
    :show-search="true"
    search-placeholder="Search..."
/>
--}}

@props([
    'user' => null,
    'tenant' => null,
    'navigation' => [],
    'notifications' => [],
    'unreadCount' => 0,
    'breadcrumbs' => [],
    'showSearch' => true,
    'searchPlaceholder' => 'Search...',
    'className' => '',
    'variant' => 'app',
    'onSearch' => null,
    'onNotificationClick' => null,
    'onSettingsClick' => null,
    'onLogout' => null,
])

@php
    // Get authenticated user
    $user = $user ?? Auth::user();
    
    // Try to get tenant safely
    try {
        $tenant = $tenant ?? $user?->tenant;
    } catch (\Exception $e) {
        $tenant = null;
    }
    
    // User data for React
    $userData = $user ? [
        'id' => $user->id,
        'name' => $user->name ?? (isset($user->first_name) ? $user->first_name . ' ' . $user->last_name : 'User'),
        'email' => $user->email ?? '',
        'avatar' => $user->avatar ?? null,
        'roles' => $user->roles ?? [],
        'tenant_id' => $user->tenant_id ?? null,
        'permissions' => $user->permissions ?? [],
    ] : null;
    
    // Tenant data
    $tenantData = $tenant ? [
        'id' => $tenant->id,
        'name' => $tenant->name,
        'type' => $tenant->type ?? null,
    ] : null;
    
    // Navigation items (should be filtered by RBAC on server side)
    $navItems = is_array($navigation) ? $navigation : [];
    
    // Notifications data
    $notificationsData = is_array($notifications) ? $notifications : [];
    
    // Breadcrumbs data
    $breadcrumbsData = is_array($breadcrumbs) ? $breadcrumbs : [];
@endphp

<div id="header-mount" 
     data-user='@json($userData)'
     data-tenant='@json($tenantData)'
     data-nav-items='@json($navItems)'
     data-notifications='@json($notificationsData)'
     data-unread-count='@json($unreadCount)'
     data-breadcrumbs='@json($breadcrumbsData)'
     data-show-search='@json($showSearch)'
     data-search-placeholder='@json($searchPlaceholder)'
     data-variant='@json($variant)'
     class="header-shell-wrapper">
    {{-- React Header will mount here --}}
    <div class="loading-state" style="display: none;">
        Loading header...
    </div>
</div>

@push('scripts')
<script>
    (function() {
        'use strict';
        
        // Wait for React to be ready
        if (typeof window.headerShellMount === 'undefined') {
            console.warn('HeaderShell mount function not available yet. Waiting...');
            
            // Retry after DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initHeader);
            } else {
                setTimeout(initHeader, 100);
            }
        } else {
            initHeader();
        }
        
        function initHeader() {
            const mountEl = document.getElementById('header-mount');
            if (!mountEl) {
                console.error('❌ Header mount element not found!');
                return;
            }
            
            // Get data from attributes
            const userData = JSON.parse(mountEl.dataset.user || 'null');
            const tenantData = JSON.parse(mountEl.dataset.tenant || 'null');
            const navItems = JSON.parse(mountEl.dataset.navItems || '[]');
            const notifications = JSON.parse(mountEl.dataset.notifications || '[]');
            const unreadCount = parseInt(mountEl.dataset.unreadCount || '0');
            const breadcrumbs = JSON.parse(mountEl.dataset.breadcrumbs || '[]');
            const showSearch = mountEl.dataset.showSearch === 'true';
            const searchPlaceholder = mountEl.dataset.searchPlaceholder || 'Search...';
            const variant = mountEl.dataset.variant || 'app';
            
            // Initialize header if the React components are loaded
            if (window.headerShellMount) {
                console.log('✅ Initializing HeaderShell...');
                window.headerShellMount({
                    user: userData,
                    tenant: tenantData,
                    navigation: navItems,
                    notifications: notifications,
                    unreadCount: unreadCount,
                    breadcrumbs: breadcrumbs,
                    showSearch: showSearch,
                    searchPlaceholder: searchPlaceholder,
                    variant: variant,
                });
                console.log('✅ HeaderShell initialized successfully');
            } else {
                console.error('❌ headerShellMount function not found!');
                
                // Show fallback loading state
                const loadingEl = mountEl.querySelector('.loading-state');
                if (loadingEl) {
                    loadingEl.style.display = 'block';
                }
            }
        }
    })();
</script>
@endpush
