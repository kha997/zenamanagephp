<!-- Search & Filters -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
        <!-- Search -->
        <div class="flex-1 max-w-md">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input 
                    type="text" 
                    x-model="filters.q"
                    @input.debounce.250ms="performSearch()"
                    placeholder="Search users, IPs, keys, events..."
                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    aria-label="Search security data"
                >
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center space-x-4">
            <!-- Range Filter -->
            <div class="flex items-center space-x-2">
                <label for="range-filter" class="text-sm font-medium text-gray-700">Range:</label>
                <select 
                    id="range-filter"
                    x-model="filters.range"
                    @change="performSearch()"
                    class="block w-32 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                >
                    <option value="24h">24 hours</option>
                    <option value="7d">7 days</option>
                    <option value="30d">30 days</option>
                    <option value="90d">90 days</option>
                </select>
            </div>

            <!-- Tenant Filter -->
            <div class="flex items-center space-x-2">
                <label for="tenant-filter" class="text-sm font-medium text-gray-700">Tenant:</label>
                <select 
                    id="tenant-filter"
                    x-model="filters.tenant"
                    @change="performSearch()"
                    :disabled="optionsLoading"
                    class="block w-40 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm disabled:bg-gray-100 disabled:cursor-not-allowed"
                >
                    <option value="">All Tenants</option>
                    <template x-for="tenant in filterOptions.tenants" :key="tenant.id">
                        <option :value="tenant.id" x-text="tenant.name"></option>
                    </template>
                    <option x-show="filterOptions.tenants.length === 0 && !optionsLoading" value="" disabled>No tenants found</option>
                </select>
            </div>

            <!-- Severity Filter -->
            <div class="flex items-center space-x-2">
                <label for="severity-filter" class="text-sm font-medium text-gray-700">Severity:</label>
                <select 
                    id="severity-filter"
                    x-model="filters.severity"
                    @change="performSearch()"
                    :disabled="optionsLoading"
                    class="block w-32 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm disabled:bg-gray-100 disabled:cursor-not-allowed"
                >
                    <option value="">All</option>
                    <template x-for="severity in filterOptions.severity" :key="severity">
                        <option :value="severity" x-text="severity.charAt(0).toUpperCase() + severity.slice(1)"></option>
                    </template>
                    <option x-show="filterOptions.severity.length === 0 && !optionsLoading" value="" disabled>No severity levels</option>
                </select>
            </div>

            <!-- Source Filter -->
            <div class="flex items-center space-x-2">
                <label for="source-filter" class="text-sm font-medium text-gray-700">Source:</label>
                <select 
                    id="source-filter"
                    x-model="filters.source"
                    @change="performSearch()"
                    :disabled="optionsLoading"
                    class="block w-32 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm disabled:bg-gray-100 disabled:cursor-not-allowed"
                >
                    <option value="">All</option>
                    <template x-for="source in filterOptions.sources" :key="source">
                        <option :value="source" x-text="source.charAt(0).toUpperCase() + source.slice(1).replace('_', ' ')"></option>
                    </template>
                    <option x-show="filterOptions.sources.length === 0 && !optionsLoading" value="" disabled>No sources found</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Presets -->
    <div class="mt-4 pt-4 border-t border-gray-200">
        <div class="flex flex-wrap items-center space-x-2">
            <span class="text-sm font-medium text-gray-700">Quick Presets:</span>
            
            <button 
                @click="applyPreset('critical-now')"
                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-red-500"
                aria-label="Show critical security events from last 24 hours"
            >
                <i class="fas fa-exclamation-triangle mr-1"></i>
                Critical Now
            </button>
            
            <button 
                @click="applyPreset('brute-force')"
                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 hover:bg-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-500"
                aria-label="Show suspected brute force attacks"
            >
                <i class="fas fa-shield-alt mr-1"></i>
                Brute-force Suspect
            </button>
            
            <button 
                @click="applyPreset('no-mfa')"
                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-yellow-500"
                aria-label="Show users without MFA enabled"
            >
                <i class="fas fa-user-shield mr-1"></i>
                No-MFA Users
            </button>
            
            <button 
                @click="applyPreset('risky-keys')"
                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 hover:bg-purple-200 focus:outline-none focus:ring-2 focus:ring-purple-500"
                aria-label="Show API keys that need attention"
            >
                <i class="fas fa-key mr-1"></i>
                Risky Keys
            </button>
            
            <button 
                @click="applyPreset('long-sessions')"
                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                aria-label="Show sessions longer than 7 days"
            >
                <i class="fas fa-clock mr-1"></i>
                Long Sessions
            </button>
        </div>
    </div>

    <!-- Active Filters Display -->
    <div x-show="hasActiveFilters()" class="mt-4 pt-4 border-t border-gray-200">
        <div class="flex flex-wrap items-center space-x-2">
            <span class="text-sm font-medium text-gray-700">Active Filters:</span>
            
            <template x-for="(value, key) in getActiveFilters()" :key="key">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    <span x-text="getFilterLabel(key)"></span>: <span x-text="value"></span>
                    <button 
                        @click="clearFilter(key)"
                        class="ml-1 text-blue-600 hover:text-blue-800 focus:outline-none"
                        :aria-label="`Remove ${getFilterLabel(key)} filter`"
                    >
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </span>
            </template>
            
            <button 
                @click="clearAllFilters()"
                class="text-xs text-gray-600 hover:text-gray-800 underline"
            >
                Clear all
            </button>
        </div>
    </div>
</div>


<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/security/_filters.blade.php ENDPATH**/ ?>