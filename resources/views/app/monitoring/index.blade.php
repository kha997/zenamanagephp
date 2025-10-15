@extends('layouts.app-layout')

@section('title', __('monitoring.dashboard_title'))

@section('content')
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">{{ __('monitoring.dashboard_title') }}</h1>
        <div class="page-actions">
            <button @click="refreshMetrics()" 
                    class="btn btn-outline-secondary">
                <i class="fas fa-sync-alt mr-2"></i>
                {{ __('monitoring.refresh') }}
            </button>
            <button @click="exportMetrics()" 
                    class="btn btn-primary">
                <i class="fas fa-download mr-2"></i>
                {{ __('monitoring.export') }}
            </button>
        </div>
    </div>
</div>

<div class="content-wrapper" x-data="monitoringDashboard()">
    <!-- System Status Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-heartbeat text-2xl text-green-500"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('monitoring.system_status') }}</h3>
                    <p class="text-2xl font-bold" :class="systemHealth.status === 'healthy' ? 'text-green-600' : 'text-red-600'" 
                       x-text="systemHealth.status"></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-clock text-2xl text-blue-500"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('monitoring.avg_response_time') }}</h3>
                    <p class="text-2xl font-bold text-blue-600" x-text="apiMetrics.avg_response_time + 'ms'"></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-2xl text-yellow-500"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('monitoring.error_rate') }}</h3>
                    <p class="text-2xl font-bold text-yellow-600" x-text="(apiMetrics.error_rate * 100).toFixed(2) + '%'"></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-chart-line text-2xl text-purple-500"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('monitoring.requests_per_minute') }}</h3>
                    <p class="text-2xl font-bold text-purple-600" x-text="apiMetrics.requests_per_minute"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Metrics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- API Metrics -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('monitoring.api_metrics') }}</h2>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">{{ __('monitoring.p95_response_time') }}</span>
                        <span class="font-semibold" x-text="apiMetrics.p95_response_time + 'ms'"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">{{ __('monitoring.total_requests') }}</span>
                        <span class="font-semibold" x-text="apiMetrics.total_requests"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">{{ __('monitoring.success_rate') }}</span>
                        <span class="font-semibold" x-text="((1 - apiMetrics.error_rate) * 100).toFixed(2) + '%'"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Database Metrics -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('monitoring.database_metrics') }}</h2>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">{{ __('monitoring.connection_count') }}</span>
                        <span class="font-semibold" x-text="databaseMetrics.connection_count"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">{{ __('monitoring.slow_queries') }}</span>
                        <span class="font-semibold" x-text="databaseMetrics.slow_queries"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">{{ __('monitoring.cache_hit_ratio') }}</span>
                        <span class="font-semibold" x-text="databaseMetrics.cache_hit_ratio.toFixed(2) + '%'"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Queue Metrics -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('monitoring.queue_metrics') }}</h2>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">{{ __('monitoring.pending_jobs') }}</span>
                        <span class="font-semibold" x-text="queueMetrics.pending_jobs"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">{{ __('monitoring.failed_jobs') }}</span>
                        <span class="font-semibold" x-text="queueMetrics.failed_jobs"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">{{ __('monitoring.processed_jobs') }}</span>
                        <span class="font-semibold" x-text="queueMetrics.processed_jobs"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Resources -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('monitoring.system_resources') }}</h2>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">{{ __('monitoring.memory_usage') }}</span>
                        <span class="font-semibold" x-text="systemHealth.memory_usage.current + 'MB'"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">{{ __('monitoring.disk_usage') }}</span>
                        <span class="font-semibold" x-text="systemHealth.disk_usage.percentage + '%'"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">{{ __('monitoring.uptime') }}</span>
                        <span class="font-semibold" x-text="systemHealth.uptime"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Sizes -->
    <div class="mt-8">
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('monitoring.table_sizes') }}</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <template x-for="(size, table) in databaseMetrics.table_sizes" :key="table">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900" x-text="size + 'MB'"></div>
                            <div class="text-sm text-gray-600 capitalize" x-text="table"></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function monitoringDashboard() {
    return {
        apiMetrics: {},
        databaseMetrics: {},
        queueMetrics: {},
        systemHealth: {},
        
        init() {
            this.loadMetrics();
            // Auto-refresh every 30 seconds
            setInterval(() => {
                this.loadMetrics();
            }, 30000);
        },
        
        async loadMetrics() {
            try {
                const response = await fetch('/api/v1/app/monitoring/dashboard');
                const data = await response.json();
                
                if (data.status === 'success') {
                    this.apiMetrics = data.data.api_metrics;
                    this.databaseMetrics = data.data.database_metrics;
                    this.queueMetrics = data.data.queue_metrics;
                    this.systemHealth = data.data.system_health;
                }
            } catch (error) {
                console.error('Failed to load metrics:', error);
            }
        },
        
        async refreshMetrics() {
            await this.loadMetrics();
        },
        
        async exportMetrics() {
            try {
                const response = await fetch('/api/v1/app/monitoring/dashboard');
                const data = await response.json();
                
                if (data.status === 'success') {
                    const blob = new Blob([JSON.stringify(data.data, null, 2)], {
                        type: 'application/json'
                    });
                    
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `monitoring-${new Date().toISOString().split('T')[0]}.json`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                }
            } catch (error) {
                console.error('Failed to export metrics:', error);
            }
        }
    }
}
</script>
@endsection
