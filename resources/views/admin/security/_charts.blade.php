{{-- Security KPI Charts - CLS Protected Design --}}
<div class="bg-white shadow-md rounded-lg mb-6" data-charts-container>
    <div class="flex justify-between items-center p-6 pb-4">
        <h3 class="text-lg font-semibold text-gray-900">Security Trends</h3>
        <div class="flex space-x-2">
            <button 
               data-period="7d"
                class="px-3 py-1 rounded text-sm font-medium transition-colors bg-gray-200 text-gray-700 hover:bg-gray-300"
            >
                7d
            </button>
            <button 
                data-period="30d"
                class="px-3 py-1 rounded text-sm font-medium transition-colors bg-gray-200 text-gray-700 hover:bg-gray-300"
            >
                30d
            </button>
            <button 
                data-period="90d"
                class="px-3 py-1 rounded text-sm font-medium transition-colors bg-gray-200 text-gray-700 hover:bg-gray-300"
            >
                90d
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 p-6 pt-0">
        {{-- MFA Adoption Chart --}}
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

        {{-- Successful Logins Chart --}}
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

        {{-- Active Sessions Chart --}}
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

        {{-- Failed Logins Chart --}}
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
</div>