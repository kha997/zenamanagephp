<?php $__env->startSection('title', 'Billing Dashboard'); ?>

<?php $__env->startSection('breadcrumb'); ?>
<li class="flex items-center">
    <i class="fas fa-chevron-right text-gray-400 mr-2"></i>
    <span class="text-gray-900">Billing</span>
</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-6" x-data="billingDashboard()" x-init="init()">
    
    <div class="flex items-center justify-between">
        <div>
            <nav class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                <span class="text-gray-900">Billing</span>
                <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                <span class="text-gray-500">Overview</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900">Billing Dashboard</h1>
            <p class="text-gray-600">Revenue tracking, plans & subscriptions overview</p>
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

    
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Time Range</label>
                <select x-model="filters.range" @change="applyFilters()" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="this_month">This Month</option>
                    <option value="last_30d">Last 30 Days</option>
                    <option value="last_90d">Last 90 Days</option>
                    <option value="YTD">Year to Date</option>
                    <option value="last_12m">Last 12 Months</option>
                </select>
            </div>

            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Plan</label>
                <select x-model="filters.plan" @change="applyFilters()" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Plans</option>
                    <option value="basic">Basic</option>
                    <option value="professional">Professional</option>
                    <option value="enterprise">Enterprise</option>
                </select>
            </div>

            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Region</label>
                <select x-model="filters.region" @change="applyFilters()" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Regions</option>
                    <option value="us">United States</option>
                    <option value="eu">Europe</option>
                    <option value="asia">Asia</option>
                </select>
            </div>

            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                <select x-model="filters.currency" @change="applyFilters()" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                    <option value="GBP">GBP</option>
                </select>
            </div>

            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Grouping</label>
                <select x-model="filters.grouping" @change="applyFilters()" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="day">Day</option>
                    <option value="week">Week</option>
                    <option value="month">Month</option>
                </select>
            </div>
        </div>
    </div>

    
    <div x-show="isLoading" class="flex items-center justify-center py-12">
        <div class="text-center">
            <i class="fas fa-spinner fa-spin text-3xl text-blue-600 mb-4"></i>
            <p class="text-gray-600">Loading billing data...</p>
        </div>
    </div>
    
    
    <div x-show="error" class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle text-red-600 mr-3"></i>
            <div class="flex-1">
                <h3 class="text-sm font-medium text-red-800">Error loading billing data</h3>
                <p class="text-sm text-red-700 mt-1" x-text="error"></p>
                <div class="mt-2 text-xs text-red-600">
                    <span x-text="'Range: ' + filters.range"></span> • 
                    <span x-text="'Plan: ' + (filters.plan || 'All')"></span> • 
                    <span x-text="'Currency: ' + filters.currency"></span>
                </div>
            </div>
            <div class="flex space-x-2">
                <button @click="refreshData()" 
                        :disabled="isLoading"
                        class="px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700 disabled:opacity-50">
                    <i :class="isLoading ? 'fas fa-spinner fa-spin' : 'fas fa-redo'" class="mr-1"></i>
                    Retry
                </button>
                <a href="/admin" class="px-3 py-1 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">
                    <i class="fas fa-home mr-1"></i>
                    Dashboard
                </a>
            </div>
        </div>
    </div>

    
    <div x-show="!isLoading && !error" class="space-y-6">
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-lg hover:border-green-300 transition-all duration-200 cursor-pointer group"
                 @click="drillDownToInvoices()"
                 @keydown.enter="drillDownToInvoices()"
                 @keydown.space.prevent="drillDownToInvoices()"
                 tabindex="0"
                 role="button"
                 aria-label="View Monthly Revenue details"
                 title="Click to view paid invoices">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm font-medium text-gray-600 group-hover:text-green-600 transition-colors">Monthly Revenue</p>
                        <p class="text-2xl font-bold text-gray-900 group-hover:text-green-700 transition-colors" x-text="formatCurrency(overview?.kpi?.monthly_revenue?.value)">—</p>
                        <p class="text-sm" :class="(overview?.kpi?.monthly_revenue?.delta_pct_vs_last_month || 0) >= 0 ? 'text-green-600' : 'text-red-600'">
                            <i :class="(overview?.kpi?.monthly_revenue?.delta_pct_vs_last_month || 0) >= 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'" class="mr-1"></i>
                            <span x-text="Math.abs(overview?.kpi?.monthly_revenue?.delta_pct_vs_last_month || 0) + '%'">0%</span> from last month
                        </p>
                    </div>
                    <div class="bg-green-100 rounded-full p-3 group-hover:bg-green-200 transition-colors">
                        <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-2 text-xs text-gray-500 group-hover:text-green-500 transition-colors">
                    <i class="fas fa-external-link-alt mr-1"></i>Click to view paid invoices
                </div>
            </div>

            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-lg hover:border-blue-300 transition-all duration-200 cursor-pointer group"
                 @click="drillDownToSubscriptions()"
                 @keydown.enter="drillDownToSubscriptions()"
                 @keydown.space.prevent="drillDownToSubscriptions()"
                 tabindex="0"
                 role="button"
                 aria-label="View Active Subscriptions details"
                 title="Click to view active subscriptions">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm font-medium text-gray-600 group-hover:text-blue-600 transition-colors">Active Subscriptions</p>
                        <p class="text-2xl font-bold text-gray-900 group-hover:text-blue-700 transition-colors" x-text="overview?.kpi?.active_subscriptions?.value ?? '—'">—</p>
                        <p class="text-sm" :class="(overview?.kpi?.active_subscriptions?.delta_vs_last_month || 0) >= 0 ? 'text-green-600' : 'text-red-600'">
                            <i :class="(overview?.kpi?.active_subscriptions?.delta_vs_last_month || 0) >= 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'" class="mr-1"></i>
                            <span x-text="Math.abs(overview?.kpi?.active_subscriptions?.delta_vs_last_month || 0)">0</span> from last month
                        </p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-3 group-hover:bg-blue-200 transition-colors">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-2 text-xs text-gray-500 group-hover:text-blue-500 transition-colors">
                    <i class="fas fa-external-link-alt mr-1"></i>Click to view subscriptions
                </div>
        </div>
        
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-lg hover:border-red-300 transition-all duration-200 cursor-pointer group"
                 @click="drillDownToChurn()"
                 @keydown.enter="drillDownToChurn()"
                 @keydown.space.prevent="drillDownToChurn()"
                 tabindex="0"
                 role="button"
                 aria-label="View Churn Rate details"
                 title="Click to view canceled subscriptions">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm font-medium text-gray-600 group-hover:text-red-600 transition-colors">Churn Rate</p>
                        <p class="text-2xl font-bold text-gray-900 group-hover:text-red-700 transition-colors" x-text="overview?.kpi?.churn_rate?.value_pct ? (overview.kpi.churn_rate.value_pct + '%') : '—'">—</p>
                        <p class="text-sm text-gray-600">Monthly churn</p>
                    </div>
                    <div class="bg-red-100 rounded-full p-3 group-hover:bg-red-200 transition-colors">
                        <i class="fas fa-user-times text-red-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-2 text-xs text-gray-500 group-hover:text-red-500 transition-colors">
                    <i class="fas fa-external-link-alt mr-1"></i>Click to view canceled subscriptions
                </div>
            </div>
        </div>
        
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Plan Distribution</h3>
                <span class="text-sm text-gray-500">Active subscriptions by plan</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <template x-for="plan in overview?.plan_distribution || []" :key="plan.plan">
                    <div class="bg-gray-50 rounded-lg p-4 cursor-pointer hover:bg-gray-100 transition-colors"
                         @click="drillDownToPlan(plan.plan)"
                         @keydown.enter="drillDownToPlan(plan.plan)"
                         @keydown.space.prevent="drillDownToPlan(plan.plan)"
                         tabindex="0"
                         role="button"
                         :aria-label="`View ${plan.plan} plan subscriptions`">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium text-gray-900 capitalize" x-text="plan.plan"></h4>
                                <p class="text-2xl font-bold text-blue-600" x-text="plan.active ?? 0">0</p>
                            </div>
                            <div class="text-right">
                                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-building text-blue-600"></i>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2 text-xs text-gray-500">
                            <i class="fas fa-external-link-alt mr-1"></i>Click to view details
                        </div>
                    </div>
                </template>
                <div x-show="!overview?.plan_distribution || overview.plan_distribution.length === 0" 
                     class="col-span-full text-center py-8 text-gray-500">
                    <i class="fas fa-chart-pie text-3xl mb-2"></i>
                    <p>No plan distribution data available</p>
                </div>
            </div>
        </div>

        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Revenue Trend</h3>
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                        <span class="text-sm text-gray-600">Revenue</span>
                    </div>
                </div>
                <div class="h-64">
                    <canvas id="revenue-chart" width="400" height="200"></canvas>
        </div>
    </div>
    
            
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">New vs Canceled</h3>
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                            <span class="text-sm text-gray-600">New</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                            <span class="text-sm text-gray-600">Canceled</span>
                        </div>
                    </div>
                </div>
                <div class="h-64">
                    <canvas id="subscriptions-chart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Average Revenue Per Unit (ARPU)</h3>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-purple-500 rounded-full"></div>
                    <span class="text-sm text-gray-600">ARPU</span>
                </div>
            </div>
            <div class="h-64">
                <canvas id="arpu-chart" width="800" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
