<?php $__env->startSection('title', 'Maintenance'); ?>

<?php $__env->startSection('breadcrumb'); ?>
<li class="flex items-center">
    <i class="fas fa-chevron-right text-gray-400 mr-2"></i>
    <span class="text-gray-900">Maintenance</span>
</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Maintenance</h1>
            <p class="text-gray-600">System maintenance and health checks</p>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Health Checks</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Database</span>
                    <span class="flex items-center text-green-600">
                        <i class="fas fa-check-circle mr-1"></i>
                        Healthy
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Cache</span>
                    <span class="flex items-center text-green-600">
                        <i class="fas fa-check-circle mr-1"></i>
                        Healthy
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Queue</span>
                    <span class="flex items-center text-green-600">
                        <i class="fas fa-check-circle mr-1"></i>
                        Healthy
                    </span>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Maintenance Tasks</h3>
            <div class="space-y-3">
                <button @click="runTask('reindex')" 
                        class="w-full text-left px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-search mr-2"></i>Reindex Search
                </button>
                <button @click="runTask('retry-jobs')" 
                        class="w-full text-left px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700">
                    <i class="fas fa-redo mr-2"></i>Retry Failed Jobs
                </button>
                <button @click="runTask('clear-cache')" 
                        class="w-full text-left px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                    <i class="fas fa-broom mr-2"></i>Clear Cache
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function runTask(task) {
        if (confirm('Are you sure you want to run this task?')) {
            console.log('Running task:', task);
            // In real implementation, this would call API endpoint
        }
    }
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/maintenance/index.blade.php ENDPATH**/ ?>