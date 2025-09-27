@extends('layouts.app-layout')

@section('title', 'Dashboard Phase 3 - ZenaManage')

@section('content')
<div x-data="phase3Dashboard()" x-init="init()" class="min-h-screen bg-gray-50">
    
    <!-- Universal Page Frame: Header → Global Nav → Page Nav → KPI Strip → Alert Bar → Main Content → Activity -->
    
    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Dashboard Phase 3</h1>
                    <p class="text-gray-600 mt-1">Universal Page Frame with KPI-first design</p>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- Smart Search Trigger -->
                    <button @click="openSmartSearch()" 
                            class="flex items-center space-x-2 px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            aria-label="Open smart search">
                        <i class="fas fa-search"></i>
                        <span class="hidden sm:inline">Search</span>
                        <kbd class="hidden sm:inline px-2 py-1 bg-gray-200 rounded text-xs">Ctrl+K</kbd>
                    </button>
                    
                    <!-- Refresh Button -->
                    <button @click="refreshData()" 
                            :disabled="refreshing"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
                        <i class="fas fa-sync-alt mr-2" :class="refreshing ? 'animate-spin' : ''"></i>
                        <span x-show="!refreshing">Refresh</span>
                        <span x-show="refreshing">Refreshing...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Global Navigation -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <nav class="flex space-x-8 py-4">
                <a href="/app/dashboard" class="text-gray-600 hover:text-gray-900 font-medium">Dashboard</a>
                <a href="/app/projects" class="text-gray-600 hover:text-gray-900 font-medium">Projects</a>
                <a href="/app/tasks" class="text-gray-600 hover:text-gray-900 font-medium">Tasks</a>
                <a href="/app/team" class="text-gray-600 hover:text-gray-900 font-medium">Team</a>
                <a href="/app/analytics" class="text-gray-600 hover:text-gray-900 font-medium">Analytics</a>
            </nav>
        </div>
    </div>

    <!-- Page Navigation -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <nav class="flex space-x-4 py-3">
                <button @click="activeTab = 'overview'" 
                        :class="activeTab === 'overview' ? 'text-blue-600 border-blue-600' : 'text-gray-600 border-transparent'"
                        class="px-3 py-2 border-b-2 font-medium text-sm transition-colors">
                    Overview
                </button>
                <button @click="activeTab = 'projects'" 
                        :class="activeTab === 'projects' ? 'text-blue-600 border-blue-600' : 'text-gray-600 border-transparent'"
                        class="px-3 py-2 border-b-2 font-medium text-sm transition-colors">
                    Projects
                </button>
                <button @click="activeTab = 'tasks'" 
                        :class="activeTab === 'tasks' ? 'text-blue-600 border-blue-600' : 'text-gray-600 border-transparent'"
                        class="px-3 py-2 border-b-2 font-medium text-sm transition-colors">
                    Tasks
                </button>
                <button @click="activeTab = 'team'" 
                        :class="activeTab === 'team' ? 'text-blue-600 border-blue-600' : 'text-gray-600 border-transparent'"
                        class="px-3 py-2 border-b-2 font-medium text-sm transition-colors">
                    Team
                </button>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- KPI Strip (4 cards maximum above the fold) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            
            <!-- Active Projects KPI -->
            @include('components.dashboard-kpi-card', [
                'kpi_key' => 'projects-active',
                'label' => 'Active Projects',
                'value' => '12',
                'trend' => '+8% from last week',
                'trend_type' => 'positive',
                'icon' => 'fas fa-project-diagram',
                'icon_color' => 'blue',
                'primary_action' => [
                    'label' => 'View Projects',
                    'url' => '/app/projects',
                    'method' => 'GET'
                ],
                'secondary_action' => [
                    'label' => 'Create Project',
                    'url' => '/app/projects/create',
                    'method' => 'GET'
                ]
            ])
            
            <!-- Tasks Due Today KPI -->
            @include('components.dashboard-kpi-card', [
                'kpi_key' => 'tasks-today',
                'label' => 'Tasks Due Today',
                'value' => '7',
                'trend' => '+3 from yesterday',
                'trend_type' => 'positive',
                'icon' => 'fas fa-tasks',
                'icon_color' => 'green',
                'primary_action' => [
                    'label' => 'View Tasks',
                    'url' => '/app/tasks?filter=today',
                    'method' => 'GET'
                ],
                'secondary_action' => [
                    'label' => 'Add Task',
                    'url' => '/app/tasks/create',
                    'method' => 'GET'
                ]
            ])
            
            <!-- Overdue Tasks KPI -->
            @include('components.dashboard-kpi-card', [
                'kpi_key' => 'tasks-overdue',
                'label' => 'Overdue Tasks',
                'value' => '3',
                'trend' => '-2 from last week',
                'trend_type' => 'positive',
                'icon' => 'fas fa-exclamation-triangle',
                'icon_color' => 'red',
                'primary_action' => [
                    'label' => 'View Overdue',
                    'url' => '/app/tasks?filter=overdue',
                    'method' => 'GET'
                ],
                'secondary_action' => [
                    'label' => 'Resolve All',
                    'url' => '/app/tasks/resolve-overdue',
                    'method' => 'POST'
                ]
            ])
            
            <!-- Focus Minutes KPI -->
            @include('components.dashboard-kpi-card', [
                'kpi_key' => 'focus-minutes',
                'label' => 'Focus Minutes Today',
                'value' => '142',
                'trend' => '+25% from yesterday',
                'trend_type' => 'positive',
                'icon' => 'fas fa-clock',
                'icon_color' => 'purple',
                'primary_action' => [
                    'label' => 'Start Focus',
                    'url' => '/app/focus-mode',
                    'method' => 'GET'
                ],
                'secondary_action' => [
                    'label' => 'View Stats',
                    'url' => '/app/analytics/focus',
                    'method' => 'GET'
                ]
            ])
            
        </div>

        <!-- Alert Bar (Critical alerts only) -->
        <div x-show="alerts.length > 0" class="mb-8">
            <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-semibold text-red-900 flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                        Critical Alerts
                    </h3>
                    <button @click="dismissAllAlerts()" class="text-sm text-red-600 hover:text-red-800">
                        <i class="fas fa-times mr-1"></i>Dismiss All
                    </button>
                </div>
                <div class="space-y-2">
                    <template x-for="alert in alerts.slice(0, 3)" :key="alert.id">
                        <div class="flex items-start space-x-3 p-3 bg-red-100 rounded-lg border border-red-200">
                            <div class="p-2 bg-red-200 rounded-lg">
                                <i class="fas fa-exclamation text-red-600 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-red-900" x-text="alert.title"></p>
                                <p class="text-xs text-red-700 mt-1" x-text="alert.message"></p>
                                <div class="flex items-center space-x-4 mt-2">
                                    <span class="text-xs text-red-600" x-text="alert.created_at"></span>
                                    <template x-if="alert.action_url">
                                        <a :href="alert.action_url" class="text-xs text-red-600 hover:text-red-800 underline">
                                            <i class="fas fa-external-link-alt mr-1"></i>Resolve
                                        </a>
                                    </template>
                                </div>
                            </div>
                            <button @click="dismissAlert(alert.id)" class="text-red-400 hover:text-red-600">
                                <i class="fas fa-times text-sm"></i>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left Column: Charts & Insights -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Revenue Goal Donut Chart -->
                @include('components.revenue-goal-chart')
                
                <!-- Cohort Analysis Horizontal Bar Chart -->
                @include('components.cohort-analysis-chart')
                
            </div>
            
            <!-- Right Column: Activity Feed & Quick Actions -->
            <div class="space-y-6">
                
                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <template x-for="action in quickActions" :key="action.id">
                            <button @click="executeQuickAction(action)" 
                                    class="w-full flex items-center space-x-3 p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors border border-blue-200">
                                <div class="p-2 bg-blue-500 rounded-lg">
                                    <i :class="action.icon" class="text-white text-sm"></i>
                                </div>
                                <span class="text-sm font-medium text-blue-900" x-text="action.label"></span>
                            </button>
                        </template>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Activity</h3>
                    <div class="space-y-3">
                        <template x-for="item in recentActivity" :key="item.id">
                            <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                                <div class="p-2 bg-blue-100 rounded-lg">
                                    <i class="fas fa-circle text-blue-600 text-xs"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900" x-text="item.description"></p>
                                    <p class="text-xs text-gray-500">
                                        <span x-text="item.user"></span> • <span x-text="item.time"></span>
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                
            </div>
            
        </div>
        
    </div>
    
    <!-- Smart Search Component -->
    @include('components.smart-search')
    
