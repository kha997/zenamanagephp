{{-- Standardized Layout Wrapper Component --}}
{{-- Provides consistent layout structure for all pages --}}

@props([
    'variant' => 'app', // 'app' or 'admin'
    'title' => null,
    'subtitle' => null,
    'breadcrumbs' => [],
    'actions' => null,
    'user' => null,
    'tenant' => null,
    'notifications' => [],
    'showNotifications' => true,
    'showUserMenu' => true,
    'theme' => 'light',
    'sticky' => true,
    'condensedOnScroll' => true
])

@php
    $isAdmin = $variant === 'admin';
    $user = $user ?? Auth::user();
    $tenant = $tenant ?? ($user ? $user->tenant : null);
    
    // Default page title based on current route
    if (!$title) {
        $routeName = request()->route()->getName();
        $title = match(true) {
            str_contains($routeName, 'dashboard') => $isAdmin ? 'Admin Dashboard' : 'Dashboard',
            str_contains($routeName, 'projects') => 'Projects',
            str_contains($routeName, 'tasks') => 'Tasks',
            str_contains($routeName, 'users') => 'Users',
            str_contains($routeName, 'tenants') => 'Tenants',
            str_contains($routeName, 'settings') => 'Settings',
            default => ucfirst(str_replace(['app.', 'admin.'], '', $routeName))
        };
    }
    
    // Default subtitle based on variant
    if (!$subtitle) {
        $subtitle = $isAdmin 
            ? 'System overview and management' 
            : 'Welcome back, ' . ($user->first_name ?? 'User');
    }
@endphp

<div class="min-h-screen bg-gray-50" 
     x-data="layoutWrapperComponent()" 
     :class="{ 'theme-dark': theme === 'dark' }"
     data-theme="{{ $theme }}">
    
    {{-- Header --}}
    <x-shared.header-wrapper 
        variant="{{ $variant }}"
        :user="$user"
        :tenant="$tenant"
        :navigation="app(App\Services\HeaderService::class)->getNavigation($user, $variant)"
        :notifications="$notifications"
        :unread-count="count($notifications)"
        :breadcrumbs="$breadcrumbs"
        :theme="$theme" />
    
    {{-- Primary Navigator --}}
    <x-shared.navigation.primary-navigator 
        variant="{{ $variant }}"
        :navigation="app(App\Services\HeaderService::class)->getNavigation($user, $variant)"
    />
    
    {{-- Main Layout Container --}}
    <div class="flex flex-col min-h-screen">
        {{-- Main Content Area --}}
        <main class="flex-1">
            {{-- Page Header --}}
            <div class="bg-white shadow-sm border-b border-gray-200 {{ $sticky ? 'sticky top-header z-10' : '' }}">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            {{-- Breadcrumbs --}}
                            @if(!empty($breadcrumbs))
                                <nav class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                                    @foreach($breadcrumbs as $index => $crumb)
                                        @if($index > 0)
                                            <i class="fas fa-chevron-right text-xs"></i>
                                        @endif
                                        @if($crumb['url'] ?? false)
                                            <a href="{{ $crumb['url'] }}" class="hover:text-gray-700">
                                                {{ $crumb['label'] }}
                                            </a>
                                        @else
                                            <span class="text-gray-900 font-medium">{{ $crumb['label'] }}</span>
                                        @endif
                                    @endforeach
                                </nav>
                            @endif
                            
                            {{-- Page Title --}}
                            <h1 class="text-2xl font-bold text-gray-900 truncate">
                                {{ $title }}
                            </h1>
                            
                            {{-- Page Subtitle --}}
                            @if($subtitle)
                                <p class="mt-1 text-sm text-gray-600">
                                    {{ $subtitle }}
                                </p>
                            @endif
                        </div>
                        
                        {{-- Page Actions --}}
                        @if($actions)
                            <div class="flex items-center space-x-3 ml-4">
                                {{ $actions }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            
            {{-- Page Content --}}
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {{-- Success Message --}}
                @if(session('success'))
                    <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-md">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium">{{ session('success') }}</p>
                            </div>
                        </div>
                    </div>
                @endif
                
                {{-- Error Message --}}
                @if(session('error'))
                    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium">{{ session('error') }}</p>
                            </div>
                        </div>
                    </div>
                @endif
                
                {{-- Warning Message --}}
                @if(session('warning'))
                    <div class="mb-6 bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-md">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium">{{ session('warning') }}</p>
                            </div>
                        </div>
                    </div>
                @endif
                
                {{-- Info Message --}}
                @if(session('info'))
                    <div class="mb-6 bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-md">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium">{{ session('info') }}</p>
                            </div>
                        </div>
                    </div>
                @endif
                
                {{-- Main Content Slot --}}
                {{ $slot }}
            </div>
        </main>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('layoutWrapperComponent', () => ({
        theme: '{{ $theme }}',
        
        init() {
            // Initialize theme
            this.applyTheme();
            
            // Handle scroll for condensed header
            if ({{ $condensedOnScroll ? 'true' : 'false' }}) {
                this.handleScroll();
            }
        },
        
        applyTheme() {
            document.documentElement.setAttribute('data-theme', this.theme);
        },
        
        toggleTheme() {
            this.theme = this.theme === 'light' ? 'dark' : 'light';
            this.applyTheme();
        },
        
        handleScroll() {
            // This will be handled by the header component
        }
    }));
});
</script>
@endpush