function billingDashboard() {
    return {
        isLoading: true,
        error: null,
        lastRefresh: '',
        overview: null,
        revenueSeries: [],
        subscriptionsSeries: [],
        arpuSeries: [],
        filters: {
            range: 'last_30d',
            plan: '',
            region: '',
            currency: 'USD',
            grouping: 'day'
        },
        charts: {
            revenue: null,
            subscriptions: null,
            arpu: null
        },

        async init() {
            this.loadFiltersFromURL();
            await this.loadData();
            this.setupCharts();
            this.syncURL();
        },

        loadFiltersFromURL() {
            const url = new URL(window.location);
            Object.keys(this.filters).forEach(key => {
                if (url.searchParams.has(key)) {
                    this.filters[key] = url.searchParams.get(key);
                }
            });
        },

        async loadData() {
            try {
                this.isLoading = true;
                this.error = null;

                // Load overview data
                const overviewResponse = await fetch(`/api/admin/billing/overview?${this.buildQueryString()}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    credentials: 'same-origin'
                });

                if (!overviewResponse.ok) {
                    const errorData = await overviewResponse.json().catch(() => ({}));
                    throw new Error(errorData.message || `HTTP ${overviewResponse.status}: Failed to load overview data`);
                }

                const overviewData = await overviewResponse.json();
                
                if (overviewData.status === 'error') {
                    throw new Error(overviewData.message || 'API returned error status');
                }

                this.overview = overviewData.data || {};
                this.lastRefresh = new Date().toLocaleTimeString() + ' • ' + new Date().toLocaleDateString();

                // Load series data
                await Promise.all([
                    this.loadSeriesData('revenue'),
                    this.loadSeriesData('subs_new_vs_canceled'),
                    this.loadSeriesData('arpu')
                ]);

            } catch (error) {
                console.error('Error loading billing data:', error);
                this.error = error.message;
                this.overview = null;
            } finally {
                this.isLoading = false;
            }
        },

        async loadSeriesData(metric) {
            try {
                const response = await fetch(`/api/admin/billing/series?metric=${metric}&${this.buildQueryString()}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || `HTTP ${response.status}: Failed to load ${metric} data`);
                }

                const data = await response.json();
                
                if (data.status === 'error') {
                    throw new Error(data.message || `API returned error status for ${metric}`);
                }
                
                switch (metric) {
                    case 'revenue':
                        this.revenueSeries = data.data || [];
                        break;
                    case 'subs_new_vs_canceled':
                        this.subscriptionsSeries = data.data || [];
                        break;
                    case 'arpu':
                        this.arpuSeries = data.data || [];
                        break;
                }
            } catch (error) {
                console.error(`Error loading ${metric} data:`, error);
                // Set empty data for failed series
                switch (metric) {
                    case 'revenue':
                        this.revenueSeries = [];
                        break;
                    case 'subs_new_vs_canceled':
                        this.subscriptionsSeries = [];
                        break;
                    case 'arpu':
                        this.arpuSeries = [];
                        break;
                }
            }
        },

        async refreshData() {
            await this.loadData();
            this.updateCharts();
        },

        applyFilters() {
            this.syncURL();
            this.loadData().then(() => {
                this.updateCharts();
            });
        },

        buildQueryString() {
            const params = new URLSearchParams();
            Object.entries(this.filters).forEach(([key, value]) => {
                if (value && value !== '') {
                    // Map filter values to API expected values
                    if (key === 'plan' && value === 'all') {
                        // Don't send 'all' - send empty string or omit
                        return;
                    }
                    params.append(key, value);
                }
            });
            return params.toString();
        },

        syncURL() {
            const url = new URL(window.location);
            Object.entries(this.filters).forEach(([key, value]) => {
                if (value && value !== '') {
                    url.searchParams.set(key, value);
                } else {
                    url.searchParams.delete(key);
                }
            });
            window.history.replaceState({}, '', url);
        },

        setupCharts() {
            this.$nextTick(() => {
                this.createRevenueChart();
                this.createSubscriptionsChart();
                this.createARPUChart();
            });
        },

        createRevenueChart() {
            const ctx = document.getElementById('revenue-chart');
            if (!ctx) return;

            this.charts.revenue = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: (this.revenueSeries || []).map(item => item.t),
                    datasets: [{
                        label: 'Revenue',
                        data: (this.revenueSeries || []).map(item => item.amount),
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true
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

        createSubscriptionsChart() {
            const ctx = document.getElementById('subscriptions-chart');
            if (!ctx) return;

            this.charts.subscriptions = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: (this.subscriptionsSeries || []).map(item => item.t),
                    datasets: [
                        {
                            label: 'New',
                            data: (this.subscriptionsSeries || []).map(item => item.new),
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Canceled',
                            data: (this.subscriptionsSeries || []).map(item => item.canceled),
                            borderColor: '#EF4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            tension: 0.4
                        }
                    ]
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
        },

        createARPUChart() {
            const ctx = document.getElementById('arpu-chart');
            if (!ctx) return;

            this.charts.arpu = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: (this.arpuSeries || []).map(item => item.t),
                    datasets: [{
                        label: 'ARPU',
                        data: (this.arpuSeries || []).map(item => item.value),
                        borderColor: '#8B5CF6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        tension: 0.4,
                        fill: true
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
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        },

        updateCharts() {
            if (this.charts.revenue) {
                this.charts.revenue.data.labels = (this.revenueSeries || []).map(item => item.t);
                this.charts.revenue.data.datasets[0].data = (this.revenueSeries || []).map(item => item.amount);
                this.charts.revenue.update();
            }

            if (this.charts.subscriptions) {
                this.charts.subscriptions.data.labels = (this.subscriptionsSeries || []).map(item => item.t);
                this.charts.subscriptions.data.datasets[0].data = (this.subscriptionsSeries || []).map(item => item.new);
                this.charts.subscriptions.data.datasets[1].data = (this.subscriptionsSeries || []).map(item => item.canceled);
                this.charts.subscriptions.update();
            }

            if (this.charts.arpu) {
                this.charts.arpu.data.labels = (this.arpuSeries || []).map(item => item.t);
                this.charts.arpu.data.datasets[0].data = (this.arpuSeries || []).map(item => item.value);
                this.charts.arpu.update();
            }
        },

        formatCurrency(amount) {
            if (amount === null || amount === undefined || isNaN(amount)) {
                return '—';
            }
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: this.filters.currency || 'USD'
            }).format(amount);
        },

        drillDownToInvoices() {
            const params = new URLSearchParams({
                range: this.filters.range,
                status: 'paid'
            });
            window.location.href = `/admin/billing/invoices?${params.toString()}`;
        },

        drillDownToSubscriptions() {
            const params = new URLSearchParams({
                range: this.filters.range,
                status: 'active'
            });
            window.location.href = `/admin/billing/subscriptions?${params.toString()}`;
        },

        drillDownToChurn() {
            const params = new URLSearchParams({
                range: 'this_month',
                status: 'canceled'
            });
            window.location.href = `/admin/billing/subscriptions?${params.toString()}`;
        },

        drillDownToPlan(plan) {
            const params = new URLSearchParams({
                plan: plan,
                status: 'active'
            });
            window.location.href = `/admin/billing/subscriptions?${params.toString()}`;
        }
    }
}
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/billing/index.blade.php ENDPATH**/ ?>