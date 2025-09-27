<!-- Analytics Dashboard Content -->
<div x-data="analyticsDashboard()" x-init="init()" class="space-y-6 mobile-content">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Analytics Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500">Comprehensive insights and performance metrics</p>
        </div>
        
        <!-- Export Actions -->
        <div class="mt-4 sm:mt-0 flex items-center space-x-3">
            <select x-model="selectedPeriod" @change="loadAnalytics()" 
                    class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="7d">Last 7 days</option>
                <option value="30d">Last 30 days</option>
                <option value="90d">Last 90 days</option>
                <option value="1y">Last year</option>
            </select>
            
            <button @click="exportReport('pdf')" 
                    class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-file-pdf mr-2 text-red-600"></i>
                PDF
            </button>
            
            <button @click="exportReport('excel')" 
                    class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-file-excel mr-2 text-green-600"></i>
                Excel
            </button>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span class="ml-3 text-gray-600">Loading analytics...</span>
    </div>

    <!-- Error State -->
    <div x-show="error" class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-400"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">Error loading analytics</h3>
                <div class="mt-2 text-sm text-red-700">
                    <p x-text="error"></p>
                </div>
                <div class="mt-4">
                    <button @click="loadAnalytics()" 
                            class="bg-red-100 px-3 py-2 rounded-md text-sm font-medium text-red-800 hover:bg-red-200">
                        Try again
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Content -->
    <div x-show="!loading && !error" class="space-y-6">
        <!-- Key Metrics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <template x-for="metric in keyMetrics" :key="metric.id">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-md flex items-center justify-center" 
                                     :class="metric.colorClass">
                                    <i :class="metric.icon" class="text-white"></i>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate" x-text="metric.label"></dt>
                                    <dd class="text-lg font-medium text-gray-900" x-text="metric.value"></dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="flex items-center text-sm" :class="metric.changeClass">
                                <i :class="metric.changeIcon" class="mr-1"></i>
                                <span x-text="metric.change"></span>
                                <span class="ml-1">vs last period</span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Project Status Distribution -->
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Project Status Distribution</h3>
                    <div class="flex space-x-2">
                        <button @click="toggleChartType('projectStatus', 'doughnut')" 
                                :class="chartTypes.projectStatus === 'doughnut' ? 'bg-blue-100 text-blue-600' : 'text-gray-400'"
                                class="p-1 rounded">
                            <i class="fas fa-circle"></i>
                        </button>
                        <button @click="toggleChartType('projectStatus', 'bar')" 
                                :class="chartTypes.projectStatus === 'bar' ? 'bg-blue-100 text-blue-600' : 'text-gray-400'"
                                class="p-1 rounded">
                            <i class="fas fa-chart-bar"></i>
                        </button>
                    </div>
                </div>
                <div class="h-64">
                    <canvas id="projectStatusChart"></canvas>
                </div>
            </div>

            <!-- Task Completion Trends -->
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Task Completion Trends</h3>
                    <div class="flex space-x-2">
                        <button @click="toggleChartType('taskTrends', 'line')" 
                                :class="chartTypes.taskTrends === 'line' ? 'bg-blue-100 text-blue-600' : 'text-gray-400'"
                                class="p-1 rounded">
                            <i class="fas fa-chart-line"></i>
                        </button>
                        <button @click="toggleChartType('taskTrends', 'bar')" 
                                :class="chartTypes.taskTrends === 'bar' ? 'bg-blue-100 text-blue-600' : 'text-gray-400'"
                                class="p-1 rounded">
                            <i class="fas fa-chart-bar"></i>
                        </button>
                    </div>
                </div>
                <div class="h-64">
                    <canvas id="taskTrendsChart"></canvas>
                </div>
            </div>

            <!-- Team Performance -->
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Team Performance</h3>
                    <div class="flex space-x-2">
                        <button @click="toggleChartType('teamPerformance', 'radar')" 
                                :class="chartTypes.teamPerformance === 'radar' ? 'bg-blue-100 text-blue-600' : 'text-gray-400'"
                                class="p-1 rounded">
                            <i class="fas fa-chart-area"></i>
                        </button>
                        <button @click="toggleChartType('teamPerformance', 'bar')" 
                                :class="chartTypes.teamPerformance === 'bar' ? 'bg-blue-100 text-blue-600' : 'text-gray-400'"
                                class="p-1 rounded">
                            <i class="fas fa-chart-bar"></i>
                        </button>
                    </div>
                </div>
                <div class="h-64">
                    <canvas id="teamPerformanceChart"></canvas>
                </div>
            </div>

            <!-- Budget vs Actual -->
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Budget vs Actual</h3>
                    <div class="flex space-x-2">
                        <button @click="toggleChartType('budget', 'bar')" 
                                :class="chartTypes.budget === 'bar' ? 'bg-blue-100 text-blue-600' : 'text-gray-400'"
                                class="p-1 rounded">
                            <i class="fas fa-chart-bar"></i>
                        </button>
                        <button @click="toggleChartType('budget', 'line')" 
                                :class="chartTypes.budget === 'line' ? 'bg-blue-100 text-blue-600' : 'text-gray-400'"
                                class="p-1 rounded">
                            <i class="fas fa-chart-line"></i>
                        </button>
                    </div>
                </div>
                <div class="h-64">
                    <canvas id="budgetChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Tables -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Top Performing Projects -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Top Performing Projects</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="project in topProjects" :key="project.id">
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900" x-text="project.name"></div>
                                        <div class="text-sm text-gray-500" x-text="project.client"></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                                                <div class="bg-blue-600 h-2 rounded-full" 
                                                     :style="`width: ${project.progress}%`"></div>
                                            </div>
                                            <span class="text-sm text-gray-900" x-text="project.progress + '%'"></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                              :class="getStatusClass(project.status)"
                                              x-text="project.status"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Team Member Performance -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Team Member Performance</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tasks</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completion</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="member in teamPerformance" :key="member.id">
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                                                <span class="text-sm font-medium text-gray-700" x-text="member.initials"></span>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900" x-text="member.name"></div>
                                                <div class="text-sm text-gray-500" x-text="member.role"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="member.totalTasks"></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                                                <div class="bg-green-600 h-2 rounded-full" 
                                                     :style="`width: ${member.completionRate}%`"></div>
                                            </div>
                                            <span class="text-sm text-gray-900" x-text="member.completionRate + '%'"></span>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('analyticsDashboard', () => ({
        loading: true,
        error: null,
        selectedPeriod: '30d',
        keyMetrics: [],
        topProjects: [],
        teamPerformance: [],
        charts: {},
        chartTypes: {
            projectStatus: 'doughnut',
            taskTrends: 'line',
            teamPerformance: 'radar',
            budget: 'bar'
        },

        async init() {
            await this.loadAnalytics();
            this.$nextTick(() => {
                this.initializeCharts();
            });
        },

        async loadAnalytics() {
            this.loading = true;
            this.error = null;
            
            try {
                const response = await fetch(`/api/v1/app/analytics?period=${this.selectedPeriod}`);
                const data = await response.json();
                
                if (data.success) {
                    this.keyMetrics = data.data.keyMetrics || [];
                    this.topProjects = data.data.topProjects || [];
                    this.teamPerformance = data.data.teamPerformance || [];
                    
                    // Update charts if they exist
                    this.updateCharts(data.data);
                } else {
                    this.error = data.error?.message || 'Failed to load analytics';
                }
            } catch (error) {
                console.error('Analytics loading error:', error);
                this.error = 'Failed to load analytics data';
            } finally {
                this.loading = false;
            }
        },

        initializeCharts() {
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js not loaded, using fallback');
                return;
            }

            this.createProjectStatusChart();
            this.createTaskTrendsChart();
            this.createTeamPerformanceChart();
            this.createBudgetChart();
        },

        createProjectStatusChart() {
            const ctx = document.getElementById('projectStatusChart');
            if (!ctx) return;

            if (this.charts.projectStatus) {
                this.charts.projectStatus.destroy();
            }

            this.charts.projectStatus = new Chart(ctx, {
                type: this.chartTypes.projectStatus,
                data: {
                    labels: ['Active', 'Completed', 'On Hold', 'Planning'],
                    datasets: [{
                        data: [45, 30, 15, 10],
                        backgroundColor: [
                            '#3B82F6',
                            '#10B981',
                            '#F59E0B',
                            '#6B7280'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        },

        createTaskTrendsChart() {
            const ctx = document.getElementById('taskTrendsChart');
            if (!ctx) return;

            if (this.charts.taskTrends) {
                this.charts.taskTrends.destroy();
            }

            this.charts.taskTrends = new Chart(ctx, {
                type: this.chartTypes.taskTrends,
                data: {
                    labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                    datasets: [{
                        label: 'Completed Tasks',
                        data: [12, 19, 15, 25],
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Created Tasks',
                        data: [8, 15, 12, 18],
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        createTeamPerformanceChart() {
            const ctx = document.getElementById('teamPerformanceChart');
            if (!ctx) return;

            if (this.charts.teamPerformance) {
                this.charts.teamPerformance.destroy();
            }

            this.charts.teamPerformance = new Chart(ctx, {
                type: this.chartTypes.teamPerformance,
                data: {
                    labels: ['Productivity', 'Quality', 'Collaboration', 'Innovation', 'Delivery'],
                    datasets: [{
                        label: 'Team Average',
                        data: [85, 92, 78, 88, 90],
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.2)',
                        pointBackgroundColor: '#3B82F6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        },

        createBudgetChart() {
            const ctx = document.getElementById('budgetChart');
            if (!ctx) return;

            if (this.charts.budget) {
                this.charts.budget.destroy();
            }

            this.charts.budget = new Chart(ctx, {
                type: this.chartTypes.budget,
                data: {
                    labels: ['Q1', 'Q2', 'Q3', 'Q4'],
                    datasets: [{
                        label: 'Budget',
                        data: [100000, 120000, 110000, 130000],
                        backgroundColor: 'rgba(59, 130, 246, 0.5)',
                        borderColor: '#3B82F6',
                        borderWidth: 2
                    }, {
                        label: 'Actual',
                        data: [95000, 115000, 108000, 125000],
                        backgroundColor: 'rgba(16, 185, 129, 0.5)',
                        borderColor: '#10B981',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        },

        toggleChartType(chartName, newType) {
            this.chartTypes[chartName] = newType;
            this.updateChart(chartName);
        },

        updateChart(chartName) {
            switch (chartName) {
                case 'projectStatus':
                    this.createProjectStatusChart();
                    break;
                case 'taskTrends':
                    this.createTaskTrendsChart();
                    break;
                case 'teamPerformance':
                    this.createTeamPerformanceChart();
                    break;
                case 'budget':
                    this.createBudgetChart();
                    break;
            }
        },

        updateCharts(data) {
            // Update chart data if needed
            if (data.chartData) {
                // Implementation for updating chart data
            }
        },

        getStatusClass(status) {
            const classes = {
                'active': 'bg-green-100 text-green-800',
                'completed': 'bg-blue-100 text-blue-800',
                'on_hold': 'bg-yellow-100 text-yellow-800',
                'planning': 'bg-gray-100 text-gray-800'
            };
            return classes[status] || 'bg-gray-100 text-gray-800';
        },

        async exportReport(format) {
            try {
                const response = await fetch('/api/v1/app/reporting/export', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        type: 'dashboard',
                        format: format,
                        period: this.selectedPeriod
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    window.open(data.data.download_url, '_blank');
                } else {
                    console.error('Export failed:', data.error);
                }
            } catch (error) {
                console.error('Export error:', error);
            }
        }
    }));
});
</script>