</div>

<!-- Include required JavaScript libraries -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/lodash@latest/lodash.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
function phase3Dashboard() {
    return {
        refreshing: false,
        activeTab: 'overview',
        alerts: [
            {
                id: 1,
                title: 'Project Deadline Approaching',
                message: 'Project Alpha deadline is in 3 days',
                created_at: '2 hours ago',
                action_url: '/app/projects/alpha'
            },
            {
                id: 2,
                title: 'Budget Overrun Warning',
                message: 'Project Beta has exceeded 90% of budget',
                created_at: '4 hours ago',
                action_url: '/app/projects/beta'
            }
        ],
        quickActions: [
            { id: 1, label: 'New Project', icon: 'fas fa-plus', action: 'create_project' },
            { id: 2, label: 'Add Task', icon: 'fas fa-tasks', action: 'add_task' },
            { id: 3, label: 'Invite Team', icon: 'fas fa-user-plus', action: 'invite_team' },
            { id: 4, label: 'Upload File', icon: 'fas fa-upload', action: 'upload_file' }
        ],
        recentActivity: [
            { id: 1, description: 'Task "Design Review" completed', user: 'John Doe', time: '2 hours ago' },
            { id: 2, description: 'New project "Mobile App" created', user: 'Sarah Smith', time: '4 hours ago' },
            { id: 3, description: 'Team member invited', user: 'Mike Johnson', time: '6 hours ago' },
            { id: 4, description: 'Document uploaded', user: 'Jane Wilson', time: '8 hours ago' }
        ],
        
        init() {
            console.log('🚀 Phase 3 Dashboard initialized');
            this.loadData();
            this.setupKeyboardShortcuts();
            this.initCharts();
        },
        
        async loadData() {
            console.log('📊 Loading Phase 3 dashboard data...');
            // Simulate API calls
            await new Promise(resolve => setTimeout(resolve, 1000));
            console.log('✅ Phase 3 data loaded');
        },
        
        async refreshData() {
            this.refreshing = true;
            console.log('🔄 Refreshing Phase 3 data...');
            await this.loadData();
            setTimeout(() => {
                this.refreshing = false;
            }, 1000);
        },
        
        setupKeyboardShortcuts() {
            document.addEventListener('keydown', (e) => {
                // Ctrl+K for smart search
                if (e.ctrlKey && e.key === 'k') {
                    e.preventDefault();
                    this.openSmartSearch();
                }
                
                // Escape to close modals
                if (e.key === 'Escape') {
                    this.closeModals();
                }
            });
        },
        
        openSmartSearch() {
            // Trigger smart search modal
            const modal = document.getElementById('search-modal');
            if (modal) {
                modal.classList.remove('hidden');
                const input = modal.querySelector('#kbdInput');
                if (input) {
                    input.focus();
                }
            }
        },
        
        closeModals() {
            const modal = document.getElementById('search-modal');
            if (modal) {
                modal.classList.add('hidden');
            }
        },
        
        dismissAlert(alertId) {
            console.log('🚨 Dismissing alert:', alertId);
            this.alerts = this.alerts.filter(alert => alert.id !== alertId);
        },
        
        dismissAllAlerts() {
            console.log('🚨 Dismissing all alerts');
            this.alerts = [];
        },
        
        executeQuickAction(action) {
            console.log('⚡ Executing quick action:', action.action);
            switch(action.action) {
                case 'create_project':
                    window.location.href = '/app/projects/create';
                    break;
                case 'add_task':
                    window.location.href = '/app/tasks/create';
                    break;
                case 'invite_team':
                    window.location.href = '/app/team/invite';
                    break;
                case 'upload_file':
                    window.location.href = '/app/documents/upload';
                    break;
                default:
                    console.log('Unknown action:', action.action);
            }
        },
        
        initCharts() {
            console.log('📊 Initializing Phase 3 charts');
            this.initProjectStatusChart();
            this.initTaskTrendChart();
        },
        
        initProjectStatusChart() {
            const ctx = document.getElementById('projectStatusChart');
            if (!ctx) return;

            const canvas = ctx.getContext('2d');
            canvas.clearRect(0, 0, ctx.width, ctx.height);
            
            // Draw a simple pie chart
            const data = [
                { label: 'Active', value: 8, color: '#3B82F6' },
                { label: 'Completed', value: 4, color: '#10B981' },
                { label: 'On Hold', value: 2, color: '#F59E0B' },
                { label: 'Cancelled', value: 1, color: '#EF4444' }
            ];
            
            let currentAngle = 0;
            const centerX = ctx.width / 2;
            const centerY = ctx.height / 2;
            const radius = 80;
            
            data.forEach(item => {
                const sliceAngle = (item.value / 15) * 2 * Math.PI;
                
                canvas.beginPath();
                canvas.moveTo(centerX, centerY);
                canvas.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
                canvas.closePath();
                canvas.fillStyle = item.color;
                canvas.fill();
                
                currentAngle += sliceAngle;
            });
            
            // Add labels
            canvas.fillStyle = '#374151';
            canvas.font = '12px Arial';
            canvas.textAlign = 'center';
            canvas.fillText('Project Status', centerX, centerY - 100);
        },
        
        initTaskTrendChart() {
            const ctx = document.getElementById('taskTrendChart');
            if (!ctx) return;

            const canvas = ctx.getContext('2d');
            canvas.clearRect(0, 0, ctx.width, ctx.height);
            
            // Draw a simple line chart
            const data = [5, 8, 12, 15, 18, 20, 25];
            const padding = 40;
            const chartWidth = ctx.width - 2 * padding;
            const chartHeight = ctx.height - 2 * padding;
            
            // Draw axes
            canvas.strokeStyle = '#E5E7EB';
            canvas.lineWidth = 1;
            canvas.beginPath();
            canvas.moveTo(padding, padding);
            canvas.lineTo(padding, ctx.height - padding);
            canvas.lineTo(ctx.width - padding, ctx.height - padding);
            canvas.stroke();
            
            // Draw line
            canvas.strokeStyle = '#3B82F6';
            canvas.lineWidth = 3;
            canvas.beginPath();
            
            data.forEach((value, index) => {
                const x = padding + (index / (data.length - 1)) * chartWidth;
                const y = ctx.height - padding - (value / 25) * chartHeight;
                
                if (index === 0) {
                    canvas.moveTo(x, y);
                } else {
                    canvas.lineTo(x, y);
                }
            });
            canvas.stroke();
            
            // Add title
            canvas.fillStyle = '#374151';
            canvas.font = '12px Arial';
            canvas.textAlign = 'center';
            canvas.fillText('Task Completion Trend', ctx.width / 2, 20);
        }
    };
}
</script>

<style>
/* Phase 3 Dashboard Styles */
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Mobile responsive */
@media (max-width: 768px) {
    .grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-4 {
        @apply grid-cols-1;
    }
    
    .lg\\:grid-cols-3 {
        @apply grid-cols-1;
    }
    
    .lg\\:col-span-2 {
        @apply col-span-1;
    }
}

/* Focus states for accessibility */
button:focus,
input:focus {
    @apply outline-none ring-2 ring-blue-500 ring-opacity-50;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .bg-white {
        @apply border-2 border-black;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .transition-all,
    .transition-colors {
        transition: none;
    }
}
</style>
@endsection
