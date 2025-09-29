
<div x-show="!isLoading && !error && total > 0" class="bg-white px-6 py-3 border-t border-gray-200">
    <div class="flex items-center justify-between">
        <div class="flex items-center text-sm text-gray-700">
            <span>Showing</span>
            <span class="mx-1 font-medium" x-text="((page - 1) * perPage) + 1"></span>
            <span>to</span>
            <span class="mx-1 font-medium" x-text="Math.min(page * perPage, total)"></span>
            <span>of</span>
            <span class="mx-1 font-medium" x-text="total"></span>
            <span>results</span>
        </div>
        
        <div class="flex items-center space-x-2">
            <!-- Previous Button -->
            <button @click="changePage(page - 1)" 
                    :disabled="page === 1"
                    :class="page === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50'"
                    class="px-3 py-1 text-sm border border-gray-300 rounded-md">
                <i class="fas fa-chevron-left"></i>
            </button>
            
            <!-- Page Numbers -->
            <template x-for="pageNum in getVisiblePages()" :key="pageNum">
                <button @click="changePage(pageNum)" 
                        :class="pageNum === page ? 'bg-blue-600 text-white border-blue-600' : 'text-gray-700 border-gray-300 hover:bg-gray-50'"
                        class="px-3 py-1 text-sm border rounded-md">
                    <span x-text="pageNum"></span>
                </button>
            </template>
            
            <!-- Next Button -->
            <button @click="changePage(page + 1)" 
                    :disabled="page === lastPage"
                    :class="page === lastPage ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50'"
                    class="px-3 py-1 text-sm border border-gray-300 rounded-md">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
</div><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/users/_pagination.blade.php ENDPATH**/ ?>