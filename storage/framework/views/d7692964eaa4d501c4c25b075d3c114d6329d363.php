<?php $__env->startSection('title', 'Advanced Analytics Dashboard - ZenaManage Phase 4'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="advancedAnalytics()" x-init="init()" class="min-h-screen bg-gray-50">
    
    <!-- Advanced Analytics Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Advanced Analytics</h1>
                    <p class="text-gray-600 mt-1">Real-time insights, interactive visualizations, and AI-powered recommendations</p>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- Time Range Selector -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700">Time Range:</label>
                        <select x-model="timeRange" @change="updateTimeRange()" 
                                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="7d">Last 7 days</option>
                            <option value="30d">Last 30 days</option>
                            <option value="90d">Last 90 days</option>
                            <option value="1y">Last year</option>
                        </select>
                    </div>
                    
                    <!-- Export Button -->
                    <button @click="exportDashboard()" 
                            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-download mr-2"></i>Export
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

    <!-- Advanced Filters Bar -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <h3 class="text-lg font-semibold text-gray-900">Advanced Filters</h3>
                    
                    <!-- Project Filter -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700">Project:</label>
                        <select x-model="filters.project" @change="applyFilters()" 
                                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">All Projects</option>
                            <template x-for="project in projects" :key="project.id">
                                <option :value="project.id" x-text="project.name"></option>
                            </template>
                        </select>
                    </div>
                    
                    <!-- Team Filter -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700">Team:</label>
                        <select x-model="filters.team" @change="applyFilters()" 
                                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">All Teams</option>
                            <template x-for="team in teams" :key="team.id">
                                <option :value="team.id" x-text="team.name"></option>
                            </template>
                        </select>
                    </div>
                    
                    <!-- Status Filter -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700">Status:</label>
                        <select x-model="filters.status" @change="applyFilters()" 
                                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="on_hold">On Hold</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex items-center space-x-2">
                    <button @click="clearFilters()" 
                            class="px-3 py-2 text-gray-600 hover:text-gray-900 transition-colors">
                        <i class="fas fa-times mr-1"></i>Clear All
                    </button>
                    <button @click="saveFilterPreset()" 
                            class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-save mr-1"></i>Save Preset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Analytics Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Key Performance Indicators -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            
            <!-- Revenue Growth -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Revenue Growth</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="kpis.revenueGrowth + '%'"></p>
                        <p class="text-xs text-green-600" x-text="'+' + kpis.revenueGrowthChange + '% vs last period'"></p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-lg">
                        <i class="fas fa-chart-line text-green-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-500 h-2 rounded-full" :style="'width: ' + kpis.revenueGrowth + '%'"></div>
                    </div>
                </div>
            </div>
            
            <!-- Customer Satisfaction -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Customer Satisfaction</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="kpis.customerSatisfaction + '/10'"></p>
                        <p class="text-xs text-blue-600" x-text="kpis.customerSatisfactionChange + ' vs last month'"></p>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <i class="fas fa-star text-blue-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="flex space-x-1">
                        <template x-for="i in 10" :key="i">
                            <div class="w-2 h-2 rounded-full" 
                                 :class="i <= kpis.customerSatisfaction ? 'bg-blue-500' : 'bg-gray-200'"></div>
                        </template>
                    </div>
                </div>
            </div>
            
            <!-- Project Completion Rate -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Project Completion</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="kpis.projectCompletion + '%'"></p>
                        <p class="text-xs text-purple-600" x-text="kpis.projectCompletionChange + ' vs last quarter'"></p>
                    </div>
                    <div class="p-3 bg-purple-100 rounded-lg">
                        <i class="fas fa-check-circle text-purple-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-purple-500 h-2 rounded-full" :style="'width: ' + kpis.projectCompletion + '%'"></div>
                    </div>
                </div>
            </div>
            
            <!-- Team Productivity -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Team Productivity</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="kpis.teamProductivity + '%'"></p>
                        <p class="text-xs text-orange-600" x-text="kpis.teamProductivityChange + ' vs last week'"></p>
                    </div>
                    <div class="p-3 bg-orange-100 rounded-lg">
                        <i class="fas fa-users text-orange-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-orange-500 h-2 rounded-full" :style="'width: ' + kpis.teamProductivity + '%'"></div>
                    </div>
                </div>
            </div>
            
        </div>

        <!-- Advanced Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            
            <!-- Revenue Trend Chart -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Revenue Trend</h3>
                    <div class="flex items-center space-x-2">
                        <button @click="toggleChartType('revenue')" 
                                class="px-3 py-1 text-sm bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                            <i class="fas fa-chart-line mr-1"></i>Line
                        </button>
                        <button @click="toggleChartType('revenue')" 
                                class="px-3 py-1 text-sm bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                            <i class="fas fa-chart-bar mr-1"></i>Bar
                        </button>
                    </div>
                </div>
                <div id="revenue-trend-chart" class="h-80"></div>
            </div>
            
            <!-- Project Status Distribution -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Project Status Distribution</h3>
                    <button @click="drillDown('project-status')" 
                            class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors">
                        <i class="fas fa-search-plus mr-1"></i>Drill Down
                    </button>
                </div>
                <div id="project-status-chart" class="h-80"></div>
            </div>
            
        </div>

        <!-- AI Insights & Recommendations -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-robot text-blue-600 mr-2"></i>
                    AI-Powered Insights & Recommendations
                </h3>
                <button @click="generateInsights()" 
                        :disabled="generatingInsights"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
                    <i class="fas fa-magic mr-2" :class="generatingInsights ? 'animate-spin' : ''"></i>
                    <span x-show="!generatingInsights">Generate Insights</span>
                    <span x-show="generatingInsights">Generating...</span>
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <template x-for="insight in aiInsights" :key="insight.id">
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <div class="flex items-start space-x-3">
                            <div class="p-2 rounded-lg" :class="insight.type === 'warning' ? 'bg-yellow-100' : insight.type === 'success' ? 'bg-green-100' : 'bg-blue-100'">
                                <i :class="insight.icon" 
                                   :class="insight.type === 'warning' ? 'text-yellow-600' : insight.type === 'success' ? 'text-green-600' : 'text-blue-600'"
                                   class="text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-gray-900" x-text="insight.title"></h4>
                                <p class="text-xs text-gray-600 mt-1" x-text="insight.description"></p>
                                <div class="mt-2">
                                    <button @click="applyRecommendation(insight)" 
                                            class="text-xs text-blue-600 hover:text-blue-800 underline">
                                        <i class="fas fa-lightbulb mr-1"></i>Apply Recommendation
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Real-time Activity Feed -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-broadcast-tower text-green-600 mr-2"></i>
                    Real-time Activity Feed
                </h3>
                <div class="flex items-center space-x-2">
                    <div class="flex items-center space-x-1">
                        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="text-sm text-gray-600">Live</span>
                    </div>
                    <button @click="toggleAutoRefresh()" 
                            class="px-3 py-1 text-sm bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-sync-alt mr-1" :class="autoRefresh ? 'animate-spin' : ''"></i>
                        <span x-text="autoRefresh ? 'Auto Refresh ON' : 'Auto Refresh OFF'"></span>
                    </button>
                </div>
            </div>
            
            <div class="space-y-3 max-h-96 overflow-y-auto">
                <template x-for="activity in realTimeActivity" :key="activity.id">
                    <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <i :class="activity.icon" class="text-blue-600 text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900" x-text="activity.description"></p>
                            <div class="flex items-center space-x-4 mt-1">
                                <span class="text-xs text-gray-500" x-text="activity.user"></span>
                                <span class="text-xs text-gray-500" x-text="activity.time"></span>
                                <span class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded-full" x-text="activity.category"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
        
    </div>
    
</div>

<!-- Include required JavaScript libraries -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/lodash@latest/lodash.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
function advancedAnalytics() {
    return {
        refreshing: false,
        generatingInsights: false,
        timeRange: '30d',
        autoRefresh: true,
        chartTypes: {
            revenue: 'line',
            projectStatus: 'pie'
        },
        filters: {
            project: '',
            team: '',
            status: ''
        },
        kpis: {
            revenueGrowth: 23,
            revenueGrowthChange: 5,
            customerSatisfaction: 8.5,
            customerSatisfactionChange: '+0.3',
            projectCompletion: 87,
            projectCompletionChange: '+12%',
            teamProductivity: 92,
            teamProductivityChange: '+8%'
        },
        projects: [
            { id: 1, name: 'Project Alpha' },
            { id: 2, name: 'Project Beta' },
            { id: 3, name: 'Project Gamma' }
        ],
        teams: [
            { id: 1, name: 'Development Team' },
            { id: 2, name: 'Design Team' },
            { id: 3, name: 'Marketing Team' }
        ],
        aiInsights: [
            {
                id: 1,
                type: 'success',
                icon: 'fas fa-trending-up',
                title: 'Revenue Growth Opportunity',
                description: 'Your Q4 revenue is 23% higher than Q3. Consider expanding successful strategies.'
            },
            {
                id: 2,
                type: 'warning',
                icon: 'fas fa-exclamation-triangle',
                title: 'Project Delay Risk',
                description: 'Project Alpha is 15% behind schedule. Consider reallocating resources.'
            },
            {
                id: 3,
                type: 'info',
                icon: 'fas fa-lightbulb',
                title: 'Team Productivity Boost',
                description: 'Development team shows 92% productivity. Share best practices with other teams.'
            }
        ],
        realTimeActivity: [
            {
                id: 1,
                description: 'Project Alpha milestone completed',
                user: 'John Doe',
                time: '2 minutes ago',
                category: 'Project',
                icon: 'fas fa-check-circle'
            },
            {
                id: 2,
                description: 'New team member joined',
                user: 'Sarah Smith',
                time: '5 minutes ago',
                category: 'Team',
                icon: 'fas fa-user-plus'
            },
            {
                id: 3,
                description: 'Budget report generated',
                user: 'System',
                time: '8 minutes ago',
                category: 'Report',
                icon: 'fas fa-file-alt'
            }
        ],
        
        init() {
            console.log('🚀 Advanced Analytics Dashboard initialized');
            this.loadData();
            this.initCharts();
            this.startRealTimeUpdates();
        },
        
        async loadData() {
            console.log('📊 Loading advanced analytics data...');
            // Simulate API calls
            await new Promise(resolve => setTimeout(resolve, 1000));
            console.log('✅ Advanced analytics data loaded');
        },
        
        async refreshData() {
            this.refreshing = true;
            console.log('🔄 Refreshing advanced analytics data...');
            await this.loadData();
            this.updateCharts();
            setTimeout(() => {
                this.refreshing = false;
            }, 1000);
        },
        
        updateTimeRange() {
            console.log('📅 Time range updated:', this.timeRange);
            this.loadData();
            this.updateCharts();
        },
        
        applyFilters() {
            console.log('🔍 Applying filters:', this.filters);
            this.loadData();
            this.updateCharts();
        },
        
        clearFilters() {
            this.filters = {
                project: '',
                team: '',
                status: ''
            };
            this.applyFilters();
        },
        
        saveFilterPreset() {
            console.log('💾 Saving filter preset');
            // Implementation for saving filter presets
        },
        
        toggleChartType(chartName) {
            this.chartTypes[chartName] = this.chartTypes[chartName] === 'line' ? 'bar' : 'line';
            this.updateCharts();
        },
        
        drillDown(dimension) {
            console.log('🔍 Drilling down into:', dimension);
            // Implementation for drill-down functionality
        },
        
        async generateInsights() {
            this.generatingInsights = true;
            console.log('🤖 Generating AI insights...');
            
            // Simulate AI processing
            await new Promise(resolve => setTimeout(resolve, 2000));
            
            // Add new insight
            this.aiInsights.push({
                id: Date.now(),
                type: 'info',
                icon: 'fas fa-chart-line',
                title: 'New Insight Generated',
                description: 'Based on current data patterns, consider optimizing resource allocation for better efficiency.'
            });
            
            this.generatingInsights = false;
        },
        
        applyRecommendation(insight) {
            console.log('✅ Applying recommendation:', insight.title);
            // Implementation for applying recommendations
        },
        
        toggleAutoRefresh() {
            this.autoRefresh = !this.autoRefresh;
            if (this.autoRefresh) {
                this.startRealTimeUpdates();
            } else {
                this.stopRealTimeUpdates();
            }
        },
        
        startRealTimeUpdates() {
            if (this.realTimeInterval) {
                clearInterval(this.realTimeInterval);
            }
            
            this.realTimeInterval = setInterval(() => {
                this.addRealTimeActivity();
            }, 5000); // Update every 5 seconds
        },
        
        stopRealTimeUpdates() {
            if (this.realTimeInterval) {
                clearInterval(this.realTimeInterval);
                this.realTimeInterval = null;
            }
        },
        
        addRealTimeActivity() {
            const activities = [
                'Task completed',
                'New comment added',
                'File uploaded',
                'Status updated',
                'Meeting scheduled'
            ];
            
            const users = ['John Doe', 'Sarah Smith', 'Mike Johnson', 'Jane Wilson'];
            const categories = ['Task', 'Comment', 'File', 'Status', 'Meeting'];
            const icons = ['fas fa-check', 'fas fa-comment', 'fas fa-file', 'fas fa-sync', 'fas fa-calendar'];
            
            const randomActivity = activities[Math.floor(Math.random() * activities.length)];
            const randomUser = users[Math.floor(Math.random() * users.length)];
            const randomCategory = categories[Math.floor(Math.random() * categories.length)];
            const randomIcon = icons[Math.floor(Math.random() * icons.length)];
            
            this.realTimeActivity.unshift({
                id: Date.now(),
                description: randomActivity,
                user: randomUser,
                time: 'Just now',
                category: randomCategory,
                icon: randomIcon
            });
            
            // Keep only last 10 activities
            if (this.realTimeActivity.length > 10) {
                this.realTimeActivity = this.realTimeActivity.slice(0, 10);
            }
        },
        
        exportDashboard() {
            console.log('📤 Exporting dashboard data...');
            // Implementation for dashboard export
            const data = {
                kpis: this.kpis,
                filters: this.filters,
                timeRange: this.timeRange,
                timestamp: new Date().toISOString()
            };
            
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `analytics-dashboard-${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        },
        
        initCharts() {
            console.log('📊 Initializing advanced charts');
            this.initRevenueTrendChart();
            this.initProjectStatusChart();
        },
        
        updateCharts() {
            console.log('📊 Updating charts with new data');
            this.initRevenueTrendChart();
            this.initProjectStatusChart();
        },
        
        initRevenueTrendChart() {
            const options = {
                series: [{
                    name: 'Revenue',
                    data: [10000, 12000, 15000, 18000, 22000, 25000, 28000, 30000, 32000, 35000]
                }],
                chart: {
                    type: this.chartTypes.revenue,
                    height: 300,
                    toolbar: {
                        show: true,
                        tools: {
                            download: true,
                            selection: true,
                            zoom: true,
                            zoomin: true,
                            zoomout: true,
                            pan: true,
                            reset: true
                        }
                    }
                },
                colors: ['#3b82f6'],
                xaxis: {
                    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct']
                },
                yaxis: {
                    title: {
                        text: 'Revenue ($)'
                    }
                },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return '$' + val.toLocaleString()
                        }
                    }
                }
            };
            
            const chart = new ApexCharts(document.querySelector("#revenue-trend-chart"), options);
            chart.render();
        },
        
        initProjectStatusChart() {
            const options = {
                series: [44, 55, 13, 43, 22],
                chart: {
                    type: 'pie',
                    height: 300
                },
                labels: ['Active', 'Completed', 'On Hold', 'Planning', 'Cancelled'],
                colors: ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ef4444'],
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return val + ' projects'
                        }
                    }
                }
            };
            
            const chart = new ApexCharts(document.querySelector("#project-status-chart"), options);
            chart.render();
        }
    };
}
</script>

<style>
/* Advanced Analytics Styles */
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .5; }
}

/* Mobile responsive */
@media (max-width: 768px) {
    .grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-4 {
        @apply grid-cols-1;
    }
    
    .lg\\:grid-cols-2 {
        @apply grid-cols-1;
    }
    
    .lg\\:grid-cols-3 {
        @apply grid-cols-1;
    }
}

/* Focus states for accessibility */
button:focus,
select:focus,
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
    .animate-spin,
    .animate-pulse {
        animation: none;
    }
}
</style>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app-layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/_future/advanced-analytics.blade.php ENDPATH**/ ?>