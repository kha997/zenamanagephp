<?php $__env->startSection('title', 'Project Manager Dashboard'); ?>
<?php $__env->startSection('page-title', 'Project Manager Dashboard'); ?>
<?php $__env->startSection('page-description', 'Project oversight and team management'); ?>
<?php $__env->startSection('user-initials', 'PM'); ?>
<?php $__env->startSection('user-name', 'Project Manager'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="projectManagerDashboard()">
    <!-- Project Manager Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="dashboard-card metric-card green p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Active Projects</p>
                    <p class="text-3xl font-bold text-white">8</p>
                    <p class="text-white/80 text-sm">+2 this week</p>
                </div>
                <i class="fas fa-project-diagram text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card blue p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Open Tasks</p>
                    <p class="text-3xl font-bold text-white">23</p>
                    <p class="text-white/80 text-sm">+5 this week</p>
                </div>
                <i class="fas fa-tasks text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card orange p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Overdue Tasks</p>
                    <p class="text-3xl font-bold text-white">3</p>
                    <p class="text-white/80 text-sm">-1 from yesterday</p>
                </div>
                <i class="fas fa-exclamation-triangle text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card purple p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">On Schedule</p>
                    <p class="text-3xl font-bold text-white">20</p>
                    <p class="text-white/80 text-sm">+3 this week</p>
                </div>
                <i class="fas fa-check-circle text-4xl text-white/60"></i>
            </div>
        </div>
    </div>

    <!-- Project Management Tools -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- RFI Status -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">RFI Status</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">RFI-2024-001</p>
                        <p class="text-sm text-gray-500">Electrical Design Clarification</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-red-600">Pending</p>
                        <p class="text-sm text-gray-500">2 days</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">RFI-2024-002</p>
                        <p class="text-sm text-gray-500">Material Specification</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-yellow-600">Under Review</p>
                        <p class="text-sm text-gray-500">1 day</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">RFI-2024-003</p>
                        <p class="text-sm text-gray-500">Structural Details</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-green-600">Resolved</p>
                        <p class="text-sm text-gray-500">Today</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Budget Tracking -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Budget Tracking</h3>
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

        <!-- Change Requests -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Change Requests</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">CR-2024-001</p>
                        <p class="text-sm text-gray-500">Design Modification</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-red-600">Pending Approval</p>
                        <p class="text-sm text-gray-500">$15K impact</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">CR-2024-002</p>
                        <p class="text-sm text-gray-500">Material Substitution</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-yellow-600">Under Review</p>
                        <p class="text-sm text-gray-500">$8K impact</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">CR-2024-003</p>
                        <p class="text-sm text-gray-500">Scope Addition</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-green-600">Approved</p>
                        <p class="text-sm text-gray-500">$25K impact</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- My Projects -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Active Projects -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">My Projects</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-4">
                <div class="p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-semibold text-gray-900">Office Building Complex</h4>
                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">Active</span>
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
                        <span>My Tasks: 8</span>
                    </div>
                </div>
                
                <div class="p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-semibold text-gray-900">Shopping Mall Development</h4>
                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-medium rounded-full">In Progress</span>
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
                        <span>My Tasks: 5</span>
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
                        <span>My Tasks: 3</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Tasks -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Upcoming Tasks</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Review Design</p>
                        <p class="text-sm text-gray-500">Office Building Complex</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium text-red-600">High Priority</p>
                        <p class="text-xs text-gray-500">Due: Jan 20</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Site Inspection</p>
                        <p class="text-sm text-gray-500">Shopping Mall Development</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium text-yellow-600">Medium Priority</p>
                        <p class="text-xs text-gray-500">Due: Jan 22</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Budget Review</p>
                        <p class="text-sm text-gray-500">Residential Complex</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium text-blue-600">Low Priority</p>
                        <p class="text-xs text-gray-500">Due: Jan 25</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Team Meeting</p>
                        <p class="text-sm text-gray-500">All Projects</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium text-green-600">Scheduled</p>
                        <p class="text-xs text-gray-500">Due: Jan 18</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Team Management & Project Analytics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Team Performance -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Team Performance</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View Details</button>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold mr-3">
                            JS
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">John Smith</p>
                            <p class="text-sm text-gray-500">Site Engineer</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-green-600">95%</p>
                        <p class="text-sm text-gray-500">Completion</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white font-semibold mr-3">
                            SW
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Sarah Wilson</p>
                            <p class="text-sm text-gray-500">Designer</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-green-600">88%</p>
                        <p class="text-sm text-gray-500">Completion</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center text-white font-semibold mr-3">
                            MJ
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Mike Johnson</p>
                            <p class="text-sm text-gray-500">Developer</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-yellow-600">75%</p>
                        <p class="text-sm text-gray-500">Completion</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Project Analytics -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Project Analytics</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View Reports</button>
            </div>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Budget Utilization</span>
                        <span class="text-sm font-medium text-gray-900">78%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full" style="width: 78%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Timeline Adherence</span>
                        <span class="text-sm font-medium text-gray-900">85%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: 85%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Quality Score</span>
                        <span class="text-sm font-medium text-gray-900">92%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-purple-600 h-2 rounded-full" style="width: 92%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Client Satisfaction</span>
                        <span class="text-sm font-medium text-gray-900">94%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-yellow-600 h-2 rounded-full" style="width: 94%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function projectManagerDashboard() {
    return {
        // Project management functions
        createProject() {
            window.location.href = '/projects/create';
        },
        
        assignTask() {
            window.location.href = '/tasks/create';
        },
        
        viewProjectDetails(projectId) {
            window.location.href = `/projects/${projectId}`;
        },
        
        scheduleMeeting() {
            window.location.href = '/meetings/schedule';
        },
        
        generateReport() {
            window.location.href = '/reports/project';
        }
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/dashboards/pm.blade.php ENDPATH**/ ?>