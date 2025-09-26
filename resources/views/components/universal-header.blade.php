{{-- Universal Header Component --}}
{{-- Fixed header with logo, greeting, avatar dropdown, notifications, theme toggle --}}

<header class="universal-header bg-white shadow-sm border-b border-gray-200 fixed top-0 left-0 right-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-full">
            <!-- Left Side: Logo + Brand + Greeting -->
            <div class="flex items-center space-x-4">
                <!-- Logo -->
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-cube text-white text-sm"></i>
                    </div>
                    <span class="text-xl font-bold text-gray-900">ZenaManage</span>
                </div>
                
                <!-- Greeting (Hidden on mobile) -->
                <div class="hidden md:block">
                    <span class="text-sm text-gray-600">
                        Hello, <span class="font-medium text-gray-900">{{ Auth::user()->first_name ?? 'User' }}</span>
                    </span>
                </div>
            </div>
            
            <!-- Right Side: Notifications + Theme Toggle + User Avatar -->
            <div class="flex items-center space-x-3">
                <!-- Notifications Bell -->
                <div class="relative">
                    <button @click="toggleNotifications" 
                            class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
                            aria-label="Notifications">
                        <i class="fas fa-bell text-lg"></i>
                        <!-- Notification Badge -->
                        <span x-show="alerts.length > 0" 
                              class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"
                              x-text="alerts.length"></span>
                    </button>
                    
                    <!-- Notifications Dropdown -->
                    <div x-show="notificationsOpen" 
                         x-transition
                         @click.away="notificationsOpen = false"
                         class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                        <div class="p-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">Notifications</h3>
                        </div>
                        <div class="max-h-64 overflow-y-auto">
                            <template x-for="alert in alerts" :key="alert.id">
                                <div class="p-3 border-b border-gray-100 hover:bg-gray-50">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <p class="text-sm text-gray-900" x-text="alert.message"></p>
                                            <p class="text-xs text-gray-500 mt-1" x-text="alert.time"></p>
                                        </div>
                                        <div class="flex items-center space-x-1 ml-2">
                                            <button @click="resolveAlert(alert.id)" 
                                                    class="text-green-600 hover:text-green-800 text-xs">
                                                Resolve
                                            </button>
                                            <button @click="acknowledgeAlert(alert.id)" 
                                                    class="text-blue-600 hover:text-blue-800 text-xs">
                                                Ack
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div x-show="alerts.length === 0" class="p-4 text-center text-gray-500">
                                No notifications
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Theme Toggle -->
                <button @click="toggleTheme()" 
                        class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
                        aria-label="Toggle theme">
                    <i class="fas fa-sun" x-show="theme === 'light'"></i>
                    <i class="fas fa-moon" x-show="theme === 'dark'"></i>
                </button>
                
                <!-- User Avatar Dropdown -->
                <div class="relative">
                    <button @click="toggleUserMenu" 
                            class="flex items-center space-x-2 p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <!-- Avatar -->
                        <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                            <span class="text-white text-sm font-medium">
                                {{ strtoupper(substr(Auth::user()->first_name ?? 'U', 0, 1)) }}
                            </span>
                        </div>
                        <!-- Name (Hidden on mobile) -->
                        <span class="hidden md:block text-sm font-medium text-gray-900">
                            {{ Auth::user()->first_name ?? 'User' }} {{ Auth::user()->last_name ?? '' }}
                        </span>
                        <!-- Dropdown Arrow -->
                        <i class="fas fa-chevron-down text-xs text-gray-500"></i>
                    </button>
                    
                    <!-- User Menu Dropdown -->
                    <div x-show="userMenuOpen" 
                         x-transition
                         @click.away="userMenuOpen = false"
                         class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                        <div class="py-1">
                            <!-- Profile -->
                            <a href="/app/profile" 
                               class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user mr-3 text-gray-400"></i>
                                Profile
                            </a>
                            
                            <!-- Settings -->
                            <a href="/app/settings" 
                               class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-cog mr-3 text-gray-400"></i>
                                Settings
                            </a>
                            
                            <!-- Switch Tenant (if applicable) -->
                            @if(Auth::user()->hasRole('super_admin'))
                                <a href="/admin/tenants" 
                                   class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-building mr-3 text-gray-400"></i>
                                    Switch Tenant
                                </a>
                            @endif
                            
                            <!-- Divider -->
                            <div class="border-t border-gray-200 my-1"></div>
                            
                            <!-- Logout -->
                            <form method="POST" action="{{ route('logout') }}" class="block">
                                @csrf
                                <button type="submit" 
                                        class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-sign-out-alt mr-3 text-gray-400"></i>
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
    // Add to universalFrame Alpine.js data
    document.addEventListener('alpine:init', () => {
        Alpine.data('universalFrame', () => ({
            // ... existing code ...
            
            // Header State
            notificationsOpen: false,
            userMenuOpen: false,
            
            // Header Actions
            toggleNotifications() {
                this.notificationsOpen = !this.notificationsOpen;
            },
            
            toggleUserMenu() {
                this.userMenuOpen = !this.userMenuOpen;
            },
            
            // ... rest of existing code ...
        }));
    });
</script>
