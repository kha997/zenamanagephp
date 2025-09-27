
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
    <!-- Total Tenants -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
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
    </div>
    
    <!-- Total Users -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
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
    </div>
    
    <!-- Errors 24h -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
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
    </div>
    
    <!-- Queue Jobs -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
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
    </div>
    
    <!-- Storage Used -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
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
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/dashboard/_kpis.blade.php ENDPATH**/ ?>