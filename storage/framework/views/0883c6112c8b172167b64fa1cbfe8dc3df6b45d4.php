
<div id="kpi-strip" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 lg:gap-6 w-full" aria-live="polite" style="grid-template-columns: repeat(5, 1fr);">
    <!-- Total Tenants -->
    <div class="kpi-panel bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer" 
         @click="window.location.href='/admin/tenants'"
         role="button"
         tabindex="0"
         data-testid="kpi-tenants"
         :aria-label="getAriaLabel('tenants', kpis.totalTenants.value, kpis.totalTenants.deltaPct, kpis.totalTenants.period)"
         @keydown.enter="window.location.href='/admin/tenants'"
         @keydown.space.prevent="window.location.href='/admin/tenants'"
         aria-live="polite">
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
        <div class="sparkline-container h-8 mb-3">
            <canvas id="totalTenantsSparkline" class="w-full h-full" role="img" aria-label="Tenants trend sparkline"></canvas>
        </div>
        <!-- Primary Action Button -->
        <button class="w-full px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                :aria-label="getAriaLabel('tenants', kpis.totalTenants.value, kpis.totalTenants.deltaPct, kpis.totalTenants.period)">
            <i class="fas fa-eye mr-1" aria-hidden="true"></i>View Tenants
        </button>
    </div>
    
    <!-- Total Users -->
    <div class="kpi-panel bg-white rounded-md shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer" 
         @click="window.location.href='/admin/users'"
         data-testid="kpi-users">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Total Users</p>
                <p class="text-2xl font-bold text-gray-900" x-text="kpis.totalUsers.value">1,247</p>
                <p class="text-sm text-green-600">
                    <i class="fas fa-arrow-up mr-1"></i>
                    <span x-text="'+' + kpis.totalUsers.deltaPct + '%'">+12.1%</span> from last month
                </p>
            </div>
            <div class="bg-green-100 rounded-full p-3">
                <i class="fas fa-users text-green-600 text-xl"></i>
            </div>
        </div>
        <!-- Sparkline Chart -->
        <div class="sparkline-container h-8 mb-3">
            <canvas id="totalUsersSparkline" class="w-full h-full" role="img" aria-label="Users trend sparkline"></canvas>
        </div>
        <!-- Primary Action Button -->
        <button class="w-full px-3 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
            <i class="fas fa-users mr-1"></i>Manage Users
        </button>
    </div>
    
    <!-- Errors 24h -->
    <div class="kpi-panel bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer" 
         @click="window.location.href='/admin/alerts'"
         data-testid="kpi-errors">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Errors (24h)</p>
                <p class="text-2xl font-bold text-gray-900" x-text="kpis.errors24h.value">12</p>
                <p class="text-sm text-red-600">
                    <i class="fas fa-arrow-up mr-1"></i>
                    <span x-text="'+' + kpis.errors24h.deltaAbs">+3</span> from yesterday
                </p>
            </div>
            <div class="bg-red-100 rounded-full p-3">
                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
            </div>
        </div>
        <!-- Sparkline Chart -->
        <div class="sparkline-container h-8 mb-3">
            <canvas id="errors24hSparkline" class="w-full h-full" role="img" aria-label="Errors trend sparkline"></canvas>
        </div>
        <!-- Primary Action Button -->
        <button class="w-full px-3 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors">
            <i class="fas fa-exclamation-triangle mr-1"></i>View Errors
        </button>
    </div>
    
    <!-- Queue Jobs -->
    <div class="kpi-panel bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer" 
         @click="window.location.href='/admin/maintenance'"
         data-testid="kpi-queue">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Queue Jobs</p>
                <p class="text-2xl font-bold text-gray-900" x-text="kpis.queueJobs.value">156</p>
                <p class="text-sm text-yellow-600">
                    <i class="fas fa-clock mr-1"></i>
                    <span x-text="kpis.queueJobs.status">Processing</span>
                </p>
            </div>
            <div class="bg-yellow-100 rounded-full p-3">
                <i class="fas fa-tasks text-yellow-600 text-xl"></i>
            </div>
        </div>
        <!-- Sparkline Chart -->
        <div class="sparkline-container h-8 mb-3">
            <canvas id="queueJobsSparkline" class="w-full h-full" role="img" aria-label="Queue trend sparkline"></canvas>
        </div>
        <!-- Primary Action Button -->
        <button class="w-full px-3 py-2 bg-yellow-600 text-white text-sm font-medium rounded-lg hover:bg-yellow-700 transition-colors">
            <i class="fas fa-tasks mr-1"></i>Monitor Queue
        </button>
    </div>
    
    <!-- Storage Used -->
    <div class="kpi-panel bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer" 
         @click="window.location.href='/admin/maintenance'"
         data-testid="kpi-storage">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Storage Used</p>
                <p class="text-2xl font-bold text-gray-900" x-text="formatBytes(kpis.storage.usedBytes)">2.2TB</p>
                <p class="text-sm text-gray-600">
                    <i class="fas fa-database mr-1"></i>
                    <span x-text="Math.round((kpis.storage.usedBytes / kpis.storage.capacityBytes) * 100)">69</span>% of <span x-text="formatBytes(kpis.storage.capacityBytes)">3.2TB</span>
                </p>
            </div>
            <div class="bg-purple-100 rounded-full p-3">
                <i class="fas fa-database text-purple-600 text-xl"></i>
            </div>
        </div>
        <!-- Sparkline Chart -->
        <div class="sparkline-container h-8 mb-3">
            <canvas id="storageSparkline" class="w-full h-full" role="img" aria-label="Storage trend sparkline"></canvas>
        </div>
        <!-- Primary Action Button -->
        <button class="w-full px-3 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition-colors">
            <i class="fas fa-database mr-1"></i>Manage Storage
        </button>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/dashboard/_kpis.blade.php ENDPATH**/ ?>