<?php $__env->startSection('title', 'Project History'); ?>
<?php $__env->startSection('page-title', 'Project History'); ?>
<?php $__env->startSection('page-description', 'View project history and activity log'); ?>
<?php $__env->startSection('user-initials', 'PM'); ?>
<?php $__env->startSection('user-name', 'Project Manager'); ?>
<?php $__env->startSection('current-route', 'projects'); ?>

<?php
$breadcrumb = [
    [
        'label' => 'Dashboard',
        'url' => '/dashboard',
        'icon' => 'fas fa-home'
    ],
    [
        'label' => 'Projects Management',
        'url' => '/projects'
    ],
    [
        'label' => 'Project History',
        'url' => '/projects/' . ($projectData->id ?? '1') . '/history'
    ]
];
$currentRoute = 'projects';
?>

<?php $__env->startSection('content'); ?>
<div x-data="projectHistory()">
    <!-- Project Information Card -->
    <div class="dashboard-card p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                Project Information
            </h3>
            <div class="flex space-x-2">
                <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                    ID: <?php echo e($projectData->id ?? 'PROJ-001'); ?>

                </span>
                <span class="px-3 py-1 bg-green-100 text-green-800 text-sm rounded-full">
                    <?php echo e(ucfirst($projectData->status ?? 'Active')); ?>

                </span>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div class="flex items-center">
                <i class="fas fa-project-diagram text-gray-400 mr-2"></i>
                <span class="text-gray-600">Project:</span>
                <span class="ml-2 font-medium"><?php echo e($projectData->name ?? 'Sample Project'); ?></span>
            </div>
            <div class="flex items-center">
                <i class="fas fa-calendar text-gray-400 mr-2"></i>
                <span class="text-gray-600">Created:</span>
                <span class="ml-2 font-medium"><?php echo e($projectData->created_at ?? date('Y-m-d H:i:s')); ?></span>
            </div>
            <div class="flex items-center">
                <i class="fas fa-clock text-gray-400 mr-2"></i>
                <span class="text-gray-600">Last Updated:</span>
                <span class="ml-2 font-medium"><?php echo e($projectData->updated_at ?? date('Y-m-d H:i:s')); ?></span>
            </div>
        </div>
    </div>

    <!-- Activity Timeline -->
    <div class="dashboard-card p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                <i class="fas fa-history text-purple-600 mr-2"></i>
                Activity Timeline
            </h3>
            <div class="flex space-x-2">
                <select x-model="filterType" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="all">All Activities</option>
                    <option value="status">Status Changes</option>
                    <option value="documents">Document Updates</option>
                    <option value="assignments">Assignments</option>
                </select>
                <button 
                    @click="exportHistory()"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center"
                >
                    <i class="fas fa-download mr-2"></i>
                    Export
                </button>
            </div>
        </div>

        <!-- Timeline -->
        <div class="space-y-6">
            <!-- Today -->
            <div class="border-l-4 border-blue-500 pl-4">
                <div class="flex items-center mb-4">
                    <h4 class="font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-calendar-day text-blue-600 mr-2"></i>
                        Today
                    </h4>
                </div>
                
                <div class="space-y-4 ml-4">
                    <!-- Activity 1 -->
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check text-green-600 text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-900">Project status updated</p>
                                <p class="text-xs text-gray-500">2 hours ago</p>
                            </div>
                            <p class="text-sm text-gray-600">Status changed from "In Progress" to "Active"</p>
                            <p class="text-xs text-gray-500">by John Smith</p>
                        </div>
                    </div>

                    <!-- Activity 2 -->
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-file-upload text-blue-600 text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-900">Document uploaded</p>
                                <p class="text-xs text-gray-500">4 hours ago</p>
                            </div>
                            <p class="text-sm text-gray-600">"Updated Construction Plans.pdf" uploaded</p>
                            <p class="text-xs text-gray-500">by Sarah Wilson</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Yesterday -->
            <div class="border-l-4 border-gray-300 pl-4">
                <div class="flex items-center mb-4">
                    <h4 class="font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-calendar-day text-gray-600 mr-2"></i>
                        Yesterday
                    </h4>
                </div>
                
                <div class="space-y-4 ml-4">
                    <!-- Activity 3 -->
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user-plus text-purple-600 text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-900">Team member assigned</p>
                                <p class="text-xs text-gray-500">1 day ago</p>
                            </div>
                            <p class="text-sm text-gray-600">Mike Johnson assigned as Site Engineer</p>
                            <p class="text-xs text-gray-500">by Project Manager</p>
                        </div>
                    </div>

                    <!-- Activity 4 -->
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-edit text-yellow-600 text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-900">Project details updated</p>
                                <p class="text-xs text-gray-500">1 day ago</p>
                            </div>
                            <p class="text-sm text-gray-600">Budget updated from $95,000 to $100,000</p>
                            <p class="text-xs text-gray-500">by Alex Lee</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- This Week -->
            <div class="border-l-4 border-gray-300 pl-4">
                <div class="flex items-center mb-4">
                    <h4 class="font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-calendar-week text-gray-600 mr-2"></i>
                        This Week
                    </h4>
                </div>
                
                <div class="space-y-4 ml-4">
                    <!-- Activity 5 -->
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-red-600 text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-900">Risk identified</p>
                                <p class="text-xs text-gray-500">3 days ago</p>
                            </div>
                            <p class="text-sm text-gray-600">Weather delay risk identified for outdoor work</p>
                            <p class="text-xs text-gray-500">by Emma Brown</p>
                        </div>
                    </div>

                    <!-- Activity 6 -->
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-calendar-check text-indigo-600 text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-900">Milestone reached</p>
                                <p class="text-xs text-gray-500">5 days ago</p>
                            </div>
                            <p class="text-sm text-gray-600">Design phase completed (25% progress)</p>
                            <p class="text-xs text-gray-500">System generated</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="border-t pt-6 mt-8">
            <h4 class="font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-chart-bar mr-2"></i>
                Activity Summary
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-blue-50 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-blue-600">12</div>
                    <div class="text-sm text-blue-800">Total Activities</div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-green-600">8</div>
                    <div class="text-sm text-green-800">This Week</div>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-purple-600">3</div>
                    <div class="text-sm text-purple-800">Status Changes</div>
                </div>
                <div class="bg-yellow-50 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-yellow-600">5</div>
                    <div class="text-sm text-yellow-800">Document Updates</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function projectHistory() {
    return {
        filterType: 'all',
        
        exportHistory() {
            this.showNotification('Exporting project history...', 'info');
            setTimeout(() => {
                this.showNotification('History exported successfully!', 'success');
            }, 2000);
        },
        
        showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg text-white shadow-lg transition-all duration-300 ${
                type === 'success' ? 'bg-green-600' : 
                type === 'error' ? 'bg-red-600' : 
                type === 'warning' ? 'bg-yellow-600' :
                'bg-blue-600'
            }`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Remove notification after 3 seconds
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/projects/history.blade.php ENDPATH**/ ?>