{{-- Tenants Filters --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <div class="flex flex-wrap items-center gap-4">
        <!-- Search -->
        <div class="flex-1 min-w-64">
            <input type="text" x-model="searchQuery" @input="filterTenants" 
                   placeholder="Search tenants..." 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        
        <!-- Status Filter -->
        <div class="flex items-center space-x-2">
            <label class="text-sm font-medium text-gray-700">Status:</label>
            <select x-model="statusFilter" @change="filterTenants" 
                    class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="all">All</option>
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
                <option value="pending">Pending</option>
            </select>
        </div>
        
        <!-- Plan Filter -->
        <div class="flex items-center space-x-2">
            <label class="text-sm font-medium text-gray-700">Plan:</label>
            <select x-model="planFilter" @change="filterTenants" 
                    class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="all">All</option>
                <option value="Basic">Basic</option>
                <option value="Professional">Professional</option>
                <option value="Enterprise">Enterprise</option>
            </select>
        </div>
        
        <!-- Bulk Actions -->
        <div x-show="selectedTenants.length > 0" class="flex items-center space-x-2">
            <span class="text-sm text-gray-600" x-text="selectedTenants.length + ' selected'"></span>
            <button @click="bulkAction('activate')" 
                    class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                Activate
            </button>
            <button @click="bulkAction('suspend')" 
                    class="px-3 py-1 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700">
                Suspend
            </button>
            <button @click="bulkAction('delete')" 
                    class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700">
                Delete
            </button>
        </div>
    </div>
</div>
