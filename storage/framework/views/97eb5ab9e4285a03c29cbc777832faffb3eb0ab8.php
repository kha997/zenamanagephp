
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Signups Chart -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">New Signups (30 days)</h3>
            <div class="flex items-center space-x-2">
                <select class="text-sm border border-gray-300 rounded-md px-3 py-1">
                    <option>Last 30 days</option>
                    <option>Last 90 days</option>
                    <option>Last year</option>
                </select>
            </div>
        </div>
        <div class="h-64">
            <canvas id="signupsChart"></canvas>
        </div>
    </div>
    
    <!-- Error Rate Chart -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">Error Rate</h3>
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-500">Last 30 days</span>
            </div>
        </div>
        <div class="h-64">
            <canvas id="errorsChart"></canvas>
        </div>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/dashboard/_charts.blade.php ENDPATH**/ ?>