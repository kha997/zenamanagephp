
<aside class="fixed left-0 top-16 bottom-0 bg-white border-r border-gray-200 z-30 transition-all duration-300" 
       :class="sidebarCollapsed ? 'w-16' : 'w-64'"
       x-transition>
    <div class="h-full overflow-y-auto">
        <!-- Sidebar Header with Toggle -->
        <div class="p-3 border-b border-gray-200">
            <div class="flex items-center justify-end">
                <!-- Collapse Toggle Button -->
                <button @click="toggleSidebar" 
                        class="p-1 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded transition-colors"
                        :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                        aria-label="Toggle sidebar">
                    <i :class="sidebarCollapsed ? 'fas fa-chevron-right' : 'fas fa-chevron-left'" class="text-sm"></i>
                </button>
            </div>
        </div>
        
        <!-- Navigation -->
        <nav class="p-3 space-y-1">
            <!-- Dashboard -->
            <a href="/admin/dashboard" 
               class="flex items-center px-3 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors <?php echo e(request()->is('admin/dashboard') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : ''); ?>"
               :title="sidebarCollapsed ? 'Dashboard' : ''"
               :aria-label="sidebarCollapsed ? 'Dashboard' : ''">
                <i class="fas fa-tachometer-alt mr-3 w-5 flex-shrink-0"></i>
                <span x-show="!sidebarCollapsed" class="truncate">Dashboard</span>
            </a>
            
            <!-- Tenants -->
            <a href="/admin/tenants" 
               class="flex items-center px-3 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors <?php echo e(request()->is('admin/tenants*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : ''); ?>"
               :title="sidebarCollapsed ? 'Tenants' : ''"
               :aria-label="sidebarCollapsed ? 'Tenants' : ''">
                <i class="fas fa-building mr-3 w-5 flex-shrink-0"></i>
                <span x-show="!sidebarCollapsed" class="truncate">Tenants</span>
            </a>
            
            <!-- Users -->
            <a href="/admin/users" 
               class="flex items-center px-3 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors <?php echo e(request()->is('admin/users*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : ''); ?>"
               :title="sidebarCollapsed ? 'Users' : ''"
               :aria-label="sidebarCollapsed ? 'Users' : ''">
                <i class="fas fa-users mr-3 w-5 flex-shrink-0"></i>
                <span x-show="!sidebarCollapsed" class="truncate">Users</span>
            </a>
            
            <!-- Security -->
            <a href="/admin/security" 
               class="flex items-center px-3 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors <?php echo e(request()->is('admin/security*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : ''); ?>"
               :title="sidebarCollapsed ? 'Security' : ''"
               :aria-label="sidebarCollapsed ? 'Security' : ''">
                <i class="fas fa-shield-alt mr-3 w-5 flex-shrink-0"></i>
                <span x-show="!sidebarCollapsed" class="truncate">Security</span>
            </a>
            
            <!-- Settings -->
            <a href="/admin/settings" 
               class="flex items-center px-3 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors <?php echo e(request()->is('admin/settings*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : ''); ?>"
               :title="sidebarCollapsed ? 'Settings' : ''"
               :aria-label="sidebarCollapsed ? 'Settings' : ''">
                <i class="fas fa-cog mr-3 w-5 flex-shrink-0"></i>
                <span x-show="!sidebarCollapsed" class="truncate">Settings</span>
            </a>
            
            <!-- Billing -->
            <a href="/admin/billing" 
               class="flex items-center px-3 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors <?php echo e(request()->is('admin/billing*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : ''); ?>"
               :title="sidebarCollapsed ? 'Billing' : ''"
               :aria-label="sidebarCollapsed ? 'Billing' : ''">
                <i class="fas fa-credit-card mr-3 w-5 flex-shrink-0"></i>
                <span x-show="!sidebarCollapsed" class="truncate">Billing</span>
            </a>
            
            <!-- Maintenance -->
            <a href="/admin/maintenance" 
               class="flex items-center px-3 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors <?php echo e(request()->is('admin/maintenance*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : ''); ?>"
               :title="sidebarCollapsed ? 'Maintenance' : ''"
               :aria-label="sidebarCollapsed ? 'Maintenance' : ''">
                <i class="fas fa-tools mr-3 w-5 flex-shrink-0"></i>
                <span x-show="!sidebarCollapsed" class="truncate">Maintenance</span>
            </a>
            
            <!-- Alerts -->
            <a href="/admin/alerts" 
               class="flex items-center px-3 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors <?php echo e(request()->is('admin/alerts*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : ''); ?>"
               :title="sidebarCollapsed ? 'Alerts' : ''"
               :aria-label="sidebarCollapsed ? 'Alerts' : ''">
                <i class="fas fa-exclamation-triangle mr-3 w-5 flex-shrink-0"></i>
                <span x-show="!sidebarCollapsed" class="truncate">Alerts</span>
            </a>
        </nav>
    </div>
</aside>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/layouts/partials/_sidebar.blade.php ENDPATH**/ ?>