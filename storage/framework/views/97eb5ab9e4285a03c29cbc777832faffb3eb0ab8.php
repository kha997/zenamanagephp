
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-900">System Overview</h2>
        <div class="flex items-center space-x-2">
            <select x-model="chartPeriod" @change="updateChart" 
                    class="text-sm border border-gray-300 rounded-md px-3 py-1">
                <option value="7d">Last 7 days</option>
                <option value="30d">Last 30 days</option>
                <option value="90d">Last 90 days</option>
            </select>
        </div>
    </div>
    <div class="h-64">
        <canvas id="systemChart"></canvas>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/dashboard/_charts.blade.php ENDPATH**/ ?>