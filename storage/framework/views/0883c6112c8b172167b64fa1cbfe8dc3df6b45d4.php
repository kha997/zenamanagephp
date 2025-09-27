
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
    <!-- Total Tenants -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer" 
         @click="window.location.href='/admin/tenants'"
         role="button"
         tabindex="0"
         aria-label="Total Tenants: 89, increased by 5% from last month. Click to view tenants."
         @keydown.enter="window.location.href='/admin/tenants'"
         @keydown.space.prevent="window.location.href='/admin/tenants'">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Total Tenants</p>
                <p class="text-2xl font-bold text-gray-900" x-text="kpis.totalTenants">89</p>
                <p class="text-sm text-green-600">
                    <i class="fas fa-arrow-up mr-1"></i>
                    +5% from last month
                </p>
            </div>
            <div class="bg-blue-100 rounded-full p-3">
                <i class="fas fa-building text-blue-600 text-xl"></i>
            </div>
        </div>
        <!-- Sparkline Chart -->
        <div class="h-8 mb-3">
            <canvas id="tenantsSparkline" class="w-full h-full"></canvas>
        </div>
        <!-- Primary Action Button -->
        <button class="w-full px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                aria-label="View all tenants">
            <i class="fas fa-eye mr-1" aria-hidden="true"></i>View Tenants
        </button>
    </div>
    
    <!-- Total Users -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer" 
         @click="window.location.href='/admin/users'">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Total Users</p>
                <p class="text-2xl font-bold text-gray-900" x-text="kpis.totalUsers">1,247</p>
                <p class="text-sm text-green-600">
                    <i class="fas fa-arrow-up mr-1"></i>
                    +12% from last month
                </p>
            </div>
            <div class="bg-green-100 rounded-full p-3">
                <i class="fas fa-users text-green-600 text-xl"></i>
            </div>
        </div>
        <!-- Sparkline Chart -->
        <div class="h-8 mb-3">
            <canvas id="usersSparkline" class="w-full h-full"></canvas>
        </div>
        <!-- Primary Action Button -->
        <button class="w-full px-3 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
            <i class="fas fa-users mr-1"></i>Manage Users
        </button>
    </div>
    
    <!-- Errors 24h -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer" 
         @click="window.location.href='/admin/alerts'">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Errors (24h)</p>
                <p class="text-2xl font-bold text-gray-900" x-text="kpis.errors24h">12</p>
                <p class="text-sm text-red-600">
                    <i class="fas fa-arrow-up mr-1"></i>
                    +3 from yesterday
                </p>
            </div>
            <div class="bg-red-100 rounded-full p-3">
                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
            </div>
        </div>
        <!-- Sparkline Chart -->
        <div class="h-8 mb-3">
            <canvas id="errorsSparkline" class="w-full h-full"></canvas>
        </div>
        <!-- Primary Action Button -->
        <button class="w-full px-3 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors">
            <i class="fas fa-exclamation-triangle mr-1"></i>View Errors
        </button>
    </div>
    
    <!-- Queue Jobs -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer" 
         @click="window.location.href='/admin/maintenance'">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Queue Jobs</p>
                <p class="text-2xl font-bold text-gray-900" x-text="kpis.queueJobs">156</p>
                <p class="text-sm text-yellow-600">
                    <i class="fas fa-clock mr-1"></i>
                    Processing
                </p>
            </div>
            <div class="bg-yellow-100 rounded-full p-3">
                <i class="fas fa-tasks text-yellow-600 text-xl"></i>
            </div>
        </div>
        <!-- Sparkline Chart -->
        <div class="h-8 mb-3">
            <canvas id="queueSparkline" class="w-full h-full"></canvas>
        </div>
        <!-- Primary Action Button -->
        <button class="w-full px-3 py-2 bg-yellow-600 text-white text-sm font-medium rounded-lg hover:bg-yellow-700 transition-colors">
            <i class="fas fa-tasks mr-1"></i>Monitor Queue
        </button>
    </div>
    
    <!-- Storage Used -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer" 
         @click="window.location.href='/admin/maintenance'">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Storage Used</p>
                <p class="text-2xl font-bold text-gray-900" x-text="kpis.storageUsed">2.1TB</p>
                <p class="text-sm text-gray-600">
                    <i class="fas fa-database mr-1"></i>
                    67% of 3.2TB
                </p>
            </div>
            <div class="bg-purple-100 rounded-full p-3">
                <i class="fas fa-database text-purple-600 text-xl"></i>
            </div>
        </div>
        <!-- Sparkline Chart -->
        <div class="h-8 mb-3">
            <canvas id="storageSparkline" class="w-full h-full"></canvas>
        </div>
        <!-- Primary Action Button -->
        <button class="w-full px-3 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition-colors">
            <i class="fas fa-database mr-1"></i>Manage Storage
        </button>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/dashboard/_kpis.blade.php ENDPATH**/ ?>