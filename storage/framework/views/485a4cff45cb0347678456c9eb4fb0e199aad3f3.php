<?php $__env->startSection('title', 'Subcontractor Lead Dashboard'); ?>
<?php $__env->startSection('page-title', 'Subcontractor Lead Dashboard'); ?>
<?php $__env->startSection('page-description', 'Subcontractor project management and progress tracking'); ?>
<?php $__env->startSection('user-initials', 'SC'); ?>
<?php $__env->startSection('user-name', 'Subcontractor Lead'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="subcontractorLeadDashboard()">
    <!-- Subcontractor Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="dashboard-card metric-card green p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Active Contracts</p>
                    <p class="text-3xl font-bold text-white">5</p>
                    <p class="text-white/80 text-sm">+1 this month</p>
                </div>
                <i class="fas fa-handshake text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card blue p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Progress Rate</p>
                    <p class="text-3xl font-bold text-white">78%</p>
                    <p class="text-white/80 text-sm">+5% this week</p>
                </div>
                <i class="fas fa-chart-line text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card orange p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Pending Payments</p>
                    <p class="text-3xl font-bold text-white">$45K</p>
                    <p class="text-white/80 text-sm">-$5K this week</p>
                </div>
                <i class="fas fa-dollar-sign text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card purple p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Team Members</p>
                    <p class="text-3xl font-bold text-white">24</p>
                    <p class="text-white/80 text-sm">+2 this month</p>
                </div>
                <i class="fas fa-users text-4xl text-white/60"></i>
            </div>
        </div>
    </div>

    <!-- Contract Management -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Active Contracts -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Active Contracts</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                        <div>
                            <p class="font-medium text-gray-900">Office Building - Electrical</p>
                            <p class="text-sm text-gray-500">Contract Value: $125K</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium text-green-600">85% Complete</p>
                        <p class="text-xs text-gray-500">On Schedule</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-yellow-500 rounded-full mr-3"></div>
                        <div>
                            <p class="font-medium text-gray-900">Shopping Mall - Plumbing</p>
                            <p class="text-sm text-gray-500">Contract Value: $89K</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium text-yellow-600">65% Complete</p>
                        <p class="text-xs text-gray-500">Minor Delay</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                        <div>
                            <p class="font-medium text-gray-900">Residential - HVAC</p>
                            <p class="text-sm text-gray-500">Contract Value: $67K</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium text-blue-600">45% Complete</p>
                        <p class="text-xs text-gray-500">On Schedule</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Material Requests -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Material Requests</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">Submit Request</button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center p-3 bg-red-50 border border-red-200 rounded-lg">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-red-900">Urgent</p>
                        <p class="text-sm text-red-700">Electrical cables - Office Building</p>
                    </div>
                </div>
                <div class="flex items-center p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <i class="fas fa-clock text-yellow-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-yellow-900">Pending</p>
                        <p class="text-sm text-yellow-700">Plumbing fixtures - Shopping Mall</p>
                    </div>
                </div>
                <div class="flex items-center p-3 bg-green-50 border border-green-200 rounded-lg">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-green-900">Approved</p>
                        <p class="text-sm text-green-700">HVAC units - Residential</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Tracking & Team Management -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Progress Tracking -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Progress Tracking</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">Update Progress</button>
            </div>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Office Building - Electrical</span>
                        <span class="text-sm font-medium text-gray-900">85%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full" style="width: 85%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Shopping Mall - Plumbing</span>
                        <span class="text-sm font-medium text-gray-900">65%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-yellow-600 h-2 rounded-full" style="width: 65%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Residential - HVAC</span>
                        <span class="text-sm font-medium text-gray-900">45%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: 45%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Hotel Complex - Fire Safety</span>
                        <span class="text-sm font-medium text-gray-900">25%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-purple-600 h-2 rounded-full" style="width: 25%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Team Management -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Team Management</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">Manage Team</button>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm font-medium mr-3">
                            JD
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">John Doe</p>
                            <p class="text-sm text-gray-500">Senior Electrician</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-green-600">Office Building</p>
                        <p class="text-sm text-gray-500">8 hours today</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white text-sm font-medium mr-3">
                            SM
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Sarah Miller</p>
                            <p class="text-sm text-gray-500">Plumber</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-yellow-600">Shopping Mall</p>
                        <p class="text-sm text-gray-500">6 hours today</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center text-white text-sm font-medium mr-3">
                            MJ
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Mike Johnson</p>
                            <p class="text-sm text-gray-500">HVAC Technician</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-blue-600">Residential</p>
                        <p class="text-sm text-gray-500">7 hours today</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function subcontractorLeadDashboard() {
    return {
        // Subcontractor management functions
        submitMaterialRequest() {
            window.location.href = '/material-requests/create';
        },
        
        updateProgress(contractId) {
            window.location.href = `/contracts/${contractId}/progress`;
        },
        
        manageTeam() {
            window.location.href = '/team/manage';
        },
        
        viewContractDetails(contractId) {
            window.location.href = `/contracts/${contractId}`;
        },
        
        generateProgressReport() {
            window.location.href = '/reports/progress';
        },
        
        scheduleTeamMeeting() {
            window.location.href = '/meetings/schedule';
        }
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/dashboards/subcontractor-lead.blade.php ENDPATH**/ ?>