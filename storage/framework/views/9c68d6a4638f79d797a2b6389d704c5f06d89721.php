<?php $__env->startSection('title', 'Dashboard'); ?>

<?php $__env->startSection('breadcrumb'); ?>
<li class="flex items-center">
    <i class="fas fa-chevron-right text-gray-400 mr-2"></i>
    <span class="text-gray-900">Dashboard</span>
</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-6">
    
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
                    :disabled="isRefreshing"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed">
                <i :class="isRefreshing ? 'fas fa-spinner fa-spin' : 'fas fa-sync-alt'" class="mr-2"></i>
                <span x-text="isRefreshing ? 'Refreshing...' : 'Refresh'">Refresh</span>
            </button>
        </div>
    </div>
    
    
    <div x-show="error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-red-600 mr-2"></i>
                <p class="text-red-800" x-text="error"></p>
            </div>
            <button @click="loadDashboardData()" 
                    class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700">
                Retry
            </button>
        </div>
    </div>
    
    
    <div x-show="isLoading" class="space-y-6">
        <!-- KPI Skeletons -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 lg:gap-6">
            <template x-for="i in 5" :key="i">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 animate-pulse">
                    <div class="flex items-center justify-between mb-4">
                        <div class="space-y-2">
                            <div class="h-4 bg-gray-200 rounded w-20"></div>
                            <div class="h-8 bg-gray-200 rounded w-16"></div>
                            <div class="h-3 bg-gray-200 rounded w-24"></div>
                        </div>
                        <div class="w-12 h-12 bg-gray-200 rounded-full"></div>
                    </div>
                    <div class="h-8 bg-gray-200 rounded mb-3"></div>
                    <div class="h-8 bg-gray-200 rounded"></div>
                </div>
            </template>
        </div>
        
        <!-- Chart Skeletons -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <template x-for="i in 2" :key="i">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 animate-pulse">
                    <div class="h-6 bg-gray-200 rounded w-32 mb-6"></div>
                    <div class="h-64 bg-gray-200 rounded"></div>
                </div>
            </template>
        </div>
        
        <!-- Activity Skeleton -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 animate-pulse">
            <div class="h-6 bg-gray-200 rounded w-32 mb-6"></div>
            <div class="space-y-4">
                <template x-for="i in 4" :key="i">
                    <div class="flex items-start space-x-3">
                        <div class="w-8 h-8 bg-gray-200 rounded-full"></div>
                        <div class="flex-1 space-y-2">
                            <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                            <div class="h-3 bg-gray-200 rounded w-1/4"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
    
    
    <div x-show="!isLoading" class="space-y-6">
        
        <?php echo $__env->make('admin.dashboard._kpis', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        
        
        <?php echo $__env->make('admin.dashboard._charts', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        
        
        <?php echo $__env->make('admin.dashboard._activity', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    function adminDashboard() {
        return {
            // Data Contract v2
            kpis: {
                totalTenants: { 
                    value: 89, 
                    deltaPct: 5.2, 
                    series: [82, 83, 84, 85, 86, 87, 88, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89, 89], 
                    period: '30d' 
                },
                totalUsers: { 
                    value: 1247, 
                    deltaPct: 12.1, 
                    series: [1050, 1080, 1100, 1120, 1140, 1160, 1180, 1200, 1210, 1220, 1230, 1240, 1245, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247, 1247], 
                    period: '30d' 
                },
                errors24h: { 
                    value: 12, 
                    deltaAbs: 3, 
                    series: [5, 6, 7, 8, 9, 10, 11, 12, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2, 1, 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 12], 
                    period: '24h' 
                },
                queueJobs: { 
                    value: 156, 
                    status: 'processing', 
                    series: [100, 110, 120, 130, 140, 150, 160, 170, 165, 160, 155, 150, 145, 140, 135, 130, 125, 120, 115, 110, 105, 100, 95, 90, 85, 80, 75, 70, 65, 156], 
                    period: '24h' 
                },
                storage: { 
                    usedBytes: 2200000000000, // 2.2TB in bytes
                    capacityBytes: 3200000000000, // 3.2TB in bytes
                    series: [1500000000000, 1600000000000, 1700000000000, 1800000000000, 1900000000000, 2000000000000, 2100000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000, 2200000000000], 
                    period: '30d' 
                }
            },
            
            charts: {
                signups: {
                    points: [
                        { ts: '2024-01-01T00:00:00Z', value: 45 },
                        { ts: '2024-01-02T00:00:00Z', value: 52 },
                        { ts: '2024-01-03T00:00:00Z', value: 48 },
                        { ts: '2024-01-04T00:00:00Z', value: 61 },
                        { ts: '2024-01-05T00:00:00Z', value: 55 },
                        { ts: '2024-01-06T00:00:00Z', value: 67 }
                    ],
                    period: '30d'
                },
                errors: {
                    points: [
                        { ts: '2024-01-01T00:00:00Z', value: 2.1 },
                        { ts: '2024-01-02T00:00:00Z', value: 1.8 },
                        { ts: '2024-01-03T00:00:00Z', value: 2.3 },
                        { ts: '2024-01-04T00:00:00Z', value: 1.9 },
                        { ts: '2024-01-05T00:00:00Z', value: 2.0 },
                        { ts: '2024-01-06T00:00:00Z', value: 1.7 }
                    ],
                    period: '7d'
                }
            },
            
            // Export functionality
            showExportModal: false,
            exportFormat: 'csv',
            exportRange: '30',
            signupsRange: '30',
            errorsRange: '30',
            currentExportType: '',
            
            activity: [
                {
                    id: 'act_001',
                    type: 'tenant_created',
                    message: 'New tenant "TechCorp" registered',
                    ts: new Date(Date.now() - 2 * 60 * 1000).toISOString(),
                    actor: 'system',
                    target: { type: 'tenant', id: 'tenant_001', name: 'TechCorp' },
                    severity: 'info'
                },
                {
                    id: 'act_002',
                    type: 'user_registered',
                    message: 'User "john@techcorp.com" registered',
                    ts: new Date(Date.now() - 5 * 60 * 1000).toISOString(),
                    actor: 'john@techcorp.com',
                    target: { type: 'user', id: 'user_001', name: 'John Smith' },
                    severity: 'info'
                },
                {
                    id: 'act_003',
                    type: 'error_raised',
                    message: 'High memory usage detected on server-01',
                    ts: new Date(Date.now() - 15 * 60 * 1000).toISOString(),
                    actor: 'system',
                    target: { type: 'server', id: 'server_01', name: 'server-01' },
                    severity: 'warning'
                },
                {
                    id: 'act_004',
                    type: 'job_failed',
                    message: 'Daily backup job failed',
                    ts: new Date(Date.now() - 60 * 60 * 1000).toISOString(),
                    actor: 'system',
                    target: { type: 'job', id: 'backup_001', name: 'Daily Backup' },
                    severity: 'error'
                }
            ],
            
            // State management
            isLoading: false,
            isRefreshing: false,
            error: null,
            lastUpdated: null,
            pollingInterval: null,
            
            init() {
                this.loadDashboardData();
                this.startPolling();
                this.initCharts();
            },
            
            // API Integration
            async loadDashboardData() {
                this.isLoading = true;
                this.error = null;
                
                try {
                    // Simulate API calls
                    await Promise.all([
                        this.loadKPIs(),
                        this.loadCharts(),
                        this.loadActivity()
                    ]);
                    this.lastUpdated = new Date().toISOString();
                } catch (err) {
                    this.error = err.message || 'Failed to load dashboard data';
                } finally {
                    this.isLoading = false;
                }
            },
            
            async loadKPIs() {
                // Simulate API call to /api/admin/dashboard/kpis?period=30d
                return new Promise(resolve => setTimeout(resolve, 300));
            },
            
            async loadCharts() {
                // Simulate API calls to /api/admin/dashboard/charts/*
                return new Promise(resolve => setTimeout(resolve, 200));
            },
            
            async loadActivity() {
                // Simulate API call to /api/admin/dashboard/activity?limit=20
                return new Promise(resolve => setTimeout(resolve, 150));
            },
            
            // Polling
            startPolling() {
                this.pollingInterval = setInterval(() => {
                    this.loadDashboardData();
                }, 30000); // 30s for KPIs
            },
            
            stopPolling() {
                if (this.pollingInterval) {
                    clearInterval(this.pollingInterval);
                    this.pollingInterval = null;
                }
            },
            
            // Manual refresh
            async refreshData() {
                this.isRefreshing = true;
                try {
                    await this.loadDashboardData();
                    this.showToast('Dashboard refreshed successfully');
                } catch (err) {
                    this.showToast('Failed to refresh dashboard', 'error');
                } finally {
                    this.isRefreshing = false;
                }
            },
            
            showToast(message, type = 'success') {
                // Simple toast implementation
                const toast = document.createElement('div');
                toast.className = `fixed top-4 right-4 px-4 py-2 rounded-lg text-white z-50 ${
                    type === 'error' ? 'bg-red-500' : 'bg-green-500'
                }`;
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            },
            
            initCharts() {
                // Signups Chart
                const signupsCtx = document.getElementById('signupsChart');
                if (signupsCtx) {
                    new Chart(signupsCtx, {
                        type: 'line',
                        data: {
                            labels: this.charts.signups.points.map(p => new Date(p.ts).toLocaleDateString()),
                            datasets: [{
                                label: 'New Signups',
                                data: this.charts.signups.points.map(p => p.value),
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
                            labels: this.charts.errors.points.map(p => new Date(p.ts).toLocaleDateString()),
                            datasets: [{
                                label: 'Error Rate %',
                                data: this.charts.errors.points.map(p => p.value),
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
                // Create sparkline charts from KPI data
                const sparklineKeys = ['tenants', 'users', 'errors24h', 'queueJobs', 'storage'];
                
                sparklineKeys.forEach(key => {
                    const canvas = document.getElementById(key + 'Sparkline');
                    if (canvas && this.kpis[key]) {
                        const series = this.kpis[key].series;
                        new Chart(canvas, {
                            type: 'line',
                            data: {
                                labels: Array(series.length).fill(''),
                                datasets: [{
                                    data: series,
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
                    totalTenants: `rgba(16, 185, 129, ${alpha})`, // Green #10B981
                    totalUsers: `rgba(16, 185, 129, ${alpha})`,   // Green #10B981
                    errors24h: `rgba(239, 68, 68, ${alpha})`,     // Red #EF4444
                    queueJobs: `rgba(245, 158, 11, ${alpha})`,    // Orange #F59E0B
                    storage: `rgba(139, 92, 246, ${alpha})`       // Purple #8B5CF6
                };
                return colors[key] || `rgba(107, 114, 128, ${alpha})`;
            },
            
            // Utility functions
            formatBytes(bytes) {
                if (bytes === 0) return '0 B';
                const k = 1024;
                const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
            },
            
            formatTimeAgo(isoString) {
                const now = new Date();
                const time = new Date(isoString);
                const diffMs = now - time;
                const diffMins = Math.floor(diffMs / 60000);
                const diffHours = Math.floor(diffMs / 3600000);
                const diffDays = Math.floor(diffMs / 86400000);
                
                if (diffMins < 1) return 'Just now';
                if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
                if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
                return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
            },
            
            getSeverityColor(severity) {
                const colors = {
                    info: 'text-blue-600',
                    warning: 'text-yellow-600',
                    error: 'text-red-600'
                };
                return colors[severity] || 'text-gray-600';
            },
            
            getSeverityIcon(severity) {
                const icons = {
                    info: 'fas fa-info-circle',
                    warning: 'fas fa-exclamation-triangle',
                    error: 'fas fa-times-circle'
                };
                return icons[severity] || 'fas fa-circle';
            },
            
            // Quick Presets Implementation
            applyPreset(preset) {
                switch(preset) {
                    case 'critical':
                        this.applyCriticalPreset();
                        break;
                    case 'active':
                        this.applyActivePreset();
                        break;
                    case 'recent':
                        this.applyRecentPreset();
                        break;
                }
            },
            
            applyCriticalPreset() {
                // Set chart period to 7d for errors
                this.charts.errors.period = '7d';
                this.errorsRange = '7';
                
                // Navigate to Alerts with critical filter
                window.location.href = '/admin/alerts?severity=error&range=24h';
            },
            
            applyActivePreset() {
                // Set signups period to 30d
                this.charts.signups.period = '30d';
                this.signupsRange = '30';
                
                // Highlight top signup days (mock implementation)
                console.log('Highlighting top signup days...');
            },
            
            applyRecentPreset() {
                // Load recent activity and show drawer
                this.loadActivity();
                // In real implementation, would open activity drawer
                console.log('Loading recent activity...');
            },
            
            // Drill-down functions
            drillDownTenants() {
                window.location.href = '/admin/tenants?sort=-created_at';
            },
            
            drillDownUsers() {
                window.location.href = '/admin/users?filter=active&sort=-last_login';
            },
            
            drillDownErrors() {
                window.location.href = '/admin/alerts?severity=error&range=24h';
            },
            
            drillDownQueue() {
                window.location.href = '/admin/maintenance/tasks?tab=queue&filter=stalled|backlog';
            },
            
            drillDownStorage() {
                window.location.href = '/admin/settings?tab=storage';
                // In real implementation, would open "Top consumers" modal
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
                        points: this.charts.signups.points,
                        period: this.charts.signups.period
                    },
                    errors: {
                        points: this.charts.errors.points,
                        period: this.charts.errors.period
                    }
                };
                
                return baseData[this.currentExportType] || {};
            },
            
            downloadCSV(data, filename) {
                let csv = 'Date,Value\n';
                data.points.forEach(point => {
                    csv += `${point.ts},${point.value}\n`;
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
                // Update chart period and reload data
                this.charts.signups.period = this.signupsRange + 'd';
                this.loadCharts();
            },
            
            updateErrorsChart() {
                // Update chart period and reload data
                this.charts.errors.period = this.errorsRange + 'd';
                this.loadCharts();
            }
        }
    }
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/dashboard/index.blade.php ENDPATH**/ ?>