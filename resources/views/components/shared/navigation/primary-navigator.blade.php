{{-- Primary Navigator Component --}}
{{-- Horizontal navigation bar below header --}}

@props([
    'variant' => 'app',
    'navigation' => []
])

@php
    // Default navigation items if not provided
    $defaultNavigation = [
        ['name' => 'Dashboard', 'href' => '/app/dashboard', 'icon' => 'home'],
        ['name' => 'Projects', 'href' => '/app/projects', 'icon' => 'folder'],
        ['name' => 'Tasks', 'href' => '/app/tasks', 'icon' => 'clipboard-document-list'],
    ];
    
    $navigation = !empty($navigation) ? $navigation : $defaultNavigation;
@endphp

<nav 
    class="bg-white border-b border-gray-200 shadow-sm"
    data-testid="primary-navigator"
    data-source="blade"
    aria-label="Primary navigation"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center overflow-x-auto">
            @foreach($navigation as $item)
                @php
                    $href = $item['href'] ?? '#';
                    $name = $item['name'] ?? 'Link';
                    $icon = $item['icon'] ?? 'link';
                    $isActive = request()->is($href . '*') || request()->is(substr($href, 1) . '*');
                @endphp
                
                <a 
                    href="{{ $href }}" 
                    class="flex items-center gap-2 px-4 py-3 text-sm font-medium transition-colors whitespace-nowrap {{ $isActive ? 'border-b-2 border-blue-600 text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}"
                >
                    <i class="fas fa-{{ $icon }} text-xs" aria-hidden="true"></i>
                    <span class="hidden sm:inline">{{ $name }}</span>
                </a>
            @endforeach
        </div>
    </div>
</nav>

<style>
    .primary-navigator::-webkit-scrollbar {
        height: 4px;
    }
    .primary-navigator::-webkit-scrollbar-track {
        background: transparent;
    }
    .primary-navigator::-webkit-scrollbar-thumb {
        background: #cbd5e0;
        border-radius: 2px;
    }
    .primary-navigator::-webkit-scrollbar-thumb:hover {
        background: #a0aec0;
    }
</style>

