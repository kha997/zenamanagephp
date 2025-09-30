<?php $__env->startSection('title', 'Dashboard'); ?>

<?php $__env->startSection('breadcrumb'); ?>
<li class="flex items-center">
    <i class="fas fa-chevron-right text-gray-400 mr-2"></i>
    <span class="text-gray-900">Dashboard</span>
</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-6" x-data="adminDashboard()" x-init="init()">
    
    <div class="flex items-center justify-between">
        <div>
            <nav class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                <span class="text-gray-900">Dashboard</span>
                <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                <span class="text-gray-500">Overview</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
            <p class="text-gray-600">System overview and key metrics</p>
        </div>
        <div class="flex items-center space-x-3">
            <span class="text-xs text-gray-500">Last updated: <span x-text="lastRefresh"></span></span>
            <button @click="refreshData()" 
                    :disabled="isLoading"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
                <i :class="isLoading ? 'fas fa-spinner fa-spin' : 'fas fa-sync-alt'" class="mr-2"></i>
                Refresh
            </button>
        </div>
    </div>

    
    <div x-show="isLoading" class="flex items-center justify-center py-12">
        <div class="text-center">
            <i class="fas fa-spinner fa-spin text-3xl text-blue-600 mb-4"></i>
            <p class="text-gray-600">Loading dashboard data...</p>
        </div>
    </div>

    
    <div x-show="!isLoading" class="space-y-6">
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 lg:gap-6">
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Tenants</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="kpis.tenants?.total || 0">0</p>
                        <p class="text-sm" :class="(kpis.tenants?.growth_rate || 0) >= 0 ? 'text-green-600' : 'text-red-600'">
                            <i :class="(kpis.tenants?.growth_rate || 0) >= 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'" class="mr-1"></i>
                            <span x-text="Math.abs(kpis.tenants?.growth_rate || 0) + '%'">0%</span> from last month
                        </p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-3">
                        <i class="fas fa-building text-blue-600 text-xl"></i>
                    </div>
                </div>
                <div class="h-8">
                    <canvas id="tenants-sparkline" class="w-full h-full"></canvas>
                </div>
            </div>

            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Users</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="kpis.users?.total || 0">0</p>
                        <p class="text-sm" :class="(kpis.users?.growth_rate || 0) >= 0 ? 'text-green-600' : 'text-red-600'">
                            <i :class="(kpis.users?.growth_rate || 0) >= 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'" class="mr-1"></i>
                            <span x-text="Math.abs(kpis.users?.growth_rate || 0) + '%'">0%</span> from last month
                        </p>
                    </div>
                    <div class="bg-green-100 rounded-full p-3">
                        <i class="fas fa-users text-green-600 text-xl"></i>
                    </div>
                </div>
                <div class="h-8">
                    <canvas id="users-sparkline" class="w-full h-full"></canvas>
                </div>
            </div>

            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Errors (24h)</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="kpis.errors?.last_24h || 0">0</p>
                        <p class="text-sm" :class="(kpis.errors?.change_from_yesterday || 0) >= 0 ? 'text-red-600' : 'text-green-600'">
                            <i :class="(kpis.errors?.change_from_yesterday || 0) >= 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'" class="mr-1"></i>
                            <span x-text="Math.abs(kpis.errors?.change_from_yesterday || 0)">0</span> from yesterday
                        </p>
                    </div>
                    <div class="bg-red-100 rounded-full p-3">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                </div>
                <div class="h-8">
                    <canvas id="errors-sparkline" class="w-full h-full"></canvas>
                </div>
            </div>

            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Queue Jobs</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="kpis.queue?.active_jobs || 0">0</p>
                        <p class="text-sm text-gray-600">
                            <i class="fas fa-clock mr-1"></i>
                            <span x-text="kpis.queue?.status || 'Unknown'">Unknown</span>
                        </p>
                    </div>
                    <div class="bg-yellow-100 rounded-full p-3">
                        <i class="fas fa-tasks text-yellow-600 text-xl"></i>
                    </div>
                </div>
                <div class="h-8">
                    <canvas id="queue-sparkline" class="w-full h-full"></canvas>
                </div>
            </div>

            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Storage Used</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="formatBytes(kpis.storage?.used_bytes || 0)">0 B</p>
                        <p class="text-sm text-gray-600">
                            <i class="fas fa-database mr-1"></i>
                            <span x-text="Math.round(((kpis.storage?.used_bytes || 0) / (kpis.storage?.capacity_bytes || 1)) * 100)">0</span>% of <span x-text="formatBytes(kpis.storage?.capacity_bytes || 0)">0 B</span>
                        </p>
                    </div>
                    <div class="bg-purple-100 rounded-full p-3">
                        <i class="fas fa-database text-purple-600 text-xl"></i>
                    </div>
                </div>
                <div class="h-8">
                    <canvas id="storage-sparkline" class="w-full h-full"></canvas>
                </div>
            </div>
        </div>

        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">New Signups</h3>
                    <div class="flex items-center space-x-2">
                        <select x-model="chartRange" @change="updateCharts()" class="text-sm border border-gray-300 rounded-md px-3 py-1">
                            <option value="7d">Last 7 days</option>
                            <option value="30d">Last 30 days</option>
                            <option value="90d">Last 90 days</option>
                        </select>
                        <button @click="exportSignups()" class="px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors">
                            <i class="fas fa-download mr-1"></i>Export
                        </button>
                    </div>
                </div>
                <div class="chart-container" style="height: 280px; position: relative;">
                    <canvas id="signups-chart" width="400" height="280" aria-label="New signups chart"></canvas>
                </div>
            </div>

            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Error Rate</h3>
                    <div class="flex items-center space-x-2">
                        <select x-model="chartRange" @change="updateCharts()" class="text-sm border border-gray-300 rounded-md px-3 py-1">
                            <option value="7d">Last 7 days</option>
                            <option value="30d">Last 30 days</option>
                            <option value="90d">Last 90 days</option>
                        </select>
                        <button @click="exportErrors()" class="px-3 py-1 bg-red-600 text-white text-sm rounded-md hover:bg-red-700 transition-colors">
                            <i class="fas fa-download mr-1"></i>Export
                        </button>
                    </div>
                </div>
                <div class="chart-container" style="height: 280px; position: relative;">
                    <canvas id="errors-chart" width="400" height="280" aria-label="Error rate chart"></canvas>
                </div>
            </div>
        </div>

        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
                <a href="/admin/activity" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
            </div>
            <div class="space-y-3">
                <template x-for="activity in activities" :key="activity.id">
                    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                        <div class="flex-shrink-0">
                            <i :class="getActivityIcon(activity.severity)" class="text-lg"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm text-gray-900" x-text="activity.message"></p>
                            <p class="text-xs text-gray-500" x-text="activity.time_ago"></p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
function adminDashboard() {
    return {
        isLoading: true,
        lastRefresh: '',
        chartRange: '30d',
        kpis: {
            tenants: null,
            users: null,
            errors: null,
            queue: null,
            storage: null
        },
        charts: {
            signups: null,
            errors: null
        },
        sparklines: {
            tenants: null,
            users: null,
            errors: null,
            queue: null,
            storage: null
        },
        activities: [],

        async init() {
            this.initializeCharts();
            this.initializeSparklines();
            await this.loadDashboardData();
        },

        async loadDashboardData() {
            this.isLoading = true;
            try {
                // Load KPI data
                const kpiResponse = await fetch('/api/admin/dashboard/summary?range=' + this.chartRange);
                if (kpiResponse.ok) {
                    this.kpis = await kpiResponse.json();
                }

                // Load chart data
                const chartResponse = await fetch('/api/admin/dashboard/charts?range=' + this.chartRange);
                if (chartResponse.ok) {
                    const chartData = await chartResponse.json();
                    this.updateChartsData(chartData);
                }

                // Load activities
                const activityResponse = await fetch('/api/admin/dashboard/activity');
                if (activityResponse.ok) {
                    const activityData = await activityResponse.json();
                    this.activities = activityData.items || [];
                }

                this.lastRefresh = new Date().toLocaleTimeString();
            } catch (error) {
                console.error('Failed to load dashboard data:', error);
            } finally {
                this.isLoading = false;
            }
        },

        async refreshData() {
            await this.loadDashboardData();
            this.updateSparklines();
        },

        initializeCharts() {
            // Wait for Chart.js to load
            const initCharts = () => {
                if (typeof Chart === 'undefined') {
                    console.log('Chart.js not ready, retrying...');
                    setTimeout(initCharts, 100);
                    return;
                }

                // Destroy existing charts if they exist
                if (this.charts.signups) {
                    this.charts.signups.destroy();
                    this.charts.signups = null;
                }
                if (this.charts.errors) {
                    this.charts.errors.destroy();
                    this.charts.errors = null;
                }
                
                // Also destroy any existing Chart.js instances on the canvas
                const signupsCtx = document.getElementById('signups-chart');
                const errorsCtx = document.getElementById('errors-chart');
                
                if (signupsCtx) {
                    const existingChart = Chart.getChart(signupsCtx);
                    if (existingChart) {
                        existingChart.destroy();
                    }
                }
                
                if (errorsCtx) {
                    const existingChart = Chart.getChart(errorsCtx);
                    if (existingChart) {
                        existingChart.destroy();
                    }
                }

                if (signupsCtx) {
                    this.charts.signups = new Chart(signupsCtx, {
                        type: 'line',
                        data: { labels: [], datasets: [] },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: {
                                duration: 0 // Disable animation for faster updates
                            },
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                x: {
                                    display: true,
                                    grid: {
                                        display: true
                                    }
                                },
                                y: { 
                                    beginAtZero: true,
                                    grid: {
                                        display: true
                                    }
                                }
                            },
                            interaction: {
                                intersect: false,
                                mode: 'index'
                            }
                        }
                    });
                    console.log('Signups chart initialized');
                }

                if (errorsCtx) {
                    this.charts.errors = new Chart(errorsCtx, {
                        type: 'bar',
                        data: { labels: [], datasets: [] },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: { duration: 0 },
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                x: {
                                    display: true,
                                    grid: {
                                        display: true
                                    }
                                },
                                y: { 
                                    beginAtZero: true,
                                    max: 5,
                                    grid: {
                                        display: true
                                    }
                                }
                            }
                        }
                    });
                    console.log('Errors chart initialized');
                }
            };

            initCharts();
        },

        initializeSparklines() {
            // Wait for Chart.js to load
            const initSparklines = () => {
                if (typeof Chart === 'undefined') {
                    console.log('Chart.js not ready for sparklines, retrying...');
                    setTimeout(initSparklines, 100);
                    return;
                }

                // Destroy existing sparklines if they exist
                const sparklineIds = ['tenants', 'users', 'errors', 'queue', 'storage'];
                sparklineIds.forEach(id => {
                    if (this.sparklines[id]) {
                        this.sparklines[id].destroy();
                        this.sparklines[id] = null;
                    }
                });

                // Initialize sparkline charts
                sparklineIds.forEach(id => {
                    const canvas = document.getElementById(id + '-sparkline');
                    if (canvas) {
                        this.sparklines[id] = new Chart(canvas, {
                            type: 'line',
                            data: {
                                labels: [],
                                datasets: [{
                                    data: [],
                                    borderColor: this.getSparklineColor(id),
                                    backgroundColor: this.getSparklineColor(id, 0.1),
                                    borderWidth: 2,
                                    pointRadius: 0,
                                    tension: 0.4
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: {
                                    x: { display: false },
                                    y: { display: false }
                                }
                            }
                        });
                        console.log(`${id} sparkline initialized`);
                    }
                });
            };

            initSparklines();
        },

        updateChartsData(chartData) {
            try {
                // Store chart data in component state
                this.chartData = chartData;
                
                // Use requestAnimationFrame to avoid Alpine.js reactivity issues
                requestAnimationFrame(() => {
                    // Get chart instances directly from DOM to avoid Alpine.js proxy
                    const signupsCanvas = document.getElementById('signups-chart');
                    const errorsCanvas = document.getElementById('errors-chart');
                    
                    if (signupsCanvas && chartData.signups) {
                        // Get Chart.js instance directly from canvas
                        const chartInstance = Chart.getChart(signupsCanvas);
                        if (chartInstance) {
                            chartInstance.data.labels = chartData.signups.labels || [];
                            chartInstance.data.datasets = chartData.signups.datasets || [];
                            chartInstance.update('none');
                        }
                    }

                    if (errorsCanvas && chartData.error_rate) {
                        // Get Chart.js instance directly from canvas
                        const chartInstance = Chart.getChart(errorsCanvas);
                        if (chartInstance) {
                            chartInstance.data.labels = chartData.error_rate.labels || [];
                            chartInstance.data.datasets = chartData.error_rate.datasets || [];
                            
                            // Ensure bar chart has proper styling
                            if (chartInstance.data.datasets[0]) {
                                chartInstance.data.datasets[0].backgroundColor = 'rgba(239, 68, 68, 0.8)';
                                chartInstance.data.datasets[0].borderColor = '#EF4444';
                                chartInstance.data.datasets[0].borderWidth = 1;
                            }
                            
                            chartInstance.update('none');
                        }
                    }
                });
            } catch (error) {
                console.error('Error updating charts:', error);
            }
        },

        updateSparklines() {
            try {
                // Use requestAnimationFrame to avoid Alpine.js reactivity issues
                requestAnimationFrame(() => {
                    const sparklineIds = ['tenants', 'users', 'errors', 'queue', 'storage'];
                    
                    sparklineIds.forEach(id => {
                        const canvas = document.getElementById(id + '-sparkline');
                        if (canvas && this.kpis[id]?.sparkline) {
                            // Get Chart.js instance directly from canvas
                            const chartInstance = Chart.getChart(canvas);
                            if (chartInstance) {
                                chartInstance.data.datasets[0].data = [...this.kpis[id].sparkline];
                                chartInstance.update('none');
                            }
                        }
                    });
                });
            } catch (error) {
                console.error('Error updating sparklines:', error);
            }
        },

        async updateCharts() {
            await this.loadDashboardData();
        },

        async exportSignups() {
            try {
                const response = await fetch(`/api/admin/dashboard/signups/export.csv?range=${this.chartRange}`);
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `signups_${this.chartRange}_${new Date().toISOString().split('T')[0]}.csv`;
                    a.click();
                    window.URL.revokeObjectURL(url);
                }
            } catch (error) {
                console.error('Export failed:', error);
            }
        },

        async exportErrors() {
            try {
                const response = await fetch(`/api/admin/dashboard/errors/export.csv?range=${this.chartRange}`);
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `errors_${this.chartRange}_${new Date().toISOString().split('T')[0]}.csv`;
                    a.click();
                    window.URL.revokeObjectURL(url);
                }
            } catch (error) {
                console.error('Export failed:', error);
            }
        },

        getSparklineColor(id, alpha = 1) {
            const colors = {
                tenants: `rgba(59, 130, 246, ${alpha})`,
                users: `rgba(16, 185, 129, ${alpha})`,
                errors: `rgba(239, 68, 68, ${alpha})`,
                queue: `rgba(245, 158, 11, ${alpha})`,
                storage: `rgba(139, 92, 246, ${alpha})`
            };
            return colors[id] || `rgba(107, 114, 128, ${alpha})`;
        },

        getActivityIcon(severity) {
            const icons = {
                info: 'fas fa-info-circle text-blue-600',
                warning: 'fas fa-exclamation-triangle text-yellow-600',
                error: 'fas fa-times-circle text-red-600',
                success: 'fas fa-check-circle text-green-600'
            };
            return icons[severity] || 'fas fa-circle text-gray-600';
        },

        formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    };
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/dashboard/index.blade.php ENDPATH**/ ?>