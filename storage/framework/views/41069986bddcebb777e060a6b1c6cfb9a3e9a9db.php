<?php $__env->startSection('title', 'Client Dashboard'); ?>
<?php $__env->startSection('page-title', 'Client Dashboard'); ?>
<?php $__env->startSection('page-description', 'Client portal and project visibility'); ?>
<?php $__env->startSection('user-initials', 'CL'); ?>
<?php $__env->startSection('user-name', 'Client'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="clientDashboard()">
    <!-- Project Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="dashboard-card metric-card green p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Active Projects</p>
                    <p class="text-3xl font-bold text-white">3</p>
                    <p class="text-white/80 text-sm">All on schedule</p>
                </div>
                <i class="fas fa-project-diagram text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card blue p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Total Investment</p>
                    <p class="text-3xl font-bold text-white">$2.1M</p>
                    <p class="text-white/80 text-sm">Within budget</p>
                </div>
                <i class="fas fa-dollar-sign text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card orange p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Documents</p>
                    <p class="text-3xl font-bold text-white">47</p>
                    <p class="text-white/80 text-sm">+3 this week</p>
                </div>
                <i class="fas fa-file-alt text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card purple p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Change Requests</p>
                    <p class="text-3xl font-bold text-white">5</p>
                    <p class="text-white/80 text-sm">+1 this week</p>
                </div>
                <i class="fas fa-edit text-4xl text-white/60"></i>
            </div>
        </div>
    </div>

    <!-- Client Management Tools -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Document Approval -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Document Approval</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Design Review</p>
                        <p class="text-sm text-gray-500">Office Building - Final Plans</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-red-600">Pending</p>
                        <p class="text-sm text-gray-500">2 days</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Material Specs</p>
                        <p class="text-sm text-gray-500">Shopping Mall - Flooring</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-yellow-600">Under Review</p>
                        <p class="text-sm text-gray-500">1 day</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Change Order</p>
                        <p class="text-sm text-gray-500">Residential - Kitchen Upgrade</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-green-600">Approved</p>
                        <p class="text-sm text-gray-500">Today</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Budget Overview -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Budget Overview</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View Details</button>
            </div>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700">Office Building</span>
                    <span class="text-sm font-medium text-gray-900">$2.1M / $2.5M</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-green-600 h-2 rounded-full" style="width: 84%"></div>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700">Shopping Mall</span>
                    <span class="text-sm font-medium text-gray-900">$1.8M / $2.0M</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-yellow-600 h-2 rounded-full" style="width: 90%"></div>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700">Residential</span>
                    <span class="text-sm font-medium text-gray-900">$0.8M / $1.2M</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full" style="width: 67%"></div>
                </div>
            </div>
        </div>

        <!-- Communication Center -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Communication Center</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <i class="fas fa-envelope text-blue-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-blue-900">New Message</p>
                        <p class="text-sm text-blue-700">Project Manager - Weekly Update</p>
                    </div>
                </div>
                <div class="flex items-center p-3 bg-green-50 border border-green-200 rounded-lg">
                    <i class="fas fa-calendar text-green-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-green-900">Meeting Scheduled</p>
                        <p class="text-sm text-green-700">Design Review - Tomorrow 2PM</p>
                    </div>
                </div>
                <div class="flex items-center p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <i class="fas fa-bell text-yellow-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-yellow-900">Alert</p>
                        <p class="text-sm text-yellow-700">Budget variance detected</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Project Visibility -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Project Status -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Project Status</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-4">
                <div class="p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-semibold text-gray-900">Office Building Complex</h4>
                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">On Schedule</span>
                    </div>
                    <div class="mb-3">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm text-gray-600">Progress</span>
                            <span class="text-sm font-medium text-gray-900">75%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" style="width: 75%"></div>
                        </div>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Due: Mar 15, 2024</span>
                        <span>Budget: $2.1M / $2.5M</span>
                    </div>
                </div>
                
                <div class="p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-semibold text-gray-900">Shopping Mall Development</h4>
                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-medium rounded-full">Minor Delay</span>
                    </div>
                    <div class="mb-3">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm text-gray-600">Progress</span>
                            <span class="text-sm font-medium text-gray-900">45%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-yellow-600 h-2 rounded-full" style="width: 45%"></div>
                        </div>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Due: Feb 28, 2024</span>
                        <span>Budget: $1.8M / $2.0M</span>
                    </div>
                </div>
                
                <div class="p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-semibold text-gray-900">Residential Complex</h4>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded-full">Planning</span>
                    </div>
                    <div class="mb-3">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm text-gray-600">Progress</span>
                            <span class="text-sm font-medium text-gray-900">15%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: 15%"></div>
                        </div>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Due: Dec 15, 2024</span>
                        <span>Budget: $0.8M / $1.2M</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Recent Activities</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center p-3 bg-green-50 border border-green-200 rounded-lg">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-gray-900">Design Approved</p>
                        <p class="text-sm text-gray-500">Office Building - Foundation Plan</p>
                        <p class="text-xs text-gray-400">2 hours ago</p>
                    </div>
                </div>
                <div class="flex items-center p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <i class="fas fa-file-alt text-blue-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-gray-900">Document Received</p>
                        <p class="text-sm text-gray-500">Shopping Mall - Progress Report</p>
                        <p class="text-xs text-gray-400">4 hours ago</p>
                    </div>
                </div>
                <div class="flex items-center p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <i class="fas fa-exclamation-triangle text-yellow-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-gray-900">Change Request</p>
                        <p class="text-sm text-gray-500">Residential - Kitchen Upgrade</p>
                        <p class="text-xs text-gray-400">1 day ago</p>
                    </div>
                </div>
                <div class="flex items-center p-3 bg-purple-50 border border-purple-200 rounded-lg">
                    <i class="fas fa-calendar text-purple-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-gray-900">Meeting Scheduled</p>
                        <p class="text-sm text-gray-500">Design Review - Tomorrow 2PM</p>
                        <p class="text-xs text-gray-400">2 days ago</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function clientDashboard() {
    return {
        // Client management functions
        approveDocument(documentId) {
            window.location.href = `/documents/${documentId}/approve`;
        },
        
        rejectDocument(documentId) {
            window.location.href = `/documents/${documentId}/reject`;
        },
        
        viewProjectDetails(projectId) {
            window.location.href = `/projects/${projectId}`;
        },
        
        scheduleMeeting() {
            window.location.href = '/meetings/schedule';
        },
        
        sendMessage() {
            window.location.href = '/messages/compose';
        },
        
        viewBudgetDetails(projectId) {
            window.location.href = `/projects/${projectId}/budget`;
        }
    }
}
</script>

<div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Messages</p>
                    <p class="text-3xl font-bold text-white">12</p>
                    <p class="text-white/80 text-sm">2 unread</p>
                </div>
                <i class="fas fa-comments text-4xl text-white/60"></i>
            </div>
        </div>
    </div>

    <!-- Project Status -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- My Projects -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">My Projects</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-4">
                <div class="p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-semibold text-gray-900">Office Building Complex</h4>
                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">On Schedule</span>
                    </div>
                    <div class="mb-3">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm text-gray-600">Progress</span>
                            <span class="text-sm font-medium text-gray-900">75%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" style="width: 75%"></div>
                        </div>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Budget: $850K</span>
                        <span>Due: Mar 15, 2024</span>
                    </div>
                </div>
                
                <div class="p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-semibold text-gray-900">Shopping Mall Development</h4>
                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-medium rounded-full">Minor Delay</span>
                    </div>
                    <div class="mb-3">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm text-gray-600">Progress</span>
                            <span class="text-sm font-medium text-gray-900">45%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-yellow-600 h-2 rounded-full" style="width: 45%"></div>
                        </div>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Budget: $1.2M</span>
                        <span>Due: Jun 30, 2024</span>
                    </div>
                </div>
                
                <div class="p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-semibold text-gray-900">Residential Complex</h4>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded-full">Planning</span>
                    </div>
                    <div class="mb-3">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm text-gray-600">Progress</span>
                            <span class="text-sm font-medium text-gray-900">15%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: 15%"></div>
                        </div>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Budget: $650K</span>
                        <span>Due: Dec 15, 2024</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Updates -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Recent Updates</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-start p-3 bg-green-50 border border-green-200 rounded-lg">
                    <i class="fas fa-check-circle text-green-500 mr-3 mt-1"></i>
                    <div>
                        <p class="font-medium text-gray-900">Foundation Complete</p>
                        <p class="text-sm text-gray-600">Office Building Complex - Foundation work completed ahead of schedule</p>
                        <p class="text-xs text-gray-500">2 hours ago</p>
                    </div>
                </div>
                <div class="flex items-start p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <i class="fas fa-file-alt text-blue-500 mr-3 mt-1"></i>
                    <div>
                        <p class="font-medium text-gray-900">New Document Uploaded</p>
                        <p class="text-sm text-gray-600">Shopping Mall Development - Updated architectural plans</p>
                        <p class="text-xs text-gray-500">5 hours ago</p>
                    </div>
                </div>
                <div class="flex items-start p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <i class="fas fa-exclamation-triangle text-yellow-500 mr-3 mt-1"></i>
                    <div>
                        <p class="font-medium text-gray-900">Weather Delay</p>
                        <p class="text-sm text-gray-600">Residential Complex - Construction delayed due to weather</p>
                        <p class="text-xs text-gray-500">1 day ago</p>
                    </div>
                </div>
                <div class="flex items-start p-3 bg-purple-50 border border-purple-200 rounded-lg">
                    <i class="fas fa-calendar text-purple-500 mr-3 mt-1"></i>
                    <div>
                        <p class="font-medium text-gray-900">Site Visit Scheduled</p>
                        <p class="text-sm text-gray-600">Office Building Complex - Site inspection scheduled</p>
                        <p class="text-xs text-gray-500">2 days ago</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Communication & Documents -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Team Communication -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Team Communication</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">Start Chat</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                    <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold mr-3">
                        PM
                    </div>
                    <div class="flex-1">
                        <p class="font-medium text-gray-900">Project Manager</p>
                        <p class="text-sm text-gray-500">John Smith</p>
                    </div>
                    <div class="text-right">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        <p class="text-xs text-gray-500">Online</p>
                    </div>
                </div>
                <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                    <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white font-semibold mr-3">
                        SE
                    </div>
                    <div class="flex-1">
                        <p class="font-medium text-gray-900">Site Engineer</p>
                        <p class="text-sm text-gray-500">Mike Johnson</p>
                    </div>
                    <div class="text-right">
                        <span class="w-2 h-2 bg-yellow-500 rounded-full"></span>
                        <p class="text-xs text-gray-500">Away</p>
                    </div>
                </div>
                <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                    <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center text-white font-semibold mr-3">
                        DS
                    </div>
                    <div class="flex-1">
                        <p class="font-medium text-gray-900">Designer</p>
                        <p class="text-sm text-gray-500">Sarah Wilson</p>
                    </div>
                    <div class="text-right">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        <p class="text-xs text-gray-500">Online</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Access -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Document Access</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-file-pdf text-red-500 mr-3"></i>
                        <div>
                            <p class="font-medium text-gray-900">Project Contract</p>
                            <p class="text-sm text-gray-500">Office Building Complex</p>
                        </div>
                    </div>
                    <button class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-file-image text-blue-500 mr-3"></i>
                        <div>
                            <p class="font-medium text-gray-900">Architectural Plans</p>
                            <p class="text-sm text-gray-500">Shopping Mall Development</p>
                        </div>
                    </div>
                    <button class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-file-alt text-green-500 mr-3"></i>
                        <div>
                            <p class="font-medium text-gray-900">Progress Report</p>
                            <p class="text-sm text-gray-500">Residential Complex</p>
                        </div>
                    </div>
                    <button class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-file-excel text-green-600 mr-3"></i>
                        <div>
                            <p class="font-medium text-gray-900">Budget Breakdown</p>
                            <p class="text-sm text-gray-500">All Projects</p>
                        </div>
                    </div>
                    <button class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function clientDashboard() {
    return {
        // Client portal functions
        downloadDocument(documentId) {
            window.location.href = `/documents/${documentId}/download`;
        },
        
        startChat(userId) {
            window.location.href = `/chat/${userId}`;
        },
        
        viewProjectDetails(projectId) {
            window.location.href = `/projects/${projectId}`;
        },
        
        scheduleMeeting() {
            window.location.href = '/meetings/schedule';
        }
    }
}
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.dashboard', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/dashboards/client.blade.php ENDPATH**/ ?>