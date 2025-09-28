
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <!-- Total Tenants -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer" 
         @click="drillDownTotal"
         role="button"
         tabindex="0"
         :aria-label="getAriaLabel('total', kpis.totalTenants.value, kpis.totalTenants.deltaPct, kpis.totalTenants.period)"
         @keydown.enter="drillDownTotal"
         @keydown.space.prevent="drillDownTotal">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Total Tenants</p>
                <p class="text-2xl font-bold text-gray-900" x-text="kpis.totalTenants.value">89</p>
                <p class="text-sm text-green-600">
                    <i class="fas fa-arrow-up mr-1"></i>
                    <span x-text="'+' + kpis.totalTenants.deltaPct + '%'">+5.2%</span> from last month
                </p>
            </div>
            <div class="bg-blue-100 rounded-full p-3">
                <i class="fas fa-building text-blue-600 text-xl"></i>
            </div>
        </div>
        <!-- Sparkline Chart -->
        <div class="h-8 mb-3">
            <canvas id="totalTenantsSparkline" class="w-full h-full"></canvas>
        </div>
        <!-- Primary Action Button -->
        <button class="w-full px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                :aria-label="getAriaLabel('total', kpis.totalTenants.value, kpis.totalTenants.deltaPct, kpis.totalTenants.period)">
            <i class="fas fa-eye mr-1" aria-hidden="true"></i>View All
        </button>
    </div>

    <!-- Active Tenants -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer" 
         @click="drillDownActive"
         role="button"
         tabindex="0"
         :aria-label="getAriaLabel('active', kpis.activeTenants.value, kpis.activeTenants.deltaPct, kpis.activeTenants.period)"
         @keydown.enter="drillDownActive"
         @keydown.space.prevent="drillDownActive">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Active</p>
                <p class="text-2xl font-bold text-gray-900" x-text="kpis.activeTenants.value">76</p>
                <p class="text-sm text-green-600">
                    <i class="fas fa-arrow-up mr-1"></i>
                    <span x-text="'+' + kpis.activeTenants.deltaPct + '%'">+3.1%</span> from last month
                </p>
            </div>
            <div class="bg-green-100 rounded-full p-3">
                <i class="fas fa-check-circle text-green-600 text-xl"></i>
            </div>
        </div>
        <!-- Sparkline Chart -->
        <div class="h-8 mb-3">
            <canvas id="activeTenantsSparkline" class="w-full h-full"></canvas>
        </div>
        <!-- Primary Action Button -->
        <button class="w-full px-3 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                :aria-label="getAriaLabel('active', kpis.activeTenants.value, kpis.activeTenants.deltaPct, kpis.activeTenants.period)">
            <i class="fas fa-filter mr-1" aria-hidden="true"></i>Filter Active
        </button>
    </div>

    <!-- Disabled/Suspended Tenants -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer" 
         @click="drillDownDisabled"
         role="button"
         tabindex="0"
         :aria-label="getAriaLabel('disabled', kpis.disabledTenants.value, kpis.disabledTenants.deltaAbs, kpis.disabledTenants.period)"
         @keydown.enter="drillDownDisabled"
         @keydown.space.prevent="drillDownDisabled">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Disabled</p>
                <p class="text-2xl font-bold text-gray-900" x-text="kpis.disabledTenants.value">8</p>
                <p class="text-sm text-red-600">
                    <i class="fas fa-arrow-up mr-1"></i>
                    <span x-text="'+' + kpis.disabledTenants.deltaAbs">+2</span> from last week
                </p>
            </div>
            <div class="bg-red-100 rounded-full p-3">
                <i class="fas fa-ban text-red-600 text-xl"></i>
            </div>
        </div>
        <!-- Sparkline Chart -->
        <div class="h-8 mb-3">
            <canvas id="disabledTenantsSparkline" class="w-full h-full"></canvas>
        </div>
        <!-- Primary Action Button -->
        <button class="w-full px-3 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                :aria-label="getAriaLabel('disabled', kpis.disabledTenants.value, kpis.disabledTenants.deltaAbs, kpis.disabledTenants.period)">
            <i class="fas fa-filter mr-1" aria-hidden="true"></i>Filter Disabled
        </button>
    </div>

    <!-- New Tenants (30d) -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer" 
         @click="drillDownNew"
         role="button"
         tabindex="0"
         :aria-label="getAriaLabel('new', kpis.newTenants.value, kpis.newTenants.deltaPct, kpis.newTenants.period)"
         @keydown.enter="drillDownNew"
         @keydown.space.prevent="drillDownNew">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-gray-600">New (30d)</p>
                <p class="text-2xl font-bold text-gray-900" x-text="kpis.newTenants.value">12</p>
                <p class="text-sm text-green-600">
                    <i class="fas fa-arrow-up mr-1"></i>
                    <span x-text="'+' + kpis.newTenants.deltaPct + '%'">+20.0%</span> from last month
                </p>
            </div>
            <div class="bg-purple-100 rounded-full p-3">
                <i class="fas fa-plus-circle text-purple-600 text-xl"></i>
            </div>
        </div>
        <!-- Sparkline Chart -->
        <div class="h-8 mb-3">
            <canvas id="newTenantsSparkline" class="w-full h-full"></canvas>
        </div>
        <!-- Primary Action Button -->
        <button class="w-full px-3 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2"
                :aria-label="getAriaLabel('new', kpis.newTenants.value, kpis.newTenants.deltaPct, kpis.newTenants.period)">
            <i class="fas fa-calendar mr-1" aria-hidden="true"></i>View Recent
        </button>
    </div>

    <!-- Trial Expiring (7d) -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer" 
         @click="drillDownTrialExpiring"
         role="button"
         tabindex="0"
         :aria-label="getAriaLabel('trial', kpis.trialExpiring.value, kpis.trialExpiring.deltaAbs, kpis.trialExpiring.period)"
         @keydown.enter="drillDownTrialExpiring"
         @keydown.space.prevent="drillDownTrialExpiring">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Trial Expiring</p>
                <p class="text-2xl font-bold text-gray-900" x-text="kpis.trialExpiring.value">3</p>
                <p class="text-sm text-orange-600">
                    <i class="fas fa-clock mr-1"></i>
                    <span x-text="kpis.trialExpiring.deltaAbs">3</span> in next 7 days
                </p>
            </div>
            <div class="bg-orange-100 rounded-full p-3">
                <i class="fas fa-hourglass-half text-orange-600 text-xl"></i>
            </div>
        </div>
        <!-- Sparkline Chart -->
        <div class="h-8 mb-3">
            <canvas id="trialExpiringSparkline" class="w-full h-full"></canvas>
        </div>
        <!-- Primary Action Button -->
        <button class="w-full px-3 py-2 bg-orange-600 text-white text-sm font-medium rounded-lg hover:bg-orange-700 transition-colors focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2"
                :aria-label="getAriaLabel('trial', kpis.trialExpiring.value, kpis.trialExpiring.deltaAbs, kpis.trialExpiring.period)">
            <i class="fas fa-exclamation-triangle mr-1" aria-hidden="true"></i>View Expiring
        </button>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/tenants/_kpis.blade.php ENDPATH**/ ?>