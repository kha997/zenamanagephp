<?php $__env->startSection('title', 'Tenant Analytics Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div class="tenants-analytics-container p-6" x-data="tenantAnalytics()" x-init="init()">
    <!-- Page Header -->
    <div class="page-header mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Tenant Analytics Dashboard</h1>
                <p class="text-gray-600 mt-1">Comprehensive insights into tenant growth, usage, and performance</p>
            </div>
            <div class="flex items-center space-x-3">
                <select x-model="selectedPeriod" @change="loadAnalytics()" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="7d">Last 7 days</option>
                    <option value="30d">Last 30 days</option>
                    <option value="90d">Last 90 days</option>
                    <option value="1y">Last year</option>
                </select>
                <button @click="exportAnalytics()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-download mr-2"></i>Export Report
                </button>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="flex items-center justify-center py-12">
        <div class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
            <p class="text-gray-600 mt-4">Loading analytics data...</p>
        </div>
    </div>

    <!-- Analytics Content -->
    <div x-show="!loading" class="space-y-6">
        <!-- Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Tenants</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="analytics?.overview?.total_tenants || 0"></p>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-full">
                        <i class="fas fa-building text-blue-600"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-sm text-green-600" x-text="`+${analytics?.overview?.new_tenants || 0} new`"></span>
                    <span class="text-sm text-gray-500 ml-2">this period</span>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Active Tenants</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="analytics?.overview?.active_tenants || 0"></p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-sm text-gray-600" x-text="`${Math.round((analytics?.overview?.active_tenants / analytics?.overview?.total_tenants) * 100) || 0}%`"></span>
                    <span class="text-sm text-gray-500 ml-2">of total</span>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Users</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="analytics?.overview?.total_users || 0"></p>
                    </div>
                    <div class="p-3 bg-purple-100 rounded-full">
                        <i class="fas fa-users text-purple-600"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-sm text-gray-600" x-text="`${analytics?.overview?.user_engagement_rate || 0}%`"></span>
                    <span class="text-sm text-gray-500 ml-2">engagement</span>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Health Score</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="`${analytics?.overview?.tenant_health_score || 0}%`"></p>
                    </div>
                    <div class="p-3 bg-yellow-100 rounded-full">
                        <i class="fas fa-heartbeat text-yellow-600"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-yellow-600 h-2 rounded-full" :style="`width: ${analytics?.overview?.tenant_health_score || 0}%`"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Growth Chart -->
            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Tenant Growth</h3>
                <div class="h-64">
                    <canvas id="growth-chart"></canvas>
                </div>
            </div>

            <!-- Plan Distribution Chart -->
            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Plan Distribution</h3>
                <div class="h-64">
                    <canvas id="plan-distribution-chart"></canvas>
                </div>
            </div>
        </div>

        <!-- Usage Analytics -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Tenants by Usage</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Projects</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Storage</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="tenant in analytics?.usage?.top_tenants || []" :key="tenant.id">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900" x-text="tenant.name"></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                          :class="tenant.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                                          x-text="tenant.status"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="tenant.active_users"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="tenant.active_projects"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="formatStorage(tenant.storage_used)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Revenue Analytics -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Monthly Recurring Revenue</h3>
                <div class="text-center">
                    <p class="text-3xl font-bold text-green-600" x-text="`$${analytics?.revenue?.total_mrr || 0}`"></p>
                    <p class="text-sm text-gray-600 mt-2">Total MRR</p>
                    <p class="text-sm text-gray-500" x-text="`$${analytics?.revenue?.average_revenue_per_tenant || 0} avg per tenant`"></p>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Churn Analysis</h3>
                <div class="text-center">
                    <p class="text-3xl font-bold text-red-600" x-text="`${analytics?.churn?.churn_rate || 0}%`"></p>
                    <p class="text-sm text-gray-600 mt-2">Churn Rate</p>
                    <p class="text-sm text-gray-500" x-text="`${analytics?.churn?.churned_count || 0} tenants churned`"></p>
                </div>
            </div>
        </div>

        <!-- Geographic Distribution -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Geographic Distribution</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <template x-for="region in analytics?.geographic?.regions || []" :key="region.region">
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-sm font-medium text-gray-900" x-text="region.region"></span>
                        <span class="text-sm text-gray-600" x-text="region.count"></span>
                    </div>
                </template>
            </div>
        </div>

        <!-- Activity Trends -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Activity Trends</h3>
            <div class="h-64">
                <canvas id="activity-trends-chart"></canvas>
            </div>
        </div>

        <!-- Performance Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <h4 class="text-sm font-medium text-gray-600 mb-2">Average Tenant Age</h4>
                <p class="text-2xl font-bold text-gray-900" x-text="`${Math.round(analytics?.performance_metrics?.average_tenant_age || 0)} days`"></p>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <h4 class="text-sm font-medium text-gray-600 mb-2">User Retention Rate</h4>
                <p class="text-2xl font-bold text-gray-900" x-text="`${analytics?.performance_metrics?.user_retention_rate || 0}%`"></p>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <h4 class="text-sm font-medium text-gray-600 mb-2">Project Completion Rate</h4>
                <p class="text-2xl font-bold text-gray-900" x-text="`${analytics?.performance_metrics?.project_completion_rate || 0}%`"></p>
            </div>
        </div>
    </div>
</div>

<script>
function tenantAnalytics() {
    return {
        loading: true,
        selectedPeriod: '30d',
        analytics: null,
        charts: {},

        async init() {
            await this.loadAnalytics();
        },

        async loadAnalytics() {
            this.loading = true;
            try {
                const response = await fetch(`/api/admin/tenants/analytics?period=${this.selectedPeriod}`, {
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${this.getAuthToken()}`,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to load analytics');
                }

                const result = await response.json();
                this.analytics = result.data;
                
                // Initialize charts after data is loaded
                this.$nextTick(() => {
                    this.initializeCharts();
                });

            } catch (error) {
                console.error('Analytics loading failed:', error);
                this.showToast('Failed to load analytics data', 'error');
            } finally {
                this.loading = false;
            }
        },

        initializeCharts() {
            this.initializeGrowthChart();
            this.initializePlanDistributionChart();
            this.initializeActivityTrendsChart();
        },

        initializeGrowthChart() {
            const ctx = document.getElementById('growth-chart');
            if (!ctx || !this.analytics?.growth?.data) return;

            // Destroy existing chart
            if (this.charts.growth) {
                this.charts.growth.destroy();
            }

            const data = this.analytics.growth.data;
            this.charts.growth = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(item => item.date),
                    datasets: [{
                        label: 'New Tenants',
                        data: data.map(item => item.new_tenants),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Cumulative',
                        data: data.map(item => item.cumulative),
                        borderColor: 'rgb(16, 185, 129)',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        initializePlanDistributionChart() {
            const ctx = document.getElementById('plan-distribution-chart');
            if (!ctx || !this.analytics?.plan_distribution?.plans) return;

            // Destroy existing chart
            if (this.charts.planDistribution) {
                this.charts.planDistribution.destroy();
            }

            const data = this.analytics.plan_distribution.plans;
            this.charts.planDistribution = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(item => item.plan),
                    datasets: [{
                        data: data.map(item => item.count),
                        backgroundColor: [
                            'rgb(239, 68, 68)',
                            'rgb(59, 130, 246)',
                            'rgb(16, 185, 129)'
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
        },

        initializeActivityTrendsChart() {
            const ctx = document.getElementById('activity-trends-chart');
            if (!ctx || !this.analytics?.activity_trends?.data) return;

            // Destroy existing chart
            if (this.charts.activityTrends) {
                this.charts.activityTrends.destroy();
            }

            const data = this.analytics.activity_trends.data;
            this.charts.activityTrends = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(item => item.date),
                    datasets: [{
                        label: 'New Tenants',
                        data: data.map(item => item.new_tenants),
                        backgroundColor: 'rgba(59, 130, 246, 0.8)'
                    }, {
                        label: 'New Users',
                        data: data.map(item => item.new_users),
                        backgroundColor: 'rgba(16, 185, 129, 0.8)'
                    }, {
                        label: 'New Projects',
                        data: data.map(item => item.new_projects),
                        backgroundColor: 'rgba(245, 158, 11, 0.8)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        async exportAnalytics() {
            try {
                const response = await fetch('/api/admin/tenants/analytics/export', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${this.getAuthToken()}`,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        period: this.selectedPeriod,
                        format: 'pdf'
                    })
                });

                if (!response.ok) {
                    throw new Error('Export failed');
                }

                const result = await response.json();
                if (result.download_url) {
                    window.open(result.download_url, '_blank');
                }
                this.showToast('Analytics report exported successfully', 'success');

            } catch (error) {
                console.error('Export failed:', error);
                this.showToast('Failed to export analytics report', 'error');
            }
        },

        formatStorage(bytes) {
            if (!bytes) return '0 MB';
            const mb = bytes / 1024 / 1024;
            return `${mb.toFixed(2)} MB`;
        },

        getAuthToken() {
            const token = document.querySelector('meta[name="api-token"]')?.content || 
                         localStorage.getItem('auth_token') || 
                         '5|uGddv7wdYNtoCu9RACfpytV7LrLQQODBdvi4PBce2f517aac';
            return token;
        },

        showToast(message, type = 'info') {
            // Use existing toast system
            if (window.Tenants && typeof window.Tenants.showToast === 'function') {
                window.Tenants.showToast(message, type);
            } else {
                console.log(`${type.toUpperCase()}: ${message}`);
            }
        }
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/tenants/analytics.blade.php ENDPATH**/ ?>