{{-- Admin Sidebar --}}
<aside class="fixed left-0 top-16 bottom-0 bg-white border-r border-gray-200 z-30 transition-all duration-300" 
       :class="sidebarCollapsed ? 'w-16' : 'w-64'"
       x-transition>
    <div class="h-full overflow-y-auto">
        <!-- Sidebar Header with Toggle -->
        <div class="p-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div x-show="!sidebarCollapsed" class="flex items-center">
                    <i class="fas fa-crown text-yellow-500 text-xl mr-2"></i>
                    <span class="text-lg font-bold text-gray-900">Super Admin</span>
                </div>
                <div x-show="sidebarCollapsed" class="flex justify-center">
                    <i class="fas fa-crown text-yellow-500 text-xl"></i>
                </div>
                <button @click="toggleSidebar" 
                        class="p-1 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded transition-colors"
                        :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                        x-text="sidebarCollapsed ? 'Expand' : 'Collapse'">
                    <i :class="sidebarCollapsed ? 'fas fa-chevron-right' : 'fas fa-chevron-left'" class="text-sm"></i>
                </button>
            </div>
        </div>
        
        <!-- Navigation -->
        <nav class="p-4 space-y-2">
            <!-- Dashboard -->
            <a href="/admin/dashboard" 
               class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors {{ request()->is('admin/dashboard') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : '' }}"
               :title="sidebarCollapsed ? 'Dashboard' : ''">
                <i class="fas fa-tachometer-alt mr-3 w-5"></i>
                <span x-show="!sidebarCollapsed">Dashboard</span>
            </a>
            
            <!-- Tenants -->
            <a href="/admin/tenants" 
               class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors {{ request()->is('admin/tenants*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : '' }}"
               :title="sidebarCollapsed ? 'Tenants' : ''">
                <i class="fas fa-building mr-3 w-5"></i>
                <span x-show="!sidebarCollapsed">Tenants</span>
            </a>
            
            <!-- Users -->
            <a href="/admin/users" 
               class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors {{ request()->is('admin/users*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : '' }}"
               :title="sidebarCollapsed ? 'Users' : ''">
                <i class="fas fa-users mr-3 w-5"></i>
                <span x-show="!sidebarCollapsed">Users</span>
            </a>
            
            <!-- Security -->
            <a href="/admin/security" 
               class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors {{ request()->is('admin/security*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : '' }}"
               :title="sidebarCollapsed ? 'Security' : ''">
                <i class="fas fa-shield-alt mr-3 w-5"></i>
                <span x-show="!sidebarCollapsed">Security</span>
            </a>
            
            <!-- Settings -->
            <a href="/admin/settings" 
               class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors {{ request()->is('admin/settings*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : '' }}"
               :title="sidebarCollapsed ? 'Settings' : ''">
                <i class="fas fa-cog mr-3 w-5"></i>
                <span x-show="!sidebarCollapsed">Settings</span>
            </a>
            
            <!-- Billing -->
            <a href="/admin/billing" 
               class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors {{ request()->is('admin/billing*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : '' }}"
               :title="sidebarCollapsed ? 'Billing' : ''">
                <i class="fas fa-credit-card mr-3 w-5"></i>
                <span x-show="!sidebarCollapsed">Billing</span>
            </a>
            
            <!-- Maintenance -->
            <a href="/admin/maintenance" 
               class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors {{ request()->is('admin/maintenance*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : '' }}"
               :title="sidebarCollapsed ? 'Maintenance' : ''">
                <i class="fas fa-tools mr-3 w-5"></i>
                <span x-show="!sidebarCollapsed">Maintenance</span>
            </a>
            
            <!-- Alerts -->
            <a href="/admin/alerts" 
               class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors {{ request()->is('admin/alerts*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : '' }}"
               :title="sidebarCollapsed ? 'Alerts' : ''">
                <i class="fas fa-exclamation-triangle mr-3 w-5"></i>
                <span x-show="!sidebarCollapsed">Alerts</span>
            </a>
        </nav>
        
        <!-- System Status -->
        <div class="p-4 border-t border-gray-200 mt-4">
            <div x-show="!sidebarCollapsed">
                <h3 class="text-sm font-medium text-gray-900 mb-3">System Status</h3>
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">Database</span>
                        <span class="flex items-center text-green-600">
                            <i class="fas fa-circle text-xs mr-1"></i>
                            Online
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">Cache</span>
                        <span class="flex items-center text-green-600">
                            <i class="fas fa-circle text-xs mr-1"></i>
                            Online
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">Queue</span>
                        <span class="flex items-center text-green-600">
                            <i class="fas fa-circle text-xs mr-1"></i>
                            Online
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Collapsed System Status -->
            <div x-show="sidebarCollapsed" class="flex flex-col items-center space-y-3">
                <div class="flex items-center justify-center" title="Database: Online">
                    <i class="fas fa-database text-green-600"></i>
                </div>
                <div class="flex items-center justify-center" title="Cache: Online">
                    <i class="fas fa-memory text-green-600"></i>
                </div>
                <div class="flex items-center justify-center" title="Queue: Online">
                    <i class="fas fa-tasks text-green-600"></i>
                </div>
            </div>
        </div>
    </div>
</aside>
