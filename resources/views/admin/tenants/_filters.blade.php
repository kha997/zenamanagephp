{{-- Tenants Filters --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <!-- Search & Filters Row -->
    <div class="flex flex-wrap items-center gap-4 mb-4">
        <!-- Server-side Search -->
        <div class="flex-1 min-w-64">
            <div class="relative">
                <input type="text" 
                       x-model="searchQuery" 
                       @input.debounce.250ms="performServerSearch"
                       placeholder="Search tenants by name, domain, owner..." 
                       class="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       aria-label="Search Tenants">
                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
            </div>
        </div>
        
        <!-- Status Filter -->
        <div class="flex items-center space-x-2">
            <label class="text-sm font-medium text-gray-700">Status:</label>
            <select x-model="statusFilter" @change="applyFilters" 
                    class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">All</option>
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
                <option value="trial">Trial</option>
                <option value="disabled">Disabled</option>
            </select>
        </div>
        
        <!-- Plan Filter -->
        <div class="flex items-center space-x-2">
            <label class="text-sm font-medium text-gray-700">Plan:</label>
            <select x-model="planFilter" @change="applyFilters" 
                    class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">All</option>
                <option value="Basic">Basic</option>
                <option value="Professional">Professional</option>
                <option value="Enterprise">Enterprise</option>
            </select>
        </div>
        
        <!-- Date Range Filter -->
        <div class="flex items-center space-x-2">
            <label class="text-sm font-medium text-gray-700">From:</label>
            <input type="date" 
                   x-model="dateFrom" 
                   @change="applyFilters"
                   class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        
        <div class="flex items-center space-x-2">
            <label class="text-sm font-medium text-gray-700">To:</label>
            <input type="date" 
                   x-model="dateTo" 
                   @change="applyFilters"
                   class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        
        <!-- Clear Filters -->
        <button @click="clearFilters" 
                class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-lg hover:bg-gray-50">
            <i class="fas fa-times mr-1"></i>Clear
        </button>
    </div>
    
    <!-- Presets Row -->
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <span class="text-sm font-medium text-gray-700">Quick Filters:</span>
        <button @click="applyPreset('active')" 
                :class="activePreset === 'active' ? 'bg-green-100 text-green-800 border-green-300' : 'bg-gray-100 text-gray-700 border-gray-300'"
                class="px-3 py-1 text-sm border rounded-full hover:bg-green-50 transition-colors">
            Active
        </button>
        <button @click="applyPreset('disabled')" 
                :class="activePreset === 'disabled' ? 'bg-red-100 text-red-800 border-red-300' : 'bg-gray-100 text-gray-700 border-gray-300'"
                class="px-3 py-1 text-sm border rounded-full hover:bg-red-50 transition-colors">
            Disabled
        </button>
        <button @click="applyPreset('new')" 
                :class="activePreset === 'new' ? 'bg-purple-100 text-purple-800 border-purple-300' : 'bg-gray-100 text-gray-700 border-gray-300'"
                class="px-3 py-1 text-sm border rounded-full hover:bg-purple-50 transition-colors">
            New (30d)
        </button>
        <button @click="applyPreset('trial')" 
                :class="activePreset === 'trial' ? 'bg-orange-100 text-orange-800 border-orange-300' : 'bg-gray-100 text-gray-700 border-gray-300'"
                class="px-3 py-1 text-sm border rounded-full hover:bg-orange-50 transition-colors">
            Trial Expiring
        </button>
    </div>
    
    <!-- Bulk Actions -->
    <div x-show="selectedTenants.length > 0" class="flex items-center space-x-2 pt-4 border-t border-gray-200">
        <span class="text-sm text-gray-600" x-text="selectedTenants.length + ' selected'"></span>
        <button @click="bulkAction('activate')" 
                class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
            Activate
        </button>
        <button @click="bulkAction('suspend')" 
                class="px-3 py-1 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700">
            Suspend
        </button>
        <button @click="bulkAction('change-plan')" 
                class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
            Change Plan
        </button>
        <button @click="bulkAction('export')" 
                class="px-3 py-1 bg-gray-600 text-white text-sm rounded hover:bg-gray-700">
            Export
        </button>
        <button @click="bulkAction('delete')" 
                class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700">
            Delete
        </button>
    </div>
    
    <!-- Active Filters Display -->
    <div x-show="hasActiveFilters" class="mt-4 pt-4 border-t border-gray-200">
        <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-600">Active filters:</span>
            <template x-if="localSearchQuery">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    Search: <span x-text="localSearchQuery"></span>
                    <button @click="localSearchQuery = ''; applyFilters()" class="ml-1 text-blue-600 hover:text-blue-800">
                        <i class="fas fa-times"></i>
                    </button>
                </span>
            </template>
            <template x-if="statusFilter">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    Status: <span x-text="statusFilter"></span>
                    <button @click="statusFilter = ''; applyFilters()" class="ml-1 text-green-600 hover:text-green-800">
                        <i class="fas fa-times"></i>
                    </button>
                </span>
            </template>
            <template x-if="planFilter">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                    Plan: <span x-text="planFilter"></span>
                    <button @click="planFilter = ''; applyFilters()" class="ml-1 text-purple-600 hover:text-purple-800">
                        <i class="fas fa-times"></i>
                    </button>
                </span>
            </template>
        </div>
    </div>
</div>
