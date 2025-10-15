<?php $__env->startSection('title', 'Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div class="min-h-screen bg-gray-50">
    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Welcome back, <span class="font-medium text-gray-900"><?php echo e(optional(Auth::user())->first_name ?? 'User'); ?></span>
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <button onclick="refreshDashboard()" 
                            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Refresh
                    </button>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('projects.create')): ?>
                        <a href="<?php echo e(route('app.projects.create')); ?>" 
                           class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-plus mr-2"></i>
                            New Project
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Success Message -->
        <?php if(session('success')): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium"><?php echo e(session('success')); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Projects KPI -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200 hover:shadow-md transition-shadow duration-200">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-project-diagram text-blue-600 text-lg"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Total Projects</p>
                            <p class="text-2xl font-bold text-gray-900" id="projects-count">
                                <span class="animate-pulse bg-gray-200 h-8 w-16 rounded"></span>
                            </p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center text-sm" id="projects-change">
                            <span class="animate-pulse bg-gray-200 h-4 w-20 rounded"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users KPI -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200 hover:shadow-md transition-shadow duration-200">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users text-green-600 text-lg"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Active Users</p>
                            <p class="text-2xl font-bold text-gray-900" id="users-count">
                                <span class="animate-pulse bg-gray-200 h-8 w-16 rounded"></span>
                            </p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center text-sm" id="users-change">
                            <span class="animate-pulse bg-gray-200 h-4 w-20 rounded"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress KPI -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200 hover:shadow-md transition-shadow duration-200">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-chart-line text-purple-600 text-lg"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Average Progress</p>
                            <p class="text-2xl font-bold text-gray-900" id="progress-count">
                                <span class="animate-pulse bg-gray-200 h-8 w-16 rounded"></span>
                            </p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center text-sm" id="progress-change">
                            <span class="animate-pulse bg-gray-200 h-4 w-20 rounded"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Budget KPI -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200 hover:shadow-md transition-shadow duration-200">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-dollar-sign text-yellow-600 text-lg"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Budget Utilization</p>
                            <p class="text-2xl font-bold text-gray-900" id="budget-count">
                                <span class="animate-pulse bg-gray-200 h-8 w-16 rounded"></span>
                            </p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center text-sm" id="budget-change">
                            <span class="animate-pulse bg-gray-200 h-4 w-20 rounded"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Projects -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">Recent Projects</h2>
                        <a href="<?php echo e(route('app.projects.index')); ?>" 
                           class="text-sm text-blue-600 hover:text-blue-500 font-medium">
                            View all
                        </a>
                    </div>
                </div>
                <div class="p-6">
                    <div id="recent-projects" class="space-y-4">
                        <!-- Loading skeleton -->
                        <div class="animate-pulse">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 bg-gray-200 rounded-lg"></div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                                    <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                                </div>
                                <div class="flex-shrink-0">
                                    <div class="h-4 bg-gray-200 rounded w-16"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">Recent Activity</h2>
                        <button onclick="loadMoreActivity()" 
                                class="text-sm text-blue-600 hover:text-blue-500 font-medium">
                            Load more
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <div id="recent-activity" class="space-y-4">
                        <!-- Loading skeleton -->
                        <div class="animate-pulse">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-gray-200 rounded-full"></div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                                    <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Project Progress Chart -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Project Progress</h2>
                </div>
                <div class="p-6">
                    <div id="project-progress-chart" class="h-64">
                        <!-- Chart will be rendered here -->
                        <div class="animate-pulse bg-gray-200 h-full rounded"></div>
                    </div>
                </div>
            </div>

            <!-- Task Distribution Chart -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Task Distribution</h2>
                </div>
                <div class="p-6">
                    <div id="task-distribution-chart" class="h-64">
                        <!-- Chart will be rendered here -->
                        <div class="animate-pulse bg-gray-200 h-full rounded"></div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Scripts -->
