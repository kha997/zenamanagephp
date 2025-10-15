


<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'search' => true,
    'searchPlaceholder' => 'Search...',
    'filters' => [],
    'sortOptions' => [],
    'viewModes' => ['table', 'grid', 'list'],
    'currentViewMode' => 'table',
    'bulkActions' => [],
    'showFilters' => true,
    'showSort' => true,
    'showViewMode' => true,
    'theme' => 'light'
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'search' => true,
    'searchPlaceholder' => 'Search...',
    'filters' => [],
    'sortOptions' => [],
    'viewModes' => ['table', 'grid', 'list'],
    'currentViewMode' => 'table',
    'bulkActions' => [],
    'showFilters' => true,
    'showSort' => true,
    'showViewMode' => true,
    'theme' => 'light'
]); ?>
<?php foreach (array_filter(([
    'search' => true,
    'searchPlaceholder' => 'Search...',
    'filters' => [],
    'sortOptions' => [],
    'viewModes' => ['table', 'grid', 'list'],
    'currentViewMode' => 'table',
    'bulkActions' => [],
    'showFilters' => true,
    'showSort' => true,
    'showViewMode' => true,
    'theme' => 'light'
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
    $hasFilters = !empty($filters);
    $hasSortOptions = !empty($sortOptions);
    $hasViewModes = !empty($viewModes) && count($viewModes) > 1;
    $hasBulkActions = !empty($bulkActions);
?>

<div class="filter-bar" 
     x-data="filterBarComponent()" 
     :class="{ 'filters-open': filtersOpen }">
    
    
    <div class="filter-bar-main">
        <div class="flex items-center justify-between">
            
            <div class="flex items-center space-x-4 flex-1">
                
                <?php if($search): ?>
                    <div class="relative flex-1 max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" 
                               x-model="searchQuery"
                               @input.debounce.300ms="handleSearch()"
                               placeholder="<?php echo e($searchPlaceholder); ?>"
                               class="filter-search-input">
                    </div>
                <?php endif; ?>
                
                
                <?php if($hasFilters): ?>
                    <button @click="toggleFilters()" 
                            :class="{ 'bg-blue-50 text-blue-700': filtersOpen }"
                            class="filter-toggle-btn">
                        <i class="fas fa-filter mr-2"></i>
                        Filters
                        <span x-show="activeFiltersCount > 0" 
                              x-text="activeFiltersCount"
                              class="ml-2 bg-blue-600 text-white text-xs px-2 py-1 rounded-full"></span>
                    </button>
                <?php endif; ?>
                
                
                <?php if($hasSortOptions): ?>
                    <div class="relative">
                        <select x-model="sortBy" 
                                @change="handleSort()"
                                class="filter-select">
                            <?php $__currentLoopData = $sortOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($option['value']); ?>"><?php echo e($option['label']); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <i class="fas fa-sort text-gray-400"></i>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            
            <div class="flex items-center space-x-3">
                
                <?php if($hasViewModes): ?>
                    <div class="flex items-center bg-gray-100 rounded-lg p-1">
                        <?php $__currentLoopData = $viewModes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mode): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <button @click="setViewMode('<?php echo e($mode); ?>')"
                                    :class="{ 'bg-white shadow-sm': viewMode === '<?php echo e($mode); ?>' }"
                                    class="filter-view-mode-btn">
                                <i class="fas fa-<?php echo e($mode === 'table' ? 'table' : ($mode === 'grid' ? 'th' : 'list')); ?>"></i>
                            </button>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                <?php endif; ?>
                
                
                <?php if($hasBulkActions): ?>
                    <div x-show="selectedItems.length > 0" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="flex items-center space-x-2">
                        <span class="text-sm text-gray-600" x-text="`${selectedItems.length} selected`"></span>
                        <?php $__currentLoopData = $bulkActions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $action): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <button @click="<?php echo e($action['handler']); ?>" 
                                    class="filter-bulk-action-btn">
                                <i class="<?php echo e($action['icon']); ?> mr-1"></i>
                                <?php echo e($action['label']); ?>

                            </button>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                <?php endif; ?>
                
                
                <?php echo e($actions ?? ''); ?>

            </div>
        </div>
    </div>
    
    
    <?php if($hasFilters): ?>
        <div x-show="filtersOpen" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="filter-panel">
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php $__currentLoopData = $filters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $filter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="filter-group">
                        <label class="filter-label"><?php echo e($filter['label']); ?></label>
                        
                        <?php if($filter['type'] === 'select'): ?>
                            <select x-model="filters.<?php echo e($filter['key']); ?>" 
                                    @change="applyFilters()"
                                    class="filter-select">
                                <option value=""><?php echo e($filter['placeholder'] ?? 'All'); ?></option>
                                <?php $__currentLoopData = $filter['options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($option['value']); ?>"><?php echo e($option['label']); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        <?php elseif($filter['type'] === 'date'): ?>
                            <input type="date" 
                                   x-model="filters.<?php echo e($filter['key']); ?>"
                                   @change="applyFilters()"
                                   class="filter-input">
                        <?php elseif($filter['type'] === 'date-range'): ?>
                            <div class="flex space-x-2">
                                <input type="date" 
                                       x-model="filters.<?php echo e($filter['key']); ?>_from"
                                       @change="applyFilters()"
                                       placeholder="From"
                                       class="filter-input">
                                <input type="date" 
                                       x-model="filters.<?php echo e($filter['key']); ?>_to"
                                       @change="applyFilters()"
                                       placeholder="To"
                                       class="filter-input">
                            </div>
                        <?php elseif($filter['type'] === 'multiselect'): ?>
                            <div class="relative">
                                <select x-model="filters.<?php echo e($filter['key']); ?>" 
                                        @change="applyFilters()"
                                        multiple
                                        class="filter-select">
                                    <?php $__currentLoopData = $filter['options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($option['value']); ?>"><?php echo e($option['label']); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            
            
            <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200">
                <button @click="clearFilters()" 
                        class="filter-clear-btn">
                    <i class="fas fa-times mr-2"></i>
                    Clear All Filters
                </button>
                
                <div class="flex items-center space-x-2">
                    <button @click="applyFilters()" 
                            class="filter-apply-btn">
                        <i class="fas fa-check mr-2"></i>
                        Apply Filters
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('filterBarComponent', () => ({
        searchQuery: '',
        filtersOpen: false,
        activeFiltersCount: 0,
        viewMode: '<?php echo e($currentViewMode); ?>',
        sortBy: '<?php echo e($sortOptions[0]['value'] ?? 'updated_at'); ?>',
        selectedItems: [],
        filters: {},
        
        init() {
            // Initialize filters from URL params or default values
            this.initializeFilters();
            this.updateActiveFiltersCount();
        },
        
        initializeFilters() {
            // Initialize filters object
            <?php if($hasFilters): ?>
                <?php $__currentLoopData = $filters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $filter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    this.filters.<?php echo e($filter['key']); ?> = '';
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>
        },
        
        toggleFilters() {
            this.filtersOpen = !this.filtersOpen;
        },
        
        handleSearch() {
            this.$dispatch('filter-search', {
                query: this.searchQuery
            });
        },
        
        handleSort() {
            this.$dispatch('filter-sort', {
                sortBy: this.sortBy,
                sortDirection: 'asc'
            });
        },
        
        setViewMode(mode) {
            this.viewMode = mode;
            this.$dispatch('filter-view-mode', {
                viewMode: mode
            });
        },
        
        applyFilters() {
            this.updateActiveFiltersCount();
            this.$dispatch('filter-apply', {
                filters: this.filters
            });
        },
        
        clearFilters() {
            this.filters = {};
            this.searchQuery = '';
            this.activeFiltersCount = 0;
            this.$dispatch('filter-clear');
        },
        
        updateActiveFiltersCount() {
            let count = 0;
            Object.values(this.filters).forEach(value => {
                if (value && value !== '') {
                    count++;
                }
            });
            if (this.searchQuery) count++;
            this.activeFiltersCount = count;
        }
    }));
});
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/filter-bar.blade.php ENDPATH**/ ?>