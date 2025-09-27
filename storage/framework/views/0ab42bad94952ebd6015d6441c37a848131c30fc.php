<!-- Admin Header Component -->
<header x-data="adminHeaderComponent()" class="bg-white shadow-lg border-b border-gray-200 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Logo and Brand -->
            <div class="flex items-center space-x-4">
                <!-- Logo -->
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-red-600 to-orange-600 rounded-xl flex items-center justify-center shadow-lg">
                        <span class="text-white font-bold text-lg">Z</span>
                    </div>
                    <div class="flex flex-col">
                        <h1 class="text-xl font-bold text-gray-900 leading-tight">ZenaManage</h1>
                        <p class="text-xs text-gray-500 leading-tight">Admin Panel</p>
                    </div>
                </div>
            </div>

            <!-- Admin Navigation Menu -->
            <nav class="hidden md:flex items-center space-x-1">
                <button @click="navigateTo('dashboard')" class="admin-nav-button flex items-center space-x-2 px-4 py-2 rounded-lg text-sm font-semibold transition-colors" 
                   :class="currentView === 'dashboard' ? 'bg-red-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </button>
                <button @click="navigateTo('users')" class="admin-nav-button flex items-center space-x-2 px-4 py-2 rounded-lg text-sm font-semibold transition-colors" 
                   :class="currentView === 'users' ? 'bg-red-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </button>
                <button @click="navigateTo('tenants')" class="admin-nav-button flex items-center space-x-2 px-4 py-2 rounded-lg text-sm font-semibold transition-colors" 
                   :class="currentView === 'tenants' ? 'bg-red-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'">
                    <i class="fas fa-building"></i>
                    <span>Tenants</span>
                </button>
                <button @click="navigateTo('projects')" class="admin-nav-button flex items-center space-x-2 px-4 py-2 rounded-lg text-sm font-semibold transition-colors" 
                   :class="currentView === 'projects' ? 'bg-red-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'">
                    <i class="fas fa-project-diagram"></i>
                    <span>Projects</span>
                </button>
                <button @click="navigateTo('security')" class="admin-nav-button flex items-center space-x-2 px-4 py-2 rounded-lg text-sm font-semibold transition-colors" 
                   :class="currentView === 'security' ? 'bg-red-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'">
                    <i class="fas fa-shield-alt"></i>
                    <span>Security</span>
                </button>
                <button @click="navigateTo('alerts')" class="admin-nav-button flex items-center space-x-2 px-4 py-2 rounded-lg text-sm font-semibold transition-colors relative" 
                   :class="currentView === 'alerts' ? 'bg-red-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Alerts</span>
                    <span class="notification-badge bg-red-500 text-white text-xs px-2 py-1 rounded-full font-bold ml-1">3</span>
                </button>
                <button @click="navigateTo('activities')" class="admin-nav-button flex items-center space-x-2 px-4 py-2 rounded-lg text-sm font-semibold transition-colors" 
                   :class="currentView === 'activities' ? 'bg-red-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'">
                    <i class="fas fa-history"></i>
                    <span>Activities</span>
                </button>
                <button @click="navigateTo('analytics')" class="admin-nav-button flex items-center space-x-2 px-4 py-2 rounded-lg text-sm font-semibold transition-colors" 
                   :class="currentView === 'analytics' ? 'bg-red-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'">
                    <i class="fas fa-chart-bar"></i>
                    <span>Analytics</span>
                </button>
                <button @click="navigateTo('maintenance')" class="admin-nav-button flex items-center space-x-2 px-4 py-2 rounded-lg text-sm font-semibold transition-colors" 
                   :class="currentView === 'maintenance' ? 'bg-red-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'">
                    <i class="fas fa-tools"></i>
                    <span>Maintenance</span>
                </button>
                <button @click="navigateTo('settings')" class="admin-nav-button flex items-center space-x-2 px-4 py-2 rounded-lg text-sm font-semibold transition-colors" 
                   :class="currentView === 'settings' ? 'bg-red-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </button>
            </nav>

            <!-- User Greeting -->
            <div class="flex-1 flex justify-center">
                <h3 class="text-lg font-semibold text-gray-800">
                    <span class="text-red-600">Xin chào,</span> 
                    <span class="text-gray-900" x-text="userName || 'Super Admin'">Super Admin</span>
                </h3>
            </div>

            <!-- Right Side Actions -->
            <div class="flex items-center space-x-4">
                <!-- System Status -->
                <div class="flex items-center space-x-2 px-3 py-2 bg-green-50 text-green-700 rounded-lg">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="text-sm font-medium">System Healthy</span>
                </div>

                <!-- Quick Actions -->
                <div class="flex items-center space-x-2">
                    <button class="flex items-center space-x-2 bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-sync-alt"></i>
                        <span class="hidden md:inline text-sm font-medium">Refresh</span>
                    </button>
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center space-x-2 bg-blue-600 text-white px-3 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-plus"></i>
                            <span class="hidden md:inline text-sm font-medium">Quick Actions</span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        
                        <!-- Quick Actions Dropdown -->
                        <div x-show="open" @click.away="open = false" 
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                            <div class="p-4 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
                            </div>
                            <div class="py-1">
                                <a href="/admin/users" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-user-plus mr-3 text-gray-400"></i>
                                    Add New User
                                </a>
                                <a href="/admin/tenants" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-building mr-3 text-gray-400"></i>
                                    Add New Tenant
                                </a>
                                <a href="/admin/maintenance" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-download mr-3 text-gray-400"></i>
                                    Backup System
                                </a>
                                <a href="/admin/security" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-shield-alt mr-3 text-gray-400"></i>
                                    Security Scan
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin User Menu -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center space-x-3 text-gray-700 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500 rounded-lg p-2">
                        <div class="w-8 h-8 bg-gradient-to-br from-red-500 to-orange-500 rounded-full flex items-center justify-center">
                            <span class="text-white text-sm font-bold">A</span>
                        </div>
                        <div class="hidden md:block text-left">
                            <p class="text-sm font-medium text-gray-900">Super Admin</p>
                            <p class="text-xs text-gray-500">System Administrator</p>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                    </button>
                    
                    <!-- Admin User Dropdown -->
                    <div x-show="open" @click.away="open = false" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                        <div class="p-4 border-b border-gray-200">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-orange-500 rounded-full flex items-center justify-center">
                                    <span class="text-white font-bold">A</span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Super Admin</p>
                                    <p class="text-xs text-gray-500">superadmin@zena.com</p>
                                    <p class="text-xs text-red-600">System Administrator</p>
                                </div>
                            </div>
                        </div>
                        <div class="py-1">
                            <a href="/admin/settings" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-cog mr-3 text-gray-400"></i>
                                Admin Settings
                            </a>
                            <a href="/admin/maintenance" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-tools mr-3 text-gray-400"></i>
                                System Maintenance
                            </a>
                            <a href="/app/dashboard" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-tachometer-alt mr-3 text-gray-400"></i>
                                User Dashboard
                            </a>
                            <div class="border-t border-gray-200 my-1"></div>
                            <a href="/logout" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-3 text-gray-400"></i>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</header>

<script>
// Admin Header component logic
document.addEventListener('alpine:init', () => {
    Alpine.data('adminHeaderComponent', () => ({
        userName: 'Super Admin',
        
        get currentView() {
            const path = window.location.pathname;
            const view = path.split('/').pop();
            return view || 'dashboard';
        },
        
        init() {
            // Initialize admin header component
            console.log('Admin header component initialized');
            
            // Get user name from session or auth
            this.getUserName();
        },
        
        getUserName() {
            // Try to get user name from various sources
            try {
                // Check if user data is available in session
                if (window.userData && window.userData.name) {
                    this.userName = window.userData.name;
                } else if (window.Auth && window.Auth.user && window.Auth.user.name) {
                    this.userName = window.Auth.user.name;
                } else {
                    // Default to Super Admin
                    this.userName = 'Super Admin';
                }
            } catch (error) {
                console.log('Using default user name');
                this.userName = 'Super Admin';
            }
        },
        
        navigateTo(view) {
            // Emit event to parent SPA component
            window.dispatchEvent(new CustomEvent('admin-navigate', { 
                detail: { view: view } 
            }));
        }
    }));
});
</script>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/admin-header.blade.php ENDPATH**/ ?>