<script>
// Dashboard data management
class DashboardManager {
    constructor() {
        this.loading = false;
        this.init();
    }

    async init() {
        await this.loadDashboardData();
        this.setupEventListeners();
    }

    async loadDashboardData() {
        if (this.loading) return;
        
        this.loading = true;
        this.showLoadingStates();

        try {
            const [kpis, projects, activity] = await Promise.all([
                this.fetchKPIs(),
                this.fetchRecentProjects(),
                this.fetchRecentActivity()
            ]);

            this.renderKPIs(kpis);
            this.renderRecentProjects(projects);
            this.renderRecentActivity(activity);
            
            // Load charts after data is ready
            setTimeout(() => {
                this.loadCharts();
            }, 500);

        } catch (error) {
            console.error('Failed to load dashboard data:', error);
            this.showError('Failed to load dashboard data. Please try again.');
        } finally {
            this.loading = false;
        }
    }

    async fetchKPIs() {
        const response = await fetch('/api/dashboard/kpis', {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        if (!response.ok) {
            throw new Error('Failed to fetch KPIs');
        }

        return await response.json();
    }

    async fetchRecentProjects() {
        const response = await fetch('/api/projects?limit=5&sort=updated_at&order=desc', {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        if (!response.ok) {
            throw new Error('Failed to fetch recent projects');
        }

        const data = await response.json();
        return data.data || [];
    }

    async fetchRecentActivity() {
        const response = await fetch('/api/dashboard/recent-activity', {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        if (!response.ok) {
            throw new Error('Failed to fetch recent activity');
        }

        return await response.json();
    }

    renderKPIs(kpis) {
        // Projects KPI
        document.getElementById('projects-count').innerHTML = kpis.projects.total || 0;
        document.getElementById('projects-change').innerHTML = this.formatChange(kpis.projects.change);

        // Users KPI
        document.getElementById('users-count').innerHTML = kpis.users.active || 0;
        document.getElementById('users-change').innerHTML = this.formatChange(kpis.users.change);

        // Progress KPI
        document.getElementById('progress-count').innerHTML = `${kpis.progress.overall || 0}%`;
        document.getElementById('progress-change').innerHTML = this.formatChange(kpis.progress.change);

        // Budget KPI
        document.getElementById('budget-count').innerHTML = `${kpis.budget.utilization || 0}%`;
        document.getElementById('budget-change').innerHTML = this.formatChange(kpis.budget.change);
    }

    renderRecentProjects(projects) {
        const container = document.getElementById('recent-projects');
        
        if (projects.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8">
                    <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-project-diagram text-2xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No projects yet</h3>
                    <p class="text-gray-500 mb-4">Get started by creating your first project.</p>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('projects.create')): ?>
                        <a href="<?php echo e(route('app.projects.create')); ?>" 
                           class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>
                            Create First Project
                        </a>
                    <?php endif; ?>
                </div>
            `;
            return;
        }

        container.innerHTML = projects.map(project => `
            <div class="flex items-center space-x-4 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-project-diagram text-blue-600"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-medium text-gray-900 truncate">${project.name}</h3>
                    <p class="text-sm text-gray-500">${project.owner?.name || 'No owner'}</p>
                </div>
                <div class="flex-shrink-0">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getStatusColor(project.status)}">
                        ${project.status}
                    </span>
                </div>
            </div>
        `).join('');
    }

    renderRecentActivity(activities) {
        const container = document.getElementById('recent-activity');
        
        if (activities.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8">
                    <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-history text-2xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No recent activity</h3>
                    <p class="text-gray-500">Activity will appear here as you work on projects.</p>
                </div>
            `;
            return;
        }

        container.innerHTML = activities.map(activity => `
            <div class="flex items-start space-x-3 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-${this.getActivityIcon(activity.type)} text-gray-600 text-sm"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-900">${activity.description}</p>
                    <p class="text-xs text-gray-500">${this.formatTimeAgo(activity.timestamp)}</p>
                </div>
            </div>
        `).join('');
    }

    async loadCharts() {
        // Load Chart.js if not already loaded
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js not loaded');
            return;
        }

        try {
            const response = await fetch('/api/dashboard/charts', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            if (!response.ok) {
                throw new Error('Failed to fetch chart data');
            }

            const data = await response.json();
            this.loadProjectProgressChart(data.data.project_progress);
            this.loadTaskDistributionChart(data.data.task_distribution);
        } catch (error) {
            console.error('Failed to load charts:', error);
            // Fallback to mock data
            this.loadProjectProgressChart();
            this.loadTaskDistributionChart();
        }
    }

    loadProjectProgressChart(chartData = null) {
        const ctx = document.getElementById('project-progress-chart');
        if (!ctx) return;

        // Use real data if provided, otherwise fallback to mock data
        const data = chartData || {
            labels: ['Planning', 'Active', 'On Hold', 'Completed', 'Cancelled'],
            datasets: [{
                label: 'Projects',
                data: [2, 5, 1, 3, 0],
                backgroundColor: ['#F59E0B', '#10B981', '#EF4444', '#3B82F6', '#6B7280'],
                borderWidth: 0
            }]
        };

        new Chart(ctx, {
            type: 'doughnut',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    loadTaskDistributionChart(chartData = null) {
        const ctx = document.getElementById('task-distribution-chart');
        if (!ctx) return;

        // Use real data if provided, otherwise fallback to mock data
        const data = chartData || {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Average Progress %',
                data: [12, 19, 3, 5, 2, 3],
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 2,
                fill: true
            }]
        };

        new Chart(ctx, {
            type: 'line',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: ${context.parsed.y}%`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
    }

    showLoadingStates() {
        // KPIs are already showing loading states
        // Charts will show loading states
    }

    showError(message) {
        // Show error message to user
        console.error(message);
    }

    formatChange(change) {
        if (!change) return '<span class="text-gray-400">No change</span>';
        
        const isPositive = change > 0;
        const color = isPositive ? 'text-green-600' : 'text-red-600';
        const icon = isPositive ? 'fas fa-arrow-up' : 'fas fa-arrow-down';
        
        return `<span class="${color} flex items-center">
            <i class="${icon} mr-1 text-xs"></i>
            ${Math.abs(change)}%
        </span>`;
    }

    getStatusColor(status) {
        const colors = {
            'planning': 'bg-yellow-100 text-yellow-800',
            'active': 'bg-green-100 text-green-800',
            'on_hold': 'bg-red-100 text-red-800',
            'completed': 'bg-blue-100 text-blue-800',
            'cancelled': 'bg-gray-100 text-gray-800'
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    }

    getActivityIcon(type) {
        const icons = {
            'project': 'project-diagram',
            'task': 'tasks',
            'user': 'user',
            'system': 'cog'
        };
        return icons[type] || 'circle';
    }

    formatTimeAgo(timestamp) {
        const now = new Date();
        const time = new Date(timestamp);
        const diff = now - time;
        
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);
        
        if (minutes < 60) return `${minutes}m ago`;
        if (hours < 24) return `${hours}h ago`;
        return `${days}d ago`;
    }

    setupEventListeners() {
        // Auto-refresh every 5 minutes
        setInterval(() => {
            this.loadDashboardData();
        }, 300000);
    }
}

// Initialize dashboard when page loads
document.addEventListener('DOMContentLoaded', () => {
    new DashboardManager();
});

// Global functions for buttons
function refreshDashboard() {
    if (window.dashboardManager) {
        window.dashboardManager.loadDashboardData();
    }
}

function loadMoreActivity() {
    // Implement load more activity
    console.log('Load more activity');
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/_legacy/dashboard/dashboard-new-legacy.blade.php ENDPATH**/ ?>