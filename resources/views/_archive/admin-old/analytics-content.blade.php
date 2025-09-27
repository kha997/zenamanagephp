<!-- Admin Analytics Content -->
<div x-data="adminAnalytics()" x-init="init()" class="space-y-6">
    <!-- Loading State -->
    <div x-show="loading" class="flex justify-center items-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span class="ml-2 text-gray-600">Loading analytics...</span>
    </div>

    <!-- Main Content -->
    <div x-show="!loading" class="space-y-6">
        
        <!-- Header Section -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Advanced Analytics & Reporting</h1>
                    <p class="text-gray-600 mt-1">Comprehensive system analytics and performance insights</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700">Time Range:</label>
                        <select x-model="timeRange" @change="refreshData()" 
                                class="border border-gray-300 rounded-md px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="24h">Last 24 Hours</option>
                            <option value="7d">Last 7 Days</option>
                            <option value="30d">Last 30 Days</option>
                            <option value="90d">Last 90 Days</option>
                        </select>
                    </div>
                    <button @click="refreshData()" 
                            :disabled="refreshing"
                            class="flex items-center space-x-2 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 disabled:opacity-50">
                        <i class="fas fa-sync-alt" :class="{'animate-spin': refreshing}"></i>
                        <span>Refresh</span>
                    </button>
                    <button @click="exportReport()" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-download mr-2"></i>Export Report
                    </button>
                </div>
            </div>

            <!-- Analytics Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-chart-line text-blue-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-blue-600">Total Requests</p>
                            <p class="text-2xl font-bold text-blue-900" x-text="stats.totalRequests || '0'"></p>
                            <p class="text-xs text-blue-700" x-text="`${stats.requestGrowth || 0}% vs last period`"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-r from-green-50 to-green-100 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-green-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-600">Active Users</p>
                            <p class="text-2xl font-bold text-green-900" x-text="stats.activeUsers || '0'"></p>
                            <p class="text-xs text-green-700" x-text="`${stats.userGrowth || 0}% vs last period`"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-building text-purple-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-purple-600">Tenant Activity</p>
                            <p class="text-2xl font-bold text-purple-900" x-text="stats.tenantActivity || '0'"></p>
                            <p class="text-xs text-purple-700" x-text="`${stats.tenantGrowth || 0}% vs last period`"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-r from-orange-50 to-orange-100 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clock text-orange-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-orange-600">Avg Response Time</p>
                            <p class="text-2xl font-bold text-orange-900" x-text="stats.avgResponseTime || '0ms'"></p>
                            <p class="text-xs text-orange-700" x-text="`${stats.responseGrowth || 0}% vs last period`"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Performance Trends -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Performance Trends</h3>
                    <div class="flex items-center space-x-2">
                        <button @click="toggleChart('performance')" 
                                class="text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas fa-expand"></i>
                        </button>
                    </div>
                </div>
                <div class="h-64">
                    <canvas id="performanceChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- User Activity -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">User Activity</h3>
                    <div class="flex items-center space-x-2">
                        <button @click="toggleChart('users')" 
                                class="text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas fa-expand"></i>
                        </button>
                    </div>
                </div>
                <div class="h-64">
                    <canvas id="userActivityChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Tenant Distribution -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Tenant Distribution</h3>
                    <div class="flex items-center space-x-2">
                        <button @click="toggleChart('tenants')" 
                                class="text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas fa-expand"></i>
                        </button>
                    </div>
                </div>
                <div class="h-64">
                    <canvas id="tenantChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Error Analysis -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Error Analysis</h3>
                    <div class="flex items-center space-x-2">
                        <button @click="toggleChart('errors')" 
                                class="text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas fa-expand"></i>
                        </button>
                    </div>
                </div>
                <div class="h-64">
                    <canvas id="errorChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Reports -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Top Tenants -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Top Tenants</h3>
                    <button @click="viewTenantDetails()" class="text-sm text-blue-600 hover:text-blue-800">
                        View All
                    </button>
                </div>
                <div class="space-y-3">
                    <template x-for="tenant in topTenants" :key="tenant.id">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-building text-blue-600 text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900" x-text="tenant.name"></p>
                                    <p class="text-xs text-gray-500" x-text="tenant.users + ' users'"></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-900" x-text="tenant.requests"></p>
                                <p class="text-xs text-gray-500">requests</p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- API Endpoints -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">API Endpoints</h3>
                    <button @click="viewApiDetails()" class="text-sm text-blue-600 hover:text-blue-800">
                        View All
                    </button>
                </div>
                <div class="space-y-3">
                    <template x-for="endpoint in topEndpoints" :key="endpoint.path">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                     :class="getEndpointColor(endpoint.method)">
                                    <span class="text-xs font-bold text-white" x-text="endpoint.method"></span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900" x-text="endpoint.path"></p>
                                    <p class="text-xs text-gray-500" x-text="endpoint.avgTime + 'ms avg'"></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-900" x-text="endpoint.requests"></p>
                                <p class="text-xs text-gray-500">requests</p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- System Health -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">System Health</h3>
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                        Healthy
                    </span>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">CPU Usage</span>
                        <div class="flex items-center space-x-2">
                            <div class="w-20 bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: 45%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900">45%</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Memory Usage</span>
                        <div class="flex items-center space-x-2">
                            <div class="w-20 bg-gray-200 rounded-full h-2">
                                <div class="bg-yellow-500 h-2 rounded-full" style="width: 68%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900">68%</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Disk Usage</span>
                        <div class="flex items-center space-x-2">
                            <div class="w-20 bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-500 h-2 rounded-full" style="width: 32%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900">32%</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Network I/O</span>
                        <div class="flex items-center space-x-2">
                            <div class="w-20 bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: 25%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900">25%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Options -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Export Reports</h3>
                <div class="flex items-center space-x-2">
                    <button @click="exportPDF()" 
                            class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                        <i class="fas fa-file-pdf mr-2"></i>Export PDF
                    </button>
                    <button @click="exportExcel()" 
                            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-file-excel mr-2"></i>Export Excel
                    </button>
                    <button @click="exportCSV()" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-file-csv mr-2"></i>Export CSV
                    </button>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 border border-gray-200 rounded-lg">
                    <h4 class="font-medium text-gray-900 mb-2">Performance Report</h4>
                    <p class="text-sm text-gray-600 mb-3">Detailed performance metrics and trends</p>
                    <button @click="exportPerformanceReport()" 
                            class="text-sm text-blue-600 hover:text-blue-800">Generate Report</button>
                </div>
                <div class="p-4 border border-gray-200 rounded-lg">
                    <h4 class="font-medium text-gray-900 mb-2">User Activity Report</h4>
                    <p class="text-sm text-gray-600 mb-3">User engagement and activity patterns</p>
                    <button @click="exportUserReport()" 
                            class="text-sm text-blue-600 hover:text-blue-800">Generate Report</button>
                </div>
                <div class="p-4 border border-gray-200 rounded-lg">
                    <h4 class="font-medium text-gray-900 mb-2">System Health Report</h4>
                    <p class="text-sm text-gray-600 mb-3">System performance and health metrics</p>
                    <button @click="exportHealthReport()" 
                            class="text-sm text-blue-600 hover:text-blue-800">Generate Report</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function adminAnalytics() {
    return {
        loading: true,
        refreshing: false,
        timeRange: '7d',
        
        // Data
        stats: {
            totalRequests: 0,
            requestGrowth: 0,
            activeUsers: 0,
            userGrowth: 0,
            tenantActivity: 0,
            tenantGrowth: 0,
            avgResponseTime: '0ms',
            responseGrowth: 0
        },
        
        topTenants: [],
        topEndpoints: [],
        
        // Charts
        charts: {},

        async init() {
            await this.loadAnalyticsData();
            this.setupCharts();
        },

        async loadAnalyticsData() {
            try {
                this.loading = true;
                
                // Mock data for demonstration
                await new Promise(resolve => setTimeout(resolve, 1000));
                
                this.stats = {
                    totalRequests: 125430,
                    requestGrowth: 12.5,
                    activeUsers: 2847,
                    userGrowth: 8.3,
                    tenantActivity: 156,
                    tenantGrowth: 15.2,
                    avgResponseTime: '245ms',
                    responseGrowth: -5.2
                };
                
                this.topTenants = [
                    { id: 1, name: 'Acme Corp', users: 45, requests: 12500 },
                    { id: 2, name: 'TechStart Inc', users: 32, requests: 8900 },
                    { id: 3, name: 'Global Solutions', users: 28, requests: 7200 },
                    { id: 4, name: 'Innovation Labs', users: 24, requests: 6800 },
                    { id: 5, name: 'Future Systems', users: 19, requests: 5400 }
                ];
                
                this.topEndpoints = [
                    { path: '/api/v1/users', method: 'GET', requests: 15420, avgTime: '120ms' },
                    { path: '/api/v1/projects', method: 'GET', requests: 12300, avgTime: '180ms' },
                    { path: '/api/v1/tasks', method: 'POST', requests: 8900, avgTime: '95ms' },
                    { path: '/api/v1/auth/login', method: 'POST', requests: 5600, avgTime: '75ms' },
                    { path: '/api/v1/tenants', method: 'GET', requests: 4200, avgTime: '200ms' }
                ];
                
                this.loading = false;
                
            } catch (error) {
                console.error('Error loading analytics data:', error);
                this.loading = false;
            }
        },

        setupCharts() {
            // Destroy existing charts first
            Object.values(this.charts).forEach(chart => {
                if (chart) chart.destroy();
            });
            this.charts = {};

            // Performance Chart
            const perfCtx = document.getElementById('performanceChart');
            if (perfCtx) {
                this.charts.performance = new Chart(perfCtx, {
                    type: 'line',
                    data: {
                        labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
                        datasets: [{
                            label: 'Response Time (ms)',
                            data: [180, 220, 195, 245, 210, 190],
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            // User Activity Chart
            const userCtx = document.getElementById('userActivityChart');
            if (userCtx) {
                this.charts.users = new Chart(userCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                        datasets: [{
                            label: 'Active Users',
                            data: [1200, 1350, 1100, 1450, 1600, 800, 600],
                            backgroundColor: 'rgba(34, 197, 94, 0.8)',
                            borderColor: 'rgb(34, 197, 94)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            // Tenant Chart
            const tenantCtx = document.getElementById('tenantChart');
            if (tenantCtx) {
                this.charts.tenants = new Chart(tenantCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Active', 'Inactive', 'Suspended'],
                        datasets: [{
                            data: [85, 12, 3],
                            backgroundColor: [
                                'rgba(34, 197, 94, 0.8)',
                                'rgba(251, 191, 36, 0.8)',
                                'rgba(239, 68, 68, 0.8)'
                            ]
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
            }

            // Error Chart
            const errorCtx = document.getElementById('errorChart');
            if (errorCtx) {
                this.charts.errors = new Chart(errorCtx, {
                    type: 'line',
                    data: {
                        labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
                        datasets: [{
                            label: 'Errors',
                            data: [2, 5, 3, 8, 4, 2],
                            borderColor: 'rgb(239, 68, 68)',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        },

        getEndpointColor(method) {
            const colors = {
                'GET': 'bg-green-500',
                'POST': 'bg-blue-500',
                'PUT': 'bg-yellow-500',
                'DELETE': 'bg-red-500',
                'PATCH': 'bg-purple-500'
            };
            return colors[method] || 'bg-gray-500';
        },

        toggleChart(chartName) {
            console.log('Toggle chart:', chartName);
            // Implement chart expansion logic
        },

        refreshData() {
            this.refreshing = true;
            setTimeout(() => {
                this.loadAnalyticsData();
                this.refreshing = false;
            }, 1000);
        },

        exportReport() {
            console.log('Exporting comprehensive report...');
            // Implement export logic
        },

        exportPDF() {
            console.log('Exporting PDF report...');
        },

        exportExcel() {
            console.log('Exporting Excel report...');
        },

        exportCSV() {
            console.log('Exporting CSV report...');
        },

        exportPerformanceReport() {
            console.log('Exporting performance report...');
        },

        exportUserReport() {
            console.log('Exporting user report...');
        },

        exportHealthReport() {
            console.log('Exporting health report...');
        },

        viewTenantDetails() {
            console.log('Viewing tenant details...');
        },

        viewApiDetails() {
            console.log('Viewing API details...');
        }
    }
}
</script>
