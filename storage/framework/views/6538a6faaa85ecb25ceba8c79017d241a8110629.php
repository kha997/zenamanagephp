


<nav class="global-nav bg-white shadow-sm border-b border-gray-200 sticky top-16 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-full">
            <!-- Navigation Items -->
            <div class="flex items-center space-x-1">
                <?php if(Auth::user()->hasRole('super_admin')): ?>
                    
                    <?php echo $__env->make('components.navigation.admin-nav', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                <?php else: ?>
                    
                    <?php echo $__env->make('components.navigation.tenant-nav', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                <?php endif; ?>
            </div>
            
            <!-- Right Side: Search + Actions -->
            <div class="flex items-center space-x-3">
                <!-- Smart Search -->
                <div class="relative">
                    <div class="relative">
                        <input type="text" 
                               placeholder="Search..." 
                               data-search-input
                               class="w-64 pl-10 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               @keydown.enter="performSearch($event.target.value)">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <!-- Search Shortcut Hint -->
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <kbd class="text-xs text-gray-400">/</kbd>
                        </div>
                    </div>
                    
                    <!-- Search Results Dropdown -->
                    <div x-show="searchResults.length > 0" 
                         x-transition
                         @click.away="searchResults = []"
                         class="absolute top-full left-0 right-0 mt-1 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                        <div class="max-h-64 overflow-y-auto">
                            <template x-for="result in searchResults" :key="result.id">
                                <a :href="result.url" 
                                   class="block p-3 hover:bg-gray-50 border-b border-gray-100 last:border-b-0">
                                    <div class="flex items-center space-x-3">
                                        <i :class="result.icon" class="text-gray-400"></i>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900" x-text="result.title"></p>
                                            <p class="text-xs text-gray-500" x-text="result.description"></p>
                                        </div>
                                    </div>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>
                
                <!-- Refresh Button -->
                <button @click="refreshData()" 
                        :disabled="refreshing"
                        class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        aria-label="Refresh data">
                    <i class="fas fa-sync-alt" :class="refreshing ? 'animate-spin' : ''"></i>
                </button>
                
                <!-- Mobile Menu Button (Hidden on desktop) -->
                <button @click="mobileMenuOpen = !mobileMenuOpen" 
                        class="md:hidden p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
                        aria-label="Open mobile menu">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Mobile Navigation Menu -->
    <div x-show="mobileMenuOpen" 
         x-transition
         @click.away="mobileMenuOpen = false"
         class="md:hidden bg-white border-t border-gray-200">
        <div class="px-4 py-2 space-y-1">
            <?php if(Auth::user()->hasRole('super_admin')): ?>
                
                <?php echo $__env->make('components.navigation.mobile-admin-nav', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php else: ?>
                
                <?php echo $__env->make('components.navigation.mobile-tenant-nav', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php endif; ?>
        </div>
    </div>
</nav>

<script>
    // Add to universalFrame Alpine.js data
    document.addEventListener('alpine:init', () => {
        Alpine.data('universalFrame', () => ({
            // ... existing code ...
            
            // Navigation State
            currentNavItem: 'dashboard',
            searchResults: [],
            searchQuery: '',
            
            // Navigation Actions
            setActiveNavItem(item) {
                this.currentNavItem = item;
            },
            
            performSearch(query) {
                if (!query.trim()) {
                    this.searchResults = [];
                    return;
                }
                
                // Simulate search results - this will be replaced with actual API calls
                this.searchResults = [
                    {
                        id: 1,
                        title: 'Project Alpha',
                        description: 'Construction project in downtown',
                        url: '/app/projects/1',
                        icon: 'fas fa-project-diagram'
                    },
                    {
                        id: 2,
                        title: 'Task Review',
                        description: 'Review pending tasks',
                        url: '/app/tasks?status=pending',
                        icon: 'fas fa-tasks'
                    }
                ];
            },
            
            refreshData() {
                this.refreshing = true;
                // Simulate data fetching
                setTimeout(() => {
                    console.log('Data refreshed!');
                    this.refreshing = false;
                }, 1500);
            },
            
            // ... rest of existing code ...
        }));
    });
</script>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/navigation/universal-navigation.blade.php ENDPATH**/ ?>