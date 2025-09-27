<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $__env->yieldContent('title', 'Dashboard'); ?> - ZenaManage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50" x-data="appDashboard()">
    <!-- Universal Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <i class="fas fa-project-diagram text-blue-500 text-2xl mr-3"></i>
                        <h1 class="text-2xl font-bold text-gray-900">ZenaManage</h1>
                    </div>
                    <div class="hidden md:flex items-center space-x-4">
                        <span class="px-3 py-1 bg-green-100 text-green-800 text-sm font-medium rounded-full">
                            <i class="fas fa-circle text-green-500 mr-1"></i>
                            Online
                        </span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <button @click="toggleNotifications" class="relative p-2 text-gray-600 hover:text-gray-900 focus:outline-none">
                            <i class="fas fa-bell text-xl"></i>
                            <span x-show="unreadNotifications > 0" x-text="unreadNotifications" 
                                  class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"></span>
                        </button>
                        <div x-show="showNotifications" @click.away="showNotifications = false" 
                             class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                            <div class="p-4 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900">Notifications</h3>
                            </div>
                            <div class="max-h-64 overflow-y-auto">
                                <template x-for="notification in notifications" :key="'app-' + notification.id">
                                    <div class="p-4 border-b border-gray-100 hover:bg-gray-50">
                                        <div class="flex items-start space-x-3">
                                            <div class="flex-shrink-0">
                                                <i :class="notification.icon" :class="notification.type === 'warning' ? 'text-yellow-500' : 'text-blue-500'"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900" x-text="notification.title"></p>
                                                <p class="text-sm text-gray-500" x-text="notification.message"></p>
                                                <p class="text-xs text-gray-400 mt-1" x-text="notification.time"></p>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="relative">
                        <button @click="toggleUserMenu" class="flex items-center space-x-2 text-gray-700 hover:text-gray-900 focus:outline-none">
                            <img src="https://ui-avatars.com/api/?name=User&background=10b981&color=ffffff" 
                                 alt="User" class="h-8 w-8 rounded-full">
                            <span class="hidden md:block text-sm font-medium">User</span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <div x-show="showUserMenu" @click.away="showUserMenu = false" 
                             class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                            <div class="py-1">
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-user mr-2"></i>Profile
                                </a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-cog mr-2"></i>Settings
                                </a>
                                <hr class="my-1">
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Universal Navigation -->
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-3">
                <div class="flex items-center space-x-8">
                    <a href="/app" class="text-blue-600 font-medium border-b-2 border-blue-600 pb-2">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                    <a href="/app/projects" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-project-diagram mr-2"></i>Projects
                    </a>
                    <a href="/app/tasks" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-tasks mr-2"></i>Tasks
                    </a>
                    <a href="/app/calendar" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-calendar mr-2"></i>Calendar
                    </a>
                    <a href="/app/team" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-users mr-2"></i>Team
                    </a>
                    <a href="/app/documents" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-file-alt mr-2"></i>Documents
                    </a>
                    <a href="/app/settings" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-cog mr-2"></i>Settings
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <input type="text" placeholder="Search..." 
                               class="w-64 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <i class="fas fa-search absolute right-3 top-3 text-gray-400"></i>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
        <?php echo $__env->yieldContent('content'); ?>
    </main>

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

    <script>
        function appDashboard() {
            return {
                showNotifications: false,
                showUserMenu: false,
                showModal: false,
                modalTitle: '',
                modalContent: '',
                currentModal: '',
                unreadNotifications: 2,

                notifications: [
                    {
                        id: 1,
                        title: 'Task Assigned',
                        message: 'You have been assigned to "Update Documentation"',
                        icon: 'fas fa-tasks',
                        type: 'info',
                        time: '5 minutes ago'
                    },
                    {
                        id: 2,
                        title: 'Project Update',
                        message: 'Project "Mobile App" status updated to "In Progress"',
                        icon: 'fas fa-project-diagram',
                        type: 'success',
                        time: '1 hour ago'
                    }
                ],

                toggleNotifications() {
                    this.showNotifications = !this.showNotifications;
                    if (this.showNotifications) {
                        this.unreadNotifications = 0;
                    }
                },

                toggleUserMenu() {
                    this.showUserMenu = !this.showUserMenu;
                },

                openModal(type) {
                    this.currentModal = type;
                    this.showModal = true;
                },

                closeModal() {
                    this.showModal = false;
                    this.currentModal = '';
                },

                executeModalAction() {
                    console.log('Executing action:', this.currentModal);
                    this.closeModal();
                }
            }
        }
    </script>

    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/layouts/app.blade.php ENDPATH**/ ?>