<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $__env->yieldContent('title', 'Super Admin'); ?> - ZenaManage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
</head>
<body class="bg-gray-50" x-data="adminApp()">
    <!-- Topbar -->
    <?php echo $__env->make('layouts.partials._topbar', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    
    <div class="flex">
        <!-- Sidebar - Desktop -->
        <div class="hidden lg:block">
            <?php echo $__env->make('layouts.partials._sidebar', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                    </div>
                    
        <!-- Main Content -->
        <main class="flex-1 transition-all duration-300 pb-16 lg:pb-0" 
              :class="sidebarCollapsed ? 'lg:ml-16' : 'lg:ml-64'">
            <!-- Breadcrumb -->
            <nav class="bg-white border-b border-gray-200 px-6 py-3">
                <ol class="flex items-center space-x-2 text-sm text-gray-500">
                    <li><a href="/admin" class="hover:text-gray-700">Super Admin</a></li>
                    <?php echo $__env->yieldContent('breadcrumb'); ?>
                </ol>
            </nav>
            
            <!-- Page Content -->
            <div class="p-6">
                <?php echo $__env->yieldContent('content'); ?>
            </div>
        </main>
    </div>
    
    <!-- Mobile Bottom Navigation -->
    <div class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-40">
        <div class="flex items-center justify-around py-2">
            <a href="/admin/dashboard" 
               class="flex flex-col items-center py-2 px-3 text-xs <?php echo e(request()->is('admin/dashboard') ? 'text-blue-600' : 'text-gray-500'); ?>">
                <i class="fas fa-tachometer-alt text-lg mb-1"></i>
                <span>Dashboard</span>
            </a>
            <a href="/admin/tenants" 
               class="flex flex-col items-center py-2 px-3 text-xs <?php echo e(request()->is('admin/tenants*') ? 'text-blue-600' : 'text-gray-500'); ?>">
                <i class="fas fa-building text-lg mb-1"></i>
                <span>Tenants</span>
            </a>
            <a href="/admin/users" 
               class="flex flex-col items-center py-2 px-3 text-xs <?php echo e(request()->is('admin/users*') ? 'text-blue-600' : 'text-gray-500'); ?>">
                <i class="fas fa-users text-lg mb-1"></i>
                <span>Users</span>
            </a>
            <div class="relative">
                <button @click="showMobileMenu = !showMobileMenu" 
                        class="flex flex-col items-center py-2 px-3 text-xs text-gray-500">
                    <i class="fas fa-ellipsis-h text-lg mb-1"></i>
                    <span>More</span>
                </button>
                
                <!-- Mobile Menu Dropdown -->
                <div x-show="showMobileMenu" @click.away="showMobileMenu = false" 
                     class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200">
                    <div class="py-1">
                        <a href="/admin/security" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-shield-alt mr-2"></i>Security
                        </a>
                        <a href="/admin/settings" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-cog mr-2"></i>Settings
                        </a>
                        <a href="/admin/billing" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-credit-card mr-2"></i>Billing
                        </a>
                        <a href="/admin/maintenance" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-tools mr-2"></i>Maintenance
                        </a>
                        <a href="/admin/alerts" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Alerts
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php echo $__env->make('layouts.partials._footer', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    
    <!-- Modals -->
    <div x-show="showModal" x-transition class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900" x-text="modalTitle"></h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
        </div>
                <div x-html="modalContent"></div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button @click="closeModal" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button @click="executeModalAction" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Confirm
                    </button>
        </div>
        </div>
        </div>
        </div>
        
    <!-- Loading Overlay -->
    <div x-show="isLoading" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 flex items-center space-x-3">
            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
            <span class="text-gray-700">Loading...</span>
        </div>
        </div>
        
    <script>
        function adminApp() {
            return {
                showModal: false,
                modalTitle: '',
                modalContent: '',
                currentModal: '',
                isLoading: false,
                sidebarOpen: true,
                notifications: [],
                unreadNotifications: 0,
                showNotifications: false,
                showUserMenu: false,
                showMobileMenu: false,
                
                // Sidebar Collapse
                sidebarCollapsed: false,
                
                // Global Search
                globalSearchQuery: '',
                globalSearchResults: {
                    tenants: [],
                    users: [],
                    errors: []
                },
                showGlobalSearchResults: false,
                globalSearchTimeout: null,
                
                init() {
                    this.loadNotifications();
                    this.startRealTimeUpdates();
                    this.loadSidebarState();
                },
                
                // Sidebar Collapse Functions
                toggleSidebar() {
                    this.sidebarCollapsed = !this.sidebarCollapsed;
                    this.saveSidebarState();
                },
                
                loadSidebarState() {
                    const saved = localStorage.getItem('sidebarCollapsed');
                    if (saved !== null) {
                        this.sidebarCollapsed = JSON.parse(saved);
                    }
                },
                
                saveSidebarState() {
                    localStorage.setItem('sidebarCollapsed', JSON.stringify(this.sidebarCollapsed));
                },
                
                // Global Search Functions
                performGlobalSearch() {
                    if (this.globalSearchQuery.length < 2) {
                        this.showGlobalSearchResults = false;
                        return;
                    }
                    
                    // Simulate global search API call
                    this.globalSearchResults = this.getGlobalSearchResults(this.globalSearchQuery);
                    this.showGlobalSearchResults = true;
                },
                
                getGlobalSearchResults(query) {
                    // Mock global search data - in real implementation, this would call /api/search/global
                    const mockTenants = [
                        { id: 1, name: 'TechCorp', domain: 'techcorp.com', url: '/admin/tenants' },
                        { id: 2, name: 'ABC Corp', domain: 'abccorp.com', url: '/admin/tenants' }
                    ];
                    
                    const mockUsers = [
                        { id: 1, name: 'John Smith', email: 'john@techcorp.com', url: '/admin/users' },
                        { id: 2, name: 'Sarah Johnson', email: 'sarah@abccorp.com', url: '/admin/users' }
                    ];
                    
                    const mockErrors = [
                        { id: 1, message: 'Database Connection Error', time: '2 minutes ago', url: '/admin/alerts' },
                        { id: 2, message: 'High Memory Usage', time: '15 minutes ago', url: '/admin/alerts' }
                    ];
                    
                    return {
                        tenants: mockTenants.filter(item => 
                            item.name.toLowerCase().includes(query.toLowerCase()) ||
                            item.domain.toLowerCase().includes(query.toLowerCase())
                        ),
                        users: mockUsers.filter(item => 
                            item.name.toLowerCase().includes(query.toLowerCase()) ||
                            item.email.toLowerCase().includes(query.toLowerCase())
                        ),
                        errors: mockErrors.filter(item => 
                            item.message.toLowerCase().includes(query.toLowerCase())
                        )
                    };
                },
                
                selectGlobalSearchResult(result) {
                    this.globalSearchQuery = '';
                    this.showGlobalSearchResults = false;
                    window.location.href = result.url;
                },
                
                loadNotifications() {
                    // Simulate loading notifications
                    this.notifications = [
                        {
                            id: 1,
                            title: 'New Tenant Registration',
                            message: 'ABC Corp registered for trial',
                            type: 'info',
                            time: '2 minutes ago'
                        },
                        {
                            id: 2,
                            title: 'System Alert',
                            message: 'High memory usage detected',
                            type: 'warning',
                            time: '15 minutes ago'
                        }
                    ];
                    this.unreadNotifications = this.notifications.length;
                },
                
                startRealTimeUpdates() {
                    // Simulate real-time updates
                    setInterval(() => {
                        // Update notifications, stats, etc.
                    }, 30000);
                },
                
                openModal(type, data = {}) {
                    this.currentModal = type;
                    this.showModal = true;
                    this.modalTitle = data.title || 'Confirm Action';
                    this.modalContent = data.content || 'Are you sure you want to proceed?';
                },
                
                closeModal() {
                    this.showModal = false;
                    this.currentModal = '';
                },
                
                executeModalAction() {
                    console.log('Executing action:', this.currentModal);
                    this.closeModal();
                },
                
                showLoading() {
                    this.isLoading = true;
                },
                
                hideLoading() {
                    this.isLoading = false;
                },
                
                toggleSidebar() {
                    this.sidebarOpen = !this.sidebarOpen;
                },
                
                markNotificationAsRead(id) {
                    this.notifications = this.notifications.filter(n => n.id !== id);
                    this.unreadNotifications = this.notifications.length;
                },
                
                markAllNotificationsAsRead() {
                    this.notifications = [];
                    this.unreadNotifications = 0;
                }
            }
        }
</script>
    
    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/layouts/admin.blade.php ENDPATH**/ ?>