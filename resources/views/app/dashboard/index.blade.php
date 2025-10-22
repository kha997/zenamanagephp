@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="min-h-screen bg-gray-50" x-data="dashboardData()" x-init="init()">
    <!-- Alert Banner -->
    @include('app.dashboard._alerts')
    
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <p class="text-sm text-gray-600">Welcome back, {{ Auth::user()->name ?? 'User' }}</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button @click="refreshDashboard()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-sync-alt mr-2"></i>Refresh
                    </button>
                    <a href="{{ route('app.projects.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i>New Project
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Strip -->
    @include('app.dashboard._kpis')

    <!-- Main Content Grid -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Row 1: Recent Projects + Activity Feed -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Recent Projects -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Recent Projects</h3>
                </div>
                <div class="p-6">
                    @forelse($recentProjects as $project)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg mb-3">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-project-diagram text-blue-600"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900">{{ $project->name }}</h4>
                                    <p class="text-xs text-gray-500">{{ ucfirst($project->status) }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-900">{{ $project->progress }}%</p>
                                <div class="w-16 bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $project->progress }}%"></div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8">
                            <i class="fas fa-project-diagram text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No projects yet</p>
                            <a href="{{ route('app.projects.create') }}" class="text-blue-600 hover:text-blue-800 font-medium">Create your first project</a>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Activity Feed -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Recent Activity</h3>
                </div>
                <div class="p-6">
                    @forelse($recentActivity as $activity)
                        <div class="flex items-start space-x-3 mb-4">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-bell text-blue-600 text-sm"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-900">{{ $activity->message }}</p>
                                <p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($activity->created_at)->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8">
                            <i class="fas fa-bell text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No recent activity</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Row 2: Project Progress Chart + Quick Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Project Progress Chart -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Project Progress</h3>
                </div>
                <div class="p-6">
                    <div class="h-64 flex items-center justify-center">
                        <canvas id="projectProgressChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            @include('app.dashboard._quick-actions')
        </div>

        <!-- Row 3: Team Status + Task Completion Chart -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Team Status -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Team Status</h3>
                </div>
                <div class="p-6">
                    @forelse($teamMembers as $member)
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                                        <span class="text-sm font-medium text-gray-600">{{ substr($member['name'], 0, 1) }}</span>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $member['name'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $member['role'] }}</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div class="w-2 h-2 rounded-full {{ $member['status_color'] }}"></div>
                                <span class="text-xs text-gray-500">{{ ucfirst($member['status']) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8">
                            <i class="fas fa-users text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No team members</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Task Completion Chart -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Task Completion</h3>
                </div>
                <div class="p-6">
                    <div class="h-64 flex items-center justify-center">
                        <canvas id="taskCompletionChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Bootstrap data from server
window.dashboardBootstrap = {!! $dashboardBootstrap !!};

// Initialize charts immediately with server data
document.addEventListener('DOMContentLoaded', function() {
    // Project Progress Chart
    const projectProgressCtx = document.getElementById('projectProgressChart');
    if (projectProgressCtx && window.dashboardBootstrap.charts.projectProgress) {
        new Chart(projectProgressCtx, window.dashboardBootstrap.charts.projectProgress);
    }
    
    // Task Completion Chart
    const taskCompletionCtx = document.getElementById('taskCompletionChart');
    if (taskCompletionCtx && window.dashboardBootstrap.charts.taskCompletion) {
        new Chart(taskCompletionCtx, window.dashboardBootstrap.charts.taskCompletion);
    }
});

// Dashboard Alpine.js component
function dashboardData() {
    return {
        kpis: {
            totalProjects: 0,
            projectGrowth: '+0%',
            activeTasks: 0,
            taskGrowth: '+0%',
            teamMembers: 0,
            teamGrowth: '+0%',
            completionRate: 0,
        },
        alerts: [],
        recentProjects: [],
        recentActivity: [],
        teamStatus: [],
        charts: {
            projectProgress: null,
            taskCompletion: null,
        },
        
        init() {
            // Load bootstrap data
            if (window.dashboardBootstrap) {
                const bootstrap = window.dashboardBootstrap;
                
                // Normalize data to ensure arrays
                const normalize = (value) => 
                    Array.isArray(value) ? value : Object.values(value || {});
                
                this.kpis = bootstrap.kpis || this.kpis;
                this.alerts = normalize(bootstrap.alerts);
                this.recentProjects = normalize(bootstrap.recentProjects);
                this.recentActivity = normalize(bootstrap.recentActivity);
                this.teamStatus = normalize(bootstrap.teamStatus);
                this.charts = bootstrap.charts || {};
                
                // Debug log
                console.log('Dashboard Bootstrap Data:', {
                    kpis: this.kpis,
                    alerts: this.alerts,
                    recentProjects: this.recentProjects,
                    recentActivity: this.recentActivity,
                    teamStatus: this.teamStatus,
                    charts: this.charts
                });
            }
            
            // Initialize charts after DOM is ready
            this.$nextTick(() => {
                this.initCharts();
            });
        },
        
        initCharts() {
            // Initialize Project Progress Chart (Doughnut)
            if (this.charts.projectProgress) {
                this.initProjectProgressChart();
            }
            
            // Initialize Task Completion Chart (Line)
            if (this.charts.taskCompletion) {
                this.initTaskCompletionChart();
            }
        },
        
        initProjectProgressChart() {
            const ctx = document.getElementById('projectProgressChart');
            if (!ctx) return;
            
            // Destroy existing chart
            if (window.projectProgressChartInstance) {
                window.projectProgressChartInstance.destroy();
            }
            
            window.projectProgressChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: this.charts.projectProgress,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                    },
                },
            });
        },
        
        initTaskCompletionChart() {
            const ctx = document.getElementById('taskCompletionChart');
            if (!ctx) return;
            
            // Destroy existing chart
            if (window.taskCompletionChartInstance) {
                window.taskCompletionChartInstance.destroy();
            }
            
            window.taskCompletionChartInstance = new Chart(ctx, {
                type: 'line',
                data: this.charts.taskCompletion,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                        },
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                    },
                },
            });
        },
        
        refreshDashboard() {
            window.location.reload();
        },
        
        formatTime(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffInHours = Math.floor((now - date) / (1000 * 60 * 60));
            
            if (diffInHours < 1) {
                return 'Just now';
            } else if (diffInHours < 24) {
                return `${diffInHours}h ago`;
            } else {
                const diffInDays = Math.floor(diffInHours / 24);
                return `${diffInDays}d ago`;
            }
        },
        
        dismissAllAlerts() {
            this.alerts = [];
        },
        
        openModal(type) {
            // Placeholder for modal functionality
            console.log('Opening modal:', type);
        },
    };
}
</script>
@endsection