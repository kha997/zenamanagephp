{{-- Shared Header Component --}}
{{-- This component provides a bridge between Laravel Blade and React HeaderShell --}}

@props([
    'user' => null,
    'variant' => 'app'
])

@php
    $user = $user ?? Auth::user();
    
    // Try to get tenant safely
    try {
        $tenant = $user?->tenant;
    } catch (\Exception $e) {
        $tenant = null;
    }
    
    // Load menu items from config/menu.json
    $menuJson = file_get_contents(config_path('menu.json'));
    $menuItems = $menuJson ? json_decode($menuJson, true) : [];
    
    // User data for React (simplified to avoid database queries)
    $userData = $user ? [
        'id' => $user->id,
        'name' => $user->name ?? (isset($user->first_name) ? $user->first_name . ' ' . $user->last_name : 'User'),
        'email' => $user->email ?? '',
        'avatar' => $user->avatar ?? null,
        'role' => 'user',
        'roles' => ['user'],
        'tenant_id' => $user->tenant_id ?? null,
        'permissions' => [],
    ] : null;
    
    // Tenant data
    $tenantData = $tenant ? [
        'id' => $tenant->id,
        'name' => $tenant->name,
        'type' => $tenant->type ?? null,
    ] : null;
    
    // Notifications data (mock for now, will be replaced with real API call)
    $notifications = json_encode([]);
    $unreadCount = 0;
    
    // Breadcrumbs (will be dynamically set by pages)
    $breadcrumbs = json_encode([]);
@endphp

{{-- Mount point for React HeaderShell --}}
<div id="header-mount" 
     data-testid="header-legacy"
     data-source="blade"
     data-user='@json($userData)'
     data-tenant='@json($tenantData)'
     data-menu-items='@json($menuItems)'
     data-notifications='@json($notifications)'
     data-unread-count='@json($unreadCount)'
     data-breadcrumbs='@json($breadcrumbs)'
     data-logout-url="{{ route('logout') }}"
     data-csrf-token="{{ csrf_token() }}"
     style="min-height: 80px; background: #f0f0f0; border: 2px dashed #ccc; padding: 10px;">
    <!-- React Header will mount here -->
    <div style="color: #666; font-size: 12px;">Waiting for React to mount...</div>
</div>

{{-- Initialize header via JavaScript --}}
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🔍 Debug: DOMContentLoaded fired');
        
        // Get mount element
        const mountEl = document.getElementById('header-mount');
        console.log('🔍 Debug: Mount element:', mountEl);
        
        if (!mountEl) {
            console.error('❌ Header mount element not found!');
            return;
        }
        
        // Get data from attributes
        const userData = JSON.parse(mountEl.dataset.user || 'null');
        const tenantData = JSON.parse(mountEl.dataset.tenant || 'null');
        const menuItems = JSON.parse(mountEl.dataset.menuItems || '[]');
        const notifications = JSON.parse(mountEl.dataset.notifications || '[]');
        const unreadCount = parseInt(mountEl.dataset.unreadCount || '0');
        const breadcrumbs = JSON.parse(mountEl.dataset.breadcrumbs || '[]');
        const logoutUrl = mountEl.dataset.logoutUrl;
        const csrfToken = mountEl.dataset.csrfToken;
        
        console.log('🔍 Debug: User data:', userData);
        console.log('🔍 Debug: Tenant data:', tenantData);
        console.log('🔍 Debug: Menu items:', menuItems);
        console.log('🔍 Debug: initHeader function:', typeof window.initHeader);
        
        // Initialize header if the React components are loaded
        if (window.initHeader) {
            console.log('✅ Debug: Calling initHeader...');
            window.initHeader({
                user: userData,
                tenant: tenantData,
                menuItems: menuItems,
                notifications: notifications,
                unreadCount: unreadCount,
                breadcrumbs: breadcrumbs,
                logoutUrl: logoutUrl,
                csrfToken: csrfToken,
            });
            console.log('✅ Debug: initHeader called successfully');
        } else {
            console.error('❌ initHeader function not found!');
        }
    });
</script>
@endpush
