{{-- Security Trends Charts - Clean Implementation --}}
<div class="bg-white shadow-md rounded-lg mb-6">
    {{-- Header --}}
    <div class="flex justify-between items-center p-6 pb-4">
        <h3 class="text-lg font-semibold text-gray-900">Security Trends</h3>
        
        {{-- Period Selector --}}
        <div class="flex space-x-2">
            <button 
                x-data
                @click="changePeriod('7d')"
                :class="chartPeriod === '7d' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                class="px-3 py-1 rounded text-sm font-medium transition-colors">
                7d
            </button>
            <button 
                x-data
                @click="changePeriod('30d')"
                :class="chartPeriod === '30d' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                class="px-3 py-1 rounded text-sm font-medium transition-colors">
                30d
            </button>
            <button 
                x-data
                @click="changePeriod('90d')"
                :class="chartPeriod === '90d' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                class="px-3 py-1 rounded text-sm font-medium transition-colors">
                90d
            </button>
        </div>
    </div>

    {{-- Charts Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 p-6 pt-0">
        
        {{-- MFA Adoption Chart --}}
        <div class="bg-gray-50 rounded-lg p-4 min-h-[280px]">
            <h4 class="text-sm font-medium text-gray-700 mb-3">MFA Adoption</h4>
            <p class="text-xs text-gray-500 mb-4">Percentage of users with MFA enabled</p>
            
            {{-- Chart Container --}}
            <div class="relative h-[180px] w-full">
                <canvas 
                    id="mfa-adoption-chart"
                    width="100%" 
                    height="100%"
                    class="chart-canvas"
                    aria-label="MFA adoption percentage over time">
                </canvas>
                
                {{-- Loading State --}}
                <div x-show="chartsLoading" class="absolute inset-0 flex items-center justify-center">
                    <div class="flex items-center space-x-2 text-gray-500">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                        <span class="text-sm">Loading...</span>
                    </div>
                </div>
                
                {{-- Error State --}}
                <div x-show="chartError" class="absolute inset-0 flex flex-col items-center justify-center text-red-500">
                    <i class="fas fa-exclamation-triangle text-3xl mb-2"></i>
                    <p class="text-sm">Chart Error</p>
                </div>
                
                {{-- Empty State --}}
                <div x-show="!chartError && !chartsLoading && !chartData.mfaAdoption" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400">
                    <i class="fas fa-chart-line text-3xl mb-2"></i>
                    <p class="text-sm">No data available</p>
                </div>
            </div>
        </div>

        {{-- Login Attempts Chart --}}
        <div class="bg-gray-50 rounded-lg p-4 min-h-[280px]">
            <h4 class="text-sm font-medium text-gray-700 mb-3">Login Attempts</h4>
            <p class="text-xs text-gray-500 mb-4">Successful vs Failed logins</p>
            
            {{-- Chart Container --}}
            <div class="relative h-[180px] w-full">
                <canvas 
                    id="login-attempts-chart"
                    width="100%" 
                    height="100%"
                    class="chart-canvas"
                    aria-label="Login attempts over time">
                </canvas>
                
                {{-- Loading State --}}
                <div x-show="chartsLoading" class="absolute inset-0 flex items-center justify-center">
                    <div class="flex items-center space-r-2 text-gray-500">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                        <span class="text-sm">Loading...</span>
                    </div>
                </div>
                
                {{-- Error State --}}
                <div x-show="chartError" class="absolute inset-0 flex flex-col items-center justify-center text-red-500">
                    <i class="fas fa-exclamation-triangle text-3xl mb-2"></i>
                    <p class="text-sm">Chart Error</p>
                </div>
                
                {{-- Empty State --}}
                <div x-show="!chartError && !chartsLoading && !chartData.loginAttempts" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400">
                    <i class="fas fa-chart-line text-3xl mb-2"></i>
                    <p class="text-sm">No data available</p>
                </div>
            </div>
        </div>

        {{-- Active Sessions Chart --}}
        <div class="bg-gray-50 rounded-lg p-4 min-h-[280px]">
            <h4 class="text-sm font-medium text-gray-700 mb-3">Active Sessions</h4>
            <p class="text-xs text-gray-500 mb-4">Users currently logged in</p>
            
            {{-- Chart Container --}}
            <div class="relative h-[180px] w-full">
                <canvas 
                    id="active-sessions-chart"
                    width="100%" 
                    height="100%"
                    class="chart-canvas"
                    aria-label="Active sessions over time">
                </canvas>
                
                {{-- Loading State --}}
                <div x-show="chartsLoading" class="absolute inset-0 flex items-center justify-center">
                    <div class="flex items-center space-x-2 text-gray-500">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                        <span class="text-sm">Loading...</span>
                    </div>
                </div>
                
                {{-- Error State --}}
                <div x-show="chartError" class="absolute inset-0 flex flex-col items-center justify-center text-red-500">
                    <i class="fas fa-exclamation-triangle text-3xl mb-2"></i>
                    <p class="text-sm">Chart Error</p>
                </div>
                
                {{-- Empty State --}}
                <div x-show="!chartError && !chartsLoading && !chartData.activeSessions" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400">
                    <i class="fas fa-chart-line text-3xl mb-2"></i>
                    <p class="text-sm">No data available</p>
                </div>
            </div>
        </div>

        {{-- Failed Logins Chart --}}
        <div class="bg-gray-50 rounded-lg p-4 min-h-[280px]">
            <h4 class="text-sm font-medium text-gray-700 mb-3">Failed Logins</h4>
            <p class="text-xs text-gray-500 mb-4">Unsuccessful login attempts</p>
            
            {{-- Chart Container --}}
            <div class="relative h-[180px] w-full">
                <canvas 
                    id="failed-logins-chart"
                    width="100%" 
                    height="100%"
                    class="chart-canvas"
                    aria-label="Failed login attempts over time">
                </canvas>
                
                {{-- Loading State --}}
                <div x-show="chartsLoading" class="absolute inset-0 flex items-center justify-center">
                    <div class="flex items-center space-x-2 text-gray-500">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                        <span class="text-sm">Loading...</span>
                    </div>
                </div>
                
                {{-- Error State --}}
                <div x-show="chartError" class="absolute inset-0 flex flex-col items-center justify-center text-red-500">
                    <i class="fas fa-exclamation-triangle text-3xl mb-2"></i>
                    <p class="text-sm">Chart Error</p>
                </div>
                
                {{-- Empty State --}}
                <div x-show="!chartError && !chartsLoading && !chartData.failedLogins" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400">
                    <i class="fas fa-chart-line text-3xl mb-2"></i>
                    <p class="text-sm">No data available</p>
                </div>
            </div>
        </div>
    </div>
</div>
