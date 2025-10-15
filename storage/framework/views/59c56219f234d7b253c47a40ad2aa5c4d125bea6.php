<?php $__env->startSection('title', 'Dashboard Test'); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Dashboard Test Page</h1>
        <p class="text-gray-600">Testing dashboard functionality</p>
    </div>

    <!-- Test KPI Panel -->
    <div id="kpi-strip" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="kpi-panel bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-600">Test KPI</p>
                    <p class="text-2xl font-bold text-gray-900">123</p>
                </div>
                <div class="bg-blue-100 rounded-full p-3">
                    <i class="fas fa-building text-blue-600 text-xl"></i>
                </div>
            </div>
            <canvas id="testSparkline" class="w-full h-8"></canvas>
        </div>
    </div>

    <!-- Test Charts Panel -->
    <div id="charts-section" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900">Test Chart</h3>
        </div>
        <div class="min-h-chart">
            <canvas id="testChart" width="100%" height="280"></canvas>
        </div>
    </div>

    <!-- Test Activity Panel -->
    <div id="activity-section" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900">Test Activity</h3>
        </div>
        <div class="space-y-4 min-h-table">
            <div class="flex items-start space-x-3 p-3 hover:bg-gray-50 rounded-lg">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-info-circle text-blue-600"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900">Test activity item</p>
                    <p class="text-xs text-gray-500">1 minute ago</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Debug Info -->
    <div class="bg-gray-100 rounded-lg p-4">
        <h3 class="text-lg font-semibold mb-2">Debug Info</h3>
        <div class="space-y-2 text-sm">
            <div><strong>Dashboard Module:</strong> <span id="dashboard-module-status">Not loaded</span></div>
            <div><strong>SWR Module:</strong> <span id="swr-module-status">Not loaded</span></div>
            <div><strong>Chart Module:</strong> <span id="chart-module-status">Not loaded</span></div>
            <div><strong>Progress Module:</strong> <span id="progress-module-status">Not loaded</span></div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check module status
    const modules = {
        'dashboard-module-status': window.Dashboard ? 'Loaded' : 'Not loaded',
        'swr-module-status': window.swr ? 'Loaded' : 'Not loaded', 
        'chart-module-status': window.DashboardCharts ? 'Loaded' : 'Not loaded',
        'progress-module-status': window.ProgressManager ? 'Loaded' : 'Not loaded'
    };
    
    Object.entries(modules).forEach(([id, status]) => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = status;
            element.className = status === 'Loaded' ? 'text-green-600' : 'text-red-600';
        }
    });

    // Test chart initialization
    setTimeout(() => {
        if (window.DashboardCharts) {
            window.DashboardCharts.initialize();
            console.log('Test charts initialized');
        }
    }, 200);
});
</script>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/dashboard/test.blade.php ENDPATH**/ ?>