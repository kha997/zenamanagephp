{{-- Admin Dashboard KPIs --}}
<section class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Total Users</p>
                        <p class="text-3xl font-bold" x-text="kpis.totalUsers">1,247</p>
                        <p class="text-blue-100 text-sm">
                            <i class="fas fa-arrow-up mr-1"></i>
                            <span x-text="kpis.userGrowth">+12%</span> from last month
                        </p>
                    </div>
                    <div class="bg-blue-400 bg-opacity-30 rounded-full p-3">
                        <i class="fas fa-users text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">Active Tenants</p>
                        <p class="text-3xl font-bold" x-text="kpis.activeTenants">89</p>
                        <p class="text-green-100 text-sm">
                            <i class="fas fa-arrow-up mr-1"></i>
                            <span x-text="kpis.tenantGrowth">+5%</span> from last month
                        </p>
                    </div>
                    <div class="bg-green-400 bg-opacity-30 rounded-full p-3">
                        <i class="fas fa-building text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium">System Health</p>
                        <p class="text-3xl font-bold" x-text="kpis.systemHealth">99.8%</p>
                        <p class="text-purple-100 text-sm">
                            <i class="fas fa-heartbeat mr-1"></i>
                            All systems operational
                        </p>
                    </div>
                    <div class="bg-purple-400 bg-opacity-30 rounded-full p-3">
                        <i class="fas fa-heartbeat text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-sm font-medium">Storage Usage</p>
                        <p class="text-3xl font-bold" x-text="kpis.storageUsage">67%</p>
                        <p class="text-orange-100 text-sm">
                            <i class="fas fa-database mr-1"></i>
                            2.1TB of 3.2TB used
                        </p>
                    </div>
                    <div class="bg-orange-400 bg-opacity-30 rounded-full p-3">
                        <i class="fas fa-database text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
