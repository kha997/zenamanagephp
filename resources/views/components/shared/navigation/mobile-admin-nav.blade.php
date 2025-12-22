{{-- Mobile Admin Navigation Component --}}
{{-- Mobile-specific admin navigation --}}

@php
    $mobileAdminNavItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'url' => '/admin/dashboard'],
        ['key' => 'users', 'label' => 'Users', 'icon' => 'fas fa-users', 'url' => '/admin/users'],
        ['key' => 'tenants', 'label' => 'Tenants', 'icon' => 'fas fa-building', 'url' => '/admin/tenants'],
        ['key' => 'projects', 'label' => 'Projects', 'icon' => 'fas fa-project-diagram', 'url' => '/admin/projects'],
        ['key' => 'security', 'label' => 'Security', 'icon' => 'fas fa-shield-alt', 'url' => '/admin/security'],
        ['key' => 'alerts', 'label' => 'Alerts', 'icon' => 'fas fa-exclamation-triangle', 'url' => '/admin/alerts'],
        ['key' => 'activities', 'label' => 'Activities', 'icon' => 'fas fa-history', 'url' => '/admin/activities'],
        ['key' => 'analytics', 'label' => 'Analytics', 'icon' => 'fas fa-chart-bar', 'url' => '/admin/analytics'],
        ['key' => 'settings', 'label' => 'Settings', 'icon' => 'fas fa-cog', 'url' => '/admin/settings']
    ];
@endphp

@foreach($mobileAdminNavItems as $item)
    <a href="{{ $item['url'] }}" 
       @click="setActiveNavItem('{{ $item['key'] }}'); mobileMenuOpen = false"
       class="flex items-center space-x-3 px-3 py-2 text-sm font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
       :class="currentNavItem === '{{ $item['key'] }}' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'">
        <i class="{{ $item['icon'] }} w-5"></i>
        <span>{{ $item['label'] }}</span>
    </a>
@endforeach
