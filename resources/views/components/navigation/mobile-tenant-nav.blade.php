{{-- Mobile Tenant Navigation Component --}}
{{-- Mobile-specific tenant navigation --}}

@php
    $mobileTenantNavItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'url' => '/app/dashboard'],
        ['key' => 'projects', 'label' => 'Projects', 'icon' => 'fas fa-project-diagram', 'url' => '/app/projects'],
        ['key' => 'tasks', 'label' => 'Tasks', 'icon' => 'fas fa-tasks', 'url' => '/app/tasks'],
        ['key' => 'calendar', 'label' => 'Calendar', 'icon' => 'fas fa-calendar-alt', 'url' => '/app/calendar'],
        ['key' => 'documents', 'label' => 'Documents', 'icon' => 'fas fa-file-alt', 'url' => '/app/documents'],
        ['key' => 'team', 'label' => 'Team', 'icon' => 'fas fa-users', 'url' => '/app/team'],
        ['key' => 'templates', 'label' => 'Templates', 'icon' => 'fas fa-layer-group', 'url' => '/app/templates'],
        ['key' => 'settings', 'label' => 'Settings', 'icon' => 'fas fa-cog', 'url' => '/app/settings']
    ];
@endphp

@foreach($mobileTenantNavItems as $item)
    <a href="{{ $item['url'] }}" 
       @click="setActiveNavItem('{{ $item['key'] }}'); mobileMenuOpen = false"
       class="flex items-center space-x-3 px-3 py-2 text-sm font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
       :class="currentNavItem === '{{ $item['key'] }}' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'">
        <i class="{{ $item['icon'] }} w-5"></i>
        <span>{{ $item['label'] }}</span>
    </a>
@endforeach
