<!-- Pagination -->
<div class="flex items-center justify-between px-6 py-3 bg-white border-t border-gray-200">
    <div class="flex-1 flex justify-between sm:hidden">
        <!-- Mobile pagination -->
        <button 
            @click="loadPage({{ $panel }}.page - 1)"
            :disabled="{{ $panel }}.page <= 1"
            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
        >
            Previous
        </button>
        <button 
            @click="loadPage({{ $panel }}.page + 1)"
            :disabled="{{ $panel }}.page >= {{ $panel }}.last_page"
            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
        >
            Next
        </button>
    </div>
    
    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-gray-700">
                Showing
                <span class="font-medium" x-text="({{ $panel }}.page - 1) * {{ $panel }}.per_page + 1"></span>
                to
                <span class="font-medium" x-text="Math.min({{ $panel }}.page * {{ $panel }}.per_page, {{ $panel }}.total)"></span>
                of
                <span class="font-medium" x-text="{{ $panel }}.total"></span>
                results
            </p>
        </div>
        
        <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <!-- Previous button -->
                <button 
                    @click="loadPage({{ $panel }}.page - 1)"
                    :disabled="{{ $panel }}.page <= 1"
                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span class="sr-only">Previous</span>
                    <i class="fas fa-chevron-left"></i>
                </button>
                
                <!-- Page numbers -->
                <template x-for="page in getPageNumbers({{ $panel }}.page, {{ $panel }}.last_page)" :key="page">
                    <button 
                        @click="loadPage(page)"
                        :class="page === {{ $panel }}.page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'"
                        class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                        x-text="page"
                    ></button>
                </template>
                
                <!-- Next button -->
                <button 
                    @click="loadPage({{ $panel }}.page + 1)"
                    :disabled="{{ $panel }}.page >= {{ $panel }}.last_page"
                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span class="sr-only">Next</span>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </nav>
        </div>
    </div>
</div>

{{-- Pagination methods are now in the main securityPage component --}}
