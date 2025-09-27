{{-- Admin Dashboard Index --}}
@extends('layouts.admin')

@section('title', 'Dashboard')

@section('breadcrumb')
<li class="flex items-center">
    <i class="fas fa-chevron-right text-gray-400 mr-2"></i>
    <span class="text-gray-900">Dashboard</span>
</li>
@endsection

@section('content')
<div class="space-y-6">
    {{-- Page Header --}}
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
            <!-- Quick Presets -->
            <div class="flex items-center space-x-2 overflow-x-auto">
                <span class="text-sm text-gray-600 whitespace-nowrap">Quick Views:</span>
                <div class="flex space-x-2">
                    <button @click="applyPreset('critical')" 
                            class="px-3 py-1 bg-red-100 text-red-700 text-sm rounded-md hover:bg-red-200 transition-colors whitespace-nowrap">
                        <i class="fas fa-exclamation-triangle mr-1"></i>Critical
                    </button>
                    <button @click="applyPreset('active')" 
                            class="px-3 py-1 bg-green-100 text-green-700 text-sm rounded-md hover:bg-green-200 transition-colors whitespace-nowrap">
                        <i class="fas fa-check-circle mr-1"></i>Active
                    </button>
                    <button @click="applyPreset('recent')" 
                            class="px-3 py-1 bg-blue-100 text-blue-700 text-sm rounded-md hover:bg-blue-200 transition-colors whitespace-nowrap">
                        <i class="fas fa-clock mr-1"></i>Recent
                    </button>
                </div>
            </div>
            <button @click="refreshData" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors whitespace-nowrap">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
        </div>
    </div>
    
    {{-- KPI Strip --}}
    @include('admin.dashboard._kpis')
    
    {{-- Charts Section --}}
    @include('admin.dashboard._charts')
    
    {{-- Recent Activity --}}
    @include('admin.dashboard._activity')
</div>
@endsection

@push('scripts')
<script>
    function adminDashboard() {
        return {
            kpis: {
                totalTenants: 89,
                totalUsers: 1247,
                errors24h: 12,
                queueJobs: 156,
                storageUsed: '2.1TB'
            },
            
            chartData: {
                signups: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    data: [45, 52, 48, 61, 55, 67]
                },
                errors: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    data: [2.1, 1.8, 2.3, 1.9, 2.0, 1.7]
                }
            },
            
            // Export functionality
            showExportModal: false,
            exportFormat: 'csv',
            exportRange: '30',
            signupsRange: '30',
            errorsRange: '30',
            currentExportType: '',
            
            recentActivity: [
                {
                    id: 1,
                    type: 'tenant_created',
                    message: 'New tenant "TechCorp" registered',
                    time: '2 minutes ago',
                    icon: 'fas fa-building',
                    color: 'text-green-600'
                },
                {
                    id: 2,
                    type: 'user_registered',
                    message: 'User "john@techcorp.com" registered',
                    time: '5 minutes ago',
                    icon: 'fas fa-user-plus',
                    color: 'text-blue-600'
                },
                {
                    id: 3,
                    type: 'error_occurred',
                    message: 'High memory usage detected on server-01',
                    time: '15 minutes ago',
                    icon: 'fas fa-exclamation-triangle',
                    color: 'text-red-600'
                },
                {
                    id: 4,
                    type: 'backup_completed',
                    message: 'Daily backup completed successfully',
                    time: '1 hour ago',
                    icon: 'fas fa-download',
                    color: 'text-purple-600'
                }
            ],
            
            init() {
                this.initCharts();
            },
            
            initCharts() {
                // Signups Chart
                const signupsCtx = document.getElementById('signupsChart');
                if (signupsCtx) {
                    new Chart(signupsCtx, {
                        type: 'line',
                        data: {
                            labels: this.chartData.signups.labels,
                            datasets: [{
                                label: 'New Signups',
                                data: this.chartData.signups.data,
                                borderColor: 'rgb(59, 130, 246)',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
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
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
                
                // Error Rate Chart
                const errorsCtx = document.getElementById('errorsChart');
                if (errorsCtx) {
                    new Chart(errorsCtx, {
                        type: 'bar',
                        data: {
                            labels: this.chartData.errors.labels,
                            datasets: [{
                                label: 'Error Rate %',
                                data: this.chartData.errors.data,
                                backgroundColor: 'rgba(239, 68, 68, 0.8)',
                                borderColor: 'rgb(239, 68, 68)',
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
                                    beginAtZero: true,
                                    max: 3
                                }
                            }
                        }
                    });
                }
                
                // Initialize Sparkline Charts
                this.initSparklines();
            },
            
            initSparklines() {
                // Sparkline data for each KPI (30 days data)
                const sparklineData = {
                    tenants: [82, 83, 84, 85, 86, 87, 88, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89],
                    users: [1050, 1080, 1100, 1120, 1140, 1160, 1180, 1200, 1210, 1220, 1230, 1240, 1245, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247],
                    errors: [5, 6, 7, 8, 9, 10, 11, 12, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2, 1, 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 12],
                    queue: [100, 110, 120, 130, 140, 150, 160, 170, 165, 160, 155, 150, 145, 140, 135, 130, 125, 120, 115, 110, 105, 100, 95, 90, 85, 80, 75, 70, 65, 156],
                    storage: [1.5, 1.6, 1.7, 1.8, 1.9, 2.0, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1]
                };
                
                // Create sparkline charts
                Object.keys(sparklineData).forEach(key => {
                    const canvas = document.getElementById(key + 'Sparkline');
                    if (canvas) {
                        new Chart(canvas, {
                            type: 'line',
                            data: {
                                labels: Array(sparklineData[key].length).fill(''),
                                datasets: [{
                                    data: sparklineData[key],
                                    borderColor: this.getSparklineColor(key),
                                    backgroundColor: this.getSparklineColor(key, 0.1),
                                    borderWidth: 2,
                                    pointRadius: 0,
                                    pointHoverRadius: 3,
                                    tension: 0.4,
                                    fill: true
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false }
                                },
                                scales: {
                                    x: { display: false },
                                    y: { display: false }
                                },
                                elements: {
                                    point: { radius: 0 }
                                }
                            }
                        });
                    }
                });
            },
            
            getSparklineColor(key, alpha = 1) {
                const colors = {
                    tenants: `rgba(16, 185, 129, ${alpha})`, // Green #10B981
                    users: `rgba(16, 185, 129, ${alpha})`,   // Green #10B981
                    errors: `rgba(239, 68, 68, ${alpha})`,   // Red #EF4444
                    queue: `rgba(245, 158, 11, ${alpha})`,    // Orange #F59E0B
                    storage: `rgba(139, 92, 246, ${alpha})`   // Purple #8B5CF6
                };
                return colors[key] || `rgba(107, 114, 128, ${alpha})`;
            },
            
            refreshData() {
                // Simulate data refresh
                console.log('Refreshing dashboard data...');
                // In real implementation, this would fetch fresh data from API
            },
            
            // Export Functions
            exportChart(type) {
                this.currentExportType = type;
                this.showExportModal = true;
            },
            
            downloadExport() {
                const data = this.getExportData();
                const filename = `${this.currentExportType}_data_${new Date().toISOString().split('T')[0]}.${this.exportFormat}`;
                
                if (this.exportFormat === 'csv') {
                    this.downloadCSV(data, filename);
                } else {
                    this.downloadJSON(data, filename);
                }
                
                this.showExportModal = false;
            },
            
            getExportData() {
                const baseData = {
                    signups: {
                        labels: this.chartData.signups.labels,
                        data: this.chartData.signups.data,
                        range: this.signupsRange
                    },
                    errors: {
                        labels: this.chartData.errors.labels,
                        data: this.chartData.errors.data,
                        range: this.errorsRange
                    }
                };
                
                return baseData[this.currentExportType] || {};
            },
            
            downloadCSV(data, filename) {
                let csv = 'Date,Value\n';
                data.labels.forEach((label, index) => {
                    csv += `${label},${data.data[index]}\n`;
                });
                
                const blob = new Blob([csv], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                a.click();
                window.URL.revokeObjectURL(url);
            },
            
            downloadJSON(data, filename) {
                const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                a.click();
                window.URL.revokeObjectURL(url);
            },
            
            updateSignupsChart() {
                // In real implementation, this would fetch new data based on range
                console.log('Updating signups chart for range:', this.signupsRange);
            },
            
            updateErrorsChart() {
                // In real implementation, this would fetch new data based on range
                console.log('Updating errors chart for range:', this.errorsRange);
            },
            
            // Quick Presets
            applyPreset(preset) {
                switch(preset) {
                    case 'critical':
                        // Filter to show only critical items
                        this.filterCriticalItems();
                        break;
                    case 'active':
                        // Show only active/healthy items
                        this.filterActiveItems();
                        break;
                    case 'recent':
                        // Show recent activity
                        this.filterRecentItems();
                        break;
                }
            },
            
            filterCriticalItems() {
                // In real implementation, this would filter KPIs and charts to show critical items
                console.log('Filtering critical items...');
                // Example: Highlight error rates, show only problematic tenants, etc.
            },
            
            filterActiveItems() {
                // In real implementation, this would filter to show active/healthy items
                console.log('Filtering active items...');
                // Example: Show only active tenants, healthy system metrics, etc.
            },
            
            filterRecentItems() {
                // In real implementation, this would filter to show recent activity
                console.log('Filtering recent items...');
                // Example: Show recent signups, recent errors, recent activity, etc.
            }
        }
    }
</script>
@endpush
