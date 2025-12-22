{{-- Standardized DashboardShell Component --}}
{{-- Uses layout-wrapper for consistent structure --}}

@props([
    'variant' => 'app', // 'app' or 'admin'
    'user' => null,
    'tenant' => null,
    'kpis' => [],
    'charts' => [],
    'recentActivity' => [],
    'recentProjects' => [],
    'alerts' => [],
    'notifications' => [],
    'theme' => 'light',
    'title' => null,
    'subtitle' => null,
    'breadcrumbs' => [],
    'actions' => null
])

@php
    $isAdmin = $variant === 'admin';
    $user = $user ?? Auth::user();
    $tenant = $tenant ?? ($user ? $user->tenant : null);
    
    // Default KPIs based on variant
    if (empty($kpis)) {
        if ($isAdmin) {
            $kpis = [
                ['key' => 'tenants', 'label' => 'Total Tenants', 'value' => 0, 'change' => 0, 'icon' => 'fas fa-building', 'color' => 'blue'],
                ['key' => 'users', 'label' => 'Total Users', 'value' => 0, 'change' => 0, 'icon' => 'fas fa-users', 'color' => 'green'],
                ['key' => 'projects', 'label' => 'Total Projects', 'value' => 0, 'change' => 0, 'icon' => 'fas fa-project-diagram', 'color' => 'purple'],
                ['key' => 'revenue', 'label' => 'Monthly Revenue', 'value' => 0, 'change' => 0, 'icon' => 'fas fa-dollar-sign', 'color' => 'yellow']
            ];
        } else {
            $kpis = [
                ['key' => 'projects', 'label' => 'Total Projects', 'value' => 0, 'change' => 0, 'icon' => 'fas fa-project-diagram', 'color' => 'blue'],
                ['key' => 'users', 'label' => 'Active Users', 'value' => 0, 'change' => 0, 'icon' => 'fas fa-users', 'color' => 'green'],
                ['key' => 'progress', 'label' => 'Average Progress', 'value' => 0, 'change' => 0, 'icon' => 'fas fa-chart-line', 'color' => 'purple'],
                ['key' => 'budget', 'label' => 'Budget Utilization', 'value' => 0, 'change' => 0, 'icon' => 'fas fa-dollar-sign', 'color' => 'yellow']
            ];
        }
    }
    
    // Default charts based on variant
    if (empty($charts)) {
        if ($isAdmin) {
            $charts = [
                ['key' => 'tenant-growth', 'type' => 'line', 'title' => 'Tenant Growth', 'data' => []],
                ['key' => 'user-distribution', 'type' => 'doughnut', 'title' => 'User Distribution', 'data' => []]
            ];
        } else {
            $charts = [
                ['key' => 'project-progress', 'type' => 'doughnut', 'title' => 'Project Progress', 'data' => []],
                ['key' => 'task-distribution', 'type' => 'line', 'title' => 'Average Progress %', 'data' => []]
            ];
        }
    }
    
    // Default title and subtitle
    $title = $title ?? ($isAdmin ? 'Admin Dashboard' : 'Dashboard');
    $subtitle = $subtitle ?? ($isAdmin ? 'System overview and management' : 'Welcome back, ' . ($user->first_name ?? 'User'));
    
    // Default actions
    if (!$actions) {
        $actions = $isAdmin 
            ? '<a href="' . route('admin.users.create') . '" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"><i class="fas fa-plus mr-2"></i>Create User</a>'
            : '<a href="/frontend/app/projects/create" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"><i class="fas fa-plus mr-2"></i>New Project</a>';
    }
@endphp

<x-shared.layout-wrapper 
    variant="{{ $variant }}"
    :user="$user"
    :tenant="$tenant"
    :notifications="$notifications"
    theme="{{ $theme }}"
    title="{{ $title }}"
    subtitle="{{ $subtitle }}"
    :breadcrumbs="$breadcrumbs"
    :actions="$actions">
    
    <div x-data="dashboardShellComponent()">
        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            @foreach($kpis as $kpi)
                <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200 hover:shadow-md transition-shadow duration-200">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-{{ $kpi['color'] }}-100 rounded-lg flex items-center justify-center">
                                    <i class="{{ $kpi['icon'] }} text-{{ $kpi['color'] }}-600 text-lg"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">{{ $kpi['label'] }}</p>
                                <p class="text-2xl font-bold text-gray-900" id="kpi-{{ $kpi['key'] }}-count">
                                    <span class="animate-pulse bg-gray-200 h-8 w-16 rounded"></span>
                                </p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center text-sm" id="kpi-{{ $kpi['key'] }}-change">
                                <span class="animate-pulse bg-gray-200 h-4 w-20 rounded"></span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Main Content Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {{-- Recent Projects/Items --}}
            <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ $isAdmin ? 'Recent Tenants' : 'Recent Projects' }}
                        </h2>
                        <a href="{{ $isAdmin ? route('admin.tenants.index') : route('app.projects.index') }}" 
                           class="text-sm text-blue-600 hover:text-blue-500 font-medium">
                            View all
                        </a>
                    </div>
                </div>
                <div class="p-6">
                    <div id="recent-items" class="space-y-4">
                        {{-- Loading skeleton --}}
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

            {{-- Recent Activity --}}
            <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">Recent Activity</h2>
                        <button @click="loadMoreActivity()" 
                                class="text-sm text-blue-600 hover:text-blue-500 font-medium">
                            Load more
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <div id="recent-activity" class="space-y-4">
                        {{-- Loading skeleton --}}
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

        {{-- Charts Section --}}
        <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-8">
            @foreach($charts as $chart)
                <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">{{ $chart['title'] }}</h2>
                    </div>
                    <div class="p-6">
                        <div id="{{ $chart['key'] }}-chart" class="h-64">
                            {{-- Chart will be rendered here --}}
                            <div class="animate-pulse bg-gray-200 h-full rounded"></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($isAdmin)
            {{-- Admin-specific sections --}}
            <div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
                {{-- System Alerts --}}
                <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">System Alerts</h2>
                    </div>
                    <div class="p-6">
                        <div id="system-alerts" class="space-y-3">
                            {{-- Loading skeleton --}}
                            <div class="animate-pulse">
                                <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
                                <div class="h-3 bg-gray-200 rounded w-2/3"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Performance Metrics --}}
                <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Performance</h2>
                    </div>
                    <div class="p-6">
                        <div id="performance-metrics" class="space-y-3">
                            {{-- Loading skeleton --}}
                            <div class="animate-pulse">
                                <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
                                <div class="h-3 bg-gray-200 rounded w-2/3"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Quick Actions --}}
                <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Quick Actions</h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            <a href="{{ route('admin.users.create') }}" 
                               class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">
                                <i class="fas fa-user-plus mr-2 text-gray-400"></i>
                                Create User
                            </a>
                            <a href="{{ route('admin.tenants.create') }}" 
                               class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">
                                <i class="fas fa-building mr-2 text-gray-400"></i>
                                Create Tenant
                            </a>
                            <a href="{{ route('admin.projects.create') }}" 
                               class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">
                                <i class="fas fa-project-diagram mr-2 text-gray-400"></i>
                                Create Project
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-shared.layout-wrapper>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('dashboardShellComponent', () => ({
        loading: false,
        variant: '{{ $variant }}',
        
        async init() {
            await this.loadDashboardData();
            this.setupEventListeners();
        },
        
        async loadDashboardData() {
            if (this.loading) return;
            
            this.loading = true;
            this.showLoadingStates();

            try {
                const [kpis, items, activity] = await Promise.all([
                    this.fetchKPIs(),
                    this.fetchRecentItems(),
                    this.fetchRecentActivity()
                ]);

                this.renderKPIs(kpis.data || kpis);
                this.renderRecentItems(items);
                this.renderRecentActivity(activity.data || activity);
                
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
        },
        
        async fetchKPIs() {
            const endpoint = this.variant === 'admin' ? '/api/admin/dashboard/kpis' : '/api/dashboard/kpis';
            const response = await fetch(endpoint, {
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
        },
        
        async fetchRecentItems() {
            const endpoint = this.variant === 'admin' ? '/api/admin/tenants?limit=5' : '/api/projects?limit=5';
            const response = await fetch(endpoint, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            if (!response.ok) {
                throw new Error('Failed to fetch recent items');
            }

            const data = await response.json();
            return data.data || [];
        },
        
        async fetchRecentActivity() {
            const endpoint = this.variant === 'admin' ? '/api/admin/dashboard/recent-activity' : '/api/dashboard/recent-activity';
            const response = await fetch(endpoint, {
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
        },
        
        renderKPIs(kpis) {
            const kpiMapping = this.variant === 'admin' ? {
                'tenants': 'tenants',
                'users': 'users', 
                'projects': 'projects',
                'revenue': 'revenue'
            } : {
                'projects': 'projects',
                'users': 'users',
                'progress': 'progress', 
                'budget': 'budget'
            };
            
            Object.entries(kpiMapping).forEach(([key, dataKey]) => {
                const countEl = document.getElementById(`kpi-${key}-count`);
                const changeEl = document.getElementById(`kpi-${key}-change`);
                
                if (countEl && changeEl && kpis[dataKey]) {
                    countEl.innerHTML = kpis[dataKey].total || kpis[dataKey].value || 0;
                    changeEl.innerHTML = this.formatChange(kpis[dataKey].change || 0);
                }
            });
        },
        
        renderRecentItems(items) {
            const container = document.getElementById('recent-items');
            const itemType = this.variant === 'admin' ? 'tenant' : 'project';
            
            if (items.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8">
                        <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-${itemType === 'tenant' ? 'building' : 'project-diagram'} text-2xl text-gray-400"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No ${itemType}s yet</h3>
                        <p class="text-gray-500 mb-4">Get started by creating your first ${itemType}.</p>
                        <a href="${this.variant === 'admin' ? '/admin/tenants/create' : '/app/projects/create'}" 
                           class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>
                            Create First ${itemType.charAt(0).toUpperCase() + itemType.slice(1)}
                        </a>
                    </div>
                `;
                return;
            }

            container.innerHTML = items.map(item => `
                <div class="flex items-center space-x-4 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-${itemType === 'tenant' ? 'building' : 'project-diagram'} text-blue-600"></i>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-medium text-gray-900 truncate">${item.name || item.title}</h3>
                        <p class="text-sm text-gray-500">${item.description || item.status || 'No description'}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getStatusColor(item.status || 'active')}">
                            ${item.status || 'active'}
                        </span>
                    </div>
                </div>
            `).join('');
        },
        
        renderRecentActivity(activities) {
            const container = document.getElementById('recent-activity');
            
            if (activities.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8">
                        <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-history text-2xl text-gray-400"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No recent activity</h3>
                        <p class="text-gray-500">Activity will appear here as you work.</p>
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
        },
        
        async loadCharts() {
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js not loaded');
                return;
            }

            try {
                const endpoint = this.variant === 'admin' ? '/api/admin/dashboard/charts' : '/api/dashboard/charts';
                const response = await fetch(endpoint, {
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
                this.renderCharts(data.data || data);
            } catch (error) {
                console.error('Failed to load charts:', error);
                this.renderCharts(); // Fallback to mock data
            }
        },
        
        renderCharts(chartData = null) {
            const charts = this.variant === 'admin' ? [
                { key: 'tenant-growth', type: 'line' },
                { key: 'user-distribution', type: 'doughnut' }
            ] : [
                { key: 'project-progress', type: 'doughnut' },
                { key: 'task-distribution', type: 'line' }
            ];
            
            charts.forEach(chart => {
                const ctx = document.getElementById(`${chart.key}-chart`);
                if (!ctx) return;
                
                const data = chartData?.[chart.key] || this.getMockChartData(chart.key, chart.type);
                this.createChart(ctx, chart.type, data);
            });
        },
        
        createChart(ctx, type, data) {
            new Chart(ctx, {
                type: type,
                data: data,
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
        
        getMockChartData(key, type) {
            if (type === 'doughnut') {
                return {
                    labels: ['Active', 'Inactive', 'Pending'],
                    datasets: [{
                        label: 'Items',
                        data: [12, 3, 2],
                        backgroundColor: ['#10B981', '#EF4444', '#F59E0B']
                    }]
                };
            } else {
                return {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Growth',
                        data: [12, 19, 3, 5, 2, 3],
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 2,
                        fill: true
                    }]
                };
            }
        },
        
        showLoadingStates() {
            // KPIs are already showing loading states
        },
        
        showError(message) {
            console.error(message);
        },
        
        formatChange(change) {
            if (change === null || change === undefined) return '<span class="text-gray-400">No change</span>';
            
            const isPositive = change > 0;
            const color = isPositive ? 'text-green-600' : (change < 0 ? 'text-red-600' : 'text-gray-600');
            const icon = isPositive ? 'fas fa-arrow-up' : (change < 0 ? 'fas fa-arrow-down' : 'fas fa-minus');
            
            return `<span class="${color} flex items-center">
                <i class="${icon} mr-1 text-xs"></i>
                ${Math.abs(change)}%
            </span>`;
        },
        
        getStatusColor(status) {
            const colors = {
                'active': 'bg-green-100 text-green-800',
                'inactive': 'bg-gray-100 text-gray-800',
                'pending': 'bg-yellow-100 text-yellow-800',
                'completed': 'bg-blue-100 text-blue-800',
                'cancelled': 'bg-red-100 text-red-800'
            };
            return colors[status] || 'bg-gray-100 text-gray-800';
        },
        
        getActivityIcon(type) {
            const icons = {
                'project': 'project-diagram',
                'task': 'tasks',
                'user': 'user',
                'tenant': 'building',
                'system': 'cog'
            };
            return icons[type] || 'circle';
        },
        
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
        },
        
        setupEventListeners() {
            // Auto-refresh every 5 minutes
            setInterval(() => {
                this.loadDashboardData();
            }, 300000);
        },
        
        refreshDashboard() {
            this.loadDashboardData();
        },
        
        loadMoreActivity() {
            console.log('Load more activity');
        }
    }));
});
</script>
@endpush
