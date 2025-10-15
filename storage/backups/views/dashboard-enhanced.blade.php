{{-- Admin Dashboard - Enhanced UI with Better Tailwind CSS --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        secondary: '#10b981',
                        accent: '#8b5cf6',
                        warning: '#f59e0b',
                        danger: '#ef4444'
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'pulse-slow': 'pulse 3s infinite'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 min-h-screen" x-data="adminDashboard()">
    <!-- Enhanced Header -->
    <header class="bg-white/80 backdrop-blur-md shadow-xl border-b border-gray-200/50 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-6">
                    <div class="flex items-center space-x-4">
                        <div class="bg-gradient-to-r from-blue-600 to-purple-600 p-3 rounded-xl shadow-lg">
                            <i class="fas fa-crown text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Admin Dashboard</h1>
                            <p class="text-gray-600 text-sm">Welcome back, Administrator</p>
                        </div>
                    </div>
                    <div class="hidden md:flex items-center space-x-4">
                        <div class="flex items-center space-x-2 bg-green-100 px-4 py-2 rounded-full">
                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                            <span class="text-green-800 text-sm font-medium">System Online</span>
                        </div>
                        <div class="text-sm text-gray-500">
                            Last updated: <span x-text="new Date().toLocaleTimeString()"></span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- Notifications -->
                    <div class="relative">
                        <button @click="toggleNotifications" class="relative p-3 text-gray-600 hover:text-gray-900 focus:outline-none bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors">
                            <i class="fas fa-bell text-xl"></i>
                            <span x-show="unreadNotifications > 0" x-text="unreadNotifications" 
                                  class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-6 w-6 flex items-center justify-center animate-pulse"></span>
                        </button>
                        <div x-show="showNotifications" @click.away="showNotifications = false" 
                             class="absolute right-0 mt-2 w-80 bg-white rounded-2xl shadow-2xl border border-gray-200 z-50 animate-slide-up">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900">Notifications</h3>
                            </div>
                            <div class="max-h-64 overflow-y-auto">
                                <template x-for="notification in notifications" :key="'enhanced-' + notification.id">
                                    <div class="p-4 border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                        <div class="flex items-start space-x-3">
                                            <div class="flex-shrink-0">
                                                <div :class="notification.type === 'warning' ? 'bg-yellow-100' : 'bg-blue-100'" 
                                                     class="w-8 h-8 rounded-full flex items-center justify-center">
                                                    <i :class="notification.icon" 
                                                       :class="notification.type === 'warning' ? 'text-yellow-600' : 'text-blue-600'"></i>
                                                </div>
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
                    
                    <!-- User Menu -->
                    <div class="relative">
                        <button @click="toggleUserMenu" class="flex items-center space-x-3 text-gray-700 hover:text-gray-900 focus:outline-none bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-xl transition-colors">
                            <img src="https://ui-avatars.com/api/?name=Admin+User&background=3b82f6&color=ffffff&size=40" 
                                 alt="Admin User" class="h-10 w-10 rounded-full border-2 border-white shadow-lg">
                            <div class="hidden md:block text-left">
                                <div class="text-sm font-medium">Admin User</div>
                                <div class="text-xs text-gray-500">Super Administrator</div>
                            </div>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <div x-show="showUserMenu" @click.away="showUserMenu = false" 
                             class="absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-2xl border border-gray-200 z-50 animate-slide-up">
                            <div class="py-2">
                                <a href="#" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                    <i class="fas fa-user mr-3 text-gray-400"></i>Profile
                                </a>
                                <a href="#" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                    <i class="fas fa-cog mr-3 text-gray-400"></i>Settings
                                </a>
                                <hr class="my-2">
                                <a href="#" class="flex items-center px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                    <i class="fas fa-sign-out-alt mr-3"></i>Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Enhanced Navigation -->
    <nav class="bg-white/90 backdrop-blur-sm border-b border-gray-200/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-8">
                    <a href="/admin-dashboard-complete" class="flex items-center space-x-2 text-blue-600 font-medium border-b-2 border-blue-600 pb-2 px-3 py-1 rounded-lg bg-blue-50">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="#" class="flex items-center space-x-2 text-gray-600 hover:text-gray-900 font-medium px-3 py-1 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                    <a href="#" class="flex items-center space-x-2 text-gray-600 hover:text-gray-900 font-medium px-3 py-1 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-building"></i>
                        <span>Tenants</span>
                    </a>
                    <a href="#" class="flex items-center space-x-2 text-gray-600 hover:text-gray-900 font-medium px-3 py-1 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-project-diagram"></i>
                        <span>Projects</span>
                    </a>
                    <a href="#" class="flex items-center space-x-2 text-gray-600 hover:text-gray-900 font-medium px-3 py-1 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-chart-bar"></i>
                        <span>Analytics</span>
                    </a>
                    <a href="#" class="flex items-center space-x-2 text-gray-600 hover:text-gray-900 font-medium px-3 py-1 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-shield-alt"></i>
                        <span>Security</span>
                    </a>
                    <a href="#" class="flex items-center space-x-2 text-gray-600 hover:text-gray-900 font-medium px-3 py-1 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <input type="text" placeholder="Search..." 
                               class="w-64 px-4 py-2 pl-10 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white/80 backdrop-blur-sm">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Enhanced KPI Strip -->
    <section class="bg-white/80 backdrop-blur-sm border-b border-gray-200/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium mb-1">Total Users</p>
                            <p class="text-4xl font-bold mb-2" x-text="kpis.totalUsers">1,247</p>
                            <div class="flex items-center">
                                <i class="fas fa-arrow-up mr-1 text-blue-200"></i>
                                <span class="text-blue-100 text-sm font-medium" x-text="kpis.userGrowth">+12%</span>
                                <span class="text-blue-200 text-sm ml-2">from last month</span>
                            </div>
                        </div>
                        <div class="bg-blue-400/30 rounded-2xl p-4">
                            <i class="fas fa-users text-3xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm font-medium mb-1">Active Tenants</p>
                            <p class="text-4xl font-bold mb-2" x-text="kpis.activeTenants">89</p>
                            <div class="flex items-center">
                                <i class="fas fa-arrow-up mr-1 text-green-200"></i>
                                <span class="text-green-100 text-sm font-medium" x-text="kpis.tenantGrowth">+5%</span>
                                <span class="text-green-200 text-sm ml-2">from last month</span>
                            </div>
                        </div>
                        <div class="bg-green-400/30 rounded-2xl p-4">
                            <i class="fas fa-building text-3xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm font-medium mb-1">System Health</p>
                            <p class="text-4xl font-bold mb-2" x-text="kpis.systemHealth">99.8%</p>
                            <div class="flex items-center">
                                <i class="fas fa-heartbeat mr-1 text-purple-200"></i>
                                <span class="text-purple-100 text-sm font-medium">All systems operational</span>
                            </div>
                        </div>
                        <div class="bg-purple-400/30 rounded-2xl p-4">
                            <i class="fas fa-heartbeat text-3xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm font-medium mb-1">Storage Usage</p>
                            <p class="text-4xl font-bold mb-2" x-text="kpis.storageUsage">67%</p>
                            <div class="flex items-center">
                                <i class="fas fa-database mr-1 text-orange-200"></i>
                                <span class="text-orange-100 text-sm font-medium">2.1TB of 3.2TB used</span>
                            </div>
                        </div>
                        <div class="bg-orange-400/30 rounded-2xl p-4">
                            <i class="fas fa-database text-3xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column -->
            <div class="lg:col-span-2 space-y-8">
                <!-- System Overview Chart -->
                <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl border border-gray-200/50 p-8 animate-fade-in">
                    <div class="flex items-center justify-between mb-8">
                        <h2 class="text-2xl font-bold text-gray-900">System Overview</h2>
                        <div class="flex items-center space-x-2">
                            <select x-model="chartPeriod" @change="updateChart" 
                                    class="text-sm border border-gray-300 rounded-xl px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white">
                                <option value="7d">Last 7 days</option>
                                <option value="30d">Last 30 days</option>
                                <option value="90d">Last 90 days</option>
                            </select>
                        </div>
                    </div>
                    <div class="h-80">
                        <canvas id="systemChart"></canvas>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl border border-gray-200/50 p-8 animate-fade-in">
                    <div class="flex items-center justify-between mb-8">
                        <h2 class="text-2xl font-bold text-gray-900">Recent Activity</h2>
                        <a href="#" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                            View All
                            <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    <div class="space-y-6">
                        <template x-for="activity in recentActivities" :key="'enhanced-' + activity.id">
                            <div class="flex items-start space-x-4 p-4 hover:bg-gray-50 rounded-xl transition-colors">
                                <div class="flex-shrink-0">
                                    <div :class="activity.iconBg" class="w-12 h-12 rounded-xl flex items-center justify-center">
                                        <i :class="activity.icon" :class="activity.iconColor" class="text-xl"></i>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900" x-text="activity.title"></p>
                                    <p class="text-sm text-gray-600 mt-1" x-text="activity.description"></p>
                                    <p class="text-xs text-gray-400 mt-2" x-text="activity.time"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-8">
                <!-- Quick Actions -->
                <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl border border-gray-200/50 p-8 animate-fade-in">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Quick Actions</h2>
                    <div class="space-y-4">
                        <button @click="openModal('addUser')" 
                                class="w-full flex items-center justify-center px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all duration-300 transform hover:scale-105 shadow-lg">
                            <i class="fas fa-user-plus mr-3 text-xl"></i>
                            <span class="font-medium">Add User</span>
                        </button>
                        <button @click="openModal('createTenant')" 
                                class="w-full flex items-center justify-center px-6 py-4 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl hover:from-green-700 hover:to-green-800 transition-all duration-300 transform hover:scale-105 shadow-lg">
                            <i class="fas fa-building mr-3 text-xl"></i>
                            <span class="font-medium">Create Tenant</span>
                        </button>
                        <button @click="openModal('backupSystem')" 
                                class="w-full flex items-center justify-center px-6 py-4 bg-gradient-to-r from-purple-600 to-purple-700 text-white rounded-xl hover:from-purple-700 hover:to-purple-800 transition-all duration-300 transform hover:scale-105 shadow-lg">
                            <i class="fas fa-download mr-3 text-xl"></i>
                            <span class="font-medium">Backup System</span>
                        </button>
                        <button @click="openModal('systemSettings')" 
                                class="w-full flex items-center justify-center px-6 py-4 bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-xl hover:from-gray-700 hover:to-gray-800 transition-all duration-300 transform hover:scale-105 shadow-lg">
                            <i class="fas fa-cog mr-3 text-xl"></i>
                            <span class="font-medium">System Settings</span>
                        </button>
                    </div>
                </div>

                <!-- System Status -->
                <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl border border-gray-200/50 p-8 animate-fade-in">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">System Status</h2>
                    <div class="space-y-4">
                        <template x-for="status in systemStatus" :key="'enhanced-' + status.name">
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                <div class="flex items-center">
                                    <div :class="status.status === 'online' ? 'bg-green-500' : 'bg-red-500'" 
                                         class="w-3 h-3 rounded-full mr-3 animate-pulse"></div>
                                    <span class="text-sm font-medium text-gray-900" x-text="status.name"></span>
                                </div>
                                <span :class="status.status === 'online' ? 'text-green-600' : 'text-red-600'" 
                                      class="text-sm font-medium" x-text="status.status"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Enhanced Modals -->
    <div x-show="showModal" x-transition class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full mx-4 animate-slide-up">
            <div class="p-8">
                <div class="flex items-center justify-between mb-8">
                    <h3 class="text-2xl font-bold text-gray-900" x-text="modalTitle"></h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 p-2 hover:bg-gray-100 rounded-xl transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div x-html="modalContent"></div>
                <div class="flex justify-end space-x-4 mt-8">
                    <button @click="closeModal" class="px-6 py-3 text-gray-600 hover:text-gray-800 font-medium">
                        Cancel
                    </button>
                    <button @click="executeModalAction" class="px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-medium transition-colors">
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
                                tension: 0.4,
                                fill: true
                            }, {
                                label: 'Tenants',
                                data: [80, 85, 88, 87, 89, 89],
                                borderColor: 'rgb(16, 185, 129)',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                tension: 0.4,
                                fill: true
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
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.1)'
                                    }
                                },
                                x: {
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.1)'
                                    }
                                }
                            }
                        }
                    });
                },

                updateChart() {
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

                openModal(type) {
                    this.currentModal = type;
                    this.showModal = true;
                    
                    switch(type) {
                        case 'addUser':
                            this.modalTitle = 'Add New User';
                            this.modalContent = `
                                <div class="space-y-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                                        <input type="text" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                        <input type="email" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                                        <select class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
                                <div class="space-y-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Company Name</label>
                                        <input type="text" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Domain</label>
                                        <input type="text" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Plan</label>
                                        <select class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
                    console.log('Executing action:', this.currentModal);
                    this.closeModal();
                },

                startRealTimeUpdates() {
                    setInterval(() => {
                        this.kpis.totalUsers += Math.floor(Math.random() * 3);
                        this.kpis.activeTenants += Math.floor(Math.random() * 2);
                    }, 30000);
                }
            }
        }
    </script>
</body>
</html>
