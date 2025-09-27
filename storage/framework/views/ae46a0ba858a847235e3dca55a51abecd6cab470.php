
<aside class="fixed left-0 top-16 bottom-0 w-64 bg-white border-r border-gray-200 z-30" 
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0"
       x-transition>
    <div class="h-full overflow-y-auto">
        <!-- Navigation -->
        <nav class="p-4 space-y-2">
            <!-- Dashboard -->
            <a href="/admin/dashboard" 
               class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 <?php echo e(request()->is('admin/dashboard') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : ''); ?>">
                <i class="fas fa-tachometer-alt mr-3"></i>
                <span>Dashboard</span>
            </a>
            
            <!-- Tenants -->
            <a href="/admin/tenants" 
               class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 <?php echo e(request()->is('admin/tenants*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : ''); ?>">
                <i class="fas fa-building mr-3"></i>
                <span>Tenants</span>
            </a>
            
            <!-- Users -->
            <a href="/admin/users" 
               class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 <?php echo e(request()->is('admin/users*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : ''); ?>">
                <i class="fas fa-users mr-3"></i>
                <span>Users</span>
            </a>
            
            <!-- Security -->
            <a href="/admin/security" 
               class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 <?php echo e(request()->is('admin/security*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : ''); ?>">
                <i class="fas fa-shield-alt mr-3"></i>
                <span>Security</span>
            </a>
            
            <!-- Settings -->
            <a href="/admin/settings" 
               class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 <?php echo e(request()->is('admin/settings*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : ''); ?>">
                <i class="fas fa-cog mr-3"></i>
                <span>Settings</span>
            </a>
            
            <!-- Billing -->
            <a href="/admin/billing" 
               class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 <?php echo e(request()->is('admin/billing*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : ''); ?>">
                <i class="fas fa-credit-card mr-3"></i>
                <span>Billing</span>
            </a>
            
            <!-- Maintenance -->
            <a href="/admin/maintenance" 
               class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 <?php echo e(request()->is('admin/maintenance*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : ''); ?>">
                <i class="fas fa-tools mr-3"></i>
                <span>Maintenance</span>
            </a>
            
            <!-- Alerts -->
            <a href="/admin/alerts" 
               class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 <?php echo e(request()->is('admin/alerts*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : ''); ?>">
                <i class="fas fa-exclamation-triangle mr-3"></i>
                <span>Alerts</span>
            </a>
        </nav>
        
        <!-- System Status -->
        <div class="p-4 border-t border-gray-200 mt-4">
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
    </div>
</aside>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/layouts/partials/_sidebar.blade.php ENDPATH**/ ?>