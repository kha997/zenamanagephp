@extends('layouts.dashboard')

@section('title', 'Site Engineer Dashboard')
@section('page-title', 'Site Engineer Dashboard')
@section('page-description', 'Construction site management and monitoring')
@section('user-initials', 'SE')
@section('user-name', 'Site Engineer')

@section('content')
<div x-data="siteEngineerDashboard()">
    <!-- Site Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="dashboard-card metric-card green p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Active Sites</p>
                    <p class="text-3xl font-bold text-white">8</p>
                    <p class="text-white/80 text-sm">+1 this week</p>
                </div>
                <i class="fas fa-hard-hat text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card blue p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Safety Score</p>
                    <p class="text-3xl font-bold text-white">96%</p>
                    <p class="text-white/80 text-sm">+2% this month</p>
                </div>
                <i class="fas fa-shield-alt text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card orange p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Quality Issues</p>
                    <p class="text-3xl font-bold text-white">3</p>
                    <p class="text-white/80 text-sm">-1 from yesterday</p>
                </div>
                <i class="fas fa-exclamation-triangle text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card purple p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Progress Rate</p>
                    <p class="text-3xl font-bold text-white">87%</p>
                    <p class="text-white/80 text-sm">+5% this week</p>
                </div>
                <i class="fas fa-chart-line text-4xl text-white/60"></i>
            </div>
        </div>
    </div>

    <!-- Site Management Tools -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Daily Tasks -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Daily Tasks</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Site Inspection</p>
                        <p class="text-sm text-gray-500">Office Building Site A</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-red-600">Overdue</p>
                        <p class="text-sm text-gray-500">2 hours</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Progress Report</p>
                        <p class="text-sm text-gray-500">Shopping Mall Site B</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-yellow-600">Due Soon</p>
                        <p class="text-sm text-gray-500">1 hour</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Safety Check</p>
                        <p class="text-sm text-gray-500">Residential Site C</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-green-600">Completed</p>
                        <p class="text-sm text-gray-500">30 min ago</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Site Diary -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Site Diary</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">Add Entry</button>
            </div>
            <div class="space-y-3">
                <div class="p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700">Office Building Site A</span>
                        <span class="text-xs text-gray-500">09:30</span>
                    </div>
                    <p class="text-sm text-gray-600">Foundation work completed. Concrete curing on schedule.</p>
                </div>
                <div class="p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700">Shopping Mall Site B</span>
                        <span class="text-xs text-gray-500">11:15</span>
                    </div>
                    <p class="text-sm text-gray-600">Steel frame installation progressing well. Minor weather delay.</p>
                </div>
                <div class="p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700">Residential Site C</span>
                        <span class="text-xs text-gray-500">14:45</span>
                    </div>
                    <p class="text-sm text-gray-600">Electrical rough-in completed. Ready for inspection.</p>
                </div>
            </div>
        </div>

        <!-- Weather Forecast -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Weather Forecast</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View Details</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-sun text-yellow-500 mr-3"></i>
                        <div>
                            <p class="font-medium text-gray-900">Today</p>
                            <p class="text-sm text-gray-500">Clear, 25°C</p>
                        </div>
                    </div>
                    <span class="text-sm font-medium text-green-600">Good</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-cloud-rain text-blue-500 mr-3"></i>
                        <div>
                            <p class="font-medium text-gray-900">Tomorrow</p>
                            <p class="text-sm text-gray-500">Light rain, 22°C</p>
                        </div>
                    </div>
                    <span class="text-sm font-medium text-yellow-600">Caution</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-cloud-sun text-orange-500 mr-3"></i>
                        <div>
                            <p class="font-medium text-gray-900">Day After</p>
                            <p class="text-sm text-gray-500">Partly cloudy, 24°C</p>
                        </div>
                    </div>
                    <span class="text-sm font-medium text-green-600">Good</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Site Monitoring -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Site Status -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Site Status</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All Sites</button>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                        <div>
                            <p class="font-medium text-gray-900">Office Building Site A</p>
                            <p class="text-sm text-gray-500">Foundation Complete</p>
                        </div>
                    </div>
                    <span class="text-sm font-medium text-green-600">On Schedule</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-yellow-500 rounded-full mr-3"></div>
                        <div>
                            <p class="font-medium text-gray-900">Shopping Mall Site B</p>
                            <p class="text-sm text-gray-500">Steel Frame Work</p>
                        </div>
                    </div>
                    <span class="text-sm font-medium text-yellow-600">Minor Delay</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-red-500 rounded-full mr-3"></div>
                        <div>
                            <p class="font-medium text-gray-900">Residential Site C</p>
                            <p class="text-sm text-gray-500">Weather Delay</p>
                        </div>
                    </div>
                    <span class="text-sm font-medium text-red-600">Delayed</span>
                </div>
            </div>
        </div>

        <!-- Safety Alerts -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Safety Alerts</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center p-3 bg-red-50 border border-red-200 rounded-lg">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-red-900">High Priority</p>
                        <p class="text-sm text-red-700">Safety equipment check overdue</p>
                    </div>
                </div>
                <div class="flex items-center p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <i class="fas fa-exclamation-circle text-yellow-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-yellow-900">Medium Priority</p>
                        <p class="text-sm text-yellow-700">Weather conditions monitoring</p>
                    </div>
                </div>
                <div class="flex items-center p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <i class="fas fa-info-circle text-blue-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-blue-900">Low Priority</p>
                        <p class="text-sm text-blue-700">Weekly safety training scheduled</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quality Control & Progress Tracking -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Quality Control -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Quality Control</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View Reports</button>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Concrete Quality Test</p>
                        <p class="text-sm text-gray-500">Office Building Site A</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-green-600">Passed</p>
                        <p class="text-sm text-gray-500">Today</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Steel Frame Inspection</p>
                        <p class="text-sm text-gray-500">Shopping Mall Site B</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-yellow-600">Pending</p>
                        <p class="text-sm text-gray-500">Tomorrow</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Electrical Installation</p>
                        <p class="text-sm text-gray-500">Residential Site C</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-red-600">Failed</p>
                        <p class="text-sm text-gray-500">Yesterday</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Tracking -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Progress Tracking</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View Details</button>
            </div>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Office Building</span>
                        <span class="text-sm font-medium text-gray-900">75%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full" style="width: 75%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Shopping Mall</span>
                        <span class="text-sm font-medium text-gray-900">45%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: 45%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Residential Complex</span>
                        <span class="text-sm font-medium text-gray-900">30%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-yellow-600 h-2 rounded-full" style="width: 30%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Hotel Complex</span>
                        <span class="text-sm font-medium text-gray-900">15%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-purple-600 h-2 rounded-full" style="width: 15%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function siteEngineerDashboard() {
    return {
        // Site management functions
        viewSiteDetails(siteId) {
            window.location.href = `/sites/${siteId}`;
        },
        
        generateSafetyReport() {
            window.location.href = '/reports/safety';
        },
        
        scheduleInspection() {
            window.location.href = '/inspections/schedule';
        },
        
        updateProgress(projectId) {
            window.location.href = `/projects/${projectId}/progress`;
        }
    }
}
</script>
@endsection