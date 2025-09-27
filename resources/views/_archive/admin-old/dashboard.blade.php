{{-- Admin Dashboard - Complete Implementation --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ZenaManage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50" x-data="adminDashboard()">
    <!-- Universal Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <i class="fas fa-crown text-yellow-500 text-2xl mr-3"></i>
                        <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
                    </div>
                    <div class="hidden md:flex items-center space-x-4">
                        <span class="px-3 py-1 bg-green-100 text-green-800 text-sm font-medium rounded-full">
                            <i class="fas fa-circle text-green-500 mr-1"></i>
                            System Online
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
                                <template x-for="notification in notifications" :key="'admin-' + notification.id">
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
                            <img src="https://ui-avatars.com/api/?name=Admin+User&background=3b82f6&color=ffffff" 
                                 alt="Admin User" class="h-8 w-8 rounded-full">
                            <span class="hidden md:block text-sm font-medium">Admin User</span>
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
                    <a href="/admin-dashboard" class="text-blue-600 font-medium border-b-2 border-blue-600 pb-2">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                    <a href="/admin/users" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-users mr-2"></i>Users
                    </a>
                    <a href="/admin/tenants" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-building mr-2"></i>Tenants
                    </a>
                    <a href="/admin/projects" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-project-diagram mr-2"></i>Projects
                    </a>
                    <a href="/admin/analytics" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-chart-bar mr-2"></i>Analytics
                    </a>
                    <a href="/admin/security" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-shield-alt mr-2"></i>Security
                    </a>
                    <a href="/admin/settings" class="text-gray-600 hover:text-gray-900 font-medium">
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

    <!-- KPI Strip -->
    <section class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium">Total Users</p>
                            <p class="text-3xl font-bold" x-text="kpis.totalUsers">1,247</p>
                            <p class="text-blue-100 text-sm">
                                <i class="fas fa-arrow-up mr-1"></i>
                                <span x-text="kpis.userGrowth">+12%</span> from last month
                            </p>
                        </div>
                        <div class="bg-blue-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm font-medium">Active Tenants</p>
                            <p class="text-3xl font-bold" x-text="kpis.activeTenants">89</p>
                            <p class="text-green-100 text-sm">
                                <i class="fas fa-arrow-up mr-1"></i>
                                <span x-text="kpis.tenantGrowth">+5%</span> from last month
                            </p>
                        </div>
                        <div class="bg-green-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-building text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm font-medium">System Health</p>
                            <p class="text-3xl font-bold" x-text="kpis.systemHealth">99.8%</p>
                            <p class="text-purple-100 text-sm">
                                <i class="fas fa-heartbeat mr-1"></i>
                                All systems operational
                            </p>
                        </div>
                        <div class="bg-purple-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-heartbeat text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm font-medium">Storage Usage</p>
                            <p class="text-3xl font-bold" x-text="kpis.storageUsage">67%</p>
                            <p class="text-orange-100 text-sm">
                                <i class="fas fa-database mr-1"></i>
                                2.1TB of 3.2TB used
                            </p>
                        </div>
                        <div class="bg-orange-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-database text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Alert Bar -->
    <section x-show="alerts.length > 0" class="bg-yellow-50 border-b border-yellow-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                    <span class="text-yellow-800 font-medium" x-text="alerts.length + ' alerts require attention'"></span>
                </div>
                <div class="flex items-center space-x-2">
                    <button @click="dismissAllAlerts" class="text-yellow-600 hover:text-yellow-800 text-sm font-medium">
                        Dismiss All
                    </button>
                    <button @click="showAlerts = !showAlerts" class="text-yellow-600 hover:text-yellow-800 text-sm font-medium">
                        <i :class="showAlerts ? 'fas fa-chevron-up' : 'fas fa-chevron-down'"></i>
                    </button>
                </div>
            </div>
            <div x-show="showAlerts" class="mt-3 space-y-2">
                <template x-for="alert in alerts" :key="alert.id">
                    <div class="bg-white rounded-lg p-3 border border-yellow-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <i :class="alert.icon" class="text-yellow-600 mr-2"></i>
                                <span class="text-sm font-medium text-gray-900" x-text="alert.title"></span>
                            </div>
                            <button @click="dismissAlert(alert.id)" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <p class="text-sm text-gray-600 mt-1" x-text="alert.message"></p>
                    </div>
                </template>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column -->
            <div class="lg:col-span-2 space-y-8">
                <!-- System Overview Chart -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-semibold text-gray-900">System Overview</h2>
                        <div class="flex items-center space-x-2">
                            <select x-model="chartPeriod" @change="updateChart" 
                                    class="text-sm border border-gray-300 rounded-md px-3 py-1">
                                <option value="7d">Last 7 days</option>
                                <option value="30d">Last 30 days</option>
                                <option value="90d">Last 90 days</option>
                            </select>
                        </div>
                    </div>
                    <div class="h-64">
                        <canvas id="systemChart"></canvas>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-semibold text-gray-900">Recent Activity</h2>
                        <a href="/admin/activities" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            View All
                        </a>
                    </div>
                    <div class="space-y-4">
                        <template x-for="activity in recentActivities" :key="'recent-' + activity.id">
                            <div class="flex items-start space-x-3 p-3 hover:bg-gray-50 rounded-lg">
                                <div class="flex-shrink-0">
                                    <div :class="activity.iconBg" class="w-8 h-8 rounded-full flex items-center justify-center">
                                        <i :class="activity.icon" :class="activity.iconColor"></i>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900" x-text="activity.title"></p>
                                    <p class="text-sm text-gray-500" x-text="activity.description"></p>
                                    <p class="text-xs text-gray-400 mt-1" x-text="activity.time"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-8">
                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h2>
                    <div class="space-y-3">
                        <button @click="openModal('addUser')" 
                                class="w-full flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-user-plus mr-2"></i>
                            Add User
                        </button>
                        <button @click="openModal('createTenant')" 
                                class="w-full flex items-center justify-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-building mr-2"></i>
                            Create Tenant
                        </button>
                        <button @click="openModal('backupSystem')" 
                                class="w-full flex items-center justify-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-download mr-2"></i>
                            Backup System
                        </button>
                        <button @click="openModal('systemSettings')" 
                                class="w-full flex items-center justify-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                            <i class="fas fa-cog mr-2"></i>
                            System Settings
                        </button>
                    </div>
                </div>

                <!-- System Status -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">System Status</h2>
                    <div class="space-y-4">
                        <template x-for="status in systemStatus" :key="'admin-' + status.name">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div :class="status.status === 'online' ? 'bg-green-500' : 'bg-red-500'" 
                                         class="w-2 h-2 rounded-full mr-3"></div>
                                    <span class="text-sm font-medium text-gray-900" x-text="status.name"></span>
                                </div>
                                <span :class="status.status === 'online' ? 'text-green-600' : 'text-red-600'" 
                                      class="text-sm font-medium" x-text="status.status"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Activity Panel -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Activity Feed</h2>
                        <button @click="refreshActivity" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div class="space-y-3">
                        <template x-for="activity in activityFeed" :key="'feed-' + activity.id">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <img :src="activity.avatar" :alt="activity.user" 
                                         class="w-6 h-6 rounded-full">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900">
                                        <span class="font-medium" x-text="activity.user"></span>
                                        <span x-text="activity.action"></span>
                                    </p>
                                    <p class="text-xs text-gray-500" x-text="activity.time"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
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
        function adminDashboard() {
            return {
                showNotifications: false,
                showUserMenu: false,
                showAlerts: false,
                showModal: false,
                modalTitle: '',
                modalContent: '',
                currentModal: '',
                chartPeriod: '30d',
                unreadNotifications: 3,

                kpis: {
                    totalUsers: 1247,
                    userGrowth: '+12%',
                    activeTenants: 89,
                    tenantGrowth: '+5%',
                    systemHealth: '99.8%',
                    storageUsage: '67%'
                },

                alerts: [
                    {
                        id: 1,
                        title: 'High Memory Usage',
                        message: 'Server memory usage is at 85%',
                        icon: 'fas fa-exclamation-triangle',
                        type: 'warning'
                    },
                    {
                        id: 2,
                        title: 'SSL Certificate Expiring',
                        message: 'SSL certificate expires in 15 days',
                        icon: 'fas fa-certificate',
                        type: 'warning'
                    }
                ],

                notifications: [
                    {
                        id: 1,
                        title: 'New User Registration',
                        message: 'John Doe registered for tenant ABC Corp',
                        icon: 'fas fa-user-plus',
                        type: 'info',
                        time: '2 minutes ago'
                    },
                    {
                        id: 2,
                        title: 'System Backup Complete',
                        message: 'Daily backup completed successfully',
                        icon: 'fas fa-download',
                        type: 'success',
                        time: '1 hour ago'
                    },
                    {
                        id: 3,
                        title: 'Security Alert',
                        message: 'Multiple failed login attempts detected',
                        icon: 'fas fa-shield-alt',
                        type: 'warning',
                        time: '3 hours ago'
                    }
                ],

                recentActivities: [
                    {
                        id: 1,
                        title: 'User Created',
                        description: 'New user "Jane Smith" added to tenant "TechCorp"',
                        icon: 'fas fa-user-plus',
                        iconColor: 'text-blue-600',
                        iconBg: 'bg-blue-100',
                        time: '5 minutes ago'
                    },
                    {
                        id: 2,
                        title: 'Tenant Updated',
                        description: 'Tenant "ABC Corp" settings updated',
                        icon: 'fas fa-building',
                        iconColor: 'text-green-600',
                        iconBg: 'bg-green-100',
                        time: '15 minutes ago'
                    },
                    {
                        id: 3,
                        title: 'System Backup',
                        description: 'Daily system backup completed',
                        icon: 'fas fa-download',
                        iconColor: 'text-purple-600',
                        iconBg: 'bg-purple-100',
                        time: '1 hour ago'
                    }
                ],

                systemStatus: [
                    { name: 'Database', status: 'online' },
                    { name: 'Cache', status: 'online' },
                    { name: 'Queue', status: 'online' },
                    { name: 'Storage', status: 'online' },
                    { name: 'Email', status: 'online' }
                ],

                activityFeed: [
                    {
                        id: 1,
                        user: 'John Doe',
                        action: ' created a new project',
                        avatar: 'https://ui-avatars.com/api/?name=John+Doe&background=3b82f6&color=ffffff',
                        time: '2 minutes ago'
                    },
                    {
                        id: 2,
                        user: 'Jane Smith',
                        action: ' updated user permissions',
                        avatar: 'https://ui-avatars.com/api/?name=Jane+Smith&background=10b981&color=ffffff',
                        time: '5 minutes ago'
                    },
                    {
                        id: 3,
                        user: 'Admin',
                        action: ' performed system backup',
                        avatar: 'https://ui-avatars.com/api/?name=Admin&background=8b5cf6&color=ffffff',
                        time: '1 hour ago'
                    }
                ],

                init() {
                    this.initChart();
                    this.startRealTimeUpdates();
                },

                initChart() {
                    const ctx = document.getElementById('systemChart').getContext('2d');
                    this.chart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                            datasets: [{
                                label: 'Users',
                                data: [1200, 1250, 1300, 1280, 1320, 1247],
                                borderColor: 'rgb(59, 130, 246)',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.4
                            }, {
                                label: 'Tenants',
                                data: [80, 85, 88, 87, 89, 89],
                                borderColor: 'rgb(16, 185, 129)',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                },

                updateChart() {
                    // Simulate chart update based on period
                    console.log('Updating chart for period:', this.chartPeriod);
                },

                toggleNotifications() {
                    this.showNotifications = !this.showNotifications;
                    if (this.showNotifications) {
                        this.unreadNotifications = 0;
                    }
                },

                toggleUserMenu() {
                    this.showUserMenu = !this.showUserMenu;
                },

                dismissAlert(alertId) {
                    this.alerts = this.alerts.filter(alert => alert.id !== alertId);
                },

                dismissAllAlerts() {
                    this.alerts = [];
                },

                openModal(type) {
                    this.currentModal = type;
                    this.showModal = true;
                    
                    switch(type) {
                        case 'addUser':
                            this.modalTitle = 'Add New User';
                            this.modalContent = `
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                        <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                        <input type="email" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                        <select class="w-full border border-gray-300 rounded-md px-3 py-2">
                                            <option>Admin</option>
                                            <option>Project Manager</option>
                                            <option>Member</option>
                                        </select>
                                    </div>
                                </div>
                            `;
                            break;
                        case 'createTenant':
                            this.modalTitle = 'Create New Tenant';
                            this.modalContent = `
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                                        <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Domain</label>
                                        <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Plan</label>
                                        <select class="w-full border border-gray-300 rounded-md px-3 py-2">
                                            <option>Basic</option>
                                            <option>Professional</option>
                                            <option>Enterprise</option>
                                        </select>
                                    </div>
                                </div>
                            `;
                            break;
                    }
                },

                closeModal() {
                    this.showModal = false;
                    this.currentModal = '';
                },

                executeModalAction() {
                    // Simulate action execution
                    console.log('Executing action:', this.currentModal);
                    this.closeModal();
                },

                refreshActivity() {
                    // Simulate activity refresh
                    console.log('Refreshing activity feed');
                },

                startRealTimeUpdates() {
                    // Simulate real-time updates
                    setInterval(() => {
                        // Update KPIs
                        this.kpis.totalUsers += Math.floor(Math.random() * 3);
                        this.kpis.activeTenants += Math.floor(Math.random() * 2);
                    }, 30000);
                }
            }
        }
    </script>
</body>
</html>