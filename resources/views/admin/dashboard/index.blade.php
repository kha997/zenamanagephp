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
<div class="space-y-6" x-data="adminDashboard()" x-init="init()">
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
            {{-- Refresh Indicator --}}
            <span class="text-xs text-gray-500 refresh-indicator">Last updated: <span x-text="lastRefresh"></span></span>
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
    
    {{-- Error Banner --}}
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
    
    {{-- Loading State --}}
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
    
    {{-- Content --}}
    <div x-show="!isLoading" class="space-y-6">
        {{-- KPI Strip --}}
        @include('admin.dashboard._kpis')
        
        {{-- Charts Section --}}
        @include('admin.dashboard._charts')
        
        {{-- Recent Activity --}}
        @include('admin.dashboard._activity')
    </div>
</div>
@endsection

@push('scripts')
<script>
    function adminDashboard() {
        return {
            // State management
            isLoading: false,
            lastRefresh: '',
            activityCursor: '',
            
            // Feature flag for mock data
            mockData: true, // Set to false to use real BE API
            
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
            
            // Charts moved to standalone implementation
            
            // Export functionality
            showExportModal: false,
            exportFormat: 'csv',
            exportRange: '30',
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
            abortController: null,
            chartInstances: {},
            
            init() {
                this.loadDashboardData();
                this.startPolling();
                // Charts handled independently
            },
            
            // API Integration
            async loadDashboardData() {
                // Abort previous request if still pending
                if (this.abortController) {
                    this.abortController.abort();
                }
                
                this.abortController = new AbortController();
                this.isLoading = true;
                this.error = null;
                
                try {
                    if (this.mockData) {
                        // Use mock data
                        await Promise.all([
                            this.loadKPIs(),
                            this.loadActivity()
                        ]);
                    } else {
                        // Use real BE API
                        await Promise.all([
                            this.loadKPIsFromAPI(),
                            this.loadActivityFromAPI()
                        ]);
                    }
                    this.lastUpdated = new Date().toISOString();
                } catch (err) {
                    if (err.name !== 'AbortError') {
                        this.error = err.message || 'Failed to load dashboard data';
                    }
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
            
            // Real BE API methods
            async loadKPIsFromAPI() {
                const response = await fetch('/api/admin/dashboard/kpis?period=30d', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: this.abortController.signal
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                this.kpis = data.data;
                
                // Check clock skew
                if (data.meta && data.meta.generatedAt) {
                    this.checkClockSkew(data.meta.generatedAt);
                }
                
                return data;
            },
            
            async loadChartsFromAPI() {
                const [signupsRes, errorsRes] = await Promise.all([
                    fetch('/api/admin/dashboard/charts/signups?period=30d', {
                        signal: this.abortController.signal
                    }),
                    fetch('/api/admin/dashboard/charts/errors?period=7d', {
                        signal: this.abortController.signal
                    })
                ]);
                
                if (!signupsRes.ok || !errorsRes.ok) {
                    throw new Error('Failed to load charts');
                }
                
                const [signupsData, errorsData] = await Promise.all([
                    signupsRes.json(),
                    errorsRes.json()
                ]);
                
                this.charts.signups = signupsData.data;
                this.charts.errors = errorsData.data;
            },
            
            async loadActivityFromAPI() {
                const response = await fetch('/api/admin/dashboard/activity?limit=20', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: this.abortController.signal
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                this.activity = data.data;
                return data;
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
                // Destroy existing charts
                this.destroyCharts();
                
                // Signups Chart
                const signupsCtx = document.getElementById('signupsChart');
                if (signupsCtx) {
                    this.chartInstances.signups = new Chart(signupsCtx, {
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
                    this.chartInstances.errors = new Chart(errorsCtx, {
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
            
            destroyCharts() {
                Object.values(this.chartInstances).forEach(chart => {
                    if (chart) {
                        chart.destroy();
                    }
                });
                this.chartInstances = {};
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
            
            // Timezone utilities
            formatDateTime(isoString, options = {}) {
                const date = new Date(isoString);
                const defaultOptions = {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    timeZoneName: 'short'
                };
                
                return new Intl.DateTimeFormat('en-US', { ...defaultOptions, ...options }).format(date);
            },
            
            checkClockSkew(serverTime, clientTime = new Date()) {
                const skew = new Date(serverTime) - clientTime;
                const skewMs = Math.abs(skew);
                
                if (skewMs > 2000) { // > 2 seconds
                    console.warn(`Clock skew detected: ${skewMs}ms between server and client`);
                }
                
                return skewMs;
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
                
                // Navigate to Alerts with critical filter
                window.location.href = '/admin/alerts?severity=critical&range=24h&sort=-created_at';
                
                // Log analytics event
                this.logEvent('preset_click', { preset: 'critical', target: 'alerts' });
            },
            
            applyActivePreset() {
                // Set signups period to 30d
                this.charts.signups.period = '30d';
                
                // Navigate to Users with active filter
                window.location.href = '/admin/users?status=active&sort=-last_login';
                
                // Log analytics event
                this.logEvent('preset_click', { preset: 'active', target: 'users' });
            },
            
            applyRecentPreset() {
                // Load recent activity and show drawer
                this.loadActivity();
                
                // Deep-link to dashboard with drawer
                const url = new URL(window.location);
                url.searchParams.set('drawer', 'recent');
                window.history.pushState({}, '', url);
                
                // In real implementation, would open activity drawer
                console.log('Loading recent activity...');
                
                // Log analytics event
                this.logEvent('preset_click', { preset: 'recent', target: 'activity_drawer' });
            },
            
            // Drill-down functions
            drillDownTenants() {
                window.location.href = '/admin/tenants?sort=-created_at';
                this.logEvent('kpi_drilldown', { kpi: 'tenants', target: 'tenants_list' });
            },
            
            drillDownUsers() {
                window.location.href = '/admin/users?status=active&sort=-last_login';
                this.logEvent('kpi_drilldown', { kpi: 'users', target: 'users_list' });
            },
            
            drillDownErrors() {
                window.location.href = '/admin/alerts?severity=error&range=24h';
                this.logEvent('kpi_drilldown', { kpi: 'errors', target: 'alerts_list' });
            },
            
            drillDownQueue() {
                window.location.href = '/admin/maintenance/tasks?tab=queue&filter=stalled,backlog';
                this.logEvent('kpi_drilldown', { kpi: 'queue', target: 'maintenance_tasks' });
            },
            
            drillDownStorage() {
                window.location.href = '/admin/settings?tab=storage&modal=top-consumers';
                this.logEvent('kpi_drilldown', { kpi: 'storage', target: 'storage_settings' });
            },
            
            // Export Functions removed - moved to standalone charts
            
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
            },
            
            // Analytics
            logEvent(eventName, meta = {}) {
                const event = {
                    event: eventName,
                    timestamp: new Date().toISOString(),
                    page: 'admin.dashboard',
                    ...meta
                };
                
                console.log('Analytics Event:', event);
                
                // In real implementation, send to analytics service
                // fetch('/api/analytics/events', {
                //     method: 'POST',
                //     headers: { 'Content-Type': 'application/json' },
                //     body: JSON.stringify(event)
                // });
            },
            
            // i18n placeholder
            t(key, params = {}) {
                const translations = {
                    'admin.dashboard.title': 'Dashboard',
                    'admin.dashboard.subtitle': 'System overview and key metrics',
                    'admin.dashboard.kpi.tenants': 'Total Tenants',
                    'admin.dashboard.kpi.users': 'Total Users',
                    'admin.dashboard.kpi.errors': 'Errors (24h)',
                    'admin.dashboard.kpi.queue': 'Queue Jobs',
                    'admin.dashboard.kpi.storage': 'Storage Used',
                    'admin.dashboard.preset.critical': 'Critical',
                    'admin.dashboard.preset.active': 'Active',
                    'admin.dashboard.preset.recent': 'Recent',
                    'admin.dashboard.action.refresh': 'Refresh',
                    'admin.dashboard.action.view_tenants': 'View Tenants',
                    'admin.dashboard.action.manage_users': 'Manage Users',
                    'admin.dashboard.action.view_errors': 'View Errors',
                    'admin.dashboard.action.monitor_queue': 'Monitor Queue',
                    'admin.dashboard.action.manage_storage': 'Manage Storage'
                };
                
                let text = translations[key] || key;
                
                // Replace parameters
                Object.keys(params).forEach(param => {
                    text = text.replace(`{${param}}`, params[param]);
                });
                
                return text;
            },
            
            // Dashboard refresh functionality
            refresh() {
                if (window.Dashboard) {
                    window.Dashboard.refresh();
                }
            },

            formatTime(date) {
                return date.toLocaleTimeString();
            },

            updateRefreshTime() {
                this.lastRefresh = this.formatTime(new Date());
            },

            // Chart management removed - standalone implementation

            // A11y helpers
            getAriaLabel(kpi, value, delta, period) {
                const kpiName = this.t(`admin.dashboard.kpi.${kpi}`);
                const deltaText = delta > 0 ? `up ${delta}%` : `down ${Math.abs(delta)}%`;
                return `View ${kpiName} — ${value} total, ${deltaText} in ${period}`;
            },

            // Initialize dashboard (second init method - REMOVE THIS DUPLICATE)
            initDashboard() {
                const startTime = performance.now();
                
                console.log('Initializing dashboard...');
                
                // Initialize refresh time
                this.lastRefresh = new Date().toLocaleTimeString();
                
                // Set up event listeners for enhanced modules
                this.setupEventListeners();
                
                // Trigger charts initialization
                setTimeout(() => {
                    const chartInitTime = performance.now();
                    
                    if (window.DashboardCharts) {
                        window.DashboardCharts.initialize();
                    }
                    
                    // Record dashboard load performance
                    const endTime = performance.now();
                    if (window.DashboardMonitor) {
                        window.DashboardMonitor.recordDashboardLoad(startTime, endTime);
                        window.DashboardMonitor.recordRefresh('initial');
                    }
                }, 100);
                
                console.log('Dashboard initialized');
            },

            // Set up event listeners for integration
            setupEventListeners() {
                // Listen for refresh events
                document.addEventListener('dashboard:refreshed', () => {
                    this.updateRefreshTime();
                });

                // Listen for KPI updates
                document.addEventListener('dashboard:kpisUpdated', (event) => {
                    console.log('KPIs updated:', event.detail.data);
                });

                // Listen for chart updates  
                document.addEventListener('dashboard:chartsUpdated', (event) => {
                    console.log('Charts updated:', event.detail.data);
                });

                // Listen for activity updates
                document.addEventListener('dashboard:activityUpdated', (event) => {
                    console.log('Activity updated:', event.detail.data);
                });
            }
        }
    }
</script>
@endpush
