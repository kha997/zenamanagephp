@extends('layouts.dashboard')

@section('title', 'Finance Dashboard')
@section('page-title', 'Finance Dashboard')
@section('page-description', 'Financial management and budget oversight')
@section('user-initials', 'FD')
@section('user-name', 'Finance Director')

@section('content')
<div x-data="financeDashboard()">
    <!-- Financial Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="dashboard-card metric-card green p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Total Revenue</p>
                    <p class="text-3xl font-bold text-white">$2.4M</p>
                    <p class="text-white/80 text-sm">+12.5% from last month</p>
                </div>
                <i class="fas fa-chart-line text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card blue p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Total Expenses</p>
                    <p class="text-3xl font-bold text-white">$1.8M</p>
                    <p class="text-white/80 text-sm">+8.2% from last month</p>
                </div>
                <i class="fas fa-chart-pie text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card orange p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Net Profit</p>
                    <p class="text-3xl font-bold text-white">$600K</p>
                    <p class="text-white/80 text-sm">+18.3% from last month</p>
                </div>
                <i class="fas fa-coins text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card purple p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Cash Flow</p>
                    <p class="text-3xl font-bold text-white">$450K</p>
                    <p class="text-white/80 text-sm">+15.7% from last month</p>
                </div>
                <i class="fas fa-wallet text-4xl text-white/60"></i>
            </div>
        </div>
    </div>

    <!-- Charts and Analysis -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Revenue Chart -->
        <div class="dashboard-card p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Revenue Trend</h3>
            <div class="h-64 bg-gray-50 rounded-lg flex items-center justify-center">
                <div class="text-center">
                    <i class="fas fa-chart-area text-4xl text-gray-400 mb-2"></i>
                    <p class="text-gray-500">Revenue chart will be displayed here</p>
                    <p class="text-sm text-gray-400">Integration with Chart.js coming soon</p>
                </div>
            </div>
        </div>

        <!-- Expense Breakdown -->
        <div class="dashboard-card p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Expense Breakdown</h3>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-blue-500 rounded mr-3"></div>
                        <span class="text-gray-700">Labor Costs</span>
                    </div>
                    <span class="font-semibold">$850K (47%)</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-green-500 rounded mr-3"></div>
                        <span class="text-gray-700">Materials</span>
                    </div>
                    <span class="font-semibold">$420K (23%)</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-yellow-500 rounded mr-3"></div>
                        <span class="text-gray-700">Equipment</span>
                    </div>
                    <span class="font-semibold">$280K (16%)</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-red-500 rounded mr-3"></div>
                        <span class="text-gray-700">Overhead</span>
                    </div>
                    <span class="font-semibold">$250K (14%)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Invoices -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Recent Invoices</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">INV-2024-001</p>
                        <p class="text-sm text-gray-500">Office Building Project</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-green-600">$125,000</p>
                        <p class="text-sm text-gray-500">Paid</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">INV-2024-002</p>
                        <p class="text-sm text-gray-500">Shopping Mall Project</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-yellow-600">$85,000</p>
                        <p class="text-sm text-gray-500">Pending</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">INV-2024-003</p>
                        <p class="text-sm text-gray-500">Residential Complex</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-green-600">$95,000</p>
                        <p class="text-sm text-gray-500">Paid</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Budget Alerts -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Budget Alerts</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">Manage</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center p-3 bg-red-50 border border-red-200 rounded-lg">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-red-900">Office Building Project</p>
                        <p class="text-sm text-red-700">Budget exceeded by 15%</p>
                    </div>
                </div>
                <div class="flex items-center p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <i class="fas fa-exclamation-circle text-yellow-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-yellow-900">Shopping Mall Project</p>
                        <p class="text-sm text-yellow-700">Approaching budget limit</p>
                    </div>
                </div>
                <div class="flex items-center p-3 bg-green-50 border border-green-200 rounded-lg">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-green-900">Residential Complex</p>
                        <p class="text-sm text-green-700">Within budget</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function financeDashboard() {
    return {
        // Real-time data updates
        refreshData() {
            // This would connect to real API endpoints
            console.log('Refreshing finance data...');
        },
        
        // Quick actions
        createInvoice() {
            window.location.href = '/invoices/create';
        },
        
        viewReports() {
            window.location.href = '/reports/financial';
        }
    }
}
</script>
@endsection