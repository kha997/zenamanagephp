@extends('layouts.dashboard')

@section('title', 'Designer Dashboard')
@section('page-title', 'Designer Dashboard')
@section('page-description', 'Creative workflow and design management')
@section('user-initials', 'DS')
@section('user-name', 'Designer')

@section('content')
<div x-data="designerDashboard()">
    <!-- Design Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="dashboard-card metric-card green p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Active Projects</p>
                    <p class="text-3xl font-bold text-white">12</p>
                    <p class="text-white/80 text-sm">+3 this week</p>
                </div>
                <i class="fas fa-palette text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card blue p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Designs Completed</p>
                    <p class="text-3xl font-bold text-white">48</p>
                    <p class="text-white/80 text-sm">+8 this month</p>
                </div>
                <i class="fas fa-check-circle text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card orange p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Pending Reviews</p>
                    <p class="text-3xl font-bold text-white">7</p>
                    <p class="text-white/80 text-sm">-2 from yesterday</p>
                </div>
                <i class="fas fa-eye text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card purple p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Client Satisfaction</p>
                    <p class="text-3xl font-bold text-white">94%</p>
                    <p class="text-white/80 text-sm">+2% this month</p>
                </div>
                <i class="fas fa-star text-4xl text-white/60"></i>
            </div>
        </div>
    </div>

    <!-- Design Management -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Drawing Status -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Drawing Status</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">DW-2024-001</p>
                        <p class="text-sm text-gray-500">Foundation Plan</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-red-600">Pending Review</p>
                        <p class="text-sm text-gray-500">2 days overdue</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">DW-2024-002</p>
                        <p class="text-sm text-gray-500">Structural Details</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-yellow-600">In Progress</p>
                        <p class="text-sm text-gray-500">Due tomorrow</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">DW-2024-003</p>
                        <p class="text-sm text-gray-500">MEP Layout</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-green-600">Approved</p>
                        <p class="text-sm text-gray-500">Today</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submittal Tracking -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Submittal Tracking</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">SUB-2024-001</p>
                        <p class="text-sm text-gray-500">Material Specs</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-red-600">Rejected</p>
                        <p class="text-sm text-gray-500">Needs revision</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">SUB-2024-002</p>
                        <p class="text-sm text-gray-500">Equipment Data</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-yellow-600">Under Review</p>
                        <p class="text-sm text-gray-500">3 days</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">SUB-2024-003</p>
                        <p class="text-sm text-gray-500">Shop Drawings</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-green-600">Approved</p>
                        <p class="text-sm text-gray-500">Yesterday</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Technical Issues -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Technical Issues</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center p-3 bg-red-50 border border-red-200 rounded-lg">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-red-900">High Priority</p>
                        <p class="text-sm text-red-700">Structural conflict in foundation</p>
                    </div>
                </div>
                <div class="flex items-center p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <i class="fas fa-exclamation-circle text-yellow-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-yellow-900">Medium Priority</p>
                        <p class="text-sm text-yellow-700">MEP coordination needed</p>
                    </div>
                </div>
                <div class="flex items-center p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <i class="fas fa-info-circle text-blue-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-blue-900">Low Priority</p>
                        <p class="text-sm text-blue-700">Material specification update</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Design Portfolio -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Recent Designs -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Recent Designs</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View Portfolio</button>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-gray-100 rounded-lg h-32 flex items-center justify-center">
                    <div class="text-center">
                        <i class="fas fa-image text-2xl text-gray-400 mb-2"></i>
                        <p class="text-xs text-gray-500">Office Building</p>
                    </div>
                </div>
                <div class="bg-gray-100 rounded-lg h-32 flex items-center justify-center">
                    <div class="text-center">
                        <i class="fas fa-image text-2xl text-gray-400 mb-2"></i>
                        <p class="text-xs text-gray-500">Shopping Mall</p>
                    </div>
                </div>
                <div class="bg-gray-100 rounded-lg h-32 flex items-center justify-center">
                    <div class="text-center">
                        <i class="fas fa-image text-2xl text-gray-400 mb-2"></i>
                        <p class="text-xs text-gray-500">Residential</p>
                    </div>
                </div>
                <div class="bg-gray-100 rounded-lg h-32 flex items-center justify-center">
                    <div class="text-center">
                        <i class="fas fa-image text-2xl text-gray-400 mb-2"></i>
                        <p class="text-xs text-gray-500">Hotel Complex</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Design Tools -->
        <div class="dashboard-card p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Design Tools</h3>
            <div class="space-y-3">
                <button class="w-full flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                    <div class="flex items-center">
                        <i class="fas fa-paint-brush text-blue-500 mr-3"></i>
                        <span class="font-medium">AutoCAD</span>
                    </div>
                    <i class="fas fa-external-link-alt text-gray-400"></i>
                </button>
                <button class="w-full flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                    <div class="flex items-center">
                        <i class="fas fa-cube text-green-500 mr-3"></i>
                        <span class="font-medium">SketchUp</span>
                    </div>
                    <i class="fas fa-external-link-alt text-gray-400"></i>
                </button>
                <button class="w-full flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                    <div class="flex items-center">
                        <i class="fas fa-vector-square text-purple-500 mr-3"></i>
                        <span class="font-medium">Revit</span>
                    </div>
                    <i class="fas fa-external-link-alt text-gray-400"></i>
                </button>
                <button class="w-full flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                    <div class="flex items-center">
                        <i class="fas fa-palette text-pink-500 mr-3"></i>
                        <span class="font-medium">Photoshop</span>
                    </div>
                    <i class="fas fa-external-link-alt text-gray-400"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Project Status & Client Feedback -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Project Status -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Project Status</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Office Building Design</p>
                        <p class="text-sm text-gray-500">Concept Phase</p>
                    </div>
                    <div class="text-right">
                        <div class="w-16 bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: 25%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">25%</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Shopping Mall Layout</p>
                        <p class="text-sm text-gray-500">Design Phase</p>
                    </div>
                    <div class="text-right">
                        <div class="w-16 bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" style="width: 75%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">75%</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Residential Complex</p>
                        <p class="text-sm text-gray-500">Review Phase</p>
                    </div>
                    <div class="text-right">
                        <div class="w-16 bg-gray-200 rounded-full h-2">
                            <div class="bg-yellow-600 h-2 rounded-full" style="width: 90%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">90%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Client Feedback -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Recent Feedback</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-3">
                <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-star text-yellow-500 mr-1"></i>
                        <i class="fas fa-star text-yellow-500 mr-1"></i>
                        <i class="fas fa-star text-yellow-500 mr-1"></i>
                        <i class="fas fa-star text-yellow-500 mr-1"></i>
                        <i class="fas fa-star text-yellow-500 mr-2"></i>
                        <span class="text-sm font-medium text-gray-700">Office Building</span>
                    </div>
                    <p class="text-sm text-gray-600">"Excellent design concept, very innovative approach!"</p>
                </div>
                <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-star text-yellow-500 mr-1"></i>
                        <i class="fas fa-star text-yellow-500 mr-1"></i>
                        <i class="fas fa-star text-yellow-500 mr-1"></i>
                        <i class="fas fa-star text-yellow-500 mr-1"></i>
                        <i class="fas fa-star text-gray-300 mr-2"></i>
                        <span class="text-sm font-medium text-gray-700">Shopping Mall</span>
                    </div>
                    <p class="text-sm text-gray-600">"Good layout, please consider more parking spaces."</p>
                </div>
                <div class="p-3 bg-purple-50 border border-purple-200 rounded-lg">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-star text-yellow-500 mr-1"></i>
                        <i class="fas fa-star text-yellow-500 mr-1"></i>
                        <i class="fas fa-star text-yellow-500 mr-1"></i>
                        <i class="fas fa-star text-yellow-500 mr-1"></i>
                        <i class="fas fa-star text-yellow-500 mr-2"></i>
                        <span class="text-sm font-medium text-gray-700">Residential Complex</span>
                    </div>
                    <p class="text-sm text-gray-600">"Perfect! Ready for final approval."</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function designerDashboard() {
    return {
        // Design workflow management
        openDesignTool(tool) {
            // This would integrate with actual design tools
            console.log(`Opening ${tool}...`);
        },
        
        viewPortfolio() {
            window.location.href = '/portfolio';
        },
        
        createNewDesign() {
            window.location.href = '/designs/create';
        }
    }
}
</script>
@endsection