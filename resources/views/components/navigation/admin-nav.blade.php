{{-- Admin Navigation Component --}}
{{-- Admin-specific navigation items with badges --}}

@php
    $adminNavItems = [
        [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'icon' => 'fas fa-tachometer-alt',
            'url' => '/admin/dashboard',
            'badge' => null
        ],
        [
            'key' => 'users',
            'label' => 'Users',
            'icon' => 'fas fa-users',
            'url' => '/admin/users',
            'badge' => '12' // This would come from actual data
        ],
        [
            'key' => 'tenants',
            'label' => 'Tenants',
            'icon' => 'fas fa-building',
            'url' => '/admin/tenants',
            'badge' => '5'
        ],
        [
            'key' => 'projects',
            'label' => 'Projects',
            'icon' => 'fas fa-project-diagram',
            'url' => '/admin/projects',
            'badge' => '24'
        ],
        [
            'key' => 'security',
            'label' => 'Security',
            'icon' => 'fas fa-shield-alt',
            'url' => '/admin/security',
            'badge' => null
        ],
        [
            'key' => 'alerts',
            'label' => 'Alerts',
            'icon' => 'fas fa-exclamation-triangle',
            'url' => '/admin/alerts',
            'badge' => '3'
        ],
        [
            'key' => 'activities',
            'label' => 'Activities',
            'icon' => 'fas fa-history',
            'url' => '/admin/activities',
            'badge' => null
        ],
        [
            'key' => 'analytics',
            'label' => 'Analytics',
            'icon' => 'fas fa-chart-bar',
            'url' => '/admin/analytics',
            'badge' => null
        ],
        [
            'key' => 'settings',
            'label' => 'Settings',
            'icon' => 'fas fa-cog',
            'url' => '/admin/settings',
            'badge' => null
        ]
    ];
@endphp

@foreach($adminNavItems as $item)
    <a href="{{ $item['url'] }}" 
       @click="setActiveNavItem('{{ $item['key'] }}')"
       class="flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
       :class="currentNavItem === '{{ $item['key'] }}' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'">
        <i class="{{ $item['icon'] }}"></i>
        <span>{{ $item['label'] }}</span>
        @if($item['badge'])
            <span class="bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center ml-1">
                {{ $item['badge'] }}
            </span>
        @endif
    </a>
@endforeach
