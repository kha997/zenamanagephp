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
        <!-- Sidebar -->
        <?php echo $__env->make('layouts.partials._sidebar', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        
        <!-- Main Content -->
        <main class="flex-1 ml-64">
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
                
                init() {
                    this.loadNotifications();
                    this.startRealTimeUpdates();
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