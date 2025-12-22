{{-- Tenant Navigation Component --}}
{{-- Tenant-specific navigation items with badges --}}

@php
    $tenantNavItems = [
        [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'icon' => 'fas fa-tachometer-alt',
            'url' => '/app/dashboard',
            'badge' => null
        ],
        [
            'key' => 'projects',
            'label' => 'Projects',
            'icon' => 'fas fa-project-diagram',
            'url' => '/app/projects',
            'badge' => '8'
        ],
        [
            'key' => 'tasks',
            'label' => 'Tasks',
            'icon' => 'fas fa-tasks',
            'url' => '/app/tasks',
            'badge' => '15'
        ],
        [
            'key' => 'calendar',
            'label' => 'Calendar',
            'icon' => 'fas fa-calendar-alt',
            'url' => '/app/calendar',
            'badge' => null
        ],
        [
            'key' => 'documents',
            'label' => 'Documents',
            'icon' => 'fas fa-file-alt',
            'url' => '/app/documents',
            'badge' => '12'
        ],
        [
            'key' => 'team',
            'label' => 'Team',
            'icon' => 'fas fa-users',
            'url' => '/app/team',
            'badge' => '6'
        ],
        [
            'key' => 'templates',
            'label' => 'Templates',
            'icon' => 'fas fa-layer-group',
            'url' => '/app/templates',
            'badge' => '4'
        ],
        [
            'key' => 'settings',
            'label' => 'Settings',
            'icon' => 'fas fa-cog',
            'url' => '/app/settings',
            'badge' => null
        ]
    ];
@endphp

@foreach($tenantNavItems as $item)
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
