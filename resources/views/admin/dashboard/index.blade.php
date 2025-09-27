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
            <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
            <p class="text-gray-600">System overview and key metrics</p>
        </div>
        <div class="flex items-center space-x-3">
            <button @click="refreshData" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
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
            },
            
            refreshData() {
                // Simulate data refresh
                console.log('Refreshing dashboard data...');
                // In real implementation, this would fetch fresh data from API
            }
        }
    }
</script>
@endpush
