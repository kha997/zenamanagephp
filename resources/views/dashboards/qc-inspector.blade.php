@extends('layouts.dashboard')

@section('title', 'QC Inspector Dashboard')
@section('page-title', 'QC Inspector Dashboard')
@section('page-description', 'Quality control and inspection management')
@section('user-initials', 'QC')
@section('user-name', 'QC Inspector')

@section('content')
<div x-data="qcInspectorDashboard()">
    <!-- QC Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="dashboard-card metric-card green p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Inspections Today</p>
                    <p class="text-3xl font-bold text-white">12</p>
                    <p class="text-white/80 text-sm">+3 this week</p>
                </div>
                <i class="fas fa-clipboard-check text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card blue p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Pass Rate</p>
                    <p class="text-3xl font-bold text-white">94%</p>
                    <p class="text-white/80 text-sm">+2% this month</p>
                </div>
                <i class="fas fa-check-circle text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card orange p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">NCRs Issued</p>
                    <p class="text-3xl font-bold text-white">3</p>
                    <p class="text-white/80 text-sm">-1 from yesterday</p>
                </div>
                <i class="fas fa-exclamation-triangle text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card purple p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Pending Reviews</p>
                    <p class="text-3xl font-bold text-white">7</p>
                    <p class="text-white/80 text-sm">+2 this week</p>
                </div>
                <i class="fas fa-clock text-4xl text-white/60"></i>
            </div>
        </div>
    </div>

    <!-- Inspection Management -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Today's Inspections -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Today's Inspections</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                        <div>
                            <p class="font-medium text-gray-900">Concrete Quality Test</p>
                            <p class="text-sm text-gray-500">Office Building Site A - 09:00</p>
                        </div>
                    </div>
                    <span class="text-sm font-medium text-green-600">Completed</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-yellow-500 rounded-full mr-3"></div>
                        <div>
                            <p class="font-medium text-gray-900">Steel Frame Inspection</p>
                            <p class="text-sm text-gray-500">Shopping Mall Site B - 14:00</p>
                        </div>
                    </div>
                    <span class="text-sm font-medium text-yellow-600">In Progress</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                        <div>
                            <p class="font-medium text-gray-900">Electrical Installation</p>
                            <p class="text-sm text-gray-500">Residential Site C - 16:00</p>
                        </div>
                    </div>
                    <span class="text-sm font-medium text-blue-600">Scheduled</span>
                </div>
            </div>
        </div>

        <!-- Quality Alerts -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Quality Alerts</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center p-3 bg-red-50 border border-red-200 rounded-lg">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-red-900">High Priority</p>
                        <p class="text-sm text-red-700">Concrete strength below specification</p>
                    </div>
                </div>
                <div class="flex items-center p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <i class="fas fa-exclamation-circle text-yellow-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-yellow-900">Medium Priority</p>
                        <p class="text-sm text-yellow-700">Welding quality needs review</p>
                    </div>
                </div>
                <div class="flex items-center p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <i class="fas fa-info-circle text-blue-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-blue-900">Low Priority</p>
                        <p class="text-sm text-blue-700">Material delivery inspection due</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- NCR Management & Inspection Reports -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- NCR Management -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">NCR Management</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">NCR-2024-001</p>
                        <p class="text-sm text-gray-500">Concrete Quality Issue - Office Building</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-red-600">Open</p>
                        <p class="text-sm text-gray-500">2 days ago</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">NCR-2024-002</p>
                        <p class="text-sm text-gray-500">Steel Frame Alignment - Shopping Mall</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-yellow-600">Under Review</p>
                        <p class="text-sm text-gray-500">1 day ago</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">NCR-2024-003</p>
                        <p class="text-sm text-gray-500">Electrical Installation - Residential</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-green-600">Closed</p>
                        <p class="text-sm text-gray-500">3 days ago</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inspection Reports -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Recent Reports</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">Generate Report</button>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Daily Quality Report</p>
                        <p class="text-sm text-gray-500">Office Building Site A</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-green-600">Passed</p>
                        <p class="text-sm text-gray-500">Today</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Weekly Safety Inspection</p>
                        <p class="text-sm text-gray-500">Shopping Mall Site B</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-yellow-600">Minor Issues</p>
                        <p class="text-sm text-gray-500">Yesterday</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Material Quality Check</p>
                        <p class="text-sm text-gray-500">Residential Site C</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-red-600">Failed</p>
                        <p class="text-sm text-gray-500">2 days ago</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function qcInspectorDashboard() {
    return {
        // QC management functions
        scheduleInspection() {
            window.location.href = '/inspections/schedule';
        },
        
        generateNCR() {
            window.location.href = '/ncr/create';
        },
        
        viewInspectionDetails(inspectionId) {
            window.location.href = `/inspections/${inspectionId}`;
        },
        
        generateQualityReport() {
            window.location.href = '/reports/quality';
        },
        
        updateInspectionStatus(inspectionId, status) {
            // This would update the inspection status via API
            console.log(`Updating inspection ${inspectionId} to ${status}`);
        }
    }
}
</script>
@endsection
