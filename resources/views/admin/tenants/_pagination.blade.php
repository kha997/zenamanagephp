{{-- Tenants Pagination --}}
<div x-show="filteredTenants.length > 0" class="bg-white px-6 py-3 border-t border-gray-200">
    <div class="flex items-center justify-between">
        <div class="text-sm text-gray-700">
            Showing <span class="font-medium">1</span> to <span class="font-medium" x-text="filteredTenants.length"></span> 
            of <span class="font-medium" x-text="filteredTenants.length"></span> results
        </div>
        <div class="flex items-center space-x-2">
            <button class="px-3 py-1 text-sm text-gray-500 hover:text-gray-700 disabled:opacity-50" disabled>
                Previous
            </button>
            <span class="px-3 py-1 text-sm bg-blue-600 text-white rounded">1</span>
            <button class="px-3 py-1 text-sm text-gray-500 hover:text-gray-700 disabled:opacity-50" disabled>
                Next
            </button>
        </div>
    </div>
</div>
