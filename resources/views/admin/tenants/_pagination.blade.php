{{-- Tenants Pagination --}}
<div x-show="!isLoading && !error && total > 0" class="bg-white px-6 py-3 border-t border-gray-200">
    <div class="flex items-center justify-between">
        <div class="text-sm text-gray-700">
            Showing <span class="font-medium" x-text="Math.min((page - 1) * perPage + 1, total)"></span> 
            to <span class="font-medium" x-text="Math.min(page * perPage, total)"></span> 
            of <span class="font-medium" x-text="total"></span> results
        </div>
        <div class="flex items-center space-x-2">
            <button @click="changePage(page - 1)" 
                    :disabled="page <= 1"
                    class="px-3 py-1 text-sm text-gray-500 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed">
                Previous
            </button>
            
            <!-- Page Numbers -->
            <template x-for="pageNum in getVisiblePages()" :key="pageNum">
                <button @click="changePage(pageNum)" 
                        :class="pageNum === page ? 'bg-blue-600 text-white' : 'text-gray-500 hover:text-gray-700'"
                        class="px-3 py-1 text-sm rounded">
                    <span x-text="pageNum"></span>
                </button>
            </template>
            
            <button @click="changePage(page + 1)" 
                    :disabled="page >= lastPage"
                    class="px-3 py-1 text-sm text-gray-500 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed">
                Next
            </button>
        </div>
    </div>
</div>
