<?php $__env->startSection('title', 'Sales Dashboard'); ?>
<?php $__env->startSection('page-title', 'Sales Dashboard'); ?>
<?php $__env->startSection('page-description', 'Sales pipeline and client relationship management'); ?>
<?php $__env->startSection('user-initials', 'SL'); ?>
<?php $__env->startSection('user-name', 'Sales Manager'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="salesDashboard()">
    <!-- Sales Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="dashboard-card metric-card green p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Total Revenue</p>
                    <p class="text-3xl font-bold text-white">$3.2M</p>
                    <p class="text-white/80 text-sm">+18% this quarter</p>
                </div>
                <i class="fas fa-dollar-sign text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card blue p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Active Leads</p>
                    <p class="text-3xl font-bold text-white">24</p>
                    <p class="text-white/80 text-sm">+6 this week</p>
                </div>
                <i class="fas fa-users text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card orange p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Conversion Rate</p>
                    <p class="text-3xl font-bold text-white">32%</p>
                    <p class="text-white/80 text-sm">+5% this month</p>
                </div>
                <i class="fas fa-chart-line text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card purple p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">New Clients</p>
                    <p class="text-3xl font-bold text-white">8</p>
                    <p class="text-white/80 text-sm">+2 this month</p>
                </div>
                <i class="fas fa-handshake text-4xl text-white/60"></i>
            </div>
        </div>
    </div>

    <!-- Sales Pipeline -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Sales Pipeline -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Sales Pipeline</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Office Building Project</p>
                        <p class="text-sm text-gray-500">Proposal Stage</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-blue-600">$850K</p>
                        <p class="text-sm text-gray-500">High Priority</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Shopping Mall Development</p>
                        <p class="text-sm text-gray-500">Negotiation Stage</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-green-600">$1.2M</p>
                        <p class="text-sm text-gray-500">Medium Priority</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">Residential Complex</p>
                        <p class="text-sm text-gray-500">Initial Contact</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-yellow-600">$650K</p>
                        <p class="text-sm text-gray-500">Low Priority</p>
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
                <div class="flex items-start p-3 bg-gray-50 rounded-lg">
                    <i class="fas fa-phone text-green-500 mr-3 mt-1"></i>
                    <div>
                        <p class="font-medium text-gray-900">Call with ABC Corp</p>
                        <p class="text-sm text-gray-500">Discussed office building project requirements</p>
                        <p class="text-xs text-gray-400">2 hours ago</p>
                    </div>
                </div>
                <div class="flex items-start p-3 bg-gray-50 rounded-lg">
                    <i class="fas fa-envelope text-blue-500 mr-3 mt-1"></i>
                    <div>
                        <p class="font-medium text-gray-900">Email to XYZ Ltd</p>
                        <p class="text-sm text-gray-500">Sent proposal for shopping mall project</p>
                        <p class="text-xs text-gray-400">4 hours ago</p>
                    </div>
                </div>
                <div class="flex items-start p-3 bg-gray-50 rounded-lg">
                    <i class="fas fa-calendar text-purple-500 mr-3 mt-1"></i>
                    <div>
                        <p class="font-medium text-gray-900">Meeting Scheduled</p>
                        <p class="text-sm text-gray-500">Site visit with DEF Construction</p>
                        <p class="text-xs text-gray-400">Tomorrow 10:00 AM</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Client Relations & Revenue Tracking -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top Clients -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Top Clients</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</button>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold mr-3">
                            AC
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">ABC Corporation</p>
                            <p class="text-sm text-gray-500">3 active projects</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-gray-900">$1.5M</p>
                        <p class="text-sm text-green-600">+12%</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white font-semibold mr-3">
                            XL
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">XYZ Ltd</p>
                            <p class="text-sm text-gray-500">2 active projects</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-gray-900">$980K</p>
                        <p class="text-sm text-green-600">+8%</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center text-white font-semibold mr-3">
                            DC
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">DEF Construction</p>
                            <p class="text-sm text-gray-500">1 active project</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-gray-900">$750K</p>
                        <p class="text-sm text-green-600">+15%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Tracking -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Revenue Tracking</h3>
                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">View Reports</button>
            </div>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Q1 Target</span>
                        <span class="text-sm font-medium text-gray-900">$2.5M</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full" style="width: 85%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">85% achieved</p>
                </div>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Monthly Target</span>
                        <span class="text-sm font-medium text-gray-900">$800K</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: 70%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">70% achieved</p>
                </div>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Weekly Target</span>
                        <span class="text-sm font-medium text-gray-900">$200K</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-yellow-600 h-2 rounded-full" style="width: 60%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">60% achieved</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function salesDashboard() {
    return {
        // Sales management functions
        createLead() {
            window.location.href = '/leads/create';
        },
        
        scheduleMeeting() {
            window.location.href = '/meetings/schedule';
        },
        
        generateProposal() {
            window.location.href = '/proposals/create';
        },
        
        viewClientDetails(clientId) {
            window.location.href = `/clients/${clientId}`;
        }
    }
}
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.dashboard', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/dashboards/sales.blade.php ENDPATH**/ ?>