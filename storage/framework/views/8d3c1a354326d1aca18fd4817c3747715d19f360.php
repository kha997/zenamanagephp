<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div x-data="adminDashboard()" class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Admin Dashboard</h1>
                        <p class="text-gray-600 mt-1">Organization overview and management</p>
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
            <!-- Loading State -->
            <div x-show="loading" class="flex items-center justify-center h-64">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            </div>

            <!-- Dashboard Content -->
            <div x-show="!loading" class="space-y-6">
                <!-- Key Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
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

                    <!-- Total Teams -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Teams</p>
                                <p class="text-3xl font-bold text-gray-900" x-text="stats?.totalTeams || 0"></p>
                                <p class="text-sm text-gray-500 mt-1">Active teams</p>
                            </div>
                            <i class="fas fa-user-group text-green-600 text-4xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Financial Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Budget -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Budget</p>
                                <p class="text-3xl font-bold text-gray-900">
                                    $<span x-text="stats?.financial?.totalBudget?.toLocaleString() || 0"></span>
                                </p>
                                <p class="text-sm text-blue-600 mt-1">
                                    <span x-text="stats?.financial?.budgetUtilization || 0"></span>% utilized
                                </p>
                            </div>
                            <i class="fas fa-dollar-sign text-green-600 text-4xl"></i>
                        </div>
                    </div>

                    <!-- Total Revenue -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                                <p class="text-3xl font-bold text-gray-900">
                                    $<span x-text="stats?.financial?.totalRevenue?.toLocaleString() || 0"></span>
                                </p>
                                <p class="text-sm text-gray-500 mt-1">Completed projects</p>
                            </div>
                            <i class="fas fa-chart-line text-blue-600 text-4xl"></i>
                        </div>
                    </div>

                    <!-- Profit Margin -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Profit Margin</p>
                                <p class="text-3xl font-bold text-gray-900">
                                    <span x-text="stats?.financial?.profitMargin || 0"></span>%
                                </p>
                                <p class="text-sm mt-1" 
                                   :class="(stats?.financial?.profitMargin || 0) >= 15 ? 'text-green-600' : 
                                          (stats?.financial?.profitMargin || 0) >= 10 ? 'text-yellow-600' : 'text-red-600'">
                                    <span x-text="(stats?.financial?.profitMargin || 0) >= 15 ? 'Excellent' : 
                                                 (stats?.financial?.profitMargin || 0) >= 10 ? 'Good' : 'Needs Attention'"></span>
                                </p>
                            </div>
                            <i class="fas fa-chart-pie text-purple-600 text-4xl"></i>
                        </div>
                    </div>

                    <!-- Cash Flow -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Cash Flow</p>
                                <p class="text-3xl font-bold" 
                                   :class="(stats?.financial?.cashFlow || 0) >= 0 ? 'text-green-600' : 'text-red-600'">
                                    $<span x-text="Math.abs(stats?.financial?.cashFlow || 0).toLocaleString()"></span>
                                </p>
                                <p class="text-sm mt-1" 
                                   :class="(stats?.financial?.cashFlow || 0) >= 0 ? 'text-green-600' : 'text-red-600'">
                                    <span x-text="(stats?.financial?.cashFlow || 0) >= 0 ? 'Positive' : 'Negative'"></span>
                                </p>
                            </div>
                            <i class="fas fa-money-bill-wave text-orange-600 text-4xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Project Status Distribution -->
                <div class="bg-white rounded-lg shadow p-6 mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Project Status Distribution</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <template x-for="status in projectStatusDistribution" :key="status.status">
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-600" x-text="status.status"></span>
                                    <span class="text-sm font-bold text-gray-900" x-text="status.count"></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                                         :style="'width: ' + status.percentage + '%'"></div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1" x-text="status.percentage + '%'"></p>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Team Performance -->
                <div class="bg-white rounded-lg shadow p-6 mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Team Performance</h3>
                        <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All Teams</button>
                    </div>
                    <div class="space-y-3">
                        <template x-if="teamPerformance.length === 0">
                            <p class="text-gray-500 text-center py-4">No team performance data available</p>
                        </template>
                        <template x-for="team in teamPerformance" :key="team.id">
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-user-group text-blue-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900" x-text="team.name"></p>
                                        <p class="text-xs text-gray-500">
                                            <span x-text="team.memberCount"></span> members • 
                                            <span x-text="team.completedTasks"></span>/<span x-text="team.totalTasks"></span> tasks
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-green-600" x-text="team.completionRate + '%'"></p>
                                    <p class="text-xs text-gray-500">Completion Rate</p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="bg-white rounded-lg shadow p-6 mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Activities</h3>
                        <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
                    </div>
                    <div class="space-y-3">
                        <template x-if="recentActivities.length === 0">
                            <p class="text-gray-500 text-center py-4">No recent activities</p>
                        </template>
                        <template x-for="activity in recentActivities.slice(0, 10)" :key="activity.id">
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas text-lg"
                                           :class="{
                                               'fa-users text-blue-600': activity.type === 'user_created',
                                               'fa-clipboard-list text-purple-600': activity.type === 'project_created',
                                               'fa-check-circle text-green-600': activity.type === 'task_completed'
                                           }"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900" x-text="activity.description"></p>
                                        <p class="text-xs text-gray-500">
                                            <span x-show="activity.user" x-text="'by ' + activity.user + ' • '"></span>
                                            <span x-text="formatDate(activity.timestamp)"></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <button class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50">
                            <i class="fas fa-users text-blue-600"></i>
                            <span>Manage Users</span>
                        </button>
                        <button class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50">
                            <i class="fas fa-clipboard-list text-purple-600"></i>
                            <span>Manage Projects</span>
                        </button>
                        <button class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50">
                            <i class="fas fa-user-group text-green-600"></i>
                            <span>Manage Teams</span>
                        </button>
                        <button class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50">
                            <i class="fas fa-cog text-gray-600"></i>
                            <span>Settings</span>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function adminDashboard() {
            return {
                stats: null,
                recentActivities: [],
                teamPerformance: [],
                projectStatusDistribution: [],
                loading: true,
                refreshing: false,

                async init() {
                    await this.loadDashboardData();
                },

                async loadDashboardData() {
                    try {
                        this.loading = true;
                        
                        // Simulate API calls with demo data
                        await new Promise(resolve => setTimeout(resolve, 1000));
                        
                        // Demo data for Admin Dashboard
                        this.stats = {
                            totalUsers: 45,
                            totalProjects: 12,
                            totalTasks: 180,
                            totalTeams: 8,
                            totalDocuments: 95,
                            activeUsers: 42,
                            activeProjects: 8,
                            completedTasks: 120,
                            pendingTasks: 60,
                            financial: {
                                totalBudget: 2500000, // $2.5M
                                totalActual: 1800000, // $1.8M
                                totalRevenue: 2100000, // $2.1M
                                budgetUtilization: 72.0,
                                profitMargin: 16.7,
                                cashFlow: 300000, // $300K
                            }
                        };

                        this.recentActivities = [
                            {
                                id: '1',
                                type: 'user_created',
                                description: 'New user "John Smith" joined',
                                timestamp: '2024-01-15T09:30:00Z',
                                user: 'System Admin',
                                severity: 'low'
                            },
                            {
                                id: '2',
                                type: 'project_created',
                                description: 'New project "Office Building" created',
                                timestamp: '2024-01-15T08:45:00Z',
                                user: 'Project Manager',
                                severity: 'low'
                            },
                            {
                                id: '3',
                                type: 'task_completed',
                                description: 'Task "Foundation Design" completed',
                                timestamp: '2024-01-15T08:30:00Z',
                                user: 'Designer',
                                severity: 'low'
                            }
                        ];

                        this.teamPerformance = [
                            { id: '1', name: 'Design Team', memberCount: 5, completedTasks: 25, totalTasks: 30, completionRate: 83.3 },
                            { id: '2', name: 'Construction Team', memberCount: 8, completedTasks: 40, totalTasks: 50, completionRate: 80.0 },
                            { id: '3', name: 'QC Team', memberCount: 3, completedTasks: 15, totalTasks: 20, completionRate: 75.0 },
                            { id: '4', name: 'Procurement Team', memberCount: 4, completedTasks: 20, totalTasks: 25, completionRate: 80.0 },
                            { id: '5', name: 'Finance Team', memberCount: 2, completedTasks: 10, totalTasks: 15, completionRate: 66.7 }
                        ];

                        this.projectStatusDistribution = [
                            { status: 'active', count: 8, percentage: 66.7 },
                            { status: 'completed', count: 3, percentage: 25.0 },
                            { status: 'on_hold', count: 1, percentage: 8.3 },
                            { status: 'cancelled', count: 0, percentage: 0 }
                        ];

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

                formatDate(dateString) {
                    if (!dateString) return 'Never';
                    return new Date(dateString).toLocaleDateString();
                }
            }
        }
    </script>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/admin-dashboard.blade.php ENDPATH**/ ?>