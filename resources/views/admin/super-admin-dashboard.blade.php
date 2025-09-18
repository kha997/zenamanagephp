<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div x-data="superAdminDashboard()" class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Super Admin Dashboard</h1>
                        <p class="text-gray-600 mt-1">System overview and management with financial insights</p>
                    </div>
                    <button 
                        @click="refreshData()" 
                        :disabled="refreshing"
                        class="flex items-center space-x-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                    >
                        <i class="fas fa-sync-alt" :class="{'animate-spin': refreshing}"></i>
                        <span x-text="refreshing ? 'Refreshing...' : 'Refresh'"></span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- System Health Alert -->
            <div x-show="stats?.systemHealth === 'critical'" class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-600 mr-3"></i>
                    <div>
                        <h3 class="text-lg font-semibold text-red-800">System Critical Alert</h3>
                        <p class="text-red-700">Immediate attention required. Check system alerts below.</p>
                    </div>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Users -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Users</p>
                            <p class="text-3xl font-bold text-gray-900" x-text="stats?.totalUsers || 0"></p>
                            <p class="text-sm text-green-600 mt-1">
                                <span x-text="stats?.activeUsers || 0"></span> active
                            </p>
                        </div>
                        <i class="fas fa-users text-blue-600 text-4xl"></i>
                    </div>
                </div>

                <!-- Total Tenants -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Tenants</p>
                            <p class="text-3xl font-bold text-gray-900" x-text="stats?.totalTenants || 0"></p>
                            <p class="text-sm text-gray-500 mt-1">Organizations</p>
                        </div>
                        <i class="fas fa-building text-green-600 text-4xl"></i>
                    </div>
                </div>

                <!-- Total Projects -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Projects</p>
                            <p class="text-3xl font-bold text-gray-900" x-text="stats?.totalProjects || 0"></p>
                            <p class="text-sm text-blue-600 mt-1">
                                <span x-text="stats?.activeProjects || 0"></span> active
                            </p>
                        </div>
                        <i class="fas fa-clipboard-list text-purple-600 text-4xl"></i>
                    </div>
                </div>

                <!-- Total Tasks -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Tasks</p>
                            <p class="text-3xl font-bold text-gray-900" x-text="stats?.totalTasks || 0"></p>
                            <div class="flex space-x-4 mt-1">
                                <p class="text-sm text-green-600">
                                    <span x-text="stats?.completedTasks || 0"></span> completed
                                </p>
                                <p class="text-sm text-yellow-600">
                                    <span x-text="stats?.pendingTasks || 0"></span> pending
                                </p>
                            </div>
                        </div>
                        <i class="fas fa-tasks text-orange-600 text-4xl"></i>
                    </div>
                </div>
            </div>

            <!-- Financial Metrics Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Revenue Overview -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Revenue Overview</h3>
                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">
                            <i class="fas fa-arrow-up mr-1"></i>+15.2%
                        </span>
                    </div>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Monthly Recurring Revenue</span>
                            <span class="text-lg font-semibold text-gray-900">$125,000</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Annual Recurring Revenue</span>
                            <span class="text-lg font-semibold text-gray-900">$1,500,000</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Average Revenue Per User</span>
                            <span class="text-lg font-semibold text-gray-900">$100</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" style="width: 75%"></div>
                        </div>
                        <p class="text-xs text-gray-500">75% of annual target achieved</p>
                    </div>
                </div>

                <!-- Cost Analysis -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Cost Analysis</h3>
                        <span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-medium rounded-full">
                            <i class="fas fa-arrow-down mr-1"></i>-8.5%
                        </span>
                    </div>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Infrastructure Costs</span>
                            <span class="text-lg font-semibold text-gray-900">$15,000</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Support & Maintenance</span>
                            <span class="text-lg font-semibold text-gray-900">$8,500</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Marketing & Sales</span>
                            <span class="text-lg font-semibold text-gray-900">$12,000</span>
                        </div>
                        <div class="flex justify-between items-center border-t pt-2">
                            <span class="text-sm font-medium text-gray-900">Total Monthly Costs</span>
                            <span class="text-lg font-bold text-gray-900">$35,500</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Status & Storage -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- System Health -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">System Health</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Overall Status</span>
                            <span 
                                class="px-3 py-1 rounded-full text-sm font-medium"
                                :class="{
                                    'text-green-600 bg-green-100': stats?.systemHealth === 'healthy',
                                    'text-yellow-600 bg-yellow-100': stats?.systemHealth === 'warning',
                                    'text-red-600 bg-red-100': stats?.systemHealth === 'critical'
                                }"
                                x-text="stats?.systemHealth || 'healthy'"
                            ></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Last Backup</span>
                            <span class="text-sm text-gray-900" x-text="formatDate(stats?.lastBackup)"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Database Status</span>
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-600 mr-2"></i>
                                <span class="text-sm text-green-600">Connected</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Storage Usage -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Storage Usage</h3>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm text-gray-600 mb-2">
                                <span>Used Storage</span>
                                <span x-text="formatStorageSize(stats?.storageUsed) + ' / ' + formatStorageSize(stats?.storageTotal)"></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div 
                                    class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                                    :style="'width: ' + getStoragePercentage() + '%'"
                                ></div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Available</span>
                            <span class="text-sm text-gray-900" x-text="formatStorageSize((stats?.storageTotal || 0) - (stats?.storageUsed || 0))"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Alerts -->
            <div class="bg-white rounded-lg shadow p-6 mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">System Alerts</h3>
                    <button @click="navigateTo('/admin/alerts')" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
                </div>
                <div class="space-y-3">
                    <template x-if="systemAlerts.length === 0">
                        <p class="text-gray-500 text-center py-4">No active alerts</p>
                    </template>
                    <template x-for="alert in systemAlerts.slice(0, 5)" :key="alert.id">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-orange-600 mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900" x-text="alert.message"></p>
                                    <p class="text-xs text-gray-500" x-text="formatDate(alert.timestamp)"></p>
                                </div>
                            </div>
                            <span 
                                class="px-2 py-1 rounded-full text-xs font-medium"
                                :class="getSeverityColor(alert.severity)"
                                x-text="alert.severity"
                            ></span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="bg-white rounded-lg shadow p-6 mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Activities</h3>
                    <button @click="navigateTo('/admin/activities')" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
                </div>
                <div class="space-y-3">
                    <template x-if="recentActivities.length === 0">
                        <p class="text-gray-500 text-center py-4">No recent activities</p>
                    </template>
                    <template x-for="activity in recentActivities.slice(0, 10)" :key="activity.id">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i 
                                        class="fas text-lg"
                                        :class="{
                                            'fa-users text-blue-600': activity.type === 'user_created',
                                            'fa-clipboard-list text-purple-600': activity.type === 'project_created',
                                            'fa-check-circle text-green-600': activity.type === 'task_completed',
                                            'fa-exclamation-triangle text-orange-600': activity.type === 'system_alert'
                                        }"
                                    ></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900" x-text="activity.description"></p>
                                    <p class="text-xs text-gray-500">
                                        <span x-show="activity.user" x-text="'by ' + activity.user + ' • '"></span>
                                        <span x-text="formatDate(activity.timestamp)"></span>
                                    </p>
                                </div>
                            </div>
                            <template x-if="activity.severity">
                                <span 
                                    class="px-2 py-1 rounded-full text-xs font-medium"
                                    :class="getSeverityColor(activity.severity)"
                                    x-text="activity.severity"
                                ></span>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <button @click="navigateTo('/admin/users')" class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-users text-blue-600"></i>
                        <span>Manage Users</span>
                    </button>
                    <button @click="navigateTo('/admin/tenants')" class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-building text-green-600"></i>
                        <span>Manage Tenants</span>
                    </button>
                    <button @click="navigateTo('/admin/settings')" class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-cog text-gray-600"></i>
                        <span>System Settings</span>
                    </button>
                    <button @click="navigateTo('/admin/security')" class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-shield-alt text-red-600"></i>
                        <span>Security Audit</span>
                    </button>
                </div>
            </div>
        </main>
    </div>

    <script>
        function superAdminDashboard() {
            return {
                stats: null,
                recentActivities: [],
                systemAlerts: [],
                loading: true,
                refreshing: false,

                async init() {
                    await this.loadDashboardData();
                },

                async loadDashboardData() {
                    try {
                        this.loading = true;
                        
                        // Load dashboard statistics
                        const statsResponse = await fetch('/api/admin/dashboard/stats');
                        const statsData = await statsResponse.json();
                        this.stats = statsData.data;

                        // Load recent activities
                        const activitiesResponse = await fetch('/api/admin/dashboard/activities');
                        const activitiesData = await activitiesResponse.json();
                        this.recentActivities = activitiesData.data;

                        // Load system alerts
                        const alertsResponse = await fetch('/api/admin/dashboard/alerts');
                        const alertsData = await alertsResponse.json();
                        this.systemAlerts = alertsData.data;

                    } catch (error) {
                        console.error('Failed to load dashboard data:', error);
                    } finally {
                        this.loading = false;
                    }
                },

                async refreshData() {
                    this.refreshing = true;
                    await this.loadDashboardData();
                    this.refreshing = false;
                },

                getSeverityColor(severity) {
                    switch (severity) {
                        case 'critical': return 'text-red-600 bg-red-100';
                        case 'high': return 'text-orange-600 bg-orange-100';
                        case 'medium': return 'text-yellow-600 bg-yellow-100';
                        case 'low': return 'text-blue-600 bg-blue-100';
                        default: return 'text-gray-600 bg-gray-100';
                    }
                },

                formatStorageSize(bytes) {
                    if (!bytes) return '0 Bytes';
                    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(1024));
                    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
                },

                getStoragePercentage() {
                    if (!this.stats?.storageUsed || !this.stats?.storageTotal) return 0;
                    return Math.round((this.stats.storageUsed / this.stats.storageTotal) * 100);
                },

                formatDate(dateString) {
                    if (!dateString) return 'Never';
                    return new Date(dateString).toLocaleDateString();
                },

                navigateTo(url) {
                    window.location.href = url;
                }
            }
        }
    </script>
</body>
</html>
