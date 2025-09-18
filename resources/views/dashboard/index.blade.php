@extends('layouts.dashboard')

@section('title', 'Main Dashboard')
@section('page-title', 'Main Dashboard')
@section('page-description', 'Comprehensive overview of all projects and activities')
@section('user-initials', 'AD')
@section('user-name', 'Admin User')

@section('content')
<div x-data="mainDashboard()" class="space-y-8">
    <!-- Welcome Section -->
    <div class="zena-card zena-p-lg zena-fade-in">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Welcome back! 👋</h2>
                <p class="text-gray-600">Here's what's happening with your projects today.</p>
            </div>
            <div class="flex items-center space-x-4">
                <div class="text-right">
                    <p class="text-sm text-gray-500">Last updated</p>
                    <p class="text-sm font-medium text-gray-900" x-text="lastUpdated"></p>
                </div>
                <button @click="refreshData()" :disabled="refreshing" class="zena-btn zena-btn-primary">
                    <i class="fas fa-sync-alt" :class="{'animate-spin': refreshing}"></i>
                    <span x-text="refreshing ? 'Refreshing...' : 'Refresh'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="zena-metric-card green zena-fade-in">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm font-medium">Active Projects</p>
                    <p class="text-3xl font-bold text-white" x-text="metrics.activeProjects"></p>
                    <p class="text-white/80 text-sm">+2 this week</p>
                </div>
                <i class="fas fa-project-diagram text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="zena-metric-card blue zena-fade-in">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm font-medium">Total Tasks</p>
                    <p class="text-3xl font-bold text-white" x-text="metrics.totalTasks"></p>
                    <p class="text-white/80 text-sm">+5 this week</p>
                </div>
                <i class="fas fa-tasks text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="zena-metric-card orange zena-fade-in">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm font-medium">Overdue Tasks</p>
                    <p class="text-3xl font-bold text-white" x-text="metrics.overdueTasks"></p>
                    <p class="text-white/80 text-sm">-1 from yesterday</p>
                </div>
                <i class="fas fa-exclamation-triangle text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="zena-metric-card purple zena-fade-in">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm font-medium">Team Members</p>
                    <p class="text-3xl font-bold text-white" x-text="metrics.teamMembers"></p>
                    <p class="text-white/80 text-sm">All active</p>
                </div>
                <i class="fas fa-users text-4xl text-white/60"></i>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="zena-card zena-p-lg">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <button @click="navigateTo('/tasks/create')" class="zena-btn zena-btn-outline zena-flex-col zena-p-lg">
                <i class="fas fa-plus text-xl mb-2"></i>
                <span>New Task</span>
            </button>
            <button @click="navigateTo('/projects/create')" class="zena-btn zena-btn-outline zena-flex-col zena-p-lg">
                <i class="fas fa-project-diagram text-xl mb-2"></i>
                <span>New Project</span>
            </button>
            <button @click="navigateTo('/documents/create')" class="zena-btn zena-btn-outline zena-flex-col zena-p-lg">
                <i class="fas fa-file-upload text-xl mb-2"></i>
                <span>Upload Document</span>
            </button>
            <button @click="navigateTo('/team/invite')" class="zena-btn zena-btn-outline zena-flex-col zena-p-lg">
                <i class="fas fa-user-plus text-xl mb-2"></i>
                <span>Invite Member</span>
            </button>
            <button @click="navigateTo('/templates/create')" class="zena-btn zena-btn-outline zena-flex-col zena-p-lg">
                <i class="fas fa-magic text-xl mb-2"></i>
                <span>New Template</span>
            </button>
            <button @click="navigateTo('/reports')" class="zena-btn zena-btn-outline zena-flex-col zena-p-lg">
                <i class="fas fa-chart-bar text-xl mb-2"></i>
                <span>View Reports</span>
            </button>
        </div>
    </div>

    <!-- Recent Activities & Project Status -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Activities -->
        <div class="zena-card zena-p-lg">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Recent Activities</h3>
                <button @click="navigateTo('/activities')" class="zena-btn zena-btn-ghost zena-btn-sm">
                    View All
                </button>
            </div>
            <div class="space-y-4">
                <template x-for="activity in recentActivities" :key="activity.id">
                    <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                 :class="getActivityIconClass(activity.type)">
                                <i :class="getActivityIcon(activity.type)" class="text-sm"></i>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900" x-text="activity.description"></p>
                            <p class="text-xs text-gray-500" x-text="formatTime(activity.timestamp)"></p>
                        </div>
                        <span class="zena-badge" :class="getActivityBadgeClass(activity.type)" x-text="activity.type"></span>
                    </div>
                </template>
            </div>
        </div>

        <!-- Project Status Overview -->
        <div class="zena-card zena-p-lg">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Project Status</h3>
                <button @click="navigateTo('/projects')" class="zena-btn zena-btn-ghost zena-btn-sm">
                    View All
                </button>
            </div>
            <div class="space-y-4">
                <template x-for="project in projects" :key="project.id">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-medium text-gray-900" x-text="project.name"></h4>
                            <span class="zena-badge" :class="getProjectStatusClass(project.status)" x-text="project.status"></span>
                        </div>
                        <div class="mb-2">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm text-gray-600">Progress</span>
                                <span class="text-sm font-medium text-gray-900" x-text="project.progress + '%'"></span>
                            </div>
                            <div class="zena-progress">
                                <div class="zena-progress-bar" 
                                     :class="getProgressBarClass(project.progress)"
                                     :style="'width: ' + project.progress + '%'"></div>
                            </div>
                        </div>
                        <div class="flex justify-between text-sm text-gray-600">
                            <span x-text="'Due: ' + project.dueDate"></span>
                            <span x-text="project.taskCount + ' tasks'"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Team Performance & System Health -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Team Performance -->
        <div class="zena-card zena-p-lg">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Team Performance</h3>
                <button @click="navigateTo('/team')" class="zena-btn zena-btn-ghost zena-btn-sm">
                    View Team
                </button>
            </div>
            <div class="space-y-4">
                <template x-for="member in teamMembers" :key="member.id">
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold mr-3"
                                 :class="getMemberAvatarClass(member.role)">
                                <span x-text="member.initials"></span>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900" x-text="member.name"></p>
                                <p class="text-sm text-gray-500" x-text="member.role"></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold" :class="getPerformanceColor(member.performance)" x-text="member.performance + '%'"></p>
                            <p class="text-sm text-gray-500">Completion</p>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- System Health -->
        <div class="zena-card zena-p-lg">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">System Health</h3>
                <button @click="navigateTo('/health')" class="zena-btn zena-btn-ghost zena-btn-sm">
                    View Details
                </button>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Overall Status</span>
                    <span class="zena-badge zena-badge-success">Healthy</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Database</span>
                    <span class="zena-badge zena-badge-success">Connected</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Storage Usage</span>
                    <span class="text-sm font-medium text-gray-900">2.1 GB / 10 GB</span>
                </div>
                <div class="zena-progress">
                    <div class="zena-progress-bar zena-progress-bar-info" style="width: 21%"></div>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Last Backup</span>
                    <span class="text-sm font-medium text-gray-900" x-text="lastBackup"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function mainDashboard() {
    return {
        refreshing: false,
        lastUpdated: new Date().toLocaleTimeString(),
        lastBackup: '2 hours ago',
        
        metrics: {
            activeProjects: 8,
            totalTasks: 156,
            overdueTasks: 3,
            teamMembers: 12
        },
        
        recentActivities: [
            {
                id: 1,
                type: 'task_completed',
                description: 'John completed "Review Design Documents"',
                timestamp: new Date(Date.now() - 5 * 60 * 1000)
            },
            {
                id: 2,
                type: 'project_created',
                description: 'New project "Office Complex" created',
                timestamp: new Date(Date.now() - 15 * 60 * 1000)
            },
            {
                id: 3,
                type: 'document_uploaded',
                description: 'Sarah uploaded "Site Survey Report.pdf"',
                timestamp: new Date(Date.now() - 30 * 60 * 1000)
            },
            {
                id: 4,
                type: 'team_invite',
                description: 'New team member Mike Johnson invited',
                timestamp: new Date(Date.now() - 45 * 60 * 1000)
            },
            {
                id: 5,
                type: 'task_assigned',
                description: 'Task "Budget Review" assigned to Sarah',
                timestamp: new Date(Date.now() - 60 * 60 * 1000)
            }
        ],
        
        projects: [
            {
                id: 1,
                name: 'Office Building Complex',
                status: 'active',
                progress: 75,
                dueDate: 'Mar 15, 2024',
                taskCount: 23
            },
            {
                id: 2,
                name: 'Shopping Mall Development',
                status: 'active',
                progress: 45,
                dueDate: 'Feb 28, 2024',
                taskCount: 18
            },
            {
                id: 3,
                name: 'Residential Complex',
                status: 'planning',
                progress: 15,
                dueDate: 'Dec 15, 2024',
                taskCount: 8
            }
        ],
        
        teamMembers: [
            {
                id: 1,
                name: 'John Smith',
                role: 'Site Engineer',
                initials: 'JS',
                performance: 95
            },
            {
                id: 2,
                name: 'Sarah Wilson',
                role: 'Designer',
                initials: 'SW',
                performance: 88
            },
            {
                id: 3,
                name: 'Mike Johnson',
                role: 'Developer',
                initials: 'MJ',
                performance: 75
            }
        ],
        
        async refreshData() {
            this.refreshing = true;
            // Simulate API call
            await new Promise(resolve => setTimeout(resolve, 1000));
            this.lastUpdated = new Date().toLocaleTimeString();
            this.refreshing = false;
        },
        
        navigateTo(url) {
            window.location.href = url;
        },
        
        getActivityIcon(type) {
            const icons = {
                'task_completed': 'fas fa-check-circle',
                'project_created': 'fas fa-project-diagram',
                'document_uploaded': 'fas fa-file-upload',
                'team_invite': 'fas fa-user-plus',
                'task_assigned': 'fas fa-tasks'
            };
            return icons[type] || 'fas fa-info-circle';
        },
        
        getActivityIconClass(type) {
            const classes = {
                'task_completed': 'bg-green-100 text-green-600',
                'project_created': 'bg-blue-100 text-blue-600',
                'document_uploaded': 'bg-purple-100 text-purple-600',
                'team_invite': 'bg-orange-100 text-orange-600',
                'task_assigned': 'bg-yellow-100 text-yellow-600'
            };
            return classes[type] || 'bg-gray-100 text-gray-600';
        },
        
        getActivityBadgeClass(type) {
            const classes = {
                'task_completed': 'zena-badge-success',
                'project_created': 'zena-badge-info',
                'document_uploaded': 'zena-badge-info',
                'team_invite': 'zena-badge-warning',
                'task_assigned': 'zena-badge-warning'
            };
            return classes[type] || 'zena-badge-neutral';
        },
        
        getProjectStatusClass(status) {
            const classes = {
                'active': 'zena-badge-success',
                'planning': 'zena-badge-info',
                'on_hold': 'zena-badge-warning',
                'completed': 'zena-badge-neutral'
            };
            return classes[status] || 'zena-badge-neutral';
        },
        
        getProgressBarClass(progress) {
            if (progress >= 80) return 'zena-progress-bar-success';
            if (progress >= 60) return 'zena-progress-bar-info';
            if (progress >= 40) return 'zena-progress-bar-warning';
            return 'zena-progress-bar-danger';
        },
        
        getMemberAvatarClass(role) {
            const classes = {
                'Site Engineer': 'bg-blue-500',
                'Designer': 'bg-green-500',
                'Developer': 'bg-purple-500',
                'Project Manager': 'bg-orange-500'
            };
            return classes[role] || 'bg-gray-500';
        },
        
        getPerformanceColor(performance) {
            if (performance >= 90) return 'text-green-600';
            if (performance >= 80) return 'text-blue-600';
            if (performance >= 70) return 'text-yellow-600';
            return 'text-red-600';
        },
        
        formatTime(timestamp) {
            const now = new Date();
            const diff = now - timestamp;
            const minutes = Math.floor(diff / 60000);
            
            if (minutes < 1) return 'Just now';
            if (minutes < 60) return `${minutes}m ago`;
            
            const hours = Math.floor(minutes / 60);
            if (hours < 24) return `${hours}h ago`;
            
            const days = Math.floor(hours / 24);
            return `${days}d ago`;
        }
    }
}
</script>
@endsection
