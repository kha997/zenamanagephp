
<div class="bg-white shadow-md rounded-lg mb-6">
    <div class="flex justify-between items-center p-6 pb-4">
        <h3 class="text-lg font-semibold text-gray-900">Security Trends</h3>
        <div class="flex space-x-2">
            <button 
                @click="chartPeriod = '7d'; loadCharts()" 
                :class="{'bg-indigo-600 text-white': chartPeriod === '7d', 'bg-gray-200 text-gray-700': chartPeriod !== '7d'}"
                class="px-3 py-1 rounded text-sm font-medium transition-colors"
            >
                7d
            </button>
            <button 
                @click="chartPeriod = '30d'; loadCharts()" 
                :class="{'bg-indigo-600 text-white': chartPeriod === '30d', 'bg-gray-200 text-gray-700': chartPeriod !== '30d'}"
                class="px-3 py-1 rounded text-sm font-medium transition-colors"
            >
                30d
            </button>
            <button 
                @click="chartPeriod = '90d'; loadCharts()" 
                :class="{'bg-indigo-600 text-white': chartPeriod === '90d', 'bg-gray-200 text-gray-700': chartPeriod !== '90d'}"
                class="px-3 py-1 rounded text-sm font-medium transition-colors"
            >
                90d
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 p-6 pt-0">
        
        <div class="chart-card bg-white border border-gray-100 rounded-lg">
            <div class="p-4">
                <h4 class="text-sm font-medium text-gray-700 mb-3">MFA Adoption</h4>
                <p class="text-xs text-gray-500 mb-4">MFA Adoption %</p>
            </div>
            <div class="chart-wrap flex-1 px-4 pb-4">
                <div class="chart-box relative" style="height: 220px;">
                    <canvas id="mfa-adoption-chart"></canvas>
                    <div x-show="chartError" class="absolute inset-0 flex flex-col items-center justify-center text-red-500">
                        <i class="fas fa-exclamation-triangle text-3xl mb-2"></i>
                        <p class="text-sm">Chart Error</p>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="chart-card bg-white border border-gray-100 rounded-lg">
            <div class="p-4">
                <h4 class="text-sm font-medium text-gray-700 mb-3">Login Attempts</h4>
                <p class="text-xs text-gray-500 mb-4">Successful Logins</p>
            </div>
            <div class="chart-wrap flex-1 px-4 pb-4">
                <div class="chart-box relative" style="height: 220px;">
                    <canvas id="successful-logins-chart"></canvas>
                    <div x-show="chartError" class="absolute inset-0 flex flex-col items-center justify-center text-red-500">
                        <i class="fas fa-exclamation-triangle text-3xl mb-2"></i>
                        <p class="text-sm">Chart Error</p>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="chart-card bg-white border border-gray-100 rounded-lg">
            <div class="p-4">
                <h4 class="text-sm font-medium text-gray-700 mb-3">Active Sessions</h4>
                <p class="text-xs text-gray-500 mb-4">Active Sessions</p>
            </div>
            <div class="chart-wrap flex-1 px-4 pb-4">
                <div class="chart-box relative" style="height: 220px;">
                    <canvas id="active-sessions-chart"></canvas>
                    <div x-show="chartError" class="absolute inset-0 flex flex-col items-center justify-center text-red-500">
                        <i class="fas fa-exclamation-triangle text-3xl mb-2"></i>
                        <p class="text-sm">Chart Error</p>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="chart-card bg-white border border-gray-100 rounded-lg">
            <div class="p-4">
                <h4 class="text-sm font-medium text-gray-700 mb-3">Failed Logins</h4>
                <p class="text-xs text-gray-500 mb-4">Failed Logins</p>
            </div>
            <div class="chart-wrap flex-1 px-4 pb-4">
                <div class="chart-box relative" style="height: 220px;">
                    <canvas id="failed-logins-chart"></canvas>
                    <div x-show="chartError" class="absolute inset-0 flex flex-col items-center justify-center text-red-500">
                        <i class="fas fa-exclamation-triangle text-3xl mb-2"></i>
                        <p class="text-sm">Chart Error</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/security/_charts.blade.php ENDPATH**/ ?>