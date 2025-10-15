{{-- Standardized Header Component --}}
{{-- Replaces both shared/header.blade.php and admin/header.blade.php --}}

@props([
    'variant' => 'app', // 'app' or 'admin'
    'user' => null,
    'tenant' => null,
    'notifications' => [],
    'showNotifications' => true,
    'showUserMenu' => true,
    'customActions' => null,
    'theme' => 'light'
])

@php
    $isAdmin = $variant === 'admin';
    $user = $user ?? Auth::user();
    $tenant = $tenant ?? ($user ? $user->tenant : null);
    
    // Default navigation items based on variant
    $navItems = $isAdmin ? [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard', 'icon' => 'fas fa-tachometer-alt'],
        ['label' => 'Users', 'url' => '/admin/users', 'icon' => 'fas fa-users'],
        ['label' => 'Tenants', 'url' => '/admin/tenants', 'icon' => 'fas fa-building'],
        ['label' => 'Settings', 'url' => '/admin/settings', 'icon' => 'fas fa-cog']
    ] : [
        ['label' => 'Dashboard', 'url' => '/app/dashboard', 'icon' => 'fas fa-tachometer-alt'],
        ['label' => 'Projects', 'url' => '/app/projects', 'icon' => 'fas fa-project-diagram'],
        ['label' => 'Tasks', 'url' => '/app/tasks', 'icon' => 'fas fa-tasks'],
        ['label' => 'Team', 'url' => '/app/team', 'icon' => 'fas fa-users']
    ];
    
    $brandText = $isAdmin ? 'ZenaManage Admin Panel' : 'ZenaManage';
    $greeting = $isAdmin ? $user->name : "Hello, {$user->name}";
@endphp

<header class="header-shell" 
        x-data="headerComponent()" 
        :class="{ 'condensed': scrolled }"
        @scroll.window="handleScroll()">
    
    <div class="header-container">
        {{-- Logo Section --}}
        <div class="header-logo">
            <a href="{{ $isAdmin ? route('admin.dashboard') : route('app.dashboard') }}" 
               class="flex items-center space-x-2">
                <img class="h-8 w-auto" 
                     src="{{ asset('images/logo.svg') }}" 
                     alt="ZenaManage"
                     loading="lazy">
                <span class="text-xl font-bold text-header-fg">{{ $brandText }}</span>
            </a>
        </div>

        {{-- Desktop Navigation --}}
        <nav class="header-nav">
            @foreach($navItems as $item)
                <a href="{{ isset($item['route']) ? route($item['route']) : $item['url'] }}" 
                   class="header-nav-item {{ isset($item['route']) && request()->routeIs($item['route']) ? 'active' : '' }}"
                   title="{{ $item['label'] }}">
                    <i class="{{ $item['icon'] }} mr-2"></i>
                    <span class="hidden lg:inline">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        {{-- Header Actions --}}
        <div class="header-actions">
            {{-- Custom Actions Slot --}}
            @if($customActions)
                {{ $customActions }}
            @endif

            {{-- Notifications --}}
            @if($showNotifications)
                <div class="relative" x-data="{ open: false }">
                    <button class="header-action-btn" 
                            @click="open = !open; $refs.notificationPanel.focus()"
                            :class="{ 'bg-header-bg-hover': open }"
                            aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                        @if(count($notifications) > 0)
                            <span class="absolute -top-1 -right-1 h-4 w-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">
                                {{ count($notifications) }}
                            </span>
                        @endif
                    </button>
                    
                    {{-- Notification Dropdown --}}
                    <div x-show="open" 
                         x-ref="notificationPanel"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         @click.away="open = false"
                         class="header-dropdown"
                         role="menu"
                         tabindex="-1">
                        
                        <div class="px-4 py-3 border-b border-header-border">
                            <h3 class="text-sm font-medium text-header-fg">Notifications</h3>
                        </div>
                        
                        <div class="max-h-64 overflow-y-auto">
                            @forelse($notifications as $notification)
                                <div class="px-4 py-3 hover:bg-header-bg-hover border-b border-header-border last:border-b-0">
                                    <div class="flex items-start space-x-3">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-{{ $notification['icon'] ?? 'info-circle' }} text-{{ $notification['color'] ?? 'blue' }}-500"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-header-fg">{{ $notification['message'] }}</p>
                                            <p class="text-xs text-header-fg-muted mt-1">{{ $notification['time'] ?? 'Just now' }}</p>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="px-4 py-8 text-center">
                                    <i class="fas fa-bell-slash text-header-fg-muted text-2xl mb-2"></i>
                                    <p class="text-sm text-header-fg-muted">No notifications</p>
                                </div>
                            @endforelse
                        </div>
                        
                        @if(count($notifications) > 0)
                            <div class="px-4 py-2 border-t border-header-border">
                                <a href="{{ $isAdmin ? route('admin.notifications') : route('app.notifications') }}" 
                                   class="text-sm text-nav-active hover:text-nav-hover font-medium">
                                    View all notifications
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- User Menu --}}
            @if($showUserMenu && $user)
                <div class="header-user-menu" x-data="{ open: false }">
                    <button class="flex items-center space-x-2 text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-nav-active"
                            @click="open = !open; $refs.userMenu.focus()"
                            aria-label="User menu">
                        <img class="h-8 w-8 rounded-full object-cover" 
                             src="{{ $user->avatar ?? asset('images/default-avatar.png') }}" 
                             alt="{{ $user->name }}"
                             loading="lazy">
                        <span class="hidden md:inline text-header-fg">{{ $greeting }}</span>
                        <i class="fas fa-chevron-down text-header-fg-muted text-xs"></i>
                    </button>
                    
                    {{-- User Dropdown --}}
                    <div x-show="open" 
                         x-ref="userMenu"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 scale-100"
                         @click.away="open = false"
                         class="header-dropdown"
                         role="menu"
                         tabindex="-1">
                        
                        <div class="px-4 py-3 border-b border-header-border">
                            <p class="text-sm font-medium text-header-fg">{{ $user->name }}</p>
                            <p class="text-xs text-header-fg-muted">{{ $user->email }}</p>
                            @if($tenant)
                                <p class="text-xs text-header-fg-muted">{{ $tenant->name }}</p>
                            @endif
                        </div>
                        
                        <div class="py-1">
                            <a href="{{ $isAdmin ? route('admin.profile') : route('app.profile') }}" 
                               class="header-dropdown-item">
                                <i class="fas fa-user mr-2"></i>
                                Profile
                            </a>
                            <a href="{{ $isAdmin ? '#' : route('app.settings') }}" 
                               class="header-dropdown-item">
                                <i class="fas fa-cog mr-2"></i>
                                Settings
                            </a>
                            @if(!$isAdmin)
                                <a href="{{ route('app.team.index') }}" 
                                   class="header-dropdown-item">
                                    <i class="fas fa-users mr-2"></i>
                                    Team
                                </a>
                            @endif
                        </div>
                        
                        <div class="border-t border-header-border py-1">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" 
                                        class="header-dropdown-item w-full text-left">
                                    <i class="fas fa-sign-out-alt mr-2"></i>
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Mobile Menu Toggle --}}
            <button class="hamburger md:hidden" 
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    :class="{ 'active': mobileMenuOpen }"
                    aria-label="Toggle mobile menu">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
        </div>
    </div>

    {{-- Mobile Navigation Overlay --}}
    <div x-show="mobileMenuOpen" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="mobile-overlay md:hidden"
         @click="mobileMenuOpen = false">
    </div>

    {{-- Mobile Navigation Sheet --}}
    <div x-show="mobileMenuOpen" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="-translate-x-full"
         class="mobile-sheet md:hidden"
         :class="{ 'open': mobileMenuOpen, 'closed': !mobileMenuOpen }">
        
        <div class="p-4">
            {{-- Mobile Logo --}}
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center space-x-2">
                    <img class="h-6 w-auto" 
                         src="{{ asset('images/logo.svg') }}" 
                         alt="ZenaManage"
                         loading="lazy">
                    <span class="text-lg font-bold text-header-fg">{{ $brandText }}</span>
                </div>
                <button @click="mobileMenuOpen = false" 
                        class="p-2 text-header-fg-muted hover:text-header-fg">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            {{-- Mobile Navigation --}}
            <nav class="space-y-2">
                @foreach($navItems as $item)
                    <a href="{{ $item['url'] }}" 
                       class="flex items-center space-x-3 px-3 py-2 text-header-fg hover:bg-header-bg-hover rounded-lg {{ request()->is(trim($item['url'], '/')) ? 'bg-nav-active-bg text-nav-active' : '' }}"
                       @click="mobileMenuOpen = false">
                        <i class="{{ $item['icon'] }} w-5"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            {{-- Mobile User Info --}}
            @if($user)
                <div class="mt-6 pt-6 border-t border-header-border">
                    <div class="flex items-center space-x-3 mb-4">
                        <img class="h-10 w-10 rounded-full object-cover" 
                             src="{{ $user->avatar ?? asset('images/default-avatar.png') }}" 
                             alt="{{ $user->name }}"
                             loading="lazy">
                        <div>
                            <p class="text-sm font-medium text-header-fg">{{ $user->name }}</p>
                            <p class="text-xs text-header-fg-muted">{{ $user->email }}</p>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <a href="{{ $isAdmin ? route('admin.profile') : route('app.profile') }}" 
                           class="flex items-center space-x-3 px-3 py-2 text-header-fg hover:bg-header-bg-hover rounded-lg">
                            <i class="fas fa-user w-5"></i>
                            <span>Profile</span>
                        </a>
                        <a href="{{ $isAdmin ? '#' : route('app.settings') }}" 
                           class="flex items-center space-x-3 px-3 py-2 text-header-fg hover:bg-header-bg-hover rounded-lg">
                            <i class="fas fa-cog w-5"></i>
                            <span>Settings</span>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" 
                                    class="flex items-center space-x-3 px-3 py-2 text-header-fg hover:bg-header-bg-hover rounded-lg w-full text-left">
                                <i class="fas fa-sign-out-alt w-5"></i>
                                <span>Sign out</span>
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</header>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('headerComponent', () => ({
        scrolled: false,
        mobileMenuOpen: false,
        
        init() {
            this.handleScroll();
        },
        
        handleScroll() {
            this.scrolled = window.scrollY > 10;
        }
    }));
});
</script>
@endpush
