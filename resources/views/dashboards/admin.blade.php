@extends('layouts.dashboard')

@section('title', 'Admin Dashboard')
@section('page-title', 'Admin Dashboard')
@section('page-description', 'Tenant-level organization management with comprehensive oversight')
@section('user-initials', 'AD')
@section('user-name', 'Admin')

@section('content')
<div x-data="adminDashboard()">
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

    <!-- Admin Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="dashboard-card metric-card green p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Total Users</p>
                    <p class="text-3xl font-bold text-white" x-text="stats?.totalUsers || 24"></p>
                    <p class="text-white/80 text-sm">
                        <span x-text="stats?.activeUsers || 18"></span> active
                    </p>
                </div>
                <i class="fas fa-users text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card blue p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Active Projects</p>
                    <p class="text-3xl font-bold text-white" x-text="stats?.totalProjects || 12"></p>
                    <p class="text-white/80 text-sm">
                        <span x-text="stats?.activeProjects || 8"></span> active
                    </p>
                </div>
                <i class="fas fa-project-diagram text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card orange p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Total Tasks</p>
                    <p class="text-3xl font-bold text-white" x-text="stats?.totalTasks || 48"></p>
                    <div class="flex space-x-2 mt-1">
                        <p class="text-white/80 text-sm">
                            <span x-text="stats?.completedTasks || 32"></span> completed
                        </p>
                        <p class="text-white/80 text-sm">
                            <span x-text="stats?.pendingTasks || 16"></span> pending
                        </p>
                    </div>
                </div>
                <i class="fas fa-tasks text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card purple p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Documents</p>
                    <p class="text-3xl font-bold text-white" x-text="stats?.totalDocuments || 156"></p>
                    <p class="text-white/80 text-sm">
                        <span x-text="stats?.recentDocuments || 12"></span> this week
                    </p>
                </div>
                <i class="fas fa-file-alt text-4xl text-white/60"></i>
            </div>
        </div>
    </div>

    <!-- Financial Metrics Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Revenue Overview -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Revenue Overview</h3>
                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">
                    <i class="fas fa-arrow-up mr-1"></i>+12.5%
                </span>
            </div>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Monthly Revenue</span>
                    <span class="text-lg font-semibold text-gray-900">$85,000</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Project Revenue</span>
                    <span class="text-lg font-semibold text-gray-900">$1,020,000</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Average Revenue Per Project</span>
                    <span class="text-lg font-semibold text-gray-900">$85,000</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-green-600 h-2 rounded-full" style="width: 68%"></div>
                </div>
                <p class="text-xs text-gray-500">68% of annual target achieved</p>
            </div>
        </div>

        <!-- Cost Analysis -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Cost Analysis</h3>
                <span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-medium rounded-full">
                    <i class="fas fa-arrow-down mr-1"></i>-5.2%
                </span>
            </div>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Labor Costs</span>
                    <span class="text-lg font-semibold text-gray-900">$45,000</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Material Costs</span>
                    <span class="text-lg font-semibold text-gray-900">$28,500</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Equipment & Tools</span>
                    <span class="text-lg font-semibold text-gray-900">$12,000</span>
                </div>
                <div class="flex justify-between items-center border-t pt-2">
                    <span class="text-sm font-medium text-gray-900">Total Monthly Costs</span>
                    <span class="text-lg font-bold text-gray-900">$85,500</span>
                </div>
            </div>
        </div>
    </div>

    <!-- System Status & Storage -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- System Health -->
        <div class="dashboard-card p-6">
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
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">API Response Time</span>
                    <span class="text-sm text-gray-900">245ms</span>
                </div>
            </div>
        </div>

        <!-- Storage Usage -->
        <div class="dashboard-card p-6">
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
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Documents</span>
                    <span class="text-sm text-gray-900">2.4 GB</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Images & Media</span>
                    <span class="text-sm text-gray-900">1.8 GB</span>
                </div>
            </div>
        </div>
    </div>

    <!-- System Alerts -->
    <div class="dashboard-card p-6 mb-8">
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
    <div class="dashboard-card p-6 mb-8">
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
                                    'fa-file-alt text-orange-600': activity.type === 'document_uploaded',
                                    'fa-exclamation-triangle text-red-600': activity.type === 'system_alert'
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
    <div class="dashboard-card p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <button @click="navigateTo('/projects/create')" class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                <i class="fas fa-plus text-green-600"></i>
                <span>Create Project</span>
            </button>
            <button @click="navigateTo('/tasks/create')" class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                <i class="fas fa-tasks text-blue-600"></i>
                <span>Add Task</span>
            </button>
            <button @click="navigateTo('/team/invite')" class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                <i class="fas fa-user-plus text-purple-600"></i>
                <span>Invite Member</span>
            </button>
            <button @click="navigateTo('/documents/create')" class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                <i class="fas fa-upload text-orange-600"></i>
                <span>Upload Document</span>
            </button>
            <button @click="navigateTo('/team')" class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                <i class="fas fa-users text-blue-600"></i>
                <span>Manage Team</span>
            </button>
            <button @click="navigateTo('/projects')" class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                <i class="fas fa-clipboard-list text-purple-600"></i>
                <span>View Projects</span>
            </button>
            <button @click="navigateTo('/admin/settings')" class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                <i class="fas fa-cog text-gray-600"></i>
                <span>Settings</span>
            </button>
            <button @click="navigateTo('/admin/reports')" class="flex items-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                <i class="fas fa-chart-bar text-green-600"></i>
                <span>Reports</span>
            </button>
        </div>
    </div>

    <!-- Project Overview & Team Management -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Project Overview -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Project Overview</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-4">
                <div class="p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-medium text-gray-900">Office Building Complex</h4>
                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">Active</span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Progress: 75%</span>
                        <span>Due: Mar 15, 2024</span>
                    </div>
                </div>
                <div class="p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-medium text-gray-900">Shopping Mall Development</h4>
                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-medium rounded-full">In Progress</span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Progress: 45%</span>
                        <span>Due: Feb 28, 2024</span>
                    </div>
                </div>
                <div class="p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-medium text-gray-900">Residential Complex</h4>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded-full">Planning</span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Progress: 15%</span>
                        <span>Due: Dec 15, 2024</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Team Management -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Team Management</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">Manage Team</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold mr-3">
                            JS
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">John Smith</p>
                            <p class="text-sm text-gray-500">Project Manager</p>
                        </div>
                    </div>
                    <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white font-semibold mr-3">
                            SW
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Sarah Wilson</p>
                            <p class="text-sm text-gray-500">Designer</p>
                        </div>
                    </div>
                    <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center text-white font-semibold mr-3">
                            MJ
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Mike Johnson</p>
                            <p class="text-sm text-gray-500">Developer</p>
                        </div>
                    </div>
                    <span class="w-2 h-2 bg-yellow-500 rounded-full"></span>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-orange-500 rounded-full flex items-center justify-center text-white font-semibold mr-3">
                            AL
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Alex Lee</p>
                            <p class="text-sm text-gray-500">Site Engineer</p>
                        </div>
                    </div>
                    <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function adminDashboard() {
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
                // Fallback to mock data
                this.stats = {
                    totalUsers: 24,
                    activeUsers: 18,
                    totalProjects: 12,
                    activeProjects: 8,
                    totalTasks: 48,
                    completedTasks: 32,
                    pendingTasks: 16,
                    totalDocuments: 156,
                    recentDocuments: 12,
                    systemHealth: 'healthy',
                    lastBackup: new Date().toISOString(),
                    storageUsed: 4294967296, // 4GB
                    storageTotal: 10737418240 // 10GB
                };
                
                this.recentActivities = [
                    {
                        id: 1,
                        type: 'project_created',
                        description: 'New project "Office Building Complex" created',
                        user: 'John Smith',
                        timestamp: new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString(),
                        severity: null
                    },
                    {
                        id: 2,
                        type: 'task_completed',
                        description: 'Task "Site Inspection" completed successfully',
                        user: 'Sarah Wilson',
                        timestamp: new Date(Date.now() - 4 * 60 * 60 * 1000).toISOString(),
                        severity: null
                    },
                    {
                        id: 3,
                        type: 'document_uploaded',
                        description: 'Building Plans uploaded to Project Beta',
                        user: 'Mike Johnson',
                        timestamp: new Date(Date.now() - 6 * 60 * 60 * 1000).toISOString(),
                        severity: null
                    },
                    {
                        id: 4,
                        type: 'user_created',
                        description: 'New team member Sarah Wilson joined',
                        user: 'Admin',
                        timestamp: new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString(),
                        severity: null
                    }
                ];
                
                this.systemAlerts = [
                    {
                        id: 1,
                        message: 'High storage usage detected',
                        timestamp: new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString(),
                        severity: 'medium'
                    },
                    {
                        id: 2,
                        message: 'Backup scheduled for tonight',
                        timestamp: new Date(Date.now() - 4 * 60 * 60 * 1000).toISOString(),
                        severity: 'low'
                    }
                ];
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
        },

        // Admin management functions
        createProject() {
            this.navigateTo('/projects/create');
        },
        
        addTask() {
            this.navigateTo('/tasks/create');
        },
        
        inviteMember() {
            this.navigateTo('/team/invite');
        },
        
        uploadDocument() {
            this.navigateTo('/documents/create');
        },
        
        viewProjectDetails(projectId) {
            this.navigateTo(`/projects/${projectId}`);
        },
        
        manageTeam() {
            this.navigateTo('/team');
        }
    }
}
</script>
@endsection
