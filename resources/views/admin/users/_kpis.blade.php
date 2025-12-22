{{-- Users KPI Strip --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <!-- Total Users -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4" data-kpi="totalUsers">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Total Users</p>
                <p class="text-2xl font-bold text-gray-900 value" x-text="kpis.totalUsers.value.toLocaleString()"></p>
                <div class="flex items-center mt-1">
                    <span :class="kpis.totalUsers.deltaPct >= 0 ? 'text-green-600' : 'text-red-600'" 
                          class="text-sm font-medium delta">
                        <i :class="kpis.totalUsers.deltaPct >= 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'" class="mr-1"></i>
                        <span x-text="Math.abs(kpis.totalUsers.deltaPct).toFixed(1) + '%'"></span>
                    </span>
                    <span class="text-xs text-gray-500 ml-2" x-text="'from last ' + kpis.totalUsers.period"></span>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <canvas id="sparkline-totalUsers" width="60" height="24" class="opacity-60"></canvas>
                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-users text-blue-600 text-sm"></i>
                </div>
            </div>
        </div>
        <button @click="drillDownTotal" 
                class="w-full mt-3 bg-blue-600 text-white text-sm py-2 px-3 rounded-lg hover:bg-blue-700 transition-colors"
                :aria-label="getAriaLabel('view_total', {name: 'Total Users'})">
            View All
        </button>
    </div>

    <!-- Active Users (7d) -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Active (7d)</p>
                <p class="text-2xl font-bold text-gray-900" x-text="kpis.activeUsers.value.toLocaleString()"></p>
                <div class="flex items-center mt-1">
                    <span :class="kpis.activeUsers.deltaPct >= 0 ? 'text-green-600' : 'text-red-600'" 
                          class="text-sm font-medium">
                        <i :class="kpis.activeUsers.deltaPct >= 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'" class="mr-1"></i>
                        <span x-text="Math.abs(kpis.activeUsers.deltaPct).toFixed(1) + '%'"></span>
                    </span>
                    <span class="text-xs text-gray-500 ml-2" x-text="'from last ' + kpis.activeUsers.period"></span>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <canvas id="sparkline-activeUsers" width="60" height="24" class="opacity-60"></canvas>
                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-user-check text-green-600 text-sm"></i>
                </div>
            </div>
        </div>
        <button @click="drillDownActive" 
                class="w-full mt-3 bg-green-600 text-white text-sm py-2 px-3 rounded-lg hover:bg-green-700 transition-colors"
                :aria-label="getAriaLabel('view_active', {name: 'Active Users'})">
            Filter Active
        </button>
    </div>

    <!-- Locked/Disabled -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Locked/Disabled</p>
                <p class="text-2xl font-bold text-gray-900" x-text="kpis.lockedUsers.value.toLocaleString()"></p>
                <div class="flex items-center mt-1">
                    <span :class="kpis.lockedUsers.deltaAbs >= 0 ? 'text-red-600' : 'text-green-600'" 
                          class="text-sm font-medium">
                        <i :class="kpis.lockedUsers.deltaAbs >= 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'" class="mr-1"></i>
                        <span x-text="Math.abs(kpis.lockedUsers.deltaAbs)"></span>
                    </span>
                    <span class="text-xs text-gray-500 ml-2" x-text="'from last ' + kpis.lockedUsers.period"></span>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <canvas id="sparkline-lockedUsers" width="60" height="24" class="opacity-60"></canvas>
                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-user-lock text-red-600 text-sm"></i>
                </div>
            </div>
        </div>
        <button @click="drillDownLocked" 
                class="w-full mt-3 bg-red-600 text-white text-sm py-2 px-3 rounded-lg hover:bg-red-700 transition-colors"
                :aria-label="getAriaLabel('view_locked', {name: 'Locked Users'})">
            Filter Locked
        </button>
    </div>

    <!-- No-MFA -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">No-MFA</p>
                <p class="text-2xl font-bold text-gray-900" x-text="kpis.noMfaUsers.value.toLocaleString()"></p>
                <div class="flex items-center mt-1">
                    <span :class="kpis.noMfaUsers.deltaPct >= 0 ? 'text-red-600' : 'text-green-600'" 
                          class="text-sm font-medium">
                        <i :class="kpis.noMfaUsers.deltaPct >= 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'" class="mr-1"></i>
                        <span x-text="Math.abs(kpis.noMfaUsers.deltaPct).toFixed(1) + '%'"></span>
                    </span>
                    <span class="text-xs text-gray-500 ml-2" x-text="'from last ' + kpis.noMfaUsers.period"></span>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <canvas id="sparkline-noMfaUsers" width="60" height="24" class="opacity-60"></canvas>
                <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-shield-alt text-orange-600 text-sm"></i>
                </div>
            </div>
        </div>
        <button @click="drillDownNoMfa" 
                class="w-full mt-3 bg-orange-600 text-white text-sm py-2 px-3 rounded-lg hover:bg-orange-700 transition-colors"
                :aria-label="getAriaLabel('view_no_mfa', {name: 'No-MFA Users'})">
            Filter No-MFA
        </button>
    </div>

    <!-- Pending Invites -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Pending Invites</p>
                <p class="text-2xl font-bold text-gray-900" x-text="kpis.pendingInvites.value.toLocaleString()"></p>
                <div class="flex items-center mt-1">
                    <span :class="kpis.pendingInvites.deltaAbs >= 0 ? 'text-orange-600' : 'text-green-600'" 
                          class="text-sm font-medium">
                        <i :class="kpis.pendingInvites.deltaAbs >= 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'" class="mr-1"></i>
                        <span x-text="Math.abs(kpis.pendingInvites.deltaAbs)"></span>
                    </span>
                    <span class="text-xs text-gray-500 ml-2" x-text="'from last ' + kpis.pendingInvites.period"></span>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <canvas id="sparkline-pendingInvites" width="60" height="24" class="opacity-60"></canvas>
                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-envelope text-purple-600 text-sm"></i>
                </div>
            </div>
        </div>
        <button @click="drillDownInvites" 
                class="w-full mt-3 bg-purple-600 text-white text-sm py-2 px-3 rounded-lg hover:bg-purple-700 transition-colors"
                :aria-label="getAriaLabel('view_invites', {name: 'Pending Invites'})">
            View Invites
        </button>
    </div>
</div>
