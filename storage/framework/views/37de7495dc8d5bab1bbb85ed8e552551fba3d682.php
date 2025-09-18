<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Manager Dashboard - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div x-data="projectManagerDashboard()" class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Project Manager Dashboard</h1>
                        <p class="text-gray-600 mt-1">Manage your projects and teams</p>
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
                <!-- Project Overview -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
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

                    <!-- Completed Projects -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Completed Projects</p>
                                <p class="text-3xl font-bold text-gray-900" x-text="stats?.completedProjects || 0"></p>
                                <p class="text-sm text-green-600 mt-1">Successfully delivered</p>
                            </div>
                            <i class="fas fa-check-circle text-green-600 text-4xl"></i>
                        </div>
                    </div>

                    <!-- Overdue Projects -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Overdue Projects</p>
                                <p class="text-3xl font-bold text-gray-900" x-text="stats?.overdueProjects || 0"></p>
                                <p class="text-sm text-red-600 mt-1">Need attention</p>
                            </div>
                            <i class="fas fa-exclamation-triangle text-red-600 text-4xl"></i>
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
                                        <span x-text="stats?.completedTasks || 0"></span> done
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

                <!-- Financial Overview -->
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

                    <!-- Overdue Tasks -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Overdue Tasks</p>
                                <p class="text-3xl font-bold text-gray-900" x-text="stats?.overdueTasks || 0"></p>
                                <p class="text-sm text-red-600 mt-1">Require immediate attention</p>
                            </div>
                            <i class="fas fa-clock text-red-600 text-4xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Project Progress -->
                <div class="bg-white rounded-lg shadow p-6 mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Project Progress</h3>
                        <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All Projects</button>
                    </div>
                    <div class="space-y-4">
                        <template x-if="projectProgress.length === 0">
                            <p class="text-gray-500 text-center py-4">No projects found</p>
                        </template>
                        <template x-for="project in projectProgress" :key="project.id">
                            <div class="p-4 border border-gray-200 rounded-lg">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center">
                                        <h4 class="text-sm font-medium text-gray-900" x-text="project.name"></h4>
                                        <span class="ml-2 px-2 py-1 text-xs rounded-full"
                                              :class="{
                                                  'bg-green-100 text-green-800': project.status === 'completed',
                                                  'bg-blue-100 text-blue-800': project.status === 'active',
                                                  'bg-yellow-100 text-yellow-800': project.status === 'in_progress',
                                                  'bg-red-100 text-red-800': project.isOverdue
                                              }"
                                              x-text="project.status"></span>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-900" x-text="project.progress + '%'"></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                                         :style="'width: ' + project.progress + '%'"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span x-text="'Budget: $' + project.budget?.toLocaleString()"></span>
                                    <span x-text="'Actual: $' + project.actualCost?.toLocaleString()"></span>
                                    <span x-text="'Due: ' + formatDate(project.endDate)"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Upcoming Milestones -->
                <div class="bg-white rounded-lg shadow p-6 mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Upcoming Milestones</h3>
                        <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All Milestones</button>
                    </div>
                    <div class="space-y-3">
                        <template x-if="upcomingMilestones.length === 0">
                            <p class="text-gray-500 text-center py-4">No upcoming milestones</p>
                        </template>
                        <template x-for="milestone in upcomingMilestones" :key="milestone.id">
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-flag text-blue-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900" x-text="milestone.name"></p>
                                        <p class="text-xs text-gray-500">
                                            <span x-text="milestone.project_name"></span> • 
                                            <span x-text="formatDate(milestone.target_date)"></span>
                                        </p>
                                    </div>
                                </div>
                                <span class="text-sm text-gray-500" x-text="getDaysUntil(milestone.target_date)"></span>
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

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <button class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50">
                            <i class="fas fa-plus text-blue-600"></i>
                            <span>Create Project</span>
                        </button>
                        <button class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50">
                            <i class="fas fa-tasks text-purple-600"></i>
                            <span>Manage Tasks</span>
                        </button>
                        <button class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50">
                            <i class="fas fa-user-group text-green-600"></i>
                            <span>Manage Teams</span>
                        </button>
                        <button class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50">
                            <i class="fas fa-chart-bar text-orange-600"></i>
                            <span>View Reports</span>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function projectManagerDashboard() {
            return {
                stats: null,
                projectProgress: [],
                upcomingMilestones: [],
                teamPerformance: [],
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
                        
                        // Demo data for Project Manager Dashboard
                        this.stats = {
                            totalProjects: 8,
                            activeProjects: 5,
                            completedProjects: 3,
                            overdueProjects: 1,
                            totalTasks: 120,
                            completedTasks: 85,
                            pendingTasks: 35,
                            overdueTasks: 8,
                            financial: {
                                totalBudget: 1800000, // $1.8M
                                totalActual: 1650000, // $1.65M
                                totalRevenue: 1200000, // $1.2M
                                budgetUtilization: 91.7,
                                profitMargin: 8.3,
                            }
                        };

                        this.projectProgress = [
                            { id: '1', name: 'Office Building Complex', status: 'active', progress: 75, startDate: '2023-10-01', endDate: '2024-03-31', budget: 500000, actualCost: 450000, isOverdue: false },
                            { id: '2', name: 'Shopping Mall Renovation', status: 'in_progress', progress: 45, startDate: '2023-11-15', endDate: '2024-02-28', budget: 300000, actualCost: 180000, isOverdue: false },
                            { id: '3', name: 'Residential Tower', status: 'active', progress: 90, startDate: '2023-08-01', endDate: '2024-01-15', budget: 800000, actualCost: 720000, isOverdue: true },
                            { id: '4', name: 'Hospital Extension', status: 'completed', progress: 100, startDate: '2023-06-01', endDate: '2023-12-31', budget: 200000, actualCost: 200000, isOverdue: false }
                        ];

                        this.upcomingMilestones = [
                            { id: '1', name: 'Foundation Complete', project_name: 'Office Building Complex', target_date: '2024-01-20' },
                            { id: '2', name: 'Design Approval', project_name: 'Shopping Mall Renovation', target_date: '2024-01-25' },
                            { id: '3', name: 'Final Inspection', project_name: 'Residential Tower', target_date: '2024-01-15' },
                            { id: '4', name: 'Permit Submission', project_name: 'Hospital Extension', target_date: '2024-01-30' }
                        ];

                        this.teamPerformance = [
                            { id: '1', name: 'Design Team', memberCount: 5, completedTasks: 25, totalTasks: 30, completionRate: 83.3 },
                            { id: '2', name: 'Construction Team', memberCount: 8, completedTasks: 40, totalTasks: 50, completionRate: 80.0 },
                            { id: '3', name: 'QC Team', memberCount: 3, completedTasks: 15, totalTasks: 20, completionRate: 75.0 },
                            { id: '4', name: 'Procurement Team', memberCount: 4, completedTasks: 20, totalTasks: 25, completionRate: 80.0 }
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
                },

                getDaysUntil(dateString) {
                    if (!dateString) return 'Unknown';
                    const days = Math.ceil((new Date(dateString) - new Date()) / (1000 * 60 * 60 * 24));
                    return days > 0 ? `${days} days left` : days === 0 ? 'Today' : `${Math.abs(days)} days overdue`;
                }
            }
        }
    </script>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/project-manager-dashboard.blade.php ENDPATH**/ ?>