
<div class="bg-white shadow-md rounded-lg mb-6">
    <div class="flex justify-between items-center p-6 pb-4">
        <h3 class="text-lg font-semibold text-gray-900">Security Trends</h3>
        <div class="flex space-x-2">
            <button 
                @click="changePeriod('7d')" 
                :class="{'bg-indigo-600 text-white': chartPeriod === '7d', 'bg-gray-200 text-gray-700': chartPeriod !== '7d'}"
                class="px-3 py-1 rounded text-sm font-medium transition-colors"
                aria-label="7 days period"
            >
                7d
            </button>
            <button 
                @click="changePeriod('30d')" 
                :class="{'bg-indigo-600 text-white': chartPeriod === '30d', 'bg-gray-200 text-gray-700': chartPeriod !== '30d'}"
                class="px-3 py-1 rounded text-sm font-medium transition-colors"
                aria-label="30 days period"
            >
                30d
            </button>
            <button 
                @click="changePeriod('90d')" 
                :class="{'bg-indigo-600 text-white': chartPeriod === '90d', 'bg-gray-200 text-gray-700': chartPeriod !== '90d'}"
                class="px-3 py-1 rounded text-sm font-medium transition-colors"
                aria-label="90 days period"
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
                    <canvas 
                        id="mfa-adoption-chart" 
                        class="security-chart"
                        aria-label="MFA adoption percentage over time"
                        x-show="!chartError"
                    ></canvas>
                    <!-- Skeleton -->
                    <div x-show="loading && chartError === null" class="absolute inset-0 bg-gray-100 animate-pulse rounded"></div>
                    <!-- Empty State -->
                    <div x-show="!loading && !chartData?.mfaAdoption?.length" class="absolute inset-0 flex flex-col items-center justify-center text-gray-500">
                        <i class="fas fa-chart-line text-3xl mb-2"></i>
                        <p class="text-sm">No data in selected period</p>
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
                    <canvas 
                        id="successful-logins-chart" 
                        class="security-chart"
                        aria-label="Successful login attempts over time"
                        x-show="!chartError"
                    ></canvas>
                    <!-- Skeleton -->
                    <div x-show="loading && chartError === null" class="absolute inset-0 bg-gray-100 animate-pulse rounded"></div>
                    <!-- Empty State -->
                    <div x-show="!loading && !chartData?.successfulLogins?.length" class="absolute inset-0 flex flex-col items-center justify-center text-gray-500">
                        <i class="fas fa-chart-line text-3xl mb-2"></i>
                        <p class="text-sm">No data in selected period</p>
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
                    <canvas 
                        id="active-sessions-chart" 
                        class="security-chart"
                        aria-label="Active sessions count over time"
                        x-show="!chartError"
                    ></canvas>
                    <!-- Skeleton -->
                    <div x-show="loading && chartError === null" class="absolute inset-0 bg-gray-100 animate-pulse rounded"></div>
                    <!-- Empty State -->
                    <div x-show="!loading && !chartData?.activeSessions?.length" class="absolute inset-0 flex flex-col items-center justify-center text-gray-500">
                        <i class="fas fa-chart-line text-3xl mb-2"></i>
                        <p class="text-sm">No data in selected period</p>
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
                    <canvas 
                        id="failed-logins-chart" 
                        class="security-chart"
                        aria-label="Failed login attempts over time"
                        x-show="!chartError"
                    ></canvas>
                    <!-- Skeleton -->
                    <div x-show="loading && chartError === null" class="absolute inset-0 bg-gray-100 animate-pulse rounded"></div>
                    <!-- Empty State -->
                    <div x-show="!loading && !chartData?.failedLogins?.length" class="absolute inset-0 flex flex-col items-center justify-center text-gray-500">
                        <i class="fas fa-chart-line text-3xl mb-2"></i>
                        <p class="text-sm">No data in selected period</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Error -->
    <div x-show="chartError" class="bg-red-50 border border-red-200 rounded-md p-4 mx-6 mb-6">
        <div class="flex">
            <i class="fas fa-exclamation-circle text-red-400 mt-0.5"></i>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">Chart Error</h3>
                <p class="mt-1 text-sm text-red-700" x-text="chartError"></p>
                <button @click="loadCharts()" class="mt-2 text-sm text-red-600 hover:text-red-500 underline">
                    Retry
                </button>
            </div>
        </div>
    </div>
</div><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/security/_charts.blade.php ENDPATH**/ ?